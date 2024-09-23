<?php

if (!defined('ABSPATH')) exit;

use Apirone\SDK\Invoice;
use Apirone\SDK\Model\Settings;


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
    }

    public function invoice_receipt($order_id) {
        echo(__FUNCTION__);
    }
    /**
     * Payment process handler
     * 
     * @param int $order_id 
     * @return array 
     */
    public function process_payment($order_id) {
    pa(__FUNCTION__);
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
        $plugin_data = get_plugin_data(MCCP_MAIN);
        $ver_pl = get_plugin_data(MCCP_MAIN)['Version'];
        $ver_db = $this->get_option('version');
        $res = version_compare($ver_pl, $ver_db);
        pa([
            'ver_pl' => $ver_pl,
            'ver_db' => $ver_db,
            'res' => $res,
        ]);

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

    public function assets()
    {

    }

    public function show_invoice_admin_info($order) {
        if ($order->payment_method == 'mccp') {
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
        // $plugin_data = get_plugin_data(MCCP_MAIN);
        // $version = get_plugin_data(MCCP_MAIN)['Version'];
        // pa($this->settings);
        
        // $settings = get_option('woocommerce_mccp_settings');
        $version = $this->settings['version'];
        // pa($version);
        // $res = version_compare($code_version, $settings['version']);

        // if ($res == 0) {
        //     return;
        // }
        // return;

        // $version = "3.4.0.1";
        // if (version_compare($version, "3.1.0", ">=") && version_compare($version, "3.3.5.1", "<=")) {
        //     //Version in range
        // }


        // Update range from 1.1.1 to 1.2.7 - unset backlink
        if (version_compare($version, '1.1.1', '>=') && version_compare($version, '1.2.7', '<=')) {
        
        }

        // Update from 1.0.0 to 1.1.0 - modify db
        if ($version == false) {
            return; 
            global $wpdb, $table_prefix;

            // Table update when plugin already installed & active
            $table = $table_prefix . 'apirone_mccp';
            $is_metadata = sprintf('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = "%s" AND column_name = "meta"', $table);
            $row = $wpdb->get_results($is_metadata);
            if(empty($row)){
                $add_metadata = sprintf("ALTER TABLE `%s` ADD `meta` text NULL AFTER `details`", $table);
                $wpdb->query($add_metadata);
            }

            // Old version settings update
            $settings = get_option('woocommerce_mccp_settings');
            if ( !$settings )
                return;

            $apirone_account = $this->mccp_account();

            // Map old currencies
            foreach (Apirone::currencyList() as $apirone_currency) {
                $currency = $this->mccp_currency($apirone_currency, $apirone_account);
                if (array_key_exists($apirone_currency->abbr, (array) $settings['currencies'])) {
                    $old_currency = $settings['currencies'][$apirone_currency->abbr];
                    if (gettype($old_currency) === 'array') { // Update from version 1.0.0
                        if ($old_currency['address']) {
                            $currency->address = $old_currency['address'];
                            $result = Apirone::setTransferAddress($apirone_account, $currency->abbr, $old_currency['address']);
                            if ($result) {
                                $currency->valid = 1;
                            }
                        }
                        $currency->enabled = ($old_currency['enabled']) ? 1 : 0;
                    }
                    else { // Clear install of 1.1.1
                        $currency = $old_currency;
                    }
                }
                $mccp_currencies[$apirone_currency->abbr] = $currency;

            }
            // Update currencies
            $settings['currencies'] = $mccp_currencies;
            // Add new params
            $settings['factor'] = '1';
            $settings['timeout'] = '1800';
            $settings['check_timeout'] = '10';
            $settings['backlink'] = '';
            $settings['apirone_logo'] = 'yes';
            $settings['version'] = '1.1.0';
            
            // Unset unused
            unset($settings['count_confirmations']);
            unset($settings['debug']);
            unset($settings['wallets']);
            unset($settings['statuses']);

            update_option('woocommerce_mccp_settings', $settings);
            delete_option('woocommerce_mccp_wallets');
        }

    }

    //******************************************************************* */

    /**
     * Plugin version update entry point
     *
     * @return void 
     */
    function _update() {
        $this->update_1_0_0__1_1_0();
        $this->update_1_1_0__1_1_1();
        $this->update_1_1_1__1_2_0();
        $this->update_1_2_0__1_2_1();
        $this->update_1_2_1__1_2_2();
        $this->update_1_2_2__1_2_3();
        $this->update_1_2_3__1_2_7();
        $this->update_1_2_7__1_2_8();
        $this->update_1_2_8__1_2_9();
        $this->update_1_2_9__1_2_10();
    }

    function update_1_2_9__1_2_10() {
        if ($this->version() !== '1.2.9') {
            return;
        }
    
        $this->version_update('1.2.10');
    }

    function update_1_2_8__1_2_9() {
        if ($this->version() !== '1.2.8') {
            return;
        }
        $this->save_settings_to_account();

        $this->version_update('1.2.9');
    }

    function update_1_2_7__1_2_8() {
        if ($this->version() !== '1.2.7') {
            return;
        }
        $this->save_settings_to_account();

        $settings = get_option('woocommerce_mccp_settings');
        if ( !$settings ) {
            return;
        }
        $settings['processing_fee'] = 'percentage';
        unset($settings['woocommerce_mccp_account']);
        update_option('woocommerce_mccp_settings', $settings);

        $this->version_update('1.2.8');
    }

    /**
     * Update plugin from 1.2.3 to 1.2.7
     * @return void 
     */
    function update_1_2_3__1_2_7() {
        if (in_array($this->version(), ['1.2.3','1.2.4','1.2.5','1.2.6'])) {
            $this->version_update('1.2.7');

            $settings = get_option('woocommerce_mccp_settings');
            unset($settings['backlink']);
            update_option('woocommerce_mccp_settings', $settings);
        }
    }

    /**
     * Update plugin from 1.2.2 to 1.2.3
     * @return void 
     */
    function update_1_2_2__1_2_3() {
        if ($this->version() !== '1.2.2') {
            return;
        }
        $this->version_update('1.2.3');
    }

    /**
     * Update plugin from 1.2.1 to 1.2.2
     * Fix mobile layout
     * @return void 
     */
    function update_1_2_1__1_2_2() {
        if ($this->version() !== '1.2.1') {
            return;
        }
        $this->version_update('1.2.2');
    }

    /**
     * Update plugin from 1.2.0 to 1.2.1
     * Add a message when the invoice isn't created/found.
     * @return void 
     */
    function update_1_2_0__1_2_1() {
        if ($this->version() !== '1.2.0') {
            return;
        }
        $this->version_update('1.2.1');
    }

    /**
     * Update plugin from 1.1.1 to 1.2.0
     * Fee plan update to percentage
     * @return void 
     */
    function update_1_1_1__1_2_0() {
        if ($this->version() !== '1.1.1') {
            return;
        }
        
        $settings = get_option('woocommerce_mccp_settings');
        if ( !$settings ) {
            return;
        }
        $account = $this->mccp_account();
        $endpoint = '/v2/accounts/' . $account->account;

        foreach ($settings['currencies'] as $currency) {
            $params['transfer-key'] = $account->{'transfer-key'};
            $params['currency'] = $currency->abbr;
            $params['processing-fee-policy'] = 'percentage';
            
            Request::execute('patch', $endpoint, $params, true);
        }
        $this->version_update('1.2.0');
    }

    /**
     * Update plugin from 1.1.0 to 1.1.1
     *
     * @return void 
     */
    function update_1_1_0__1_1_1() {
        if ($this->version() !== '1.1.0') {
            return;
        }
        $this->version_update('1.1.1');
    }

    /**
     * Update plugin from 1.0.0 to 1.1.0
     *
     * @return void 
     */
    function update_1_0_0__1_1_0() {
        if ($this->version()) {
            return;
        }
        global $wpdb, $table_prefix;

        // Table update when plugin already installed & active
        $table = $table_prefix . DB::TABLE_INVOICE;
        $is_metadata = sprintf('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = "%s" AND column_name = "meta"', $table);
        $row = $wpdb->get_results($is_metadata);
        if(empty($row)){
            $add_metadata = sprintf("ALTER TABLE `%s` ADD `meta` text NULL AFTER `details`", $table);
               $wpdb->query($add_metadata);
        }

        // Old version settings update
        $settings = get_option('woocommerce_mccp_settings');
        if ( !$settings )
            return;

        $apirone_account = $this->mccp_account();

        // Map old currencies
        foreach (Apirone::currencyList() as $apirone_currency) {
            $currency = $this->mccp_currency($apirone_currency, $apirone_account);
            if (array_key_exists($apirone_currency->abbr, (array) $settings['currencies'])) {
                $old_currency = $settings['currencies'][$apirone_currency->abbr];
                if (gettype($old_currency) === 'array') { // Update from version 1.0.0
                    if ($old_currency['address']) {
                        $currency->address = $old_currency['address'];
                        $result = Apirone::setTransferAddress($apirone_account, $currency->abbr, $old_currency['address']);
                        if ($result) {
                            $currency->valid = 1;
                        }
                    }
                    $currency->enabled = ($old_currency['enabled']) ? 1 : 0;
                }
                else { // Clear install of 1.1.1
                    $currency = $old_currency;
                }
            }
            $mccp_currencies[$apirone_currency->abbr] = $currency;

        }
        // Update currencies
        $settings['currencies'] = $mccp_currencies;
        // Add new params
        $settings['factor'] = '1';
        $settings['timeout'] = '1800';
        $settings['check_timeout'] = '10';
        $settings['backlink'] = '';
        $settings['apirone_logo'] = 'yes';
        $settings['version'] = '1.1.0';
        
        // Unset unused
        unset($settings['count_confirmations']);
        unset($settings['debug']);
        unset($settings['wallets']);
        unset($settings['statuses']);

        update_option('woocommerce_mccp_settings', $settings);
        delete_option('woocommerce_mccp_wallets');
    }

    public function get_current_version()
    {

    }
}