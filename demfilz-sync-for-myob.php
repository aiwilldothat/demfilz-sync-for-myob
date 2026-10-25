<?php
/**
 * Plugin Name:       Demfilz Sync for MYOB
 * Plugin URI:        https://demfilz.com/demfilz-woo-to-myob
 * Description:       Send WooCommerce orders, customers and stock to MYOB (Business or AccountRight) via the official MYOB API. Free manual sync; Pro adds automation.
 * Version:           1.3.4
 * Requires at least: 6.2
 * Requires PHP:      7.2
 * Requires Plugins:  woocommerce
 * Author:            Demfilz
 * Author URI:        https://demfilz.com
 * Text Domain:       demfilz-sync-for-myob
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

define( 'DWTM_VERSION', '1.3.4' );
define( 'DWTM_FILE', __FILE__ );
define( 'DWTM_DIR', plugin_dir_path( __FILE__ ) );
define( 'DWTM_URL', plugin_dir_url( __FILE__ ) );
define( 'DWTM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimal PSR-ish autoloader for the plugin's class files.
 *
 * Classes are named Dwtm_Foo_Bar and live in includes/ as class-dwtm-foo-bar.php,
 * with sub-namespacing by directory handled in the map below.
 *
 * @param string $class_name Fully-qualified class name being requested.
 * @return void
 */
function dwtm_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'Dwtm_' ) ) {
		return;
	}

	$file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	$dirs = array(
		DWTM_DIR . 'includes/',
		DWTM_DIR . 'includes/providers/',
		DWTM_DIR . 'includes/sync/',
		DWTM_DIR . 'includes/admin/',
	);

	foreach ( $dirs as $dir ) {
		$path = $dir . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
		// Interfaces use an interface- prefix.
		$ipath = $dir . str_replace( 'class-', 'interface-', $file );
		if ( file_exists( $ipath ) ) {
			require_once $ipath;
			return;
		}
		// Abstract classes use an abstract-class- prefix.
		$apath = $dir . str_replace( 'class-', 'abstract-class-', $file );
		if ( file_exists( $apath ) ) {
			require_once $apath;
			return;
		}
	}
}
spl_autoload_register( 'dwtm_autoload' );

register_activation_hook( __FILE__, array( 'Dwtm_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Dwtm_Installer', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded (so WooCommerce is available).
 *
 * @return void
 */
function dwtm_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Demfilz Sync for WooCommerce to MYOB requires WooCommerce to be installed and active.', 'demfilz-sync-for-myob' );
				echo '</p></div>';
			}
		);
		return;
	}
	Dwtm_Plugin::instance();
}
add_action( 'plugins_loaded', 'dwtm_bootstrap' );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 *
 * @return void
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DWTM_FILE, true );
		}
	}
);
