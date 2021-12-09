<?php
  /**
   * Multi Crypto Currency PAyment Gateway class
   */
  class WC_MCCP extends WC_Payment_Gateway {

    public $enabled = false;
    public $currencies = array();
    public $order_states = array();
    public $debug = false;
    public $wallets = array();
    public $id = 'mccp';
    public $title;
    public $description;
    public $method_title;
    public $method_description;

    public function __construct() {
      $this->title         = __('Crypto currency payment', 'mccp');
      $this->description   = __('Start accepting multi cryptocurrency payments - DESC', 'mccp');
      $this->method_title  = __('Multi Crypto Currency Payments', 'mccp');
      $this->method_description = __('Start accepting multi cryptocurrency payments', 'mccp');

      $this->mccp_init_form_fields();

      $this->enabled = $this->get_option('enabled');
      $this->currencies = $this->get_option('currencies');
      $this->order_states = array(
        'new'=>'wc-on-hold',
        'partiallypaid'=>'wc-pending',
        'complete'=>'wc-processing',
      );

      $this->debug = $this->get_option('debug');
      $this->wallets = $this->get_option('wallets');

      add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ));

      //Save our GW Options into Woocommerce
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_currencies'));

      add_action('woocommerce_api_mccp_callback', 'mccp_callback_handler');
      add_action('woocommerce_api_mccp_check', 'mccp_check_handler');
    }

    /**
     * Currency list fieldtype validator
     * 
     * @param mixed $key 
     * @param mixed $value 
     * @return mixed 
     */
    public function validate_currencies_list_field($key, $value) {
      return($value);
    }

    /**
     * Write log info if parameter 'Debug Mode' switch on
     * 
     * @param mixed $message 
     * @return void 
     */
    public function debug_logger ($message) {
      if ( isset($this->debug) && $this->debug === 'yes') {
        if ( !isset($this->logger) || empty($this->logger)) {
          $this->logger = new WC_Logger();
        }

        $this->logger->add('mccp', $message);
      }
    }

    /**
     * Get currency settings from wallet by currency abbreviation
     * 
     * @param mixed $abbr 
     * @return mixed 
     */
    function get_wallet_currency ($abbr) {
      if ( property_exists($this->wallets, 'currencies')) {
        foreach($this->wallets->currencies as $currency) {
          if ($currency->abbr == $abbr) {
            if (!$currency->{'dust-rate'}) {
              $currency->{'dust-rate'} = 1000;
            }
            return $currency;
          }
        }
      }
      return false;
    }

    /**
     * Returnt transaction link to blockchair.com
     * 
     * @param mixed $currency get_transaction_link
     * @return string 
     */
    static function get_transaction_link($currency) {
      if ($currency->abbr == 'tbtc') 
        return 'https://blockchair.com/bitcoin/testnet/transaction/';
      
      return sprintf('https://blockchair.com/%s/transactions/', strtolower(str_replace([' ', '(', ')'], ['-', '/', ''],  $currency->name)));
    }
    /**
     * Returnt transaction link to blockchair.com
     * 
     * @param mixed $currency get_transaction_link
     * @return string 
     */
    static function get_address_link($currency) {
      if ($currency->abbr == 'tbtc') 
        return 'https://blockchair.com/bitcoin/testnet/address/';
      
      return sprintf('https://blockchair.com/%s/address/', strtolower(str_replace([' ', '(', ')'], ['-', '/', ''],  $currency->name)));
    }
    /**
     * Return img tag with QR-code link
     * 
     * @param mixed $currency 
     * @param mixed $input_address 
     * @param mixed $repains 
     * @return void 
     */
    static function get_qr_link($currency, $input_address, $remains) {
      $prefix = (substr_count($input_address, ':') > 0 ) ? '' : strtolower(str_replace([' ', '(', ')'], ['-', '', ''],  $currency->name)) . ':';

      return 'https://chart.googleapis.com/chart?chs=225x225&cht=qr&chl=' . urlencode($prefix . $input_address . "?amount=" . $remains);
    }

    /**
     * Generate admin page html code
     * 
     * @return void 
     */
    public function admin_options() {
      if (WC_MCCP::is_woo_currency_supported() ) :?>
      <?php mccp_activate(); ?>
        <h3><?php _e('Multi Crypto Currency Payment Gateway', 'mccp'); ?></h3>
        <div><?php _e('This plugin uses the Apirone crypto processing service.', 'mccp'); ?> <a href="https://apirone.com" target="_blank"><?php _e('Details'); ?></a></div>
        <table class="form-table mccp-settings">
          <?php $this->generate_settings_html(); ?>
        </table>
      <?php else: ?>
          <div class="inline error">
            <p><strong><?php _e('Gateway offline', 'mccp'); ?></strong>: <?php _e($this->id . ' don\'t support your shop currency', 'mccp'); ?></p>
          </div>
      <?php endif;
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
              $currencies = WC_MCCP::get_wallets()->currencies;

              foreach($currencies as $currency): ?>
                <tr valign="middle" class="single_select_page">
                  <th scope="row" class="titledesc">
                    <label for="mccp_<?php echo $currency->abbr; ?>" class="currency-label">
                    <div class="<?php echo WC_MCCP::currency_icon_wrapper($currency) ?>">
                      <img src="<?php echo WC_MCCP::currency_icon($currency->abbr); ?>" alt="<?php echo $currency->name; ?>" class="currency-icon">
                    </div>
                    <?php echo $currency->name; ?>
                    <?php echo wc_help_tip(sprintf(__('Enter valid address to activate <b>%s</b> currency', 'mccp'), $currency->name)); ?>
                    </label>
                  </th>
                  <td class="forminp">
                    <input type="text" name="woocommerce_mccp_currencies[<?php echo $currency->abbr; ?>][address]" class="input-text regular-input" 
                      value="<?php echo $this->get_currency($currency)->address; ?>">
                    <?php if ( $this->is_currency($currency) && !empty($this->get_currency($currency)->address) ): ?>
                    <input type="checkbox" name="woocommerce_mccp_currencies[<?php echo $currency->abbr; ?>][enabled]" class="currency-enabled" 
                      <?php echo $this->get_currency($currency)->enabled ? ' checked' : ''; ?>
                      <?php echo !$this->get_currency($currency)->valid ? ' disabled' : ''; ?>
                    >
                      <?php if( $this->is_valid_currency($currency)): ?>
                      <span class="valid">
                        <?php _e('Use currency', 'mccp'); ?>
                      </span>
                      <?php else: ?>
                      <span class="not-valid">
                        <?php _e('Address is not valid', 'mccp'); ?>
                      </span>
                      <?php endif;?>
                    <?php endif;?>
                    <?php echo $this->get_testnet_info_message($currency); ?>
                    
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
     * Save currensies option
     * 
     * @return void 
     */
    public function save_currencies() {
      WC_MCCP::log('[Info] Start save_currencies()...');
      if (isset($_POST['woocommerce_mccp_currencies'])) {

        $mccp_settings = get_option('woocommerce_mccp_settings');
        $wallets = WC_MCCP::get_wallets();
        $currencies = array();

        foreach ($wallets->currencies as $item) {
          if (isset($_POST['woocommerce_mccp_currencies'][$item->abbr])) {
            $_address = trim($_POST['woocommerce_mccp_currencies'][$item->abbr]['address']);
            $_enabled = isset($_POST['woocommerce_mccp_currencies'][$item->abbr]['enabled']) ? true : false;

            $currency = array();
            $currency['address'] = $_address;
            $currency['valid'] = WC_MCCP::check_address($currency['address'], $item->abbr);
            $currency['enabled'] = ($currency['valid'] && $_enabled) ? true : false;
            $currencies[$item->abbr] = $currency;
          }
        }
        $mccp_settings['currencies'] = $currencies;
        $mccp_settings['wallets'] = $wallets;
        update_option('woocommerce_' . $this->id . '_wallets', $wallets);
        update_option($this->get_option_key(), $mccp_settings);
      }

      WC_MCCP::log('[Info] End save_currencies()...');
    }

    /**
     * Save order states option
     * 
     * @return void
     */
    public function save_order_states() {
      WC_MCCP::log('[Info] Start save_order_states()...');

      $mccp_statuses = WC_MCCP::get_order_statuses_array();

      $wc_states = wc_get_order_statuses();

      if (isset($_POST['woocommerce_mccp_order_states'])) {

        $mccp_settings = get_option('woocommerce_mccp_settings');

        $order_states = array();
        foreach ($mccp_statuses as $mccp_status => $name) {

          if (!isset($_POST['woocommerce_mccp_order_states'][ $mccp_status ])) {
            continue;
          }

          $wc_status = $_POST['woocommerce_mccp_order_states'][ $mccp_status ];

          if (true === array_key_exists($wc_status, $wc_states)) {
            WC_MCCP::log('[Info] Updating order state ' . $mccp_status . ' to ' . $wc_status);
            $order_states[$mccp_status] = $wc_status;
          }

        }
        $mccp_settings['order_states'] = $order_states;
        update_option($this->get_option_key(), $mccp_settings);
      }

      WC_MCCP::log('[Info] Leaving save_order_states()...');
    }

    /**
     * Get list of wallets
     *
     *  @return JSON|false
     */
    static public function get_wallets () {
      $response = wp_remote_request(MCCP_API . '/v2/wallets', ['method' => 'OPTIONS']);
      if (is_wp_error( $response ))
        return false;
      
      return json_decode(wp_remote_retrieve_body($response));
    }

    /**
     * Currency address checker
     * 
     * @param string $assress - Private address to transfer paid sum
     * @param string $network - Abbreviation of network such as btc, tbts, etc...
     * 
     * @return boolean
     */
    static public function check_address ($address, $network) {
      if (trim($address) == '')
        return false;
      $response = wp_remote_request(MCCP_API . '/v2/networks/' . $network . '/is_valid_address?address=' . trim($address));
      if (is_wp_error( $response ))
        return false;
      
      return json_decode(wp_remote_retrieve_body($response));
    }

    /**
     * Return masked transaction hash
     * 
     * @param mixed $hash 
     * @return string 
     */
    static function mask_transaction_hash ($hash) {
      return substr($hash, 0, 8) . '......' . substr($hash, -8);
    }

    /**
     * Get saved currency
     * 
     * @param mixed $currency 
     * @return array|false 
     */
    function get_currency($currency) {
      if (gettype($this->currencies) == 'array') {
        if ( isset($this->currencies[$currency->abbr]) )
          return (object) $this->currencies[$currency->abbr];
        else
          return self::empty_cyrrency();
      }
      return self::empty_cyrrency();
    }

    /**
     * Return empty currecy object
     * 
     * @return stdClass 
     */
    static public function empty_cyrrency() {
      $_empty = new stdClass;
      $_empty->address = false;
      $_empty->enabled = false;
      $_empty->valid = false;

      return $_empty;
    }

    /**
     * Check is currency exist in options
     *
     *  @return boolean
     */
    function is_currency ($currency) {
      return isset($this->currencies[$currency->abbr]) ? true : false;
    }

    /**
     * Check is currency has valid address
     * 
     * @param object $currency
     * @return boolean
     */
    function is_valid_currency ($currency) {
      if ($this->is_currency($currency)) {
        return $this->get_currency($currency)->valid;
      }
      return false;
    }

    /**
     * Get exchange rates list
     * 
     * @return JSON|false
     */
    static public function get_supported_currencies () {
      $response = wp_remote_request(MCCP_API . '/v2/ticker');
      if ( is_wp_error( $response ))
        return [];
      
      return array_keys(json_decode(wp_remote_retrieve_body( $response ), true));
    }

    /**
     * Is woocommerce currency supported
     * 
     * @return bool 
     */
    static public function is_woo_currency_supported () {
      $supported_currencies = WC_MCCP::get_supported_currencies();
      if(in_array(strtolower(get_option('woocommerce_currency')), $supported_currencies)) {
        return true;
      }
      return false;
    }

    /**
     * Get currency icon URL
     * 
     * @param mixed $abbr 
     * @return string 
     */
    static public function currency_icon ($abbr) {
      if ( $abbr[0] == 't') {
        $abbr = substr($abbr, 1);
      }
      return sprintf(MCCP_CURRENCY_ICON, $abbr);
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @return void
     */
    function mccp_init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __('On/off', 'mccp'),
          'type' => 'checkbox',
          'label' => __('On', 'mccp'),
          'default' => 'no',
          'description' => __('Activate on deactivate MCCP gateway', 'mccp'),
          'desc_tip' => true,
        ),
        'currencies' => array(
          'type' => 'currencies_list',
          'description' => __('List of available cryptocyrrencies processed by Apirone', 'mccp'),
          'desc_tip' => true,
        ),
        'count_confirmations' => array(
          'title' => __('Minimum confirmations count', 'mccp'),
          'type' => 'text',
          'default' => '1',
          'description' => __('Minimum confirmations count for accepting payment. Must be an integer.', 'mccp'),
          'desc_tip' => true,
        ),
        'merchant' => array(
          'title' => __('Merchant name', 'mccp'),
          'type' => 'text',
          'default' => '',
          'description' => __('Shows Merchant name in the payment order. If this field is blank then Site Title from General settings will be shown.', 'mccp'),
          'desc_tip' => true,
        ),
        'debug' => array(
          'title' => __('Debug Mode', 'mccp'),
          'type' => 'checkbox',
          'label' => __('On', 'mccp'),
          'description' => __('All callback responses, debugging messages, errors logs store in "wp-content/wc-logs" but as a best practice do not enable this unless you are having issues with the plugin.', 'mccp'),
          'default' => 'no',
          'desc_tip' => true
        ),
      );
    }

    /**
     * Return order secret by order_id
     * 
     * @param mixed $order_id 
     * @return string 
     */
    static public function get_order_secret($order_id) {
      global $wpdb, $secret_table;
      $order = new WC_Order($order_id);

      $query = "SELECT * FROM $secret_table";
      $key = $wpdb->get_results($query);

      return md5($key->mdkey . $order->order_key);
    }

    function get_order_sales($order_id, $address = NULL) {
      global $wpdb, $sale_table;

      if (is_null($address))
        $query = $wpdb->prepare("SELECT * FROM $sale_table WHERE order_id=%d", $order_id);
      else
        $query = $wpdb->prepare("SELECT * FROM $sale_table WHERE order_id=%d AND address=%s", $order_id, $address);

      return $wpdb->get_results($query);
    }

    /**
     * Add sale into plugin's sale table
     * 
     * @param mixed $order_id 
     * @param mixed $address 
     * @param mixed $currency 
     * @return int|false 
     */
    static public function add_sale($order_id, $address, $currency) {
      global $wpdb, $sale_table;

      return $wpdb->insert($sale_table, array (
        'time' => current_time('mysql'),
        'order_id' => $order_id,
        'input_address' => $address,
        'currency' => $currency,
      ));

    }

    /**
     * Return localized order statuses
     * 
     * @return array 
     */
    static public function get_order_statuses_array() {
      return array(
        'new' => __('New Order', 'mccp'),
        'partiallypaid' => __('Partially paid or waiting for confirmations', 'mccp'),
        'complete' => __('Completed', 'mccp'),
      );
    }

    /**
     * Return compilled to html test currency message
     * 
     * @param mixed $currency 
     * @return string|void 
     */
    static public function get_testnet_info_message($currency) {
      if (WC_MCCP::is_test_currency($currency)) {
        return '<div class="testnet-info"><span class="testnet-info__message">' . __('WARNING: Test currency', 'mccp') . '</span>' .
          wc_help_tip(__('Use this currency for test purposes only! This currency shown for admin only on front of woocommerce!')) . '</div>';
      }
    }

    /**
     * Check test currency
     * 
     * @param mixed $currency 
     * @return bool 
     */
    static public function is_test_currency($currency) {
      return (substr_count(strtolower($currency->name), 'testnet') > 0) ? true: false;
    }

    /**
     * Return css-class for currency icon
     * 
     * @param mixed $currency 
     * @return string 
     */
    static public function currency_icon_wrapper($currency) {
      return 'icon-wrapper' . ((substr_count(strtolower($currency->name), 'testnet') > 0) ? ' test-currency': '');
    }

    /**
     * Convert value to cripto by request to apirone api
     * 
     * @param mixed $value 
     * @param string $from 
     * @param string $to 
     * @return mixed 
     */
    static public function convert_to_crypto($value, $from='usd', $to = 'btc') {
      if ($from == 'btc') {
        return $value;
      }
      $response = wp_remote_get(MCCP_API . '/v1/to' . $to . '?currency=' . $from . '&value=' . $value);

      if ( is_wp_error($response) )
        return false;
      
      if (wp_remote_retrieve_response_code($response) == 200)
        return trim(wp_remote_retrieve_body($response));

      return 0;
    }

    /**
     * Generate MCCP payment fields
     * 
     * @return void 
     */
    function payment_fields() {
      $total = WC()->cart->total;
      $woo_currency = get_woocommerce_currency();

      $active_currencies = array();
      foreach ($this->wallets->currencies as $item) {
        if (WC_MCCP::is_test_currency($item) && !current_user_can('manage_options')) {
          continue;
        }

        if (!empty($this->currencies[$item->abbr]['address']) && $this->currencies[$item->abbr]['enabled'] && $this->currencies[$item->abbr]['valid']) {
          $currency = array();

          $crypto_total = WC_MCCP::convert_to_crypto($total, $woo_currency, $item->abbr);

          $currency['abbr'] = $item->abbr;
          $currency['name'] = $item->name;
          $currency['total'] = $crypto_total > 0 ? WC_MCCP::convert_to_decimal($crypto_total, MCCP_ZERO_TRIM) : 0;
          $currency['payable'] = $currency['total'] >= WC_MCCP::get_dust_rate($item) ? true : false;
          $currency['dust-rate'] = WC_MCCP::convert_to_decimal(WC_MCCP::get_dust_rate($item), MCCP_ZERO_TRIM);
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

        <option <?php echo !$currency['payable'] ? 'disabled' : ''; ?> value="<?php echo $currency['abbr']; ?>">
          <?php echo $currency['name']; ?>:
          <?php echo $currency['total']; ?>
        </option>

        <?php endforeach; ?>

      </select>
      <?php
    }

    /**
     * Payment process handler
     * 
     * @param int $order_id 
     * @return array 
     */
    function process_payment($order_id) {
      $order = new WC_Order($order_id);

      // Create redirect URL
      $redirect = get_permalink(wc_get_page_id('pay'));
      $redirect = add_query_arg('order', $order->id, $redirect);
      $redirect = add_query_arg('key', $order->order_key, $redirect);
      $redirect = add_query_arg('mccp_currency', $_POST['mccp_currency'], $redirect);

      return array(
        'result'    => 'success',
        'redirect'  => $redirect,
      );
    }

    /**
     * Receipt page handler
     * 
     * @param mixed $order_id 
     * @return mixed 
     * @throws Exception 
     */
    function receipt_page($order_id)
    {
      if($this->enabled === 'no')
        return _e("Payment method disabled", 'mccp');

      if ($this->is_available()) {
        $crypto = array_key_exists('mccp_currency', $_GET) ? $_GET['mccp_currency'] : false;

        if (!$crypto)
          return _e('Required param not exists', 'mccp');

        $_currency = $this->currencies[$crypto];

        // Process some errors
        if (empty($_currency['address']))
          return WC_MCCP::receipt_error_message('not-supported', $crypto);

        if (!$_currency['valid'])
          return WC_MCCP::receipt_error_message('not-valid', $crypto);

        if (!$_currency['enabled'])
          return WC_MCCP::receipt_error_message('not-enabled', $crypto);

        $currency = $this->get_wallet_currency($crypto);

        $order = new WC_Order($order_id);
        $woo_currency = get_woocommerce_currency();
        $crypto_total = WC_MCCP::convert_to_crypto($order->get_total(), $woo_currency, $crypto);

        if ($crypto_total == 0)
          return _e(sprintf('Can\'t convert your paynemt to %s', $crypto), 'mccp');
        if ($crypto_total < WC_MCCP::get_dust_rate($currency))
          return _e('Your payment less than minimal payment value');
          
        $sales = $this->get_order_sales($order_id);

        if ($sales == null) {
          $secret = WC_MCCP::get_order_secret($order_id);

          $args = array(
            'address' => $_currency['address'],
            'callback' => MCCP_SHOP_URL . '?wc-api=mccp_callback&key=' . $order->order_key . '&secret=' . $secret . '&order_id=' . $order_id
          );

          $create_url = MCCP_ADDRESS_URL . '?method=create&address=' . $args['address'] . '&callback='  . urlencode($args['callback']) . '&currency=' . $crypto;

          $result = wp_remote_get($create_url);
          $result = json_decode(wp_remote_retrieve_body($result));
          if (property_exists($result, 'message'))
            return WC_MCCP::receipt_error_message('not-valid');

          if (property_exists($result, 'input_address'))
            $input_address = $result->input_address;
            WC_MCCP::add_sale($order_id, $input_address, $crypto);
          }
          else {
            $input_address = $sales[0]->input_address;
          }
        }
        $merchant = $this->get_option('merchant', get_bloginfo('name'));
        $payment = Transactions::get_order_payment_status($order_id, $crypto);
        $link = WC_MCCP::get_transaction_link($currency);

        $remains = WC_MCCP::convert_to_decimal($payment->network_remains);
        $remains = ($remains <= $currency->{'dust-rate'} * $currency->{'units-factor'}) ? 0 : $remains;
      ?>
      <div id="mccp-payment" class="mccp-payment">
        <div class="mccp-header">
          <span class="<?php echo WC_MCCP::currency_icon_wrapper($currency); ?>">
            <img src="<?php echo WC_MCCP::currency_icon($crypto); ?>" class="currency-icon">
          </span>
          <span class="currency-name">
            <?php echo $currency->name; ?>
          </span>
        </div>
        <div class="payment-info">
          <div class="mccp-qrcode">
            <img src="<?php echo WC_MCCP::get_qr_link($currency, $input_address, $remains); ?>">
          </div>
          <div class="mccp-amount">

          <?php if($remains > 0) : ?>

            <div class="mccp-address">
              <span class="info-title"><?php _e('Transfer address', 'mccp'); ?></span>
              <strong>
                <span class="copy2clipboard"><?php echo $input_address; ?></span>
                <a class="mccp-address-link" href="<?php echo WC_MCCP::get_address_link($currency) . $input_address; ?>" target="_blank"></a>
              </strong>
            </div>
            <div class="mccp-remains">
              <span class="info-title"><?php _e('Payment amount', 'mccp'); ?> (<?php echo strtoupper($crypto); ?>)</span>
              <strong>
                <span class="copy2clipboard"><?php echo $remains; ?></span>
              </strong>
            </div>

          <?php else : ?>

              <div class="mccp-success">
              <?php _e('Payment accepted', 'mccp'); ?>
              <span><?php _e('Waiting for confirmations', 'mccp'); ?></span>
            </div>

          <?php endif; ?>

          </div>
        </div>
        <div class="payment-details">
          <div class="loader-wrapper">
            <div class="mccp-loader"></div>
          </div>
          <div class="mccp-details">
            <div class="mccp-details_item mccp-details__title"><?php _e('Payment details', 'mccp'); ?></div>
            <div class="mccp-details_item mccp-details__merchant">
              <div class="mccp-label"><?php _e('Merchant:', 'mccp'); ?></div>
              <div class="mccp-value"><?php echo $merchant; ?></div>
            </div>
            <div class="mccp-details_item mccp-details__amount">
              <div class="mccp-label"><?php _e('Total amount:', 'mccp'); ?></div>
              <div class="mccp-value"><?php echo $payment->crypto_total; ?></div>
            </div>
            <div class="mccp-details_item mccp-details__date">
              <div class="mccp-label"><?php _e('Date:', 'mccp'); ?></div>
              <div class="mccp-value"><?php echo date('Y-m-d'); ?></div>
            </div>
          </div>
          <div class="mccp-transactions">
            <div class="mccp-details_item mccp-details__title"><?php _e('Transactions details', 'mccp'); ?></div>
              <ul>
              <?php if (count($payment->transactions) > 0 || count($payment->network_transactions) > 0) : ?>
                <?php if (count($payment->transactions) > 0): ?>

                <li><strong><?php _e('Confirmed transactions', 'mccp'); ?></strong></li>

                <?php foreach($payment->transactions as $_paid): ?>
                <li><a href="<?php echo $link . $_paid->input_transaction_hash; ?>" target="_blank" class="link"><?php echo WC_MCCP::mask_transaction_hash($_paid->input_transaction_hash); ?></a>
                  <strong><?php echo $_paid->value . ' ' . strtoupper($currency->abbr); ?></strong>
                </li>
                <?php endforeach; ?>

                <?php endif; ?>
                <?php if (count($payment->network_transactions) > 0): ?>
                <li><strong><?php _e('Pending transactions', 'mccp'); ?></strong></li>

                <?php foreach($payment->network_transactions as $_pending): ?>
                  <li><a href="<?php echo $link . $_pending->input_transaction_hash; ?>" target="_blank" class="link"><?php echo WC_MCCP::mask_transaction_hash($_pending->input_transaction_hash); ?></a>
                  <strtong><?php echo $_pending->value . ' ' . strtoupper($currency->abbr); ?></strtong>
                </li>
                <?php endforeach; ?>

                <?php endif; ?>
              <?php else: ?>
                <li><strong><?php _e('No transactions yet.', 'mccp'); ?></strong></li>
              <?php endif; ?>
              </ul>
          </div>
        </div>
      </div>
      <?php
    }

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

      echo '<div class="receipt_info">' . $message . '</div>';
    }

    /**
     * Get minimal payment value aka dust-rate
     * 
     * @param mixed $currency 
     * @return mixed 
     */
    static public function get_dust_rate($currency) {

      if ( property_exists($currency, 'dust-rate') ) {
        return WC_MCCP::convert_to_decimal($currency->{'dust-rate'} * $currency->{'units-factor'}, MCCP_ZERO_TRIM);
      }
      // return MCCP_DUST_RATE * $currency->{'units-factor'};
      return WC_MCCP::convert_to_decimal(MCCP_DUST_RATE * $currency->{'units-factor'}, MCCP_ZERO_TRIM);
    }

    /**
     * Convert to decimal and trim trailing zeros if $zero_trim set true
     * 
     * @param mixed $value 
     * @param bool $zero_trim (optional)
     * @return string 
     */
    static function convert_to_decimal($value, $zero_trim = MCCP_ZERO_TRIM) {
      if ($zero_trim)
        return rtrim(sprintf('%.8f', floatval($value)), 0);
      
      return sprintf('%.8f', floatval($value));
    }

    static public function log($message, $label = 'mccp') {
      $debug = get_option('woocommerce_mccp_settings')['debug'];
      if ( $debug == 'no')
        return;

      $logger = new WC_Logger();
      $logger->add($label, $message);
    }
  }
  // End WC_MCCP Class
