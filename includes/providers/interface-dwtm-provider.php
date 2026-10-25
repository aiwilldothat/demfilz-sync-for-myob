<?php
/**
 * Contract every MYOB platform adapter must implement.
 *
 * The rest of the plugin talks only to this interface, so adding/altering a MYOB
 * platform never touches the sync or admin code.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * MYOB provider adapter contract.
 */
interface Dwtm_Provider {

	/**
	 * Machine key for this platform ('business' | 'accountright').
	 *
	 * @return string
	 */
	public function key();

	/**
	 * List the company files available to the connected user.
	 *
	 * @return array|WP_Error Array of [ 'id' => string, 'name' => string, 'uri' => string ].
	 */
	public function list_company_files();

	/**
	 * Find or create a MYOB customer/contact for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string|WP_Error MYOB contact UID.
	 */
	public function upsert_customer( $order );

	/**
	 * Create (or update) a MYOB sale for an order.
	 *
	 * @param WC_Order $order        WooCommerce order.
	 * @param string   $customer_uid MYOB contact UID.
	 * @param string   $existing_uid Existing sale UID if re-syncing, else ''.
	 * @return string|WP_Error MYOB sale UID.
	 */
	public function upsert_sale( $order, $customer_uid, $existing_uid = '' );

	/**
	 * Read stock-on-hand for an item identified by SKU.
	 *
	 * @param string $sku Product SKU.
	 * @return float|WP_Error Quantity on hand.
	 */
	public function get_stock_by_sku( $sku );

	/**
	 * List MYOB tax codes (for mapping UI).
	 *
	 * @return array|WP_Error
	 */
	public function list_tax_codes();

	/**
	 * List MYOB income accounts (for mapping UI).
	 *
	 * @return array|WP_Error
	 */
	public function list_accounts();

	/**
	 * Generic company-file GET (public entry point for extensions).
	 *
	 * @param string $path Resource path incl. any OData query.
	 * @return array|WP_Error Decoded body.
	 */
	public function api_get( $path );

	/**
	 * Generic company-file POST (public entry point for extensions).
	 *
	 * @param string $path    Resource path.
	 * @param array  $payload Entity payload.
	 * @return array|WP_Error Decoded body.
	 */
	public function api_create( $path, array $payload );
}
