<?php

use ApironeApi\Apirone;
use ApironeApi\Db;
use ApironeApi\Request;

require_once('invoice-db-trait.php');
require_once('apirone_api/Apirone.php');

trait MCCP_Utils {

    /**
     * Generate receipt error html
     * 
     * @param mixed $reason 
     * @param string $sub 
     * @return void 
     */
    static public function receipt_error_message($reason, $sub = '') {

        switch ($reason) {
            case 'disabled': 
                $message = __('Payment method disabled', 'mccp');
                break;
            case 'required': 
                $message = __('Required param not exists', 'mccp');
                break;
            case 'not-supported': 
                $message = __(sprintf('Currency \'%s\' is not supported', $sub), 'mccp');
                break;
            case 'not-valid': 
                $message = __(sprintf('Currency \'%s\' has no valid address', $sub), 'mccp');
                break;
            case 'not-enabled': 
                $message = __(sprintf('Currency \'%s\' is not enabled', $sub), 'mccp');
                break;
        }

        echo '<div class="receipt_info">' . esc_html( $message ) . '</div>';
    }

    /**
     * Return woocommerce order status depends on invoice status
     *
     * @param mixed $invoice 
     * @return mixed 
     */
    public static function get_order_status_by_invoice($invoice) {
        switch ($invoice->status) {
            case 'created':
            case 'partpaid':
            case 'paid':
            case 'overpaid':
                $status = 'pending';
                break;
            case 'completed':
                $status = 'processing';
                break;
            case 'expired':
                $status = 'failed';
        }
        return $status;
    }

    /**
     * Get account.
     * Create new account when option 'woocommerce_mccp_account' not exist
     *
     * @return object|false
     */
    public function mccp_account($renew = false) {
        $account = get_option('woocommerce_mccp_account');
        if ( !$account || $renew ) {
            $account = Apirone::accountCreate();
            update_option('woocommerce_mccp_account', $account);
            $this->save_settings_to_account();
        }
        return $account;
    }

    /**
     *    Get secret.
     *  Create new secret when option 'woocommerce_mccp_secret' not exist
     *
     * @return object|false
     */

    public function mccp_secret($renew = false) {
        $secret = get_option('woocommerce_mccp_secret');

        if ( !$secret || $renew ) {
            $secret = md5(time());
            update_option('woocommerce_mccp_secret', $secret);
        }
        return $secret;
    }

    /**
     * Return currency object by currency abbr or false if not exist
     *
     * @param mixed $abbr currency abbr - btc, doge, etc
     * @return false|object
     */
    public function get_mccp_currency($abbr) {
        $currencies = (array) $this->get_option('currencies');

        if ( $currencies && array_key_exists($abbr, $currencies) ) {
            return $currencies[$abbr];
        }

        return false;
    }

    public function get_crypto_total($value, $currency, $abbr) {
        $factor = $this->get_option('factor');

        return Apirone::fiat2crypto($value * $factor, $currency, $abbr);
    }

    /**
    * Generate MCCP payment fields
    * 
    * @return void 
    */
    function payment_fields() {
        $test_customer = $this->get_option('test_customer');
        $show_test_net = false;

        if (WC()->customer->get_billing_email() == $test_customer || $test_customer == '*') {
            $show_test_net = true;
        }

        if ($this->is_repayment()) {
            $order_id = $this->is_repayment();
            $order    = wc_get_order( $order_id );
            $total = $order->get_total();
        }
        else {
            $total = WC()->cart->total;
        }

        $woo_currency = get_woocommerce_currency();
        $active_currencies = array();

        foreach ((array) $this->get_option('currencies') as $item) {
            if ($item->testnet === 1 && !current_user_can('manage_options')) {
                if ( !$show_test_net ) {
                    continue;
                }
            }

            if (!empty($item->address) && $item->enabled && $item->valid) {
                $currency = array();

                $crypto_total = Apirone::exp2dec($this->get_crypto_total($total, $woo_currency, $item->abbr), 0);

                $currency['abbr'] = $item->abbr;
                $currency['name'] = $item->name;
                $currency['total'] = $crypto_total;
                $currency['dust-rate'] = Apirone::exp2dec(Apirone::min2cur($item->{'dust-rate'}, $item->{'units-factor'}), 0);
                $currency['payable'] = $currency['total'] >= $currency['dust-rate'] ? true : false;
                $active_currencies[] = $currency;
            }
        }
        // If plugin active but haven't active currencies
        if ( empty($active_currencies)) {
            _e('Cryptocurrency payment temporary unavailable. Choose other payment method.', 'mccp');
            return;
        }
        ?>
        <select id="mccp_currency" name="mccp_currency">
        <?php foreach ( $active_currencies as $currency ) : ?>
            <option <?php echo (!$currency['payable']) ? 'disabled' : ''; ?> value="<?php echo esc_html($currency['abbr']); ?>">
                <?php echo esc_html($currency['name']); ?>:
                <?php echo esc_html($currency['total']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    function is_repayment () {
        if (isset($_GET['pay_for_order'])) {
            return get_query_var('order-pay', false);
        }

        return false;
    }

    /**
     * save existing settings for account
     *
     * @return void 
     */
    function save_settings_to_account() {
        $settings = get_option('woocommerce_mccp_settings');
        if ( !$settings ) {
            return;
        }

        $account = $this->mccp_account();
        $processing_fee = $this->get_option('processing_fee', 'percentage');
        $endpoint = '/v2/accounts/' . $account->account;

        foreach ($settings['currencies'] as $currency) {
            $params['transfer-key'] = $account->{'transfer-key'};
            $params['currency'] = $currency->abbr;
            $params['destinations'] = $currency->address ? [["address" => $currency->address]] : null;
            $params['processing-fee-policy'] = $processing_fee;

            Request::execute('patch', $endpoint, $params, true);
        }
    }

    /**
     * Get plugin version from settings
     *
     * @return false|string
     */
    function version() {
        return $this->get_option('version', false);
    }

    /**
     * Update plugin version only
     *
     * @return void 
     */    
    function version_update($new_version) {
        if ($this->update_option('version', $new_version)) {
            $this->version = $new_version;
        }
    }

    /**
     * Plugin version update entry point
     *
     * @return void 
     */
    function update() {
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
}
