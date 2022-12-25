<?php

use ApironeApi\Apirone;
use ApironeApi\Db;

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
	 *	Get account.
	 *  Create new account when option 'woocommerce_mccp_account' not exist
	 *
	 * @return object|false
	 */
	public function mccp_account($renew = false) {
		$account = get_option('woocommerce_mccp_account');

		if ( !$account || $renew ) {
			$account = Apirone::accountCreate();
			update_option('woocommerce_mccp_account', $account);
		}
		return $account;
	}

	/**
	 *	Get secret.
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
		$currencies = $this->get_option('currencies');

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
		foreach ($this->get_option('currencies') as $item) {
			if ($item->testnet === 1 && !current_user_can('manage_options')) {
				continue;
			}

			if (!empty($item->address) && $item->enabled && $item->valid) {
				$currency = array();

				$crypto_total = $this->get_crypto_total($total, $woo_currency, $item->abbr);

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
		global $wp;
		return array_key_exists('order-pay', $wp->query_vars) ? $wp->query_vars['order-pay'] : false;
	}

	/**
	 * Get plugin version
	 *
	 * @return false|string
	 */
	function version() {
		$settings = get_option('woocommerce_mccp_settings');
		return array_key_exists('version', $settings) ? $settings['version'] : false;
	}

	/**
	 * Plugin version update entry point
	 *
	 * @return void 
	 */
	function update() {
		if ($this->version() === false) {
			$this->update_1_0_0__1_1_0();
		}
	}

	/**
	 * Update plugin from 1.0.0 to 1.1.0
	 *
	 * @return void 
	 */
	function update_1_0_0__1_1_0() {
		global $wpdb, $table_prefix;

		// Table update when plugin aleady installed & active
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
		
		// Map old currensies
		foreach (Apirone::currencyList() as $apirone_currency) {
			$currency = $this->mccp_currency($apirone_currency, $apirone_account);
			if (array_key_exists($apirone_currency->abbr, $settings['currencies'])) {
				$old_currency = $settings['currencies'][$apirone_currency->abbr];
				if ($old_currency['address']) {
					$currency->address = $old_currency['address'];
					$result = Apirone::setTransferAddress($apirone_account, $currency->abbr, $old_currency['address']);
					if ($result) {
						$currency->valid = 1;
					}
				}
				$currency->enabled = ($old_currency['enabled']) ? 1 : 0;
			}

			$mccp_currencies[$apirone_currency->abbr] = $currency;

		}
		// Update currensies
		$settings['currencies'] = $mccp_currencies;
		// Add version
		$settings['version'] = '1.1.0';
		// Unset unused
		unset($settings['count_confirmations']);
		unset($settings['debug']);
		unset($settings['wallets']);
		unset($settings['statuses']);

		update_option('woocommerce_mccp_settings', $settings);		
	}

}