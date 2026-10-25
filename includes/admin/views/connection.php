<?php
/**
 * Connection tab: MYOB platform, app credentials, OAuth, company-file selection.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

$dwtm_platform  = Dwtm_Settings::get( 'platform', 'business' );
$dwtm_connected = Dwtm_OAuth::is_connected();
$dwtm_cf        = get_option( 'dwtm_company_file', array() );
$dwtm_redirect  = Dwtm_OAuth::redirect_uri();
?>

<div class="dwtm-status">
	<strong><?php esc_html_e( 'Status:', 'demfilz-sync-for-myob' ); ?></strong>
	<?php if ( $dwtm_connected ) : ?>
		<span class="dwtm-pill dwtm-ok"><?php esc_html_e( 'Connected to MYOB', 'demfilz-sync-for-myob' ); ?></span>
		<?php if ( ! empty( $dwtm_cf['name'] ) ) : ?>
			<span class="dwtm-pill"><?php echo esc_html( $dwtm_cf['name'] ); ?></span>
		<?php endif; ?>
	<?php else : ?>
		<span class="dwtm-pill dwtm-warn"><?php esc_html_e( 'Not connected', 'demfilz-sync-for-myob' ); ?></span>
	<?php endif; ?>
</div>

<?php
/**
 * Fires before the developer-app credentials block. The Pro add-on renders its
 * one-click "Connect with Demfilz" managed connection here.
 *
 * @param bool $dwtm_connected Whether MYOB is currently connected.
 */
do_action( 'dwtm_connection_before_credentials', $dwtm_connected );

/**
 * Filter whether to show the bring-your-own MYOB app fields. Pro hides them when the
 * merchant is using the managed connection.
 *
 * @param bool $show Whether to show the API key/secret fields.
 */
$dwtm_show_byo = (bool) apply_filters( 'dwtm_show_byo_credentials', true );

if ( $dwtm_show_byo ) :
	?>
<h3><?php esc_html_e( '1. MYOB platform & developer app', 'demfilz-sync-for-myob' ); ?></h3>
	<?php if ( ! $dwtm_connected ) : ?>
	<div class="notice notice-warning inline">
		<p><strong><?php esc_html_e( 'Bring your own MYOB developer key: this free version connects with an API Key and Secret from your own MYOB developer account, which MYOB charges for.', 'demfilz-sync-for-myob' ); ?></strong></p>
		<p>
			<?php esc_html_e( 'MYOB has no free API access. Registering your own developer app requires a paid MYOB developer account (currently A$110/month inc GST at their "Developer Access" level). That fee is paid to MYOB, not to us.', 'demfilz-sync-for-myob' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'To connect without a MYOB developer account of your own, the Pro add-on includes a managed one-click connection that uses our MYOB developer access.', 'demfilz-sync-for-myob' ); ?>
			<a href="https://demfilz.com/demfilz-woo-to-myob" target="_blank" rel="noopener"><?php esc_html_e( 'See Pro', 'demfilz-sync-for-myob' ); ?></a>
		</p>
	</div>
	<?php endif; ?>
<p class="description">
	<?php esc_html_e( 'Register a developer app in your MYOB account (my.myob.com.au, Developer tab) to get an API Key (client id) and Secret. Use the redirect URI below.', 'demfilz-sync-for-myob' ); ?>
</p>
<p><code><?php echo esc_html( $dwtm_redirect ); ?></code></p>

<form method="post">
	<?php wp_nonce_field( 'dwtm_credentials', 'dwtm_nonce' ); ?>
	<input type="hidden" name="dwtm_form" value="credentials" />
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'MYOB platform', 'demfilz-sync-for-myob' ); ?></th>
			<td>
				<label><input type="radio" name="platform" value="business" <?php checked( $dwtm_platform, 'business' ); ?> /> <?php esc_html_e( 'MYOB Business / Essentials', 'demfilz-sync-for-myob' ); ?></label><br />
				<label><input type="radio" name="platform" value="accountright" <?php checked( $dwtm_platform, 'accountright' ); ?> /> <?php esc_html_e( 'MYOB AccountRight', 'demfilz-sync-for-myob' ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="dwtm_cid"><?php esc_html_e( 'API Key (client id)', 'demfilz-sync-for-myob' ); ?></label></th>
			<td><input type="text" id="dwtm_cid" name="client_id" class="regular-text" value="<?php echo esc_attr( Dwtm_OAuth::client_id() ); ?>" autocomplete="off" /></td>
		</tr>
		<tr>
			<th><label for="dwtm_cs"><?php esc_html_e( 'API Secret', 'demfilz-sync-for-myob' ); ?></label></th>
			<td>
				<input type="password" id="dwtm_cs" name="client_secret" class="regular-text" value="" autocomplete="off" placeholder="<?php echo Dwtm_OAuth::client_secret() ? '••••••••' : ''; ?>" />
				<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep the existing secret.', 'demfilz-sync-for-myob' ); ?></p>
			</td>
		</tr>
	</table>
	<p>
		<button type="submit" class="button"><?php esc_html_e( 'Save', 'demfilz-sync-for-myob' ); ?></button>
		<?php if ( $dwtm_connected ) : ?>
			<button type="button" class="button button-primary dwtm-connected" disabled="disabled">
				<span class="dwtm-tick" aria-hidden="true">&#10003;</span> <?php esc_html_e( 'Connected', 'demfilz-sync-for-myob' ); ?>
			</button>
			<button type="submit" name="connect" value="1" class="button"><?php esc_html_e( 'Reconnect', 'demfilz-sync-for-myob' ); ?></button>
		<?php else : ?>
			<button type="submit" name="connect" value="1" class="button button-primary"><?php esc_html_e( 'Save &amp; Connect to MYOB', 'demfilz-sync-for-myob' ); ?></button>
		<?php endif; ?>
	</p>
</form>
<?php endif; // $dwtm_show_byo. ?>

<?php if ( $dwtm_connected ) : ?>
	<hr />
	<h3><?php esc_html_e( '2. Company file', 'demfilz-sync-for-myob' ); ?></h3>
	<?php
	$dwtm_provider = Dwtm_Provider_Factory::make();
	$dwtm_files    = is_wp_error( $dwtm_provider ) ? $dwtm_provider : $dwtm_provider->list_company_files();
	?>
	<?php if ( ! empty( $dwtm_cf['id'] ) ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'Company file in use:', 'demfilz-sync-for-myob' ); ?></strong>
				<?php echo esc_html( $dwtm_cf['name'] ); ?><br />
				<code><?php echo esc_html( $dwtm_cf['id'] ); ?></code>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( is_wp_error( $dwtm_files ) ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $dwtm_files->get_error_message() ); ?></p></div>
	<?php else : ?>
		<?php if ( empty( $dwtm_files ) ) : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'MYOB returned no company files to list.', 'demfilz-sync-for-myob' ); ?></strong></p>
				<p><?php esc_html_e( 'For API keys created after 12 March 2025, MYOB no longer lists company files over the API - the file is captured automatically when you connect instead. If the box above shows your file, you are good to go.', 'demfilz-sync-for-myob' ); ?></p>
				<p><?php esc_html_e( 'If not, click Reconnect above (approving as an Admin of the file), or paste the company file ID below. Also check you are an invited user on the file, and that you authorised with that same MYOB email.', 'demfilz-sync-for-myob' ); ?></p>
			</div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'dwtm_company_file', 'dwtm_nonce' ); ?>
			<input type="hidden" name="dwtm_form" value="company_file" />
			<table class="form-table" role="presentation">
				<?php if ( ! empty( $dwtm_files ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Select company file', 'demfilz-sync-for-myob' ); ?></th>
						<td>
							<select name="cf_id" id="dwtm_cf_select">
								<?php foreach ( $dwtm_files as $dwtm_f ) : ?>
									<option value="<?php echo esc_attr( $dwtm_f['id'] ); ?>" data-name="<?php echo esc_attr( $dwtm_f['name'] ); ?>" data-uri="<?php echo esc_attr( $dwtm_f['uri'] ); ?>" <?php selected( isset( $dwtm_cf['id'] ) ? $dwtm_cf['id'] : '', $dwtm_f['id'] ); ?>>
										<?php echo esc_html( $dwtm_f['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" name="cf_name" id="dwtm_cf_name" value="<?php echo esc_attr( isset( $dwtm_cf['name'] ) ? $dwtm_cf['name'] : '' ); ?>" />
							<input type="hidden" name="cf_uri" id="dwtm_cf_uri" value="<?php echo esc_attr( isset( $dwtm_cf['uri'] ) ? $dwtm_cf['uri'] : '' ); ?>" />
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<th><label for="dwtm_cf_manual"><?php esc_html_e( 'Company file ID', 'demfilz-sync-for-myob' ); ?></label></th>
						<td>
							<input type="text" id="dwtm_cf_manual" name="cf_id" class="regular-text" value="<?php echo esc_attr( isset( $dwtm_cf['id'] ) ? $dwtm_cf['id'] : '' ); ?>" placeholder="00000000-0000-0000-0000-000000000000" />
							<p class="description"><?php esc_html_e( 'The company file GUID (businessId). Normally filled in automatically when you connect.', 'demfilz-sync-for-myob' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( 'accountright' === $dwtm_platform ) : ?>
					<tr>
						<th><?php esc_html_e( 'Company file user (AccountRight)', 'demfilz-sync-for-myob' ); ?></th>
						<td>
							<input type="text" name="cf_user" class="regular-text" autocomplete="off" placeholder="Administrator" />
							<input type="password" name="cf_pass" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Only needed if your AccountRight company file has a user/password. Stored encrypted.', 'demfilz-sync-for-myob' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save company file', 'demfilz-sync-for-myob' ); ?></button></p>
		</form>
	<?php endif; ?>

	<hr />
	<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect from MYOB?', 'demfilz-sync-for-myob' ) ); ?>');">
		<?php wp_nonce_field( 'dwtm_disconnect', 'dwtm_nonce' ); ?>
		<input type="hidden" name="dwtm_form" value="disconnect" />
		<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Disconnect', 'demfilz-sync-for-myob' ); ?></button>
	</form>
<?php endif; ?>
