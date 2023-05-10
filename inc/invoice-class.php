<?php


use ApironeApi\Apirone;
use ApironeApi\Payment;

require_once('invoice-db-trait.php');
require_once('invoice-utils-trait.php');
require_once('invoice-admin-trait.php');

require_once('apirone_api/Apirone.php');
require_once('apirone_api/Payment.php');

/**
 * Multi Crypto Currency Payment Gateway class
 */

class WC_MCCP extends WC_Payment_Gateway {

	use MCCP_Db, MCCP_Utils, MCCP_Admin;

	// Common props
	public $id = 'mccp';
	public $title;
	public $description;
	public $method_title;
	public $method_description;

	// Plugin props
	public $enabled = false;
	public $currencies = array();
	public $statuses = array();
    public $errors_count = 0;

	public function __construct() {
		$this->update();
		$this->title         = __('Crypto currency payment', 'mccp');
		$this->description   = __('Start accepting multi cryptocurrency payments', 'mccp');
		$this->method_title  = __('Multi Crypto Currency Payments', 'mccp');
		$this->method_description = __('Start accepting multi cryptocurrency payments', 'mccp');

		$this->mccp_init_form_fields();

		$this->enabled = $this->get_option('enabled');
		$this->currencies = $this->get_option('currencies');

		add_action('woocommerce_receipt_mccp', array( $this, 'invoice_receipt' ));
		add_action('woocommerce_thankyou_mccp', array( $this, 'invoice_receipt' ));

		//Save our GW Options into Woocommerce
		add_action('woocommerce_update_options_payment_gateways_mccp', array($this, 'process_admin_options'));
		add_action('woocommerce_update_options_payment_gateways_mccp', array($this, 'save_currencies'));

		add_action('woocommerce_api_mccp_callback', array($this, 'mccp_callback_handler'));
		add_action('woocommerce_api_mccp_check', array($this, 'mccp_check_handler'));

		add_action( 'woocommerce_admin_order_data_after_billing_address', array($this, 'show_invoice_admin_info'));

	}

	/**
	 * Payment process handler
	 * 
	 * @param int $order_id 
	 * @return array 
	 */
	function process_payment($order_id) {
		$order = new WC_Order($order_id);
		$order_pay = $this->is_repayment() ? 'order-pay' : 'order';
		// Create redirect URL
		$redirect = get_permalink(wc_get_page_id('pay'));
		$redirect = add_query_arg($order_pay, $order->id, $redirect);
		$redirect = add_query_arg('key', $order->order_key, $redirect);
		$redirect = add_query_arg('mccp_currency', sanitize_text_field($_POST['mccp_currency']), $redirect);

		return array(
			'result'    => 'success',
			'redirect'  => $redirect,
		);
	}

	/**
	 * Handle payment provider callback
	 * @return never 
	 */
	function mccp_callback_handler () {

        $data = file_get_contents('php://input');
        if($data) {
            $params = json_decode(sanitize_text_field($data));
        }

        if (!$params) {
            wp_send_json("Data not received", 400);
            return;
        }

        if (!property_exists($params, 'invoice') || !property_exists($params, 'status')) {
            wp_send_json("Wrong params received: " . json_encode($params), 400);
            return;        
        }


        $invoice = WC_MCCP::get_invoice($params->invoice);

        if (!$invoice) {
            wp_send_json("Invoice not found: " . $params->invoice, 404);
            return;
        }

        $callback_secret = array_key_exists('id', $_GET) ? sanitize_text_field($_GET['id']) : '';
        $secret = get_option('woocommerce_mccp_secret');

        if (!Payment::checkInvoiceSecret($callback_secret, $secret, $invoice->order_id)) {
            wp_send_json("Secret not valid: " . $callback_secret);
            return;
        }

		$invoice_updated = Apirone::invoiceInfoPublic($invoice->invoice);

		if($invoice_updated) {
			$order = new WC_Order($invoice->order_id);
			WC_MCCP::invoice_update($order, $invoice_updated);
		}
		else {
			wp_send_json("Can't get invoice info", 400);
		}

		exit;
	}

	/**
	 * Invoce status check
	 * @return echo status value 
	 */
	function mccp_check_handler () {
        $id = array_key_exists('id', $_GET) ? sanitize_text_field($_GET['id']) : false;
		if($id) {
			$invoice = WC_MCCP::get_invoice($id);
			echo Payment::invoiceStatus($invoice);
		}
		exit;
	}

	/**
	 * Invoice page handler
	 * 
	 * @param mixed $order_id 
	 * @return mixed 
	 * @throws Exception 
	 */
	function invoice_receipt($order_id) {
		if( $this->is_available() === false )
			return _e("Payment method disabled", 'mccp');

		$crypto_abbr = array_key_exists('mccp_currency', $_GET) ? sanitize_text_field($_GET['mccp_currency']) : false;

		if (!$crypto_abbr)
			return _e('Required param not exists', 'mccp');

		$_currency = $this->currencies[$crypto_abbr];

		// Process some errors
		if (empty($_currency->address))
			return WC_MCCP::receipt_error_message('not-supported', $crypto_abbr);

		if (!$_currency->valid)
			return WC_MCCP::receipt_error_message('not-valid', $crypto_abbr);

		if (!$_currency->enabled)
			return WC_MCCP::receipt_error_message('not-enabled', $crypto_abbr);

		$order = new WC_Order($order_id);

		$woo_currency = get_woocommerce_currency();
		$crypto_total = $this->get_crypto_total($order->get_total(), $woo_currency, $crypto_abbr);

		if ($crypto_total == 0)
			return _e(sprintf('Can\'t convert your paynemt to %s', $crypto_abbr), 'mccp');
		if (Apirone::cur2min($crypto_total, $_currency->{'units-factor'}) < $_currency->{'dust-rate'})
			return _e('Your payment less than minimal payment value');
		
		$invoices_list=  $this->get_order_invoices($order_id);
		$order_invoice = is_array($invoices_list) ? $invoices_list[0] : false;
		
		// Process failed order_pay
		if ( $this->is_repayment() && ! isset($_GET['invoice'])) {
			if ($order_invoice && $order_invoice->status == 'expired' && $order->get_status() === 'failed') {
				$created = $this->invoice_create($order, $crypto_abbr, $crypto_total);
				// Save invoice 
				if ($created) {
					$this->invoice_update($order, $created);
					// Make redirect to payment page
					$args = array(
						'order-pay' => $order->id,
						'key' => $order->order_key,
						'mccp_currency' => $crypto_abbr,
						'invoice' => $created->invoice,
					);

					$url = add_query_arg($args, get_permalink(wc_get_page_id('pay')));
					wp_redirect($url); // Redirect to payment page
					exit();
				}
			}
		}

		if ($order_invoice) {
            if (Payment::invoiceStatus($order_invoice) != 0) {
                $invoice_data = Apirone::invoiceInfoPublic($order_invoice->invoice);
                if ($invoice_data) {
                    $invoiceUpdated = $this->invoice_update($order, $invoice_data);
                    $order_invoice = ($invoiceUpdated) ? $invoiceUpdated : $order_invoice;
                }
            }
		}
		else {
			$created = $this->invoice_create($order, $crypto_abbr, $crypto_total);

			if ($created) {
				$order_invoice = $this->invoice_update($order, $created);
			}
		}
		// WC()->cart->empty_cart();
		switch ($order_invoice->status) {
			case 'paid':
			case 'overpaid':
			case 'expired':
				WC()->cart->empty_cart();
				break;
		} 

		if (!$order_invoice) {
			?>
			<h2>Oops! Something went wrong.</h2>
			<p>Please, try again or choose another payment method.</p>
			<p><a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a></p>
			<?php

			return;		
		}
		
		$currencyInfo = Apirone::getCurrency($order_invoice->details->currency);
		$this->invoice_show($order_invoice, $currencyInfo);
	}

	function invoice_create($order, $crypto_abbr, $crypto_total) {
		$_callback = site_url() . '?wc-api=mccp_callback&id=' . Payment::makeInvoiceSecret($this->mccp_secret(), $order->id);
		$_currency = $this->currencies[$crypto_abbr];

		$invoiceData = Payment::makeInvoiceData(
			$crypto_abbr,
			(int)Apirone::cur2min($crypto_total, $_currency->{'units-factor'}),
			(int) $this->get_option('timeout'),
			$_callback,
			$order->get_total(),
			$order->get_currency()
		);

		return Apirone::invoiceCreate($this->mccp_account(), $invoiceData);
	}

	function invoice_show($invoice, &$currency) {
		$check_timeout = $this->get_option('check_timeout');
        $merchant = $this->get_option('merchant') ? $this->get_option('merchant') : get_bloginfo();
		$backlink = $this->get_option('backlink') ? $this->get_option('backlink') : site_url();
		$logo = $this->get_option('apirone_logo') == 'yes' ? Apirone::getAssets('logo.svg') : false;

        $statusLink = site_url() . '?wc-api=mccp_check&id=' . $invoice->invoice;

        echo Payment::invoice($invoice, $currency, $statusLink, $check_timeout, $merchant, $backlink, $logo);

	}
}
