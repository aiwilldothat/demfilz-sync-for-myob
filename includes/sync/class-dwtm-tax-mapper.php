<?php
/**
 * Maps WooCommerce tax to MYOB tax-code UIDs.
 *
 * Free tier: a single default tax code + a freight tax code (from settings, which
 * store MYOB tax-code UIDs chosen in the mapping UI). Pro overrides per-class via
 * the 'dwtm_tax_code_uid_for_item' filter.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the MYOB tax-code UID for an order line.
 */
class Dwtm_Tax_Mapper {

	/**
	 * Tax-code UID for a specific order line item.
	 *
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Line item.
	 * @return string MYOB tax-code UID.
	 */
	public function code_uid_for_item( $order, $item ) {
		$default = Dwtm_Settings::get( 'default_tax_code' );

		/**
		 * Allow Pro (or site code) to choose a tax-code UID per item/class.
		 *
		 * @param string                $default Default tax-code UID.
		 * @param WC_Order_Item_Product $item    Line item.
		 * @param WC_Order              $order   Order.
		 */
		return (string) apply_filters( 'dwtm_tax_code_uid_for_item', $default, $item, $order );
	}

	/**
	 * Tax-code UID for the freight/shipping line.
	 *
	 * @return string
	 */
	public function freight_code_uid() {
		return (string) apply_filters( 'dwtm_freight_tax_code_uid', Dwtm_Settings::get( 'freight_tax_code' ) );
	}
}
