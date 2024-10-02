<?php

if (!defined('ABSPATH')) exit;

use Apirone\SDK\Invoice;
use Apirone\SDK\Model\Settings as Options;
use Apirone\SDK\Model\Settings\Currency;
use PhpParser\Node\Expr\Instanceof_;

class WC_MCCP extends WC_Payment_Gateway
{
    public ?Options $options;
    
    public function __construct()
    {
        $this->id = 'mccp';
        $this->title = __('Crypto currency payment', 'mccp');
        $this->description = __('Start accepting multi cryptocurrency payments', 'mccp');
        $this->method_title  = __('Multi Crypto Currency Payments', 'mccp');
        $this->method_description = __('Start accepting multi cryptocurrency payments', 'mccp');

        $this->init();

        add_action('woocommerce_receipt_mccp', array( $this, 'invoice_receipt' ));
        add_action('woocommerce_api_mccp_callback', array($this, 'mccp_callback_handler'));
        add_action('woocommerce_api_mccp_check', array($this, 'mccp_check_handler'));

        add_action('woocommerce_update_options_payment_gateways_mccp', array($this, 'process_admin_options'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'show_invoice_admin_info'));
    }
    public function init()
    {
        $this->do_update();
        $this->init_settings();
        $this->get_options();
    }
    // Load existing og create new
    public function get_options()

    {
        if(isset($this->options)) {
            return $this->options;
        }
        $json = $this->get_option('options');
        if ($json) {
            $this->options = Options::fromJson($json);
        }
        else {
            $this->options = Options::init()->createAccount();
            $this->update_option('options', $this->options->toJson());
        }

        return $this->options;
    }

    public function get_secret($renew = false) {
        $secret = $this->get_option('secret');

        if ( !$secret || $renew ) {
            $secret = md5(time());
            $this->update_option('secret', $secret);
        }
        return $secret;
    }

    public function invoice_receipt($order_id)
    {
        echo(__FUNCTION__);
    }


    public function payment_fields()
    {
        echo 'Coming soon';   
    }

    public function get_option($key, $empty_value = null)
    {
        if(isset($this->options)) {        
            $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));

            if (property_exists($this->options, $property)) {
                $method = 'get' . ucfirst($property);
                $value = $this->options->$method();
                return is_bool($value) ? (($value) ? 'yes' : 'no') : $value;
            }

            $extra = $this->options->getExtra($key);
            if ($extra) {
                return $extra;
            }
        }

        return parent::get_option($key, $empty_value);
    }

    /**
     * Payment process handler
     * 
     * @param int $order_id 
     * @return array 
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        // Create redirect URL
        $redirect = $order->get_checkout_payment_url(true);
        $redirect = add_query_arg('mccp_currency', sanitize_text_field($_POST['mccp_currency']), $redirect);
        if (isset($_GET['pay_for_order'])) {
            $redirect = add_query_arg('repayment', true, $redirect);
        }

        return array(
            'result'    => 'success',
            'redirect'  => $redirect,
        );
    }

    public function process_admin_options()
    {
        $this->form_fields = $this->admin_fields();
        parent::process_admin_options();
		
        $post_data = $this->get_post_data();
        $options = $this->options_fields();

        $this->options->setMerchant($this->get_field_value('merchant', $options['merchant'], $post_data));
        $this->options->setExtra('test_customer', $this->get_field_value('test_customer', $options['test_customer'], $post_data));
        $this->options->setTimeout((int) $this->get_field_value('timeout', $options['timeout'], $post_data));
        $this->options->setExtra('processing_fee', $this->get_field_value('processing_fee', $options['processing_fee'], $post_data));
        $this->options->setFactor((float) $this->get_field_value('factor', $options['factor'], $post_data));
        $this->options->setLogo($this->get_field_value('logo', $options['logo'], $post_data) == 'yes' ? true : false);
        $this->options->setDebug($this->get_field_value('debug', $options['debug'], $post_data) == 'yes' ? true : false);

        // set addresses & tokens
        $networks_data = $this->get_field_value('networks', $options['networks'], $post_data);
        $tokens_data = $this->get_field_value('tokens', $options['tokens'], $post_data);

        foreach ($this->options->getNetworks() as $network) {

            $this->options->getCurrency($network->abbr)->parseAbbr()->setAddress($networks_data[$network->abbr]);
            $this->options->getCurrency($network->abbr)->setPolicy($this->options->getExtra('processing_fee'));
            if ($network->isNetwork()) {
                $tokens = $network->getTokens($this->options->currencies);
                if($tokens) {
                    $tokens = array_merge([$network], $tokens);
                    foreach ($tokens as $token) {
                        $this->options->getCurrency($token->abbr)->setPolicy($this->options->getExtra('processing_fee'));
                        $this->options->getCurrency($token->abbr)->setAddress($network->getAddress());
                        $this->options->setExtra($token->abbr, array_key_exists($token->abbr, $tokens_data) ? 1 : 0);
                    }
                }
            }            
        }
        $this->options->saveCurrencies();
        $this->update_option('options', $this->options->toJson());
    }
    
    public function admin_options()
    {
        global $wp_version;
        $this->form_fields = $this->admin_fields();

        foreach ($this->options->getNetworks() as $network) {
            if ($network->hasError()) {
                $this->add_error(sprintf("<strong>%s has error:</strong> %s", ...[$network->name, $network->getError()]));
            }
        }
        if ($this->get_errors()) {
            $this->display_errors();
        }   

        ?>
            <h3><?php _e('Multi Crypto Currency Payment Gateway', 'mccp'); ?></h3>
            <div><?php _e('This plugin uses the Apirone crypto processing service.', 'mccp'); ?> <a href="https://apirone.com" target="_blank"><?php _e('Details'); ?></a></div>
            <hr>
            <table class="form-table mccp-settings">
                <?php $this->generate_settings_html($this->form_fields); ?>
            </table>
            <div><hr/>
            <h3>Plugin Info:</h3>
                Version: <b><?php echo $this->get_option('version', 'n/a'); ?></b></br>
                Account: <b><?php echo $this->options->getAccount(); ?></b><br/>
                PHP version: <b><?php echo phpversion(); ?></b><br/>
                WordPress: <b><?php echo $wp_version; ?></b><br/>
                WooCommerce: <b><?php echo WC_VERSION; ?></b><br/>
                <?php _e('Apirone support: '); ?><a href="mailto:support@apirone.com">support@apirone.com</a>
                <hr/>
            </div>
        <?php
    }

    public function admin_fields()
    {
        return array(
            'enabled' => array(
                'title' => __('On/off', 'mccp'),
                'type' => 'checkbox',
                'label' => __('On', 'mccp'),
                'default' => 'no',
                'description' => __('Activate/deactivate MCCP gateway', 'mccp'),
                'desc_tip' => true,
            ),
            'options' => array(
                'type' => 'options',
                'description' => '',
                'default' => [],
            ),
        );
    }

    private function options_fields()
    {
        return array(
                'merchant' => array(
                'title' => __('Merchant name', 'mccp'),
                'type' => 'text',
                'default' => '',
                'description' => __('Shows Merchant name in the payment order. If this field is blank then Site Title from General settings will be shown.', 'mccp'),
                'desc_tip' => true,
            ),
            'test_customer' => array(
                'title' => __('Test currency customer', 'mccp'),
                'type' => 'text',
                'default' => '',
                'placeholder' => 'user@example.com',
                'description' => __('Enter an email of the customer to whom the test currencies will be shown.', 'mccp'),
                'desc_tip' => true,
            ),
            'timeout' => array(
                'title' => __('Payment timeout, sec.', 'mccp'),
                'type' => 'number',
                'default' => '1800',
                'description' => __('The period during which a customer shall pay. Set value in seconds', 'mccp'),
                'desc_tip' => true,
                'custom_attributes' => array('min' => 0,),
            ),
            'networks' => array(
                'type' => 'networks',
                'description' => __('List of available cryptocurrencies processed by Apirone', 'mccp'),
                'desc_tip' => true,
                'default' => [],
            ),
            'tokens' => array(
                'type' => 'tokens',
                'description' => __('List of available cryptocurrencies processed by Apirone', 'mccp'),
                'desc_tip' => true,
                'default' => [],
            ),
            'processing_fee' => array(
                'title' => __('Processing fee plan', 'mccp'),
                'type' => 'select',
                'options' => [
                    'percentage' => __('Percentage'),
                    'fixed' => __('Fixed'),
                ],
                'default' => 'fixed',
            ),
            'factor' => array(
                'title' => __('Price adjustment factor', 'mccp'),
                'type' => 'number',
                'default' => '1',
                'description' => __('If you want to add/subtract percent to/from the payment amount, use the following  price adjustment factor multiplied by the amount.<br />For example: <br />100% * 0.99 = 99% <br />100% * 1.01 = 101%', 'mccp'),
                'desc_tip' => true,
                'custom_attributes' => array('min' => 0.01, 'step' => '0.01', 'required' => 'required'),
            ),
            'logo' => array(
                'title' => __('Apirone logo', 'mccp'),
                'type' => 'checkbox',
                'label' => __('Display', 'mccp'),
                'default' => 'yes',
                'description' => __('Display Apirone logo on invoice page', 'mccp'),
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug mode', 'mccp'),
                'type' => 'checkbox',
                'label' => __('On', 'mccp'),
                'default' => 'no',
                'description' => __('Write debug information into log file', 'mccp'),
                'desc_tip' => true,
            ),
        );
    }

    public function generate_options_html ($key, $data)
    {
        $fields = $this->options_fields();
        ob_start();
        echo $this->generate_text_html('merchant', $fields['merchant']);
        echo $this->generate_text_html('test_customer', $fields['test_customer']);
        echo $this->generate_text_html('timeout', $fields['timeout']);
        echo $this->generate_networks_html('networks', $fields['networks']);
        echo $this->generate_select_html('processing_fee', $fields['processing_fee']);
        echo $this->generate_text_html('factor', $fields['factor']);
        echo $this->generate_checkbox_html('logo', $fields['logo']);
        echo $this->generate_checkbox_html('debug', $fields['debug']);
        return ob_get_clean();
    }
    /**
    * Generate currencies list options for admin page
    *
    * @param mixed $key 
    * @param mixed $data 
    * @return string|false 
    */
    public function generate_networks_html ($key, $data)
    {
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php _e('Networks & tokens', 'mccp'); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp" id="mccp-order_states">
                <div class="table-wrapper">
                    <table class="form-table">
                        <tbody>
                        <?php
                        foreach($this->options->getNetworks() as $network) : ?>
                            <?php $tokens = $network->getTokens($this->options->getCurrencies()); 
                                $blockchain = ($tokens) ? __(' Blockchain', 'mccp') : '';
                            ?>
                            <tr valign="middle" class="single_select_page">
                                <th scope="row" class="titledesc">
                                    <label for="mccp_<?php echo esc_html( $network->getAbbr() ); ?>" class="currency-label">
                                        <span class="currency-icon <?php echo $network->getAbbr(); ?>"></span>
                                        <span style="position:relative">
                                        <?php echo esc_html( $network->getName() . $blockchain ); ?>
                                        <?php if ($network->isTestnet()) : ?>
                                        <?php echo wc_help_tip(__('Use this currency for test purposes only! This currency shown for admin and "test currency customer" (if set) is only on the front end of Woocommerce!')); ?>
                                        <?php endif; ?>
                                        </span>

                                    <?php echo wc_help_tip(sprintf(__('Enter valid address to activate <b>%s</b> blockchain', 'mccp'), $network->getName())); ?>
                                    </label>
                                </th>
                                <td class="forminp">
                                    <input type="text" name="woocommerce_mccp_networks[<?php echo esc_html( $network->getAbbr() ); ?>]" class="input-text regular-input" value="<?php echo esc_html( $network->getAddress() ); ?>">
                                    <?php  if ($tokens) : ?>
                                        <div class="tokens_wrapper">
                                        <?php $tokens = array_merge([$network], $tokens); ?>
                                            <?php foreach ($tokens as $token) : ?>
                                            <div class="token_item">
                                                <span class="currency-icon <?php echo str_replace('@', '_', esc_html($token->getAbbr())); ?>"></span>
                                                <label for="woocommerce_mccp_tokens[<?php echo esc_html($token->getAbbr()); ?>]">
					                                <input type="checkbox" name="woocommerce_mccp_tokens[<?php echo esc_html($token->getAbbr()); ?>]"
                                                    id="woocommerce_mccp_tokens[<?php echo esc_html($token->getAbbr()); ?>]"
                                                    <?php if ($this->options->getExtra($token->getAbbr()) == 1) : ?>
                                                    checked="checked"
                                                    <?php endif; ?>>
                                                    <?php echo $token->getName(); ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
            </td>
        </tr>

        <?php
        return ob_get_clean();
    }

    public function validate_networks_field($k, $v)
    {
        return $v;
    }

    public function validate_tokens_field($k, $v)
    {
        return ($v == null) ? [] : $v;
    }

    public function show_invoice_admin_info($order)
    {
        if (is_admin() && $order->payment_method == 'mccp') {
            echo '<h3>' . __('Payment details', 'mccp') . '</h3>';
            $invoices = WC_MCCP::get_order_invoices($order->get_id());
            if ($invoices) {
                foreach ($invoices as $invoice) {
                    $currency = Apirone::getCurrency($invoice->details->currency);
                    echo '<hr />';
                    echo sprintf(__('<div>Address: <b>%s</b></div>', 'mccp'), $invoice->details->address);
                    echo sprintf(__('<div>Created: <b>%s</b></div>', 'mccp'), get_date_from_gmt($invoice->details->created, 'd.m.Y H:i:s'));
                    echo sprintf(__('<div>Amount: <b>%s %s</b></div>', 'mccp'), Apirone::min2cur($invoice->details->amount, $currency->{'units-factor'}), strtoupper($invoice->details->currency));
                    echo sprintf(__('<div>Status: <b>%s</b></div>', 'mccp'), $invoice->status);
                    foreach ($invoice->details->history as $item) {
                        $status = property_exists($item, 'txid') ? ' <a class="address-link" href="' . Apirone::getTransactionLink($currency, $item->txid) . '" target="_blank">' . $item->status . '</a>' : $item->status;
                        echo '<div>- <i>' . get_date_from_gmt($item->date, 'd.m.Y H:i:s') . '</i> <b>' . $status . '</b></div>';
                    }
                }
            }
            else {
                echo '<p>' . __('Invoice for this order not found', 'mccp') . '</p>';
            }
        }
        return;
    }

    public function do_update()
    {
        return;
        $settings = get_option('woocommerce_mccp_settings', false); 
        if (!$settings) {
            return;
        }

        $version = $settings['version'];
        $code_version = get_plugin_data(MCCP_MAIN)['Version'];

        if(version_compare($code_version, $version, '=')) {
            return;
        }

        // Update to 2.0.0 - Move plugin to SDK
        $account = get_option('woocommerce_mccp_account', null);
        $options = ($account) ? Options::fromJson($account) : Options::init()->createAccount();

        if ((version_compare($version, '1.1.0', '>=') && version_compare($version, '1.2.10', '<=')) && $account !== null) {
            // Update table - rename order_id to order
            global $wpdb, $table_prefix;
            $query = "ALTER TABLE `". $table_prefix . "apirone_mccp` RENAME COLUMN `order_id` to `order`";
            $wpdb->query($query);

            // // Move options
            $options->setMerchant($settings['merchant']);
            $options->setFactor((float) $settings['factor']);
            $options->setTimeout((int) $settings['timeout']);
            $options->setLogo($settings['apirone_logo']);
            $options->setDebug($settings['debug']);

            $options->setExtra('test_customer', $settings['test_customer']);
            $options->setExtra('processing_fee', $settings['processing_fee']);            
        }

        if ($version == false) {
            // Update 1.0.0
            $currencies = is_array($settings['currencies']) ? $settings['currencies'] : [];
            foreach ($options->getNetworks() as $network) {
                if (array_key_exists($network->abbr, $currencies)) {
                    $network->setAddress($currencies[$network->abbr]['address']);
                }
            }

            $options->saveCurrencies();
        }

        $settings['enabled'] = $this->settings['enabled'];
        // // $settings['options'] = $options->toJsonString();
        // $settings['secret'] = get_option('woocommerce_mccp_secret');
        // $settings['version'] = $code_version;

        unset($this->settings['options']);
        // update_option('woocommerce_mccp_settings', $this->settings);
        // delete_option('woocommerce_mccp_wallets');
        // delete_option('woocommerce_mccp_account');
        // delete_option('woocommerce_mccp_secret');
    }
}
