<?php
/**
 * Order-screen meta box with the manual "Sync to MYOB" button, plus a product
 * data field for the manual stock pull. Both are free-tier features.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds MYOB controls to the order and product edit screens.
 */
class Dwtm_Order_Metabox {

	/**
	 * Constructor: hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'product_field' ) );
	}

	/**
	 * Register the order meta box (supports both legacy and HPOS screens).
	 *
	 * @return void
	 */
	public function register() {
		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box( 'dwtm_order', __( 'MYOB Sync', 'demfilz-sync-for-myob' ), array( $this, 'render' ), $screen, 'side', 'default' );
	}

	/**
	 * Render the order meta box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 * @return void
	 */
	public function render( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$uid    = $order->get_meta( Dwtm_Order_Sync::META_UID );
		$status = $order->get_meta( Dwtm_Order_Sync::META_STATUS );
		$time   = $order->get_meta( Dwtm_Order_Sync::META_TIME );
		?>
		<div class="dwtm-metabox" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
			<?php if ( $status ) : ?>
				<p>
					<span class="dwtm-pill <?php echo 'success' === $status ? 'dwtm-ok' : 'dwtm-bad'; ?>"><?php echo esc_html( $status ); ?></span>
					<?php
					if ( $time ) :
						?>
						<br /><small><?php echo esc_html( $time ); ?></small><?php endif; ?>
				</p>
				<?php
				if ( $uid ) :
					?>
					<p><small><?php esc_html_e( 'MYOB UID:', 'demfilz-sync-for-myob' ); ?> <?php echo esc_html( $uid ); ?></small></p><?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Not yet synced to MYOB.', 'demfilz-sync-for-myob' ); ?></p>
			<?php endif; ?>

			<?php if ( ! Dwtm_OAuth::is_connected() ) : ?>
				<p><em><?php esc_html_e( 'Connect to MYOB in WooCommerce → MYOB Sync first.', 'demfilz-sync-for-myob' ); ?></em></p>
			<?php else : ?>
				<button type="button" class="button button-primary dwtm-push-order" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<?php echo $uid ? esc_html__( 'Re-sync to MYOB', 'demfilz-sync-for-myob' ) : esc_html__( 'Sync to MYOB', 'demfilz-sync-for-myob' ); ?>
				</button>
				<span class="dwtm-msg"></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add a "Pull MYOB stock" button to the product inventory tab.
	 *
	 * @return void
	 */
	public function product_field() {
		global $post;
		if ( ! $post || ! Dwtm_OAuth::is_connected() ) {
			return;
		}
		echo '<p class="form-field dwtm-product-stock">';
		echo '<button type="button" class="button dwtm-pull-stock" data-product="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Pull stock from MYOB', 'demfilz-sync-for-myob' ) . '</button> ';
		echo '<span class="dwtm-msg"></span>';
		echo '</p>';
	}
}
