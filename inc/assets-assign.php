<?php

function mccp_assets_admin()
{
	wp_enqueue_style( 'mccp_style', MCCP_URL . 'assets/mccp-admin.css' );
}
add_action('admin_enqueue_scripts', 'mccp_assets_admin');

function mccp_assets() {
	wp_enqueue_style ( 'mccp_style_invoice', MCCP_URL . 'inc/apirone_api/assets/style.min.css' );
	wp_enqueue_script('mccp_script_invoice', MCCP_URL . 'inc/apirone_api/assets/script.min.js', array( 'jquery'));
	wp_enqueue_style( 'mccp_style', MCCP_URL . 'assets/mccp.css' );
}
add_action('get_footer', 'mccp_assets');
