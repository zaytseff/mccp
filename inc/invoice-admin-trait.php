<?php
use ApironeApi\Apirone;

require_once('apirone_api/Apirone.php');

# TODO: add two params for status check & page 
# MAke additional section for those params

trait MCCP_Admin {

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

	/**
	* Generate admin page html code
	* 
	* @return void 
	*/
	public function admin_options() {
		$this->mccp_init_form_fields();
		if ( Apirone::isFiatSupported(get_option('woocommerce_currency')) ) :?>
			<h3><?php _e('Multi Crypto Currency Payment Gateway', 'mccp'); ?></h3>
			<div><?php _e('This plugin uses the Apirone crypto processing service.', 'mccp'); ?> <a href="https://apirone.com" target="_blank"><?php _e('Details'); ?></a></div>
			<table class="form-table mccp-settings">
				<?php $this->generate_settings_html($this->form_fields); ?>
			</table>
		<?php else: ?>
				<div class="inline error">
				<?php if ( Apirone::serviceInfo() ) : ?>
					<p><strong><?php _e('Currency check error', 'mccp'); ?></strong>: <?php _e('MCCP don\'t support your shop currency', 'mccp'); ?></p>
				<?php else : ?>
					<p><strong><?php _e('Gateway offline', 'mccp'); ?></strong>: <?php _e('Service not available. Please, try later', 'mccp'); ?></p>
					<?php endif; ?>
				</div>
		<?php endif;
	}

	/**
	 * Save apirone currensies into options table
	 *
	 * @return void 
	 */
	public function save_currencies() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$currencies = $this->mccp_currencies();

			// Remove not valid currensies before save option
			foreach ($currencies as $key => $value) {
				if (!empty($value->address) && !$value->valid) {
					$currencies[$key]->address = '';
					$currencies[$key]->enabled = 0;
					$currencies[$key]->valid = 0;
				}
			}
			$this->update_option('currencies', $currencies);
		}
	}

	/**
	* Generate currencies list options for admin page
	*
	* @param mixed $key 
	* @param mixed $data 
	* @return string|false 
	*/
	public function generate_currencies_list_html ($key, $data) {
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
										<?php echo wc_help_tip(__('Use this currency for test purposes only! This currency shown for admin only on front of woocommerce!')); ?></div>
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

	/**
	 * Init Gateway Settings Form Fields	
	 *
	 * @return void
	*/
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
			'factor' => array(
				'title' => __('Price adjustment factor', 'mccp'),
				'type' => 'number',
				'default' => '1',
				'description' => __('If you want to add/substract percent to/from the payment amount, use the following  price adjustment factor multiplied by the amount.<br />For example: <br />100% * 0.99 = 99% <br />100% * 1.01 = 101%', 'mccp'),
				'desc_tip' => true,
				'custom_attributes' => array('min' => 0.01, 'step' => '0.01', 'required' => 'required'),
			),
			'check_timeout' => array(
				'title' => __('Status update period, sec.', 'mccp'),
				'type' => 'number',
				'default' => '10',
				'description' => __('How often the invoice status shall be checked. Min value 5 seconds.', 'mccp'),
				'desc_tip' => true,
				'custom_attributes' => array('min' => 5, 'required' => 'required'),
			),
			'backlink' => array(
				'title' => __('Back to store', 'mccp'),
				'type' => 'text',
				'default' => '',
				'placeholder' => site_url(''),
				'description' => __('Enter a valid URL for the "Back to store" link on the invoice page. If empty the home page is used.', 'mccp'),
				'desc_tip' => true,
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
	* Save apirone currensies into options table
	*/
	public function validate_currencies_list_field($k, $v) {
		$currencies = $this->mccp_currencies();

		foreach ($currencies as $key => $value) {
			if (!empty($value->address) && !$value->valid) {
				$currencies[$key]->address = '';
				$currencies[$key]->enabled = 0;
				$currencies[$key]->valid = 0;
			}
		}
		return $currencies;
	}


	/**
	 * Return css-class for currency icon
	 * 
	 * @param mixed $currency 
	 * @return string 
	 */
	public function currency_icon_wrapper($currency) {
		return 'icon-wrapper' . ((substr_count(strtolower($currency->name), 'testnet') > 0) ? ' test-currency': '');
	}

	public function mccp_currency($apirone_currency, $apirone_account = false) {
			$currency = $this->get_mccp_currency($apirone_currency->abbr);
			if ($currency == false || gettype($currency) === 'array' || !property_exists($currency, 'name')) {
				$currency = new \stdClass();

				$currency->name = $apirone_currency->name;
				$currency->abbr = $apirone_currency->abbr;
				$currency->{'dust-rate'} = $apirone_currency->{'dust-rate'};
				$currency->{'units-factor'} = $apirone_currency->{'units-factor'};
				$currency->address = '';
				$currency->testnet = $apirone_currency->testnet;
				$currency->enabled = 0;
				$currency->valid = 0;
			}

			// Set address from config 
			if ($_SERVER['REQUEST_METHOD'] == 'POST' && $apirone_account) {
				if (array_key_exists('enabled', $_POST['woocommerce_mccp_currencies'][$currency->abbr])) {
					$currency->enabled = sanitize_text_field($_POST['woocommerce_mccp_currencies'][$currency->abbr]['enabled']);
				}
				$currency->address = sanitize_text_field($_POST['woocommerce_mccp_currencies'][$currency->abbr]['address']);
				if ($currency->address != '') {
					$result = Apirone::setTransferAddress($apirone_account, $apirone_currency->abbr, $currency->address);
					$currency->valid = $result ? 1 : 0;
				}
			}
			return $currency;
	}

	public function mccp_currencies() {

		$apirone_account = $this->mccp_account();
		$currencies = array();

		foreach (Apirone::currencyList() as $apirone_currency) {
			$currencies[$apirone_currency->abbr] = $this->mccp_currency($apirone_currency, $apirone_account);
		}

		return $currencies;	
	}
}
