<?php
/**
 * Logs tab: recent sync attempts.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

$dwtm_rows = Dwtm_Logger::recent( 100 );
?>

<h3><?php esc_html_e( 'Recent sync activity', 'demfilz-sync-for-myob' ); ?></h3>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'When', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Type', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Object', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Direction', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Status', 'demfilz-sync-for-myob' ); ?></th>
			<th><?php esc_html_e( 'Message', 'demfilz-sync-for-myob' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $dwtm_rows ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No sync activity yet.', 'demfilz-sync-for-myob' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $dwtm_rows as $dwtm_r ) : ?>
				<tr>
					<td><?php echo esc_html( $dwtm_r->created_at ); ?></td>
					<td><?php echo esc_html( $dwtm_r->object_type ); ?></td>
					<td>#<?php echo esc_html( $dwtm_r->object_id ); ?></td>
					<td><?php echo esc_html( $dwtm_r->direction ); ?></td>
					<td>
						<span class="dwtm-pill <?php echo 'success' === $dwtm_r->status ? 'dwtm-ok' : ( 'error' === $dwtm_r->status ? 'dwtm-bad' : 'dwtm-warn' ); ?>">
							<?php echo esc_html( $dwtm_r->status ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $dwtm_r->message ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
