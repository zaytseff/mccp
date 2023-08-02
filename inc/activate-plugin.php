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
	
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$sql = Db::createInvoicesTableQuery($wpdb->prefix, $wpdb->charset, $wpdb->collate);

	dbDelta($sql);

    if ($wpdb->last_error && class_exists('WC_Logger')) {
        $log = new WC_Logger();
        $log->error($wpdb->last_error, ['source' => 'mccp_install_error']);    
    }

	// Remove unused options from v1.0.0
	if ( get_option('mccp_db_version' ) ){
		delete_option('mccp_db_version');
	}
}
register_activation_hook( MCCP_MAIN, 'mccp_activate' );
