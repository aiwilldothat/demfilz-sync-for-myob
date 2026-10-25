<?php
/**
 * Mapping tab: sale type, default income account, tax codes.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

if ( ! Dwtm_OAuth::is_connected() ) {
	echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Connect to MYOB first to load accounts and tax codes.', 'demfilz-sync-for-myob' ) . '</p></div>';
	return;
}

$dwtm_provider = Dwtm_Provider_Factory::make();
$dwtm_accounts = is_wp_error( $dwtm_provider ) ? array() : $dwtm_provider->list_accounts();
$dwtm_taxes    = is_wp_error( $dwtm_provider ) ? array() : $dwtm_provider->list_tax_codes();
$dwtm_gift_acs = is_wp_error( $dwtm_provider ) ? array() : $dwtm_provider->list_liability_accounts();
$dwtm_accounts = is_wp_error( $dwtm_accounts ) ? array() : $dwtm_accounts;
$dwtm_taxes    = is_wp_error( $dwtm_taxes ) ? array() : $dwtm_taxes;
$dwtm_gift_acs = is_wp_error( $dwtm_gift_acs ) ? array() : $dwtm_gift_acs;

$dwtm_sale_type = Dwtm_Settings::get( 'sale_type', 'Invoice' );
$dwtm_inc       = Dwtm_Settings::get( 'default_income_acct' );
$dwtm_tax       = Dwtm_Settings::get( 'default_tax_code' );
$dwtm_freight   = Dwtm_Settings::get( 'freight_tax_code' );
$dwtm_gift_acct = Dwtm_Settings::get( 'gift_card_acct' );
$dwtm_gift_tax  = Dwtm_Settings::get( 'gift_card_tax_code' );

if ( ! function_exists( 'dwtm_render_select' ) ) :
	/**
	 * Render a select populated from a UID=>label map.
	 *
	 * @param string $name     Field name.
	 * @param array  $options  UID => label.
	 * @param string $selected Selected UID.
	 * @return void
	 */
	function dwtm_render_select( $name, $options, $selected ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html__( '- Select -', 'demfilz-sync-for-myob' ) . '</option>';
		foreach ( $options as $dwtm_uid => $dwtm_label ) {
			echo '<option value="' . esc_attr( $dwtm_uid ) . '" ' . selected( $selected, $dwtm_uid, false ) . '>' . esc_html( $dwtm_label ) . '</option>';
		}
		echo '</select>';
	}
endif;
?>

<form method="post">
	<?php wp_nonce_field( 'dwtm_mapping', 'dwtm_nonce' ); ?>
	<input type="hidden" name="dwtm_form" value="mapping" />
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Post orders to MYOB as', 'demfilz-sync-for-myob' ); ?></th>
			<td>
				<select name="sale_type">
					<?php foreach ( array( 'Invoice', 'Order', 'Quote' ) as $dwtm_st ) : ?>
						<option value="<?php echo esc_attr( $dwtm_st ); ?>" <?php selected( $dwtm_sale_type, $dwtm_st ); ?>><?php echo esc_html( $dwtm_st ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default income account', 'demfilz-sync-for-myob' ); ?></th>
			<td><?php dwtm_render_select( 'default_income_acct', $dwtm_accounts, $dwtm_inc ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default tax code', 'demfilz-sync-for-myob' ); ?></th>
			<td><?php dwtm_render_select( 'default_tax_code', $dwtm_taxes, $dwtm_tax ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Shipping tax code', 'demfilz-sync-for-myob' ); ?></th>
			<td><?php dwtm_render_select( 'freight_tax_code', $dwtm_taxes, $dwtm_freight ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Gift card account', 'demfilz-sync-for-myob' ); ?></th>
			<td>
				<?php dwtm_render_select( 'gift_card_acct', $dwtm_gift_acs, $dwtm_gift_acct ); ?>
				<p class="description"><?php esc_html_e( 'Only needed if you sell gift cards or accept gift cards or store credit (Demfilz Gift Cards and Store Credit). Choose a liability account, for example "Gift card liability". Gift cards sold post to it instead of income, and amounts paid by gift card or store credit come out of it as a negative line, so your revenue and GST stay complete and the sale total matches what the customer was charged.', 'demfilz-sync-for-myob' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Gift card tax code', 'demfilz-sync-for-myob' ); ?></th>
			<td>
				<?php dwtm_render_select( 'gift_card_tax_code', $dwtm_taxes, $dwtm_gift_tax ); ?>
				<p class="description"><?php esc_html_e( 'Choose a code with no tax, such as N-T (not reportable).', 'demfilz-sync-for-myob' ); ?></p>
			</td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Free tier maps all lines to one tax code. Pro adds per-product / per-tax-class rules.', 'demfilz-sync-for-myob' ); ?></p>
	<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save mapping', 'demfilz-sync-for-myob' ); ?></button></p>
</form>
