<?php


use ApironeApi\Apirone;
use ApironeApi\Payment;
use ApironeApi\LoggerWrapper;

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

        $debug = $this->get_option('debug') == 'yes' ? true : false;

        Apirone::setLogger(new \WC_Logger(), $debug);

        add_action('woocommerce_receipt_mccp', array( $this, 'invoice_receipt' ));
        // add_action('before_woocommerce_pay', array( $this, 'before_woocommerce_pay_mccp' ));

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

    /**
     * Handle payment provider callback
     * @return never 
     */
    public function mccp_callback_handler () {
        $data = file_get_contents('php://input');
        if($data) {
            $params = json_decode(sanitize_text_field($data));
        }

        if (!$params) {
            $msg = 'Data not received';
            LoggerWrapper::callbackError($msg);
            wp_send_json($msg, 400);
            return;
        }

        if (!property_exists($params, 'invoice') || !property_exists($params, 'status')) {
            $msg = "Wrong params received: " . json_encode($params);
            LoggerWrapper::callbackError($msg);
            wp_send_json($msg, 400);
            return;
        }


        $invoice = WC_MCCP::get_invoice($params->invoice);

        if (!$invoice) {
            $msg = "Invoice not found: " . $params->invoice;
            LoggerWrapper::callbackError($msg);
            wp_send_json($msg, 404);
            return;
        }

        $callback_secret = array_key_exists('id', $_GET) ? sanitize_text_field($_GET['id']) : '';
        $secret = get_option('woocommerce_mccp_secret');

        if (!Payment::checkInvoiceSecret($callback_secret, $secret, $invoice->order_id)) {
            $msg = "Secret not valid: " . $callback_secret;
            wp_send_json($msg, 400);
            return;
        }

        $invoice_updated = Apirone::invoiceInfoPublic($invoice->invoice);

        if($invoice_updated) {
            $order = new WC_Order($invoice->order_id);
            WC_MCCP::invoice_update($order, $invoice_updated);
        }
        else {
            $msg = "Can't get invoice info";
            wp_send_json($msg, 400);
        }

        exit;
    }

    /**
     * Invoice status check
     * @return echo status value 
     */
    public function mccp_check_handler () {
        $id = array_key_exists('id', $_GET) ? sanitize_text_field($_GET['id']) : false;
        if($id) {
            $invoice = WC_MCCP::get_invoice($id);
            echo Payment::invoiceStatus($invoice);
        }
        exit;
    }

    public function before_woocommerce_pay_mccp() {
        if (isset($_GET['key'])) {
            $order_id = get_query_var('order-pay');
            $order     = wc_get_order( $order_id );
            if (!$order || $order->get_payment_method() !== 'mccp') {
                pa(__FUNCTION__ . ' ' . __LINE__ . ' return');
                return;
            }

            $invoices_list=  $this->get_order_invoices($order_id);
            $order_invoice = is_array($invoices_list) ? $invoices_list[0] : false;

            if ($order->get_status() !== 'pending' && $order_invoice && $order_invoice->status == 'completed') {
                // Make redirect to order_received
                $args = array(
                    'key' => $order->get_order_key(),
                );

                $url = add_query_arg($args, $order->get_checkout_order_received_url());
                wp_redirect($url); // Redirect to payment page
                exit();
            }
        }
    }

    /**
     * Invoice page handler
     * 
     * @param mixed $order_id 
     * @return mixed 
     * @throws Exception 
     */
    public function invoice_receipt($order_id) {
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
            return _e(sprintf('Can\'t convert your payment to %s', $crypto_abbr), 'mccp');
        if (Apirone::cur2min($crypto_total, $_currency->{'units-factor'}) < $_currency->{'dust-rate'})
            return _e('Your payment less than minimal payment value');
        
        $invoices_list=  $this->get_order_invoices($order_id);
        $order_invoice = is_array($invoices_list) ? $invoices_list[0] : false;

        if ( isset($_GET['repayment']) && $order_invoice) {
            $created = null;

            // Create new invoice if expired;
            if ($order_invoice->status == 'expired' && $order->get_status() === 'failed') {
                $created = $this->invoice_create($order, $crypto_abbr, $crypto_total);
            }

            // Create new invoice if total or currency changed (invoice not expired)
            if (in_array($order_invoice->status, ['created', 'partpaid'])) {
                $details = $order_invoice->details;
                $amount = (int) Apirone::cur2min($crypto_total, $_currency->{'units-factor'});
                if ($details->amount !== (int) $amount || $details->currency !== $crypto_abbr) {
                    $created = $this->invoice_create($order, $crypto_abbr, $crypto_total);
                }
            }

            // Save invoice & redirect to payment page
            if ($created) {
                $this->invoice_update($order, $created);
                // Make redirect to payment page
                $args = array(
                    'key' => $order->get_order_key(),
                    'mccp_currency' => $crypto_abbr,
                );
                $url = add_query_arg($args, $order->get_checkout_payment_url(true));
                wp_redirect($url); // Redirect to payment page
                exit();
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

        if (!$order_invoice) {
            ?>
            <h2>Oops! Something went wrong.</h2>
            <p>Please, try again or choose another payment method.</p>
            <p><a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a></p>
            <?php

            return;
        }
        
        $currencyInfo = Apirone::getCurrency($order_invoice->details->currency);
        $this->invoice_show($order, $order_invoice, $currencyInfo);

        if ($order_invoice->status == 'expired' && $order->get_status() === 'failed') {
            wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
        }
    }

    public function invoice_create($order, $crypto_abbr, $crypto_total) {
        $_callback = site_url() . '?wc-api=mccp_callback&id=' . Payment::makeInvoiceSecret($this->mccp_secret(), $order->get_id());
        $_currency = $this->currencies[$crypto_abbr];

        $invoiceData = Payment::makeInvoiceData(
            $crypto_abbr,
            (int)Apirone::cur2min($crypto_total, $_currency->{'units-factor'}),
            (int) $this->get_option('timeout'),
            $_callback,
            $order->get_total(),
            $order->get_currency()
        );
        $invoiceData->linkback = $order->get_checkout_order_received_url();
        return Apirone::invoiceCreate($this->mccp_account(), $invoiceData);
    }

    public function invoice_show(&$order, &$invoice, &$currency) {
        $check_timeout = $this->get_option('check_timeout');
        $merchant = $this->get_option('merchant') ? $this->get_option('merchant') : get_bloginfo();
        $backlink = $order->needs_payment() ? $order->get_checkout_payment_url() : $order->get_checkout_order_received_url();
        $logo = $this->get_option('apirone_logo') == 'yes' ? Apirone::getAssets('logo.svg') : false;

        $statusLink = site_url() . '?wc-api=mccp_check&id=' . $invoice->invoice;

        echo Payment::invoice($invoice, $currency, $statusLink, $check_timeout, $merchant, $backlink, $logo);

    }
}
