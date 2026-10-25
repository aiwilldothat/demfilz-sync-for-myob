<?php
/**
 * Main plugin controller: wires hooks for admin, order meta box, AJAX and cron.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton plugin controller.
 */
class Dwtm_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Dwtm_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get/boot the singleton.
	 *
	 * @return Dwtm_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: register hooks.
	 */
	private function __construct() {
		// Run any pending DB upgrade.
		if ( get_option( 'dwtm_db_version' ) !== Dwtm_Installer::DB_VERSION ) {
			Dwtm_Installer::install_tables();
		}

		// Translations for plugins hosted on WordPress.org are auto-loaded since WP 4.6.

		// Admin UI.
		if ( is_admin() ) {
			new Dwtm_Admin();
			new Dwtm_Order_Metabox();
		}

		// Background token refresh.
		add_action( 'dwtm_token_refresh', array( 'Dwtm_OAuth', 'refresh' ) );

		// Manual single-order push via order action (works free).
		add_action( 'wp_ajax_dwtm_push_order', array( $this, 'ajax_push_order' ) );
		add_action( 'wp_ajax_dwtm_pull_stock', array( $this, 'ajax_pull_stock' ) );

		// Settings link on the plugins screen.
		add_filter( 'plugin_action_links_' . DWTM_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add a Settings link to the plugin row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=dwtm' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'demfilz-sync-for-myob' ) . '</a>' );
		return $links;
	}

	/**
	 * AJAX: push a single order to MYOB (free, manual).
	 *
	 * @return void
	 */
	public function ajax_push_order() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'demfilz-sync-for-myob' ) ), 403 );
		}
		check_ajax_referer( 'dwtm_admin', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$result   = ( new Dwtm_Order_Sync() )->push( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Order synced to MYOB.', 'demfilz-sync-for-myob' ) ) );
	}

	/**
	 * AJAX: pull stock for a single product (free, manual).
	 *
	 * @return void
	 */
	public function ajax_pull_stock() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'demfilz-sync-for-myob' ) ), 403 );
		}
		check_ajax_referer( 'dwtm_admin', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$result     = ( new Dwtm_Inventory_Sync() )->pull_product( $product_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				/* translators: %s: stock quantity. */
				'message' => sprintf( __( 'Stock updated from MYOB: %s', 'demfilz-sync-for-myob' ), $result ),
				'stock'   => $result,
			)
		);
	}
}
