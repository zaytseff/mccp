<?php

if (!defined('ABSPATH')) exit;

use Apirone\SDK\Invoice;
use Apirone\SDK\Model\Settings;
use Apirone\SDK\Model\Settings\Currency;

class WC_MCCP extends WC_Payment_Gateway
{
    public Settings $options;

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
        $this->init_settings();
        $this->do_update();

        $this->options = Settings::fromJson($this->get_option('options'));
    }

    public function invoice_receipt($order_id) {
        echo(__FUNCTION__);
    }


    public function payment_fields()
    {
        echo 'Coming soon';   
    }

    /**
     * Payment process handler
     * 
     * @param int $order_id 
     * @return array 
     */
    public function process_payment($order_id) {
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
        $this->mccp_init_form_fields();
        return parent::process_admin_options();
    }
    
    public function get_option($key, $empty_value = null)
    {
        return parent::get_option($key, $empty_value = null);        
    }

    public function admin_options() {
        global $wp_version;
        $this->mccp_init_form_fields();
        ?>
            <h3><?php _e('Multi Crypto Currency Payment Gateway', 'mccp'); ?></h3>
            <div><?php _e('This plugin uses the Apirone crypto processing service.', 'mccp'); ?> <a href="https://apirone.com" target="_blank"><?php _e('Details'); ?></a></div>
            <hr>
            <table class="form-table mccp-settings">
                <?php $this->generate_settings_html($this->form_fields); ?>
            </table>
            <div><hr/>
            <h3>Plugin Info:</h3>
                Version: <b><?php echo $this->get_option('version'); ?></b></br>
                Account: <b><?php // echo $this->mccp_account()->account; ?></b><br/>
                PHP version: <b><?php echo phpversion(); ?></b><br/>
                WordPress: <b><?php echo $wp_version; ?></b><br/>
                WooCommerce: <b><?php echo WC_VERSION; ?></b><br/>
                <?php _e('Apirone support: '); ?><a href="mailto:support@apirone.com">support@apirone.com</a>
                <hr/>
            </div>
        <?php
    }

    public function mccp_init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('On/off', 'mccp'),
                'type' => 'checkbox',
                'label' => __('On', 'mccp'),
                'default' => 'no',
                'description' => __('Activate/deactivate MCCP gateway', 'mccp'),
                'desc_tip' => true,
            ),
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
            'currencies' => array(
                'type' => 'currencies_list',
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
                'default' => 'percentage',
            ),
            'factor' => array(
                'title' => __('Price adjustment factor', 'mccp'),
                'type' => 'number',
                'default' => '1',
                'description' => __('If you want to add/subtract percent to/from the payment amount, use the following  price adjustment factor multiplied by the amount.<br />For example: <br />100% * 0.99 = 99% <br />100% * 1.01 = 101%', 'mccp'),
                'desc_tip' => true,
                'custom_attributes' => array('min' => 0.01, 'step' => '0.01', 'required' => 'required'),
            ),
            'apirone_logo' => array(
                'title' => __('Apirone logo', 'mccp'),
                'type' => 'checkbox',
                'label' => __('Show Apirone logo on invoice page', 'mccp'),
                'default' => 'yes',
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

    /**
    * Generate currencies list options for admin page
    *
    * @param mixed $key 
    * @param mixed $data 
    * @return string|false 
    */
    public function generate_currencies_list_html ($key, $data) {
        return;
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php _e('Currencies', 'mccp'); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp" id="mccp-order_states">
                <div class="table-wrapper">
                    <table class="form-table">
                        <tbody>
                        <?php
                        foreach($this->mccp_currencies() as $currency) : ?>
                            <tr valign="middle" class="single_select_page">
                                <th scope="row" class="titledesc">
                                    <label for="mccp_<?php echo esc_html( $currency->abbr ); ?>" class="currency-label">
                                    <div class="<?php echo $this->currency_icon_wrapper($currency) ?>">
                                        <img src="<?php echo Apirone::currencyIcon($currency->abbr); ?>" alt="<?php echo esc_html( $currency->name ); ?>" class="currency-icon">
                                    </div>
                                    <?php echo esc_html( $currency->name ); ?>

                                    <?php echo wc_help_tip(sprintf(__('Enter valid address to activate <b>%s</b> currency', 'mccp'), $currency->name)); ?>
                                    </label>
                                </th>
                                <td class="forminp">
                                    <input type="text" name="woocommerce_mccp_currencies[<?php echo esc_html( $currency->abbr ); ?>][address]" class="input-text regular-input" 
                                        value="<?php echo esc_html( $currency->address ); ?>">
                                    <?php if ( $currency->address ) : ?>
                                    <input type="checkbox" name="woocommerce_mccp_currencies[<?php echo esc_html( $currency->abbr ); ?>][enabled]" class="currency-enabled" 
                                        <?php echo $currency->enabled ? ' checked' : ''; ?>
                                        <?php echo !$currency->valid ? ' disabled' : ''; ?>
                                    >
                                    <?php if( $currency->address) : ?>
                                        <?php if( $currency->valid ) : ?>
                                        <span class="valid">
                                            <?php _e('Use currency', 'mccp'); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="not-valid">
                                            <?php _e('Address is not valid', 'mccp'); ?>
                                        </span>
                                        <?php endif;?>
                                    <?php endif;?>
                                    <?php endif;?>
                                    <?php if ($currency->testnet) : ?>
                                        <div class="testnet-info"><span class="testnet-info__message"><?php _e('WARNING: Test currency', 'mccp'); ?></span>
                                        <?php echo wc_help_tip(__('Use this currency for test purposes only! This currency shown for admin and "test currency customer" (if set) is only on the front end of Woocommerce!')); ?></div>
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

    public function show_invoice_admin_info($order) {
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
        $version = $this->get_option('version', false);
        $code_version = get_plugin_data(MCCP_MAIN)['Version'];

        if(version_compare($code_version, $version, '=')) {
            return;
        }

        // Update to 2.0.0 - Move plugin to SDK
        $account = get_option('woocommerce_mccp_account');
        $options = ($account) ? Settings::fromExistingAccount($account->account, $account->{'transfer-key'}) : Settings::init();

        if (version_compare($version, '1.1.0', '>=') && version_compare($version, '1.2.10', '<=')) {
            // Update table - rename order_id to order
            global $wpdb, $table_prefix;
            // $table = $table_prefix . 'apirone_mccp';
            $query = sprintf("ALTER TABLE `%sapirone_mccp` RENAME COLUMN `order_id` to `order`", $table_prefix);
            $wpdb->query($query);

            // Move options
            $options->setMerchant($this->settings['merchant']);
            $options->setFactor((float) $this->settings['factor']);
            $options->setTimeout((int) $this->settings['timeout']);
            $options->setLogo($this->settings['apirone_logo']);
            $options->setDebug($this->settings['debug']);

            $options->setExtra('test_customer', $this->settings['test_customer']);
            $options->setExtra('processing_fee', $this->settings['processing_fee']);            
        }

        if ($version == false) {
            // Update 1.0.0
            $currencies = is_array($this->settings['currencies']) ? $this->settings['currencies'] : [];
            foreach ($options->getNetworks() as $network) {
                if (array_key_exists($network->abbr, $currencies)) {
                    $network->setAddress($currencies[$network->abbr]['address']);
                }
            }

            $options->saveCurrencies();
        }

        $settings['enabled'] = $this->settings['enabled'];
        $settings['options'] = $options->toJsonString();
        $settings['secret'] = get_option('woocommerce_mccp_secret');
        $settings['version'] = $code_version;

        $this->settings = $settings;
        update_option('woocommerce_mccp_settings', $settings);
        delete_option('woocommerce_mccp_wallets');
        delete_option('woocommerce_mccp_account');
        delete_option('woocommerce_mccp_secret');
        pa(__FUNCTION__);
    }
}
