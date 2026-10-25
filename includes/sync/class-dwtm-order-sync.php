<?php
/**
 * Orchestrates pushing a WooCommerce order to MYOB (customer + sale), idempotently.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order -> MYOB sale sync.
 */
class Dwtm_Order_Sync {

	const META_UID    = '_dwtm_myob_uid';
	const META_STATUS = '_dwtm_sync_status';
	const META_TIME   = '_dwtm_synced_at';

	/**
	 * Push a single order to MYOB.
	 *
	 * @param int|WC_Order $order Order or id.
	 * @return true|WP_Error
	 */
	public function push( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'dwtm_no_order', __( 'Order not found.', 'demfilz-sync-for-myob' ) );
		}

		$provider = Dwtm_Provider_Factory::make();
		if ( is_wp_error( $provider ) ) {
			$this->record( $order, 'error', '', $provider->get_error_message() );
			return $provider;
		}

		// 1. Customer.
		$customer_uid = $provider->upsert_customer( $order );
		if ( is_wp_error( $customer_uid ) ) {
			$this->record( $order, 'error', '', $customer_uid->get_error_message() );
			return $customer_uid;
		}
		Dwtm_Logger::add(
			array(
				'object_type' => 'customer',
				'object_id'   => $order->get_id(),
				'status'      => 'success',
				'myob_uid'    => $customer_uid,
				'message'     => __( 'Customer synced to MYOB.', 'demfilz-sync-for-myob' ),
			)
		);

		// 2. Sale (idempotent: reuse stored UID to update).
		$existing = (string) $order->get_meta( self::META_UID );
		$sale_uid = $provider->upsert_sale( $order, $customer_uid, $existing );
		if ( is_wp_error( $sale_uid ) ) {
			$this->record( $order, 'error', '', $sale_uid->get_error_message() );
			return $sale_uid;
		}

		$order->update_meta_data( self::META_UID, $sale_uid );
		$order->update_meta_data( self::META_STATUS, 'success' );
		$order->update_meta_data( self::META_TIME, current_time( 'mysql' ) );
		$order->save();

		$this->record( $order, 'success', $sale_uid, __( 'Order synced to MYOB.', 'demfilz-sync-for-myob' ) );
		$order->add_order_note( __( 'Synced to MYOB (Demfilz Sync for WooCommerce to MYOB).', 'demfilz-sync-for-myob' ) );

		/**
		 * Fires after a successful order push (Pro uses this for refunds/follow-ups).
		 *
		 * @param WC_Order $order    Order.
		 * @param string   $sale_uid MYOB sale UID.
		 */
		do_action( 'dwtm_order_pushed', $order, $sale_uid );

		return true;
	}

	/**
	 * Write order meta + a log row for a failed/errored attempt.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $status  Status.
	 * @param string   $uid     MYOB UID if any.
	 * @param string   $message Message.
	 * @return void
	 */
	private function record( $order, $status, $uid, $message ) {
		$order->update_meta_data( self::META_STATUS, $status );
		if ( $uid ) {
			$order->update_meta_data( self::META_UID, $uid );
		}
		$order->save();

		Dwtm_Logger::add(
			array(
				'object_type' => 'order',
				'object_id'   => $order->get_id(),
				'status'      => $status,
				'myob_uid'    => $uid,
				'message'     => $message,
			)
		);
	}
}
