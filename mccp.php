<?php
/*
Plugin Name: Multi Crypto Currency Payment
Plugin URI: https://github.com/zaytseff/mccp-woo
Description: Multi currency crypto payments for Woocommerce. Uses Apirone Processing Provider
Version: 1.0.0
Author: Alex Zaytseff
Author URI: https://github.com/zaytseff
*/

if (!defined('ABSPATH'))
  exit; // Exit if accessed directly

if (!function_exists('pa')) {
  function pa($mixed, $msg = false) {
    echo '<pre>';
    if ($msg)
      echo $msg;
    if (is_bool($mixed))
      echo ($mixed) ? 'true' : 'false';
    else 
      print_r($mixed);
    echo '</pre>';
  }
}

require_once 'inc/config.php';
require_once 'inc/class-trasaction.php';

global $wpdb, $mccp_db_version;

$mccp_db_version = '1.0.0';

$sale_table = $wpdb->prefix . 'woocommerce_mccp_sale';
$transactions_table = $wpdb->prefix . 'woocommerce_mccp_transactions';
$secret_table = $wpdb->prefix . 'woocommerce_mccp_secret'; 
$charset_collate = $wpdb->get_charset_collate();


function mccp_assets_admin()
{
  wp_enqueue_style( 'mccp_style', plugin_dir_url(__FILE__) . 'assets/mccp-admin.css' );
}
add_action('admin_enqueue_scripts', 'mccp_assets_admin');

function mccp_assets() {
  wp_enqueue_style( 'mccp_style', plugin_dir_url(__FILE__) . 'assets/mccp.css' );
  wp_enqueue_script('mccp_script', plugin_dir_url(__FILE__) . 'assets/mccp.js', array( 'jquery'));
}
add_action('wp_enqueue_scripts', 'mccp_assets');

/**
 * Activate plugin
 * 
 * @return void
 */
function mccp_activate() {
  global $wpdb, $mccp_db_version, $sale_table, $transactions_table, $secret_table;

  $charset_collate = $wpdb->get_charset_collate();
  
  $sql = "CREATE TABLE `$sale_table` (
    `id` mediumint NOT NULL AUTO_INCREMENT,
    `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `input_address` text NOT NULL,
    `currency` varchar(10) NOT NULL DEFAULT 'btc',
    `order_id` int NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
  ) $charset_collate;
  ";

  $sql .= "CREATE TABLE `$transactions_table` (
    `id` mediumint NOT NULL AUTO_INCREMENT,
    `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `value` bigint NOT NULL DEFAULT '0',
    `confirmations` int NOT NULL DEFAULT '0',
    `transaction_hash` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
    `input_transaction_hash` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
    `order_id` int NOT NULL DEFAULT '0',
    `value_forwarded` bigint NOT NULL DEFAULT '0',
    `destination_address` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
    `status` int NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
  ) $charset_collate;
  ";

  $sql .= "CREATE TABLE `$secret_table` (
    `id` mediumint NOT NULL AUTO_INCREMENT,
    `mdkey` text NOT NULL,
    PRIMARY KEY (`id`)
  ) $charset_collate;
  ";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);

  // Add SALT into secret table
  $wpdb->query("INSERT IGNORE INTO $secret_table (`id`, `mdkey`) VALUES (1, MD5(NOW()))");

  add_option('mccp_db_version', $mccp_db_version);
}
register_activation_hook(__FILE__, 'mccp_activate');


// Add MCCP payment gateway to WooCommerce
add_action ('plugin_loaded', 'mccp_payment', 0);

/**
 * Wrapper for MCCP payment gateway class
 * 
 * @return void 
 */
function mccp_payment() {
  if (!class_exists('WC_Payment_Gateway'))
    return;
  if (class_exists('WC_MCCP'))
    return;

  require_once 'inc/class-mccp.php';

  /**
   * Handle payment provider callback
   * @return never 
   */
  function mccp_callback_handler () {

    $transaction = new Transactions();

    $transaction->init_incoming();

    if (!$transaction->order)
      die ('*ok*');

    echo $transaction->process();

    exit;
  }

  function mccp_check_handler () {
    $key = array_key_exists('key', $_GET) ? sanitize_text_field($_GET['key']) : '';
    $order_id = array_key_exists('order_id', $_GET) ? intval($_GET['order_id']) : '';
    $order = wc_get_order($order_id);
    http_response_code(200);

    if ($order) {
      if ($order->get_order_key() == $key) {
        $status['status'] = Transactions::get_order_payment_status($order_id)->status;
      }
      else
        $status['message'] = 'Key error';
    }
    else {
      $status['message'] = 'Order error';
    }

    echo json_encode($status);
    exit;
  }
  
  /**
   * Add MCCP gateway to WooCommerce
   */
  function add_mccp_gateway($methods) {
      $methods[] = 'WC_MCCP';
      return $methods;
  }
  add_filter('woocommerce_payment_gateways', 'add_mccp_gateway');
}
