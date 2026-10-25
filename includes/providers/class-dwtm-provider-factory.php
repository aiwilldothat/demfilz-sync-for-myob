<?php
/**
 * Builds the configured MYOB provider adapter.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Factory + accessor for the active provider.
 */
class Dwtm_Provider_Factory {

	/**
	 * Instantiate the adapter for the configured platform and company file.
	 *
	 * @return Dwtm_Provider|WP_Error
	 */
	public static function make() {
		$platform = Dwtm_Settings::get( 'platform', 'business' );
		$cf       = get_option( 'dwtm_company_file', array() );
		$cf_id    = isset( $cf['id'] ) ? $cf['id'] : '';
		$cf_uri   = isset( $cf['uri'] ) ? $cf['uri'] : '';

		if ( ! Dwtm_OAuth::is_connected() ) {
			return new WP_Error( 'dwtm_not_connected', __( 'Connect to MYOB first.', 'demfilz-sync-for-myob' ) );
		}

		switch ( $platform ) {
			case 'accountright':
				return new Dwtm_Provider_Accountright( $cf_id, $cf_uri );
			case 'business':
			default:
				return new Dwtm_Provider_Business( $cf_id, $cf_uri );
		}
	}
}
