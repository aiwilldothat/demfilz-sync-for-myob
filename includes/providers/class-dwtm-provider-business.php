<?php
/**
 * MYOB Business (formerly Essentials) adapter.
 *
 * Resource base: https://api.myob.com/business
 * The MYOB Business API shares most of AccountRight's resource schema (Sale/Invoice/Service,
 * Contact/Customer, GeneralLedger/TaxCode, Inventory/Item, ...), so this inherits the
 * AccountRight payload shaping and overrides only what differs.
 *
 * NOTE: the AccountRight adapter is validated against the live AI Accounts integration.
 * The Business *base URL and any schema deltas* are still to be confirmed against a
 * MYOB Business sandbox in the M1 spike - AccountRight is the safer first target.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * MYOB Business platform adapter. Reuses AccountRight payload shaping where identical.
 */
class Dwtm_Provider_Business extends Dwtm_Provider_Accountright {

	/**
	 * Constructor.
	 *
	 * @param string $cf_id  Company file id.
	 * @param string $cf_uri Full company-file URI.
	 */
	public function __construct( $cf_id = '', $cf_uri = '' ) {
		parent::__construct( $cf_id, $cf_uri );
		$this->base = 'https://api.myob.com/business';
	}

	/**
	 * Platform key.
	 *
	 * @return string
	 */
	public function key() {
		return 'business';
	}

	/**
	 * MYOB Business cloud files do not use a company-file token, so make sure none is sent.
	 *
	 * @return array|WP_Error
	 */
	public function list_company_files() {
		delete_option( 'dwtm_cf_token' );
		return parent::list_company_files();
	}
}
