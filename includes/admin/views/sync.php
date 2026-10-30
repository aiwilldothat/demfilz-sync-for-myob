<?php
/**
 * Sync tab: explains manual (free) vs automatic (Pro) and exposes Pro upsell.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

$dwtm_pro = Dwtm_Pro::can();
?>

<h3><?php esc_html_e( 'How syncing works', 'demfilz-sync-for-myob' ); ?></h3>
<table class="widefat striped dwtm-feature-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Feature', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Free', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Pro', 'demfilz-sync-for-myob' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr><td><?php esc_html_e( 'Push an order to MYOB (button on the order)', 'demfilz-sync-for-myob' ); ?></td><td>✔</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Sync customer/contact on push', 'demfilz-sync-for-myob' ); ?></td><td>✔</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Pull MYOB stock for a product (button)', 'demfilz-sync-for-myob' ); ?></td><td>✔</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Automatic order sync on status change', 'demfilz-sync-for-myob' ); ?></td><td>-</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Bulk / historical order backfill', 'demfilz-sync-for-myob' ); ?></td><td><?php esc_html_e( 'Last 5', 'demfilz-sync-for-myob' ); ?></td><td><?php esc_html_e( 'Unlimited', 'demfilz-sync-for-myob' ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Scheduled inventory sync (all products)', 'demfilz-sync-for-myob' ); ?></td><td>-</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Refunds / credit notes to MYOB', 'demfilz-sync-for-myob' ); ?></td><td>-</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Auto-retry failed syncs + email alerts', 'demfilz-sync-for-myob' ); ?></td><td>-</td><td>✔</td></tr>
		<tr><td><?php esc_html_e( 'Supplier POs → MYOB supplier Bills (with Demfilz Auto Order)', 'demfilz-sync-for-myob' ); ?></td><td>-</td><td>✔</td></tr>
	</tbody>
</table>

<?php
/**
 * Pro renders its own sync controls here (auto-sync triggers, schedules, bulk).
 *
 * @param bool $dwtm_pro Whether Pro is active.
 */
do_action( 'dwtm_sync_tab_after', $dwtm_pro );
?>

<?php if ( ! $dwtm_pro ) : ?>
	<div class="dwtm-upsell">
		<h3><?php esc_html_e( 'Upgrade to Pro - A$19.95/month or A$199/year', 'demfilz-sync-for-myob' ); ?></h3>
		<p><?php esc_html_e( 'Pro adds one-click managed connect, automatic order sync, scheduled inventory, bulk backfill, refunds and auto-retry. Cancel anytime.', 'demfilz-sync-for-myob' ); ?></p>
		<p><strong><?php esc_html_e( 'How to upgrade (Pro is a separate add-on):', 'demfilz-sync-for-myob' ); ?></strong></p>
		<ol class="dwtm-upsell-steps">
			<li><?php esc_html_e( 'Pick a plan below (monthly or yearly) and pay through Stripe.', 'demfilz-sync-for-myob' ); ?></li>
			<li><?php esc_html_e( 'Install the "Demfilz Sync for MYOB - Pro" add-on plugin you receive, alongside this free plugin.', 'demfilz-sync-for-myob' ); ?></li>
			<li><?php esc_html_e( 'Return to this Sync tab and paste your licence key to activate Pro.', 'demfilz-sync-for-myob' ); ?></li>
		</ol>
		<?php
		$dwtm_sub_monthly = add_query_arg(
			array(
				'product' => 'demfilz-woo-to-myob',
				'site'    => home_url(),
			),
			'https://api.demfilz.com/auto-order/subscribe'
		);
		$dwtm_sub_yearly = add_query_arg(
			array(
				'product' => 'demfilz-woo-to-myob-yearly',
				'site'    => home_url(),
			),
			'https://api.demfilz.com/auto-order/subscribe'
		);
		?>
		<a class="button button-primary" href="<?php echo esc_url( $dwtm_sub_monthly ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Pro - A$19.95/month', 'demfilz-sync-for-myob' ); ?></a>
		<a class="button" href="<?php echo esc_url( $dwtm_sub_yearly ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Pro yearly - A$199/year', 'demfilz-sync-for-myob' ); ?></a>
		<a href="https://demfilz.com/demfilz-woo-to-myob" target="_blank" rel="noopener" class="dwtm-upsell-more"><?php esc_html_e( 'Learn more', 'demfilz-sync-for-myob' ); ?></a>
	</div>
<?php endif; ?>
