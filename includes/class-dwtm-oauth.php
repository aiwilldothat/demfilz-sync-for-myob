<?php
/**
 * MYOB OAuth 2.0 connection handling: authorize, token exchange, refresh, storage.
 *
 * MYOB Business and AccountRight share the same OAuth endpoints on secure.myob.com;
 * only the resource API base URL differs (handled by the provider adapters).
 *
 * Docs: https://apisupport.myob.com (Developer portal -> Authentication).
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the OAuth dance and the encrypted token store.
 */
class Dwtm_OAuth {

	// Endpoints confirmed against the live AI Accounts AccountRight integration.
	const AUTHORIZE_URL = 'https://secure.myob.com/oauth2/account/authorize';
	const TOKEN_URL     = 'https://secure.myob.com/oauth2/v1/authorize';

	/**
	 * Granular OAuth scopes (space separated).
	 *
	 * MYOB deprecated the legacy "CompanyFile" scope on 12 March 2025: keys registered
	 * after that date must use these granular scopes, and the legacy scope stops working
	 * for all keys on 1 September 2026. We request only what this plugin actually calls:
	 *   sme-company-file      - list/select the company file
	 *   sme-sales             - Sale/Invoice|Order|Quote
	 *   sme-contacts-customer - Contact/Customer
	 *   sme-general-ledger    - GeneralLedger/Account + TaxCode (mapping)
	 *   sme-inventory         - Inventory/Item (stock pull)
	 *   offline_access        - issue a refresh token
	 *
	 * Filterable via {@see 'dwtm_oauth_scope'} so it can be tuned without editing code.
	 *
	 * @var string
	 */
	const SCOPE         = 'sme-company-file sme-sales sme-contacts-customer sme-general-ledger sme-inventory offline_access';
	const TOKENS_OPTION = 'dwtm_tokens';
	const CREDS_OPTION  = 'dwtm_app_creds';

	/**
	 * The scopes to request, filterable.
	 *
	 * @return string Space-separated scope list.
	 */
	public static function scope() {
		/**
		 * Filter the OAuth scopes requested from MYOB.
		 *
		 * @param string $scope Space-separated scope list.
		 */
		return (string) apply_filters( 'dwtm_oauth_scope', self::SCOPE );
	}

	/**
	 * Store the developer-app credentials (client id/secret) the user pastes in.
	 *
	 * @param string $client_id     MYOB API key (client id).
	 * @param string $client_secret MYOB API secret.
	 * @return void
	 */
	public static function save_app_credentials( $client_id, $client_secret ) {
		update_option(
			self::CREDS_OPTION,
			array(
				'client_id'     => sanitize_text_field( $client_id ),
				'client_secret' => Dwtm_Crypto::encrypt( $client_secret ),
			),
			false
		);
	}

	/**
	 * Get the configured client id.
	 *
	 * @return string
	 */
	public static function client_id() {
		$creds = get_option( self::CREDS_OPTION, array() );
		return isset( $creds['client_id'] ) ? $creds['client_id'] : '';
	}

	/**
	 * Get the decrypted client secret.
	 *
	 * @return string
	 */
	public static function client_secret() {
		$creds = get_option( self::CREDS_OPTION, array() );
		return isset( $creds['client_secret'] ) ? Dwtm_Crypto::decrypt( $creds['client_secret'] ) : '';
	}

	/**
	 * The redirect URI registered with MYOB (admin-ajax style callback).
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin.php?page=dwtm&dwtm_action=oauth_callback' );
	}

	/**
	 * Build the MYOB consent URL.
	 *
	 * @return string
	 */
	public static function authorize_url() {
		$state = wp_create_nonce( 'dwtm_oauth' );
		set_transient( 'dwtm_oauth_state', $state, 15 * MINUTE_IN_SECONDS );

		// prompt=consent is required for MYOB to return businessId/businessName on the redirect.
		$url = add_query_arg(
			array(
				'client_id'     => rawurlencode( self::client_id() ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::scope() ),
				'state'         => rawurlencode( $state ),
				'prompt'        => 'consent',
			),
			self::AUTHORIZE_URL
		);

		/**
		 * Filter the MYOB consent URL. The Pro add-on's managed connection replaces this
		 * with a Demfilz broker start URL so the merchant never needs their own MYOB app.
		 *
		 * @param string $url   Consent URL.
		 * @param string $state CSRF state value, also stored in a transient.
		 */
		return (string) apply_filters( 'dwtm_oauth_authorize_url', $url, $state );
	}

	/**
	 * Exchange an authorization code for tokens and store them.
	 *
	 * @param string $code Authorization code from MYOB callback.
	 * @return true|WP_Error
	 */
	public static function exchange_code( $code ) {
		/** This filter is documented in includes/class-dwtm-oauth.php */
		$payload = apply_filters( 'dwtm_oauth_token_payload', null, 'authorization_code', array( 'code' => $code ) );
		if ( null !== $payload ) {
			return self::store_token_payload( $payload );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => self::client_id(),
					'client_secret' => self::client_secret(),
					'scope'         => self::scope(),
					'code'          => $code,
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		return self::store_token_response( $response );
	}

	/**
	 * Persist an already-decoded token payload (access_token / refresh_token / expires_in).
	 *
	 * Used by the Pro managed connection, where the Demfilz broker performs the token
	 * exchange (it holds the client secret) and returns the payload to the store.
	 *
	 * @param array|WP_Error $payload Token payload or an error.
	 * @return true|WP_Error
	 */
	public static function store_token_payload( $payload ) {
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		if ( ! is_array( $payload ) || empty( $payload['access_token'] ) ) {
			return new WP_Error( 'dwtm_token_failed', __( 'MYOB token request failed.', 'demfilz-sync-for-myob' ) );
		}

		$existing = self::tokens();
		$refresh  = ! empty( $payload['refresh_token'] )
			? $payload['refresh_token']
			: Dwtm_Crypto::decrypt( isset( $existing['refresh_token_enc'] ) ? $existing['refresh_token_enc'] : '' );

		$expires_at = time() + (int) ( isset( $payload['expires_in'] ) ? $payload['expires_in'] : 1200 ) - 60;

		update_option(
			self::TOKENS_OPTION,
			array(
				'access_token_enc'  => Dwtm_Crypto::encrypt( $payload['access_token'] ),
				'refresh_token_enc' => Dwtm_Crypto::encrypt( $refresh ),
				'expires_at'        => $expires_at,
				'user'              => isset( $payload['user'] ) ? $payload['user'] : array(),
			),
			false
		);

		self::schedule_refresh( $expires_at );
		return true;
	}

	/**
	 * Persist the selected MYOB company file and its resource URI.
	 *
	 * @param string $id   Company file GUID (businessId).
	 * @param string $name Company file name, if supplied.
	 * @return void
	 */
	public static function store_company_file( $id, $name = '' ) {
		$base = ( 'accountright' === Dwtm_Settings::get( 'platform', 'business' ) )
			? 'https://api.myob.com/accountright'
			: 'https://api.myob.com/business';

		update_option(
			'dwtm_company_file',
			array(
				'id'   => $id,
				'name' => $name ? $name : $id,
				'uri'  => $base . '/' . $id,
			)
		);
	}

	/**
	 * Refresh the access token using the stored refresh token.
	 *
	 * @return true|WP_Error
	 */
	public static function refresh() {
		$tokens = self::tokens();
		// Tokens are stored encrypted under *_enc; decrypt before sending to MYOB.
		$refresh_token = ! empty( $tokens['refresh_token_enc'] ) ? Dwtm_Crypto::decrypt( $tokens['refresh_token_enc'] ) : '';
		if ( '' === $refresh_token ) {
			return new WP_Error( 'dwtm_no_refresh', __( 'No MYOB refresh token stored. Please reconnect.', 'demfilz-sync-for-myob' ) );
		}

		/**
		 * Allow the Pro add-on's managed connection to perform the refresh through the
		 * Demfilz broker (which holds the client secret) instead of calling MYOB directly.
		 *
		 * @param array|WP_Error|null $payload Decoded token payload, or null to use MYOB directly.
		 * @param string              $grant   Grant type.
		 * @param array               $args    Grant arguments.
		 */
		$payload = apply_filters( 'dwtm_oauth_token_payload', null, 'refresh_token', array( 'refresh_token' => $refresh_token ) );
		if ( null !== $payload ) {
			return self::store_token_payload( $payload );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => self::client_id(),
					'client_secret' => self::client_secret(),
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		return self::store_token_response( $response );
	}

	/**
	 * Parse a token endpoint response and persist tokens (encrypted).
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @return true|WP_Error
	 */
	private static function store_token_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : __( 'MYOB token request failed.', 'demfilz-sync-for-myob' );
			return new WP_Error( 'dwtm_token_failed', $msg, array( 'status' => $code ) );
		}

		$existing = self::tokens();
		$store    = array(
			'access_token'  => Dwtm_Crypto::encrypt( $body['access_token'] ),
			'refresh_token' => Dwtm_Crypto::encrypt(
				! empty( $body['refresh_token'] ) ? $body['refresh_token'] : Dwtm_Crypto::decrypt( isset( $existing['refresh_token_enc'] ) ? $existing['refresh_token_enc'] : '' )
			),
			'expires_at'    => time() + (int) ( isset( $body['expires_in'] ) ? $body['expires_in'] : 1200 ) - 60,
			'user'          => isset( $body['user'] ) ? $body['user'] : array(),
		);

		// Store encrypted; keep an *_enc mirror of refresh for the next refresh round-trip.
		update_option(
			self::TOKENS_OPTION,
			array(
				'access_token_enc'  => $store['access_token'],
				'refresh_token_enc' => $store['refresh_token'],
				'expires_at'        => $store['expires_at'],
				'user'              => $store['user'],
			),
			false
		);

		self::schedule_refresh( $store['expires_at'] );
		return true;
	}

	/**
	 * Raw stored token option (encrypted fields).
	 *
	 * @return array
	 */
	public static function tokens() {
		$t = get_option( self::TOKENS_OPTION, array() );
		return is_array( $t ) ? $t : array();
	}

	/**
	 * Whether we currently hold a (possibly expired) connection.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		$t = self::tokens();
		return ! empty( $t['refresh_token_enc'] );
	}

	/**
	 * Get a valid bearer access token, refreshing if expired.
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$t = self::tokens();
		if ( empty( $t['access_token_enc'] ) ) {
			return new WP_Error( 'dwtm_not_connected', __( 'Not connected to MYOB.', 'demfilz-sync-for-myob' ) );
		}

		if ( empty( $t['expires_at'] ) || time() >= (int) $t['expires_at'] ) {
			$refreshed = self::refresh();
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
			$t = self::tokens();
		}

		return Dwtm_Crypto::decrypt( $t['access_token_enc'] );
	}

	/**
	 * Schedule a background refresh just before expiry.
	 *
	 * @param int $expires_at Unix timestamp.
	 * @return void
	 */
	private static function schedule_refresh( $expires_at ) {
		wp_clear_scheduled_hook( 'dwtm_token_refresh' );
		$when = max( time() + 60, (int) $expires_at - 120 );
		wp_schedule_single_event( $when, 'dwtm_token_refresh' );
	}

	/**
	 * Disconnect: wipe tokens.
	 *
	 * @return void
	 */
	public static function disconnect() {
		delete_option( self::TOKENS_OPTION );
		delete_option( 'dwtm_company_file' );
		wp_clear_scheduled_hook( 'dwtm_token_refresh' );
	}
}
