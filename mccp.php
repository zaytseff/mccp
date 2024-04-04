<?php
/**
 * Plugin Name: Multi Crypto Currency Payment
 * Plugin URI: https://github.com/zaytseff/mccp-woo
 * Description: Multi currency crypto payments for WooCommerce. Uses Apirone Processing Provider
 * Version: 1.2.9
 * Tested up to: 6.5
 * Author: Alex Zaytseff
 * Author URI: https://github.com/zaytseff
 */

if (!defined('ABSPATH'))
	exit; // Exit if accessed directly

require_once('inc/apirone_api/Apirone.php');
require_once('inc/apirone_api/Payment.php');

define('MCCP_ROOT', __DIR__);
define('MCCP_MAIN', __FILE__);
define('MCCP_URL', plugin_dir_url(__FILE__));

require_once('inc/assets-assign.php');
require_once('inc/activate-plugin.php');

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

	require_once 'inc/invoice-class.php';

	/**
	 * Add MCCP gateway to WooCommerce
	 */
	function add_mccp_gateway($methods) {
			$methods[] = 'WC_MCCP';
			return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_mccp_gateway');
}

/**
 * Debug output
 *
 * @param mixed $mixed 
 * @param string $title 
 * @return void 
 */
function pa($mixed, $title = '') {
	echo '<pre>' . ($title ? $title . ': ' : '') . "\n";
	print_r(gettype($mixed) !== 'boolean' ? $mixed : ($mixed ? 'true' : 'false'));
	echo '</pre>';
}
