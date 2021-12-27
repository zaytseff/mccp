<?php

class Transactions {
  public $order = false;
  public $currency = false;
  // Transaction data
  public $status = 0;
  public $value = 0;
  public $input_address = '';
  public $order_id = 0;
  public $secret = '';
  public $confirmations = 0;
  public $key = '';
  public $input_transaction_hash = '';
  public $transaction_hash = '';
  public $destination_address = '';
  public $value_forwarded = 0;

  // Check data
  public $key_valid = false;
  public $secret_valid = false;
  public $sale_exist = false;
  public $transaction_exist = false;
  
  // Process order paid;
  public $paid_patrial = false;
  public $paid_complete = false;


  function __construct () {
  }

  static public function get_order_transactions ($order_id) {
    global $wpdb, $transactions_table;
    $transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $transactions_table WHERE order_id = %d", $order_id));

    return $transactions;
  }

  /**
   * 
   * @param mixed $order_id 
   * @param string $crypto 
   * @return stdClass 
   */
  static public function get_order_payment_status ($order_id, $crypto='btc') {
    $order = wc_get_order($order_id);
    if (!$order) 
      return false;

    $currency = Transactions::get_wallet_currency($crypto);
    $dust_rate = WC_MCCP::get_dust_rate($currency);

    $status = new stdClass;
    $status->crypto_total = WC_MCCP::convert_to_crypto($order->get_total(), get_woocommerce_currency(), $crypto);
    $status->crypto_arrived = WC_MCCP::convert_to_decimal(0);
    $status->crypto_remains = $status->crypto_total;

    $status->network_arrived = WC_MCCP::convert_to_decimal(0);
    $status->network_remains = $status->crypto_total;

    $status->transactions = array();
    $status->network_transactions = array();
    $status->status = 0;

    $transactions = Transactions::get_order_transactions($order_id);
    $count_confirmations = get_option('woocommerce_mccp_settings')['count_confirmations'];

    foreach ($transactions as &$item) {
      $item->value = WC_MCCP::convert_to_decimal($item->value * $currency->{'units-factor'});
      $status->status += $item->status;
      if ($item->confirmations >= $count_confirmations) {
        $status->crypto_arrived += $item->value;
        $status->crypto_remains -= $item->value;
        $status->transactions[] = $item;
      }
      else {
        $status->network_transactions[] = $item;
      }
      $status->network_arrived += $item->value;
      $status->network_remains -= $item->value;
    }

    $status->paid = $status->crypto_remains <= $dust_rate ? true : false;

    $status->crypto_total = WC_MCCP::convert_to_decimal($status->crypto_total);
    $status->crypto_arrived = WC_MCCP::convert_to_decimal($status->crypto_arrived);
    $status->crypto_remains = WC_MCCP::convert_to_decimal($status->crypto_remains);
    $status->network_arrived = WC_MCCP::convert_to_decimal($status->network_arrived);
    $status->network_remains = WC_MCCP::convert_to_decimal($status->network_remains);

    return $status;
  }

  /**
   * 
   * @param string $crypto 
   * @return mixed 
   */
  static public function get_wallet_currency($crypto = 'btc') {
    $wallets = get_option('woocommerce_mccp_wallets')->currencies;
    foreach($wallets as $item) {
      if ($item->abbr == $crypto)
        $currency = $item;
    }

    return $currency;
  }

  public function init_incoming() {
    foreach($_GET as $key => $val) {
      if (property_exists($this, $key)) {
        $this->set($key, sanitize_text_field($val));
      }
    }
    try {
      $this->order = new WC_Order($this->order_id);
    }
    catch (Exception $e) {}
  }

  public function process() {
    if ($this->get_status() < 63 )
      return $this->status;

    $this->set_sale_exist(); // 1087 - 2047
    $this->set_key_valid(); // 3135 - 4095
    $this->set_secret_valid(); // 7231 - 8191 // Can add TR
    $this->set_transaction_exist(); // 15423 - 16383 // Can update existed TR
      
    // Is transaction valid
    if ($this->get_status() < 7231)
      return $this->status;

    $this->save_transaction();

    $payment_status = Transactions::get_order_payment_status($this->order_id, $this->currency);

    $this->paid_patrial = true;
    $this->paid_complete = $payment_status->paid;

    $this->update_status();

    if ($this->status >= 31807 && $this->status < 65087 && !$payment_status->paid) {
      $this->order->update_status('wc-pending', __('Partially paid', 'mccp'));
    }
    if ($payment_statys->paid) {
      WC()->cart->empty_cart();
    }

    // Close order
    if ($this->status >= 65087 && $payment_status->paid) {
      $this->order->update_status('wc-processing', __('Payment complete', 'mccp'));
    }

    if ($this->status < 65525) {
      return $this->status;
    }

    return '*ok*';
  }

  public function save_transaction() {
    global $wpdb, $transactions_table;

    $transaction = array (
      'time' => current_time('mysql'),
      'value' => $this->value,
      'confirmations' => $this->confirmations,
      'input_transaction_hash' => $this->input_transaction_hash,
      'transaction_hash' => $this->transaction_hash,
      'order_id' => $this->order_id,
      'value_forwarded' => $this->value_forwarded,
      'destination_address' => $this->destination_address,
      'status' => $this->status,
    );

    if ($this->status >= 7231 && $this->status < 15423 ) {
      // Create new
      $wpdb->insert($transactions_table, $transaction);
    }
    else {
      // Update exist by input_transaction_hash
      $where = ['input_transaction_hash' => $this->input_transaction_hash];
      $wpdb->update($transactions_table, esc_sql($transaction), esc_sql($where));
    }
  }

  public function set_sale_exist() {
    global $wpdb, $sale_table;
    if (empty($this->input_address))
      return false;
    $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $sale_table WHERE input_address = %s", $this->input_address));
    $this->sale_exist = empty($result) ? false : true;

    if($this->sale_exist) {
      $this->currency = $result[0]->currency;
    }

    return $this->sale_exist;
  }

  public function set_transaction_exist () {
    
    global $wpdb, $transactions_table;
    if (empty($this->input_transaction_hash))
      return false;

    $result = $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM $transactions_table WHERE input_transaction_hash = %s", $this->input_transaction_hash)
    );
    $this->transaction_exist = empty($result) ? false : true;

    return $this->transaction_exist;
  }

  public function set_key_valid () {
    if ($this->key == $this->order->order_key) {
      $this->key_valid = true;
    }

    return $this->key_valid;
  }

  public function set_secret_valid() {
    if (WC_MCCP::get_order_secret($this->order_id) == $this->secret) {
      $this->secret_valid = true;
    }
    
    return $this->secret_valid;
  }

  public function status_dec() {
    return bindec($this->status);
  } 

  /**
   * 
   * @return int|float 
   */
  public function get_status() {
    // DO NOT CHANGE ITEMS ORDER
    $props = array(
      'order_id',
      'key',
      'secret',
      'value',
      'input_address',
      'input_transaction_hash',
      'transaction_hash',
      'destination_address',
      'value_forwarded',
      'confirmations',

      'sale_exist',
      'key_valid',
      'secret_valid',
      'transaction_exist',

      'paid_patrial',
      'paid_complete',
    );
    $status = '';
    foreach ($props as $prop) {
      $status = (boolval($this->$prop) ? '1' : 0) . $status;
    }
    $this->status = bindec($status);
    return $this->status;
  }

  public function update_status() {
    global $wpdb, $transactions_table;

    $this->get_status();
    $status = esc_sql(['status' => $this->status]);
    $where = esc_sql(['input_transaction_hash' => $this->input_transaction_hash]);

    $wpdb->update($transactions_table, $status, $where);
  }

  public function set($property, $value) {
    switch ($property) {
      // Integer values
      case 'value':
      case 'value_forwarded':
      case 'order_id':
      case 'confirmations':
        $this->$property = $value ? intval($value) : 0;
        break;
      // String values
      case 'input_address':
      case 'destination_address':
      case 'input_transaction_hash':
      case 'transaction_hash':
      case 'secret':
      case 'key':
        $this->$property = sanitize_text_field($value);
        break;
    }
  }
}