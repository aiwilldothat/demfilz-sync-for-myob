<?php
/**
 * Inventory read from MYOB. Free tier: manual per-product pull. Pro: scheduled/all.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pull MYOB stock-on-hand into WooCommerce.
 */
class Dwtm_Inventory_Sync {

	/**
	 * Pull stock for one product by its SKU and update Woo stock.
	 *
	 * @param int $product_id Product id.
	 * @return float|WP_Error New stock level on success.
	 */
	public function pull_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'dwtm_no_product', __( 'Product not found.', 'demfilz-sync-for-myob' ) );
		}

		$sku = $product->get_sku();
		if ( ! $sku ) {
			return new WP_Error( 'dwtm_no_sku', __( 'Product has no SKU to match against MYOB.', 'demfilz-sync-for-myob' ) );
		}

		$provider = Dwtm_Provider_Factory::make();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$qty = $provider->get_stock_by_sku( $sku );
		if ( is_wp_error( $qty ) ) {
			Dwtm_Logger::add(
				array(
					'object_type' => 'product',
					'object_id'   => $product_id,
					'direction'   => 'pull',
					'status'      => 'error',
					'message'     => $qty->get_error_message(),
				)
			);
			return $qty;
		}

		$product->set_manage_stock( true );
		$product->set_stock_quantity( $qty );
		$product->save();

		Dwtm_Logger::add(
			array(
				'object_type' => 'product',
				'object_id'   => $product_id,
				'direction'   => 'pull',
				'status'      => 'success',
				/* translators: %s: stock quantity. */
				'message'     => sprintf( __( 'Stock pulled from MYOB: %s', 'demfilz-sync-for-myob' ), $qty ),
			)
		);

		return $qty;
	}
}
