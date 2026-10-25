<?php
/**
 * Uninstall cleanup. Removes options and the log table.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$dwtm_options = array(
	'dwtm_settings',
	'dwtm_tokens',
	'dwtm_app_creds',
	'dwtm_company_file',
	'dwtm_cf_token',
	'dwtm_db_version',
);
foreach ( $dwtm_options as $dwtm_opt ) {
	delete_option( $dwtm_opt );
}

// Drop the log table.
$dwtm_table = $wpdb->prefix . 'dwtm_log';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $dwtm_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

wp_clear_scheduled_hook( 'dwtm_token_refresh' );
