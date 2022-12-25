<?php

use ApironeApi\Db;

require_once('apirone_api/Db.php');

/**
 * Activate plugin
 * 
 * @return void
 */
function mccp_activate() {
	global $wpdb;

	$sql = Db::createInvoicesTableQuery($wpdb->prefix, $wpdb->charset, $wpdb->get_charset_collate());

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	// Remove unused options from v1.0.0
	if ( get_option('mccp_db_version' ) ){
		delete_option('mccp_db_version');
	}
}
register_activation_hook( MCCP_MAIN, 'mccp_activate' );
