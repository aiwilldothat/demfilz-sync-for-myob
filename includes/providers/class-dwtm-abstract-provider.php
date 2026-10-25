<?php
/**
 * Shared HTTP plumbing for MYOB provider adapters.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base adapter: builds authenticated requests and parses MYOB responses.
 *
 * Concrete adapters set the resource base URL and shape the payloads, since the
 * MYOB Business and AccountRight schemas differ in places.
 */
abstract class Dwtm_Abstract_Provider implements Dwtm_Provider {

	/**
	 * Resource API base URL for the platform (no trailing slash).
	 *
	 * @var string
	 */
	protected $base = '';

	/**
	 * Selected company file id/guid.
	 *
	 * @var string
	 */
	protected $cf_id = '';

	/**
	 * Full company-file resource URI as returned by MYOB's file list
	 * (e.g. https://api.myob.com/accountright/{guid}). All resource calls hang off this,
	 * matching the proven AI Accounts integration (never base + cf_id).
	 *
	 * @var string
	 */
	protected $cf_uri = '';

	/**
	 * Constructor.
	 *
	 * @param string $cf_id  Company file id (optional for listing).
	 * @param string $cf_uri Full company-file URI (optional for listing).
	 */
	public function __construct( $cf_id = '', $cf_uri = '' ) {
		$this->cf_id  = $cf_id;
		$this->cf_uri = $cf_uri;
	}

	/**
	 * Common MYOB request headers.
	 *
	 * @param bool $with_cf Whether to attach the company-file token (omit when listing files).
	 * @return array|WP_Error
	 */
	protected function headers( $with_cf = true ) {
		$token = Dwtm_OAuth::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$headers = array(
			'Authorization'     => 'Bearer ' . $token,
			'x-myobapi-key'     => Dwtm_OAuth::client_id(),
			'x-myobapi-version' => 'v2',
			'Accept'            => 'application/json',
			'Content-Type'      => 'application/json',
		);

		// AccountRight cloud files may require company-file credentials (base64 user:pass).
		$cf_token = get_option( 'dwtm_cf_token', '' );
		if ( $with_cf && $cf_token ) {
			$headers['x-myobapi-cftoken'] = Dwtm_Crypto::decrypt( $cf_token );
		}

		return $headers;
	}

	/**
	 * Perform an authenticated GET.
	 *
	 * @param string $url        Absolute URL.
	 * @param array  $query_args Query parameters.
	 * @return array|WP_Error Decoded body.
	 */
	protected function get( $url, $query_args = array() ) {
		return $this->request( 'GET', $url, null, $query_args );
	}

	/**
	 * Perform an authenticated POST. Appends returnBody=true so MYOB returns the
	 * created resource (incl. its UID), as the proven integration relies on.
	 *
	 * @param string $url  Absolute URL.
	 * @param array  $body Payload.
	 * @return array|WP_Error
	 */
	protected function post( $url, $body ) {
		$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		$url = $url . $sep . 'returnBody=true';
		return $this->request( 'POST', $url, $body );
	}

	/**
	 * Perform an authenticated PUT.
	 *
	 * @param string $url  Absolute URL.
	 * @param array  $body Payload.
	 * @return array|WP_Error
	 */
	protected function put( $url, $body ) {
		return $this->request( 'PUT', $url, $body );
	}

	/**
	 * Core request helper.
	 *
	 * @param string     $method     HTTP method.
	 * @param string     $url        Absolute URL.
	 * @param array|null $body       Payload for write methods.
	 * @param array      $query_args Query parameters.
	 * @param bool       $with_cf    Whether to send the company-file token header.
	 * @return array|WP_Error
	 */
	protected function request( $method, $url, $body = null, $query_args = array(), $with_cf = true ) {
		$headers = $this->headers( $with_cf );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => $headers,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = '' !== $raw ? json_decode( $raw, true ) : array();

		if ( $status < 200 || $status >= 300 ) {
			$detail = '';
			if ( isset( $data['Errors'][0]['Message'] ) ) {
				$detail = $data['Errors'][0]['Message'];
			} elseif ( isset( $data['Message'] ) ) {
				$detail = $data['Message'];
			}
			/* translators: 1: HTTP status code, 2: MYOB error detail. */
			$msg = sprintf( __( 'MYOB API error (%1$d): %2$s', 'demfilz-sync-for-myob' ), $status, $detail );
			return new WP_Error(
				'dwtm_api_error',
				$msg,
				array(
					'status' => $status,
					'body'   => $raw,
				)
			);
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Resource URL within the selected company file. Built from the file's own URI
	 * (preferred) and falling back to base + cf_id only if no URI was stored.
	 *
	 * @param string $path Resource path beginning with '/' (e.g. '/Sale/Invoice/Service').
	 * @return string
	 */
	protected function cf_url( $path ) {
		$root = $this->cf_uri ? untrailingslashit( $this->cf_uri ) : untrailingslashit( $this->base ) . '/' . $this->cf_id;
		return $root . '/' . ltrim( $path, '/' );
	}

	/**
	 * Generic company-file GET (public entry point for extensions).
	 *
	 * @param string $path Resource path incl. any OData query (e.g. '/Contact/Supplier?$filter=...').
	 * @return array|WP_Error Decoded body.
	 */
	public function api_get( $path ) {
		return $this->get( $this->cf_url( $path ) );
	}

	/**
	 * Generic company-file POST (public entry point for extensions).
	 *
	 * @param string $path    Resource path (e.g. '/Purchase/Bill/Service').
	 * @param array  $payload Entity payload.
	 * @return array|WP_Error Decoded body.
	 */
	public function api_create( $path, array $payload ) {
		return $this->post( $this->cf_url( $path ), $payload );
	}
}
