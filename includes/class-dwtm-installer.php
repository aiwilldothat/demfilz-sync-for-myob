<?php
/**
 * Activation / deactivation and database table installation.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles install-time concerns: the sync-log table and option defaults.
 */
class Dwtm_Installer {

	/**
	 * Current schema version. Bump to trigger dbDelta on upgrade.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install_tables();
		add_option( 'dwtm_db_version', self::DB_VERSION );
		if ( ! get_option( 'dwtm_settings' ) ) {
			add_option( 'dwtm_settings', self::default_settings() );
		}
	}

	/**
	 * Run on plugin deactivation. We keep data; just clear scheduled events.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'dwtm_token_refresh' );
	}

	/**
	 * Create or update the log table.
	 *
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'dwtm_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			direction VARCHAR(20) NOT NULL DEFAULT 'push',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			myob_uid VARCHAR(64) NOT NULL DEFAULT '',
			message TEXT NULL,
			context LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY object_lookup (object_type, object_id),
			KEY status_idx (status)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'dwtm_db_version', self::DB_VERSION );
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'platform'            => 'business', // MYOB platform: business or accountright.
			'sale_type'           => 'Invoice',  // Sale document type: Invoice, Order or Quote.
			'default_income_acct' => '',
			'default_tax_code'    => 'GST',
			'freight_tax_code'    => 'GST',
			'gift_card_acct'      => '',         // Liability account UID for gift cards sold and redeemed.
			'gift_card_tax_code'  => '',         // Tax code UID for gift card lines (no tax, e.g. N-T).
			'auto_sync_status'    => array( 'processing', 'completed' ), // Statuses that trigger auto sync in Pro.
			'invoice_status'      => 'Open',
		);
	}
}
