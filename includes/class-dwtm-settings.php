<?php
/**
 * Typed accessors for plugin settings stored in the dwtm_settings option.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around get_option/update_option for settings.
 */
class Dwtm_Settings {

	const OPTION = 'dwtm_settings';

	/**
	 * Get a single setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value to return when the key is not set.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Get all settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( Dwtm_Installer::default_settings(), $saved );
	}

	/**
	 * Persist an array of settings (validated by caller).
	 *
	 * @param array $values Sanitised values to merge & save.
	 * @return void
	 */
	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all );
	}
}
