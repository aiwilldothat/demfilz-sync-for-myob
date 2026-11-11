<?php
/**
 * MYOB AccountRight adapter.
 *
 * Resource base: https://api.myob.com/accountright  (host is api.myob.com, NOT .com.au).
 * Endpoint shapes mirror the live, proven AI Accounts AccountRight integration:
 *  - resource calls hang off each company file's own Uri
 *  - Sale/Invoice/Service (free-text lines against an income account - no MYOB items needed)
 *  - tax-inclusive line Totals (IsTaxInclusive=true): the MYOB sale total always equals
 *    what the customer was charged, and MYOB derives the GST from each line's tax code.
 *    Ex-tax totals made MYOB add GST on top, overstating every sale from a store whose
 *    prices already include GST and which does not calculate tax in WooCommerce.
 *  - updates start from the stored MYOB sale (RowVersion, Terms) and are skipped when
 *    nothing changed since the last push
 *  - POST ...?returnBody=true to read back the created UID
 *  - contacts matched/created by CompanyName
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountRight platform adapter.
 */
class Dwtm_Provider_Accountright extends Dwtm_Abstract_Provider {

	/**
	 * Constructor.
	 *
	 * @param string $cf_id  Company file GUID.
	 * @param string $cf_uri Full company-file URI.
	 */
	public function __construct( $cf_id = '', $cf_uri = '' ) {
		parent::__construct( $cf_id, $cf_uri );
		$this->base = 'https://api.myob.com/accountright';
	}

	/**
	 * Platform key.
	 *
	 * @return string
	 */
	public function key() {
		return 'accountright';
	}

	/**
	 * List company files (GET the API root, without the company-file token).
	 *
	 * @return array|WP_Error
	 */
	public function list_company_files() {
		$data = $this->request( 'GET', trailingslashit( $this->base ), null, array(), false );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) $data as $cf ) {
			if ( isset( $cf['Id'] ) ) {
				$out[] = array(
					'id'   => $cf['Id'],
					'name' => isset( $cf['Name'] ) ? $cf['Name'] : $cf['Id'],
					'uri'  => isset( $cf['Uri'] ) ? $cf['Uri'] : '',
				);
			}
		}
		return $out;
	}

	/**
	 * Quote an OData string literal (escaping single quotes).
	 *
	 * @param string $s Value.
	 * @return string
	 */
	protected function odata_str( $s ) {
		return "'" . str_replace( "'", "''", (string) $s ) . "'";
	}

	/**
	 * MYOB ISO date (midnight) from a unix timestamp.
	 *
	 * @param int $ts Unix timestamp.
	 * @return string
	 */
	protected function iso_date( $ts ) {
		return gmdate( 'Y-m-d', $ts ) . 'T00:00:00';
	}

	/**
	 * Find or create a customer contact by company name. Returns the UID.
	 *
	 * @param WC_Order $order Order.
	 * @return string|WP_Error
	 */
	public function upsert_customer( $order ) {
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( $order->get_billing_company() ) {
			$name = $order->get_billing_company();
		}
		if ( '' === $name ) {
			$name = 'Web Customer';
		}

		$filter = '/Contact/Customer?$filter=' . rawurlencode( 'CompanyName eq ' . $this->odata_str( $name ) ) . '&$top=1';
		$found  = $this->get( $this->cf_url( $filter ) );
		if ( ! is_wp_error( $found ) && ! empty( $found['Items'][0]['UID'] ) ) {
			return $found['Items'][0]['UID'];
		}

		// MYOB rejects a new customer card without SellingDetails, which must carry a tax
		// code and a freight tax code ("SellingDetails is required").
		$tax_uid     = (string) Dwtm_Settings::get( 'default_tax_code' );
		$freight_uid = ( new Dwtm_Tax_Mapper() )->freight_code_uid();
		if ( '' === $freight_uid ) {
			$freight_uid = $tax_uid;
		}
		if ( '' === $tax_uid ) {
			return new WP_Error( 'dwtm_no_tax_code', __( 'MYOB needs a tax code to create a new customer. Choose a default tax code on the Mapping tab, then sync the order again.', 'demfilz-sync-for-myob' ) );
		}

		$payload = array(
			'CompanyName'    => $name,
			'IsIndividual'   => false,
			'IsActive'       => true,
			'SellingDetails' => array(
				'TaxCode'        => array( 'UID' => $tax_uid ),
				'FreightTaxCode' => array( 'UID' => $freight_uid ),
			),
			'Addresses'      => array(
				array(
					'Location' => 1,
					'Email'    => $order->get_billing_email(),
					'Phone1'   => $order->get_billing_phone(),
					'Street'   => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
					'City'     => $order->get_billing_city(),
					'State'    => $order->get_billing_state(),
					'PostCode' => $order->get_billing_postcode(),
					'Country'  => $order->get_billing_country(),
				),
			),
		);

		$obj = $this->post( $this->cf_url( '/Contact/Customer' ), $payload );
		if ( is_wp_error( $obj ) ) {
			return $obj;
		}
		if ( ! empty( $obj['UID'] ) ) {
			return $obj['UID'];
		}

		// returnBody not honoured on some files - re-query.
		$found = $this->get( $this->cf_url( $filter ) );
		if ( ! is_wp_error( $found ) && ! empty( $found['Items'][0]['UID'] ) ) {
			return $found['Items'][0]['UID'];
		}
		return new WP_Error( 'dwtm_customer_uid', __( 'Created the MYOB customer but could not resolve its UID.', 'demfilz-sync-for-myob' ) );
	}

	/**
	 * Order meta holding a hash of the sale payload last pushed to MYOB.
	 */
	const META_SALE_HASH = '_dwtm_sale_hash';

	/**
	 * Build Service invoice lines as tax-inclusive Totals (what the customer paid per
	 * line); MYOB derives the GST from each line's tax code.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected function service_lines( $order ) {
		$lines     = array();
		$tax_map   = new Dwtm_Tax_Mapper();
		$inc_acct  = Dwtm_Settings::get( 'default_income_acct' );
		$gift_acct = Dwtm_Settings::get( 'gift_card_acct' );
		$gift_tax  = Dwtm_Settings::get( 'gift_card_tax_code' );

		foreach ( $order->get_items() as $item ) {
			// A gift card being sold is money held for the customer: it posts to the gift card
			// liability account, not to income.
			$gift_sale = Dwtm_Gift_Cards::is_gift_card_sale( $item );
			$total     = round( (float) $order->get_line_total( $item, true ), 2 );
			$face      = $gift_sale ? Dwtm_Gift_Cards::face_value( $item, $total ) : $total;
			$lines[]   = array(
				'Type'        => 'Transaction',
				'Description' => $item->get_name(),
				'Total'       => $face,
				'Account'     => array( 'UID' => $gift_sale ? $gift_acct : $inc_acct ),
				'TaxCode'     => array( 'UID' => $gift_sale ? $gift_tax : $tax_map->code_uid_for_item( $order, $item ) ),
			);

			// A card sold below its value (on sale or with a coupon): the difference is a discount.
			if ( $face > $total ) {
				$lines[] = array(
					'Type'        => 'Transaction',
					/* translators: %s: product name. */
					'Description' => sprintf( __( 'Discount on %s', 'demfilz-sync-for-myob' ), $item->get_name() ),
					'Total'       => -1 * round( $face - $total, 2 ),
					'Account'     => array( 'UID' => $inc_acct ),
					'TaxCode'     => array( 'UID' => $gift_tax ),
				);
			}
		}

		$shipping = round( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), 2 );
		if ( $shipping > 0 ) {
			$lines[] = array(
				'Type'        => 'Transaction',
				'Description' => __( 'Shipping', 'demfilz-sync-for-myob' ),
				'Total'       => $shipping,
				'Account'     => array( 'UID' => $inc_acct ),
				'TaxCode'     => array( 'UID' => $tax_map->freight_code_uid() ),
			);
		}

		// Fees / surcharges (e.g. a payment surcharge added as a WooCommerce fee). A negative
		// fee (a discount-style fee) posts as a negative line.
		foreach ( $order->get_fees() as $fee ) {
			$amount = round( (float) $fee->get_total() + (float) $fee->get_total_tax(), 2 );
			if ( 0.0 === $amount ) {
				continue;
			}
			$lines[] = array(
				'Type'        => 'Transaction',
				'Description' => $fee->get_name() ? $fee->get_name() : __( 'Fee', 'demfilz-sync-for-myob' ),
				'Total'       => $amount,
				'Account'     => array( 'UID' => $inc_acct ),
				'TaxCode'     => array( 'UID' => Dwtm_Settings::get( 'default_tax_code' ) ),
			);
		}

		// Amounts paid by gift card or store credit: a negative line against the gift card
		// liability account, so the sale keeps its full revenue and GST and the sale total
		// matches the order total.
		foreach ( Dwtm_Gift_Cards::payments( $order ) as $payment ) {
			$lines[] = array(
				'Type'        => 'Transaction',
				'Description' => $payment['label'],
				'Total'       => -1 * round( (float) $payment['amount'], 2 ),
				'Account'     => array( 'UID' => $gift_acct ),
				'TaxCode'     => array( 'UID' => $gift_tax ),
			);
		}

		return $lines;
	}

	/**
	 * Create or update a Service sale for the order.
	 *
	 * @param WC_Order $order        Order.
	 * @param string   $customer_uid Customer UID.
	 * @param string   $existing_uid Existing sale UID.
	 * @return string|WP_Error
	 */
	public function upsert_sale( $order, $customer_uid, $existing_uid = '' ) {
		$type = Dwtm_Settings::get( 'sale_type', 'Invoice' ); // Invoice | Order | Quote.
		$path = '/Sale/' . $type . '/Service';
		$ts   = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time();

		$gift_unmapped = '' === (string) Dwtm_Settings::get( 'gift_card_acct' ) || '' === (string) Dwtm_Settings::get( 'gift_card_tax_code' );
		if ( $gift_unmapped && Dwtm_Gift_Cards::order_uses_gift_cards( $order ) ) {
			return new WP_Error( 'dwtm_no_gift_card_acct', __( 'This order sells a gift card or was paid partly by gift card or store credit. Choose a gift card account (a liability account) and a gift card tax code on the Mapping tab, then sync the order again.', 'demfilz-sync-for-myob' ) );
		}

		$payload = array(
			'Customer'                    => array( 'UID' => $customer_uid ),
			'Number'                      => (string) $order->get_order_number(),
			'Date'                        => $this->iso_date( $ts ),
			'CustomerPurchaseOrderNumber' => (string) $order->get_order_number(),
			'IsTaxInclusive'              => true,
			'Lines'                       => $this->service_lines( $order ),
			'Comment'                     => sprintf(
				/* translators: %s: WooCommerce order number. */
				__( 'WooCommerce order #%s', 'demfilz-sync-for-myob' ),
				$order->get_order_number()
			),
			'JournalMemo'                 => 'Woo: ' . $order->get_formatted_billing_full_name(),
		);

		$hash = md5( (string) $this->encode_body( $payload ) );

		if ( $existing_uid ) {
			// Unchanged since the last successful push (for example the same order moving
			// from processing to completed): leave the MYOB sale alone, so a sale that has
			// since been paid or edited in MYOB is not rewritten.
			if ( $hash === (string) $order->get_meta( self::META_SALE_HASH ) ) {
				return $existing_uid;
			}

			// A MYOB update replaces the whole sale and must carry its current RowVersion
			// (and Terms, "Terms is required"), so start from the stored sale and lay our
			// fields over it. Fields we do not manage (terms, addresses, salesperson) survive.
			$current = $this->get( $this->cf_url( $path . '/' . $existing_uid ) );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
			$update        = array_merge( $current, $payload );
			$update['UID'] = $existing_uid;

			$result = $this->put( $this->cf_url( $path . '/' . $existing_uid ), $update );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$order->update_meta_data( self::META_SALE_HASH, $hash );
			return $existing_uid;
		}

		$obj = $this->post( $this->cf_url( $path ), $payload );
		if ( is_wp_error( $obj ) ) {
			return $obj;
		}
		$order->update_meta_data( self::META_SALE_HASH, $hash );
		if ( ! empty( $obj['UID'] ) ) {
			return $obj['UID'];
		}

		// Re-query by Number if returnBody was not honoured.
		$filter = $path . '?$filter=' . rawurlencode( 'Number eq ' . $this->odata_str( $order->get_order_number() ) ) . '&$top=1';
		$found  = $this->get( $this->cf_url( $filter ) );
		if ( ! is_wp_error( $found ) && ! empty( $found['Items'][0]['UID'] ) ) {
			return $found['Items'][0]['UID'];
		}
		return new WP_Error( 'dwtm_sale_uid', __( 'Created the MYOB sale but could not resolve its UID.', 'demfilz-sync-for-myob' ) );
	}

	/**
	 * Stock on hand by SKU (Inventory item Number == SKU).
	 *
	 * @param string $sku SKU.
	 * @return float|WP_Error
	 */
	public function get_stock_by_sku( $sku ) {
		$filter = '/Inventory/Item?$filter=' . rawurlencode( 'Number eq ' . $this->odata_str( $sku ) ) . '&$top=1';
		$data   = $this->get( $this->cf_url( $filter ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['Items'][0] ) ) {
			return new WP_Error( 'dwtm_no_item', __( 'No matching MYOB item for that SKU.', 'demfilz-sync-for-myob' ) );
		}
		return (float) ( isset( $data['Items'][0]['QuantityOnHand'] ) ? $data['Items'][0]['QuantityOnHand'] : 0 );
	}

	/**
	 * List tax codes (UID => "CODE - rate%").
	 *
	 * @return array|WP_Error
	 */
	public function list_tax_codes() {
		$data = $this->get( $this->cf_url( '/GeneralLedger/TaxCode?$top=500' ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( isset( $data['Items'] ) ? $data['Items'] : array() ) as $t ) {
			// Trim only the zeros after the decimal point, so a 10% rate stays "10" and not "1".
			$rate             = isset( $t['Rate'] ) ? rtrim( rtrim( number_format( (float) $t['Rate'], 2, '.', '' ), '0' ), '.' ) : '';
			$out[ $t['UID'] ] = $t['Code'] . ( '' !== $rate ? ' - ' . $rate . '%' : '' );
		}
		return $out;
	}

	/**
	 * List income accounts (UID => "DisplayID Name").
	 *
	 * @return array|WP_Error
	 */
	public function list_accounts() {
		return $this->list_accounts_of_class( 'Income' );
	}

	/**
	 * List liability accounts (UID => "DisplayID Name"), for the gift card account.
	 *
	 * @return array|WP_Error
	 */
	public function list_liability_accounts() {
		return $this->list_accounts_of_class( 'Liability' );
	}

	/**
	 * List the accounts of one classification (UID => "DisplayID Name").
	 *
	 * @param string $classification MYOB account classification, e.g. Income or Liability.
	 * @return array|WP_Error
	 */
	protected function list_accounts_of_class( $classification ) {
		$filter = '/GeneralLedger/Account?$filter=' . rawurlencode( 'Classification eq ' . $this->odata_str( $classification ) ) . '&$top=1000';
		$data   = $this->get( $this->cf_url( $filter ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( isset( $data['Items'] ) ? $data['Items'] : array() ) as $a ) {
			$label            = ( isset( $a['DisplayID'] ) ? $a['DisplayID'] . ' ' : '' ) . ( isset( $a['Name'] ) ? $a['Name'] : '' );
			$out[ $a['UID'] ] = trim( $label );
		}
		return $out;
	}
}
