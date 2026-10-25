<?php
/**
 * Admin screen: menu, tab router, form/OAuth handling, and tab views.
 *
 * @package Demfilz_Woo_To_Myob
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and processes the WooCommerce -> MYOB settings page.
 */
class Dwtm_Admin {

	/**
	 * Constructor: hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Register the admin menu under WooCommerce.
	 *
	 * @return void
	 */
	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'MYOB Sync', 'demfilz-sync-for-myob' ),
			__( 'MYOB Sync', 'demfilz-sync-for-myob' ),
			'manage_woocommerce',
			'dwtm',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue admin CSS/JS only on our screen and order/product screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( $hook ) {
		$screens = array( 'woocommerce_page_dwtm', 'shop_order', 'woocommerce_page_wc-orders', 'product' );
		$screen  = get_current_screen();
		$is_ours = ( false !== strpos( (string) $hook, 'dwtm' ) ) || ( $screen && in_array( $screen->id, $screens, true ) );
		if ( ! $is_ours ) {
			return;
		}

		wp_enqueue_style( 'dwtm-admin', DWTM_URL . 'assets/css/admin.css', array(), DWTM_VERSION );
		wp_enqueue_script( 'dwtm-admin', DWTM_URL . 'assets/js/admin.js', array( 'jquery' ), DWTM_VERSION, true );
		wp_localize_script(
			'dwtm-admin',
			'dwtmAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dwtm_admin' ),
				'syncing' => __( 'Syncing...', 'demfilz-sync-for-myob' ),
			)
		);
	}

	/**
	 * The active tab slug.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'connection', 'mapping', 'sync', 'logs' ), true ) ? $tab : 'connection';
	}

	/**
	 * Process POST forms and the OAuth callback (admin_init).
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'dwtm' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// OAuth callback from MYOB.
		if ( isset( $_GET['dwtm_action'] ) && 'oauth_callback' === $_GET['dwtm_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->handle_oauth_callback();
			return;
		}

		if ( empty( $_POST['dwtm_nonce'] ) ) {
			return;
		}
		$action = isset( $_POST['dwtm_form'] ) ? sanitize_key( wp_unslash( $_POST['dwtm_form'] ) ) : '';
		check_admin_referer( 'dwtm_' . $action, 'dwtm_nonce' );

		switch ( $action ) {
			case 'credentials':
				$this->save_credentials();
				break;
			case 'company_file':
				$this->save_company_file();
				break;
			case 'mapping':
				$this->save_mapping();
				break;
			case 'disconnect':
				Dwtm_OAuth::disconnect();
				$this->notice( __( 'Disconnected from MYOB.', 'demfilz-sync-for-myob' ) );
				break;
		}
	}

	/**
	 * Save MYOB app credentials and (if requested) start OAuth.
	 *
	 * @return void
	 */
	private function save_credentials() {
		check_admin_referer( 'dwtm_credentials', 'dwtm_nonce' );
		$platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : 'business';
		Dwtm_Settings::update( array( 'platform' => in_array( $platform, array( 'business', 'accountright' ), true ) ? $platform : 'business' ) );

		$cid = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$cs  = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
		if ( $cid && $cs ) {
			Dwtm_OAuth::save_app_credentials( $cid, $cs );
		}

		if ( isset( $_POST['connect'] ) ) {
			// The OAuth consent page is on MYOB's domain; allow the redirect off-site.
			add_filter( 'allowed_redirect_hosts', array( $this, 'allow_myob_host' ) );
			wp_safe_redirect( Dwtm_OAuth::authorize_url() );
			exit;
		}
		$this->notice( __( 'MYOB app credentials saved.', 'demfilz-sync-for-myob' ) );
	}

	/**
	 * Whitelist MYOB's OAuth host so wp_safe_redirect() will send the user there.
	 *
	 * @param array $hosts Allowed redirect hosts.
	 * @return array
	 */
	public function allow_myob_host( $hosts ) {
		$hosts[] = 'secure.myob.com';
		return $hosts;
	}

	/**
	 * Handle the redirect back from MYOB after consent.
	 *
	 * @return void
	 */
	private function handle_oauth_callback() {
		/**
		 * Let the Pro managed connection handle the return leg (the Demfilz broker sends
		 * back a one-time handoff token rather than a MYOB authorization code).
		 *
		 * @param true|WP_Error|null $handled Null to fall through to the standard flow.
		 */
		$dwtm_managed = apply_filters( 'dwtm_oauth_managed_callback', null );
		if ( null !== $dwtm_managed ) {
			if ( is_wp_error( $dwtm_managed ) ) {
				$this->notice( $dwtm_managed->get_error_message(), 'error' );
				return;
			}
			$this->notice( __( 'Connected to MYOB.', 'demfilz-sync-for-myob' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=dwtm&tab=connection' ) );
			exit;
		}

		// MYOB controls this return redirect, so there is no WordPress nonce; the OAuth
		// "state" parameter is validated against a transient below as the CSRF check.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$state    = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$expected = get_transient( 'dwtm_oauth_state' );
		if ( ! $state || $state !== $expected ) {
			$this->notice( __( 'OAuth state mismatch. Please try connecting again.', 'demfilz-sync-for-myob' ), 'error' );
			return;
		}
		delete_transient( 'dwtm_oauth_state' );

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		// For API keys created after 12 March 2025, MYOB no longer lists company files from
		// GET /accountright/. The selected file arrives on this redirect as businessId /
		// businessName instead (which is why the authorize URL sends prompt=consent).
		$business_id   = '';
		$business_name = '';
		foreach ( array( 'businessId', 'businessid', 'business_id' ) as $dwtm_k ) {
			if ( ! empty( $_GET[ $dwtm_k ] ) ) {
				$business_id = sanitize_text_field( wp_unslash( $_GET[ $dwtm_k ] ) );
				break;
			}
		}
		foreach ( array( 'businessName', 'businessname', 'business_name' ) as $dwtm_k ) {
			if ( ! empty( $_GET[ $dwtm_k ] ) ) {
				$business_name = sanitize_text_field( wp_unslash( $_GET[ $dwtm_k ] ) );
				break;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $code ) {
			$this->notice( __( 'MYOB did not return an authorization code.', 'demfilz-sync-for-myob' ), 'error' );
			return;
		}

		$result = Dwtm_OAuth::exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			$this->notice( $result->get_error_message(), 'error' );
			return;
		}

		if ( $business_id ) {
			$this->store_company_file( $business_id, $business_name );
			$this->notice( __( 'Connected to MYOB and company file selected.', 'demfilz-sync-for-myob' ) );
		} else {
			$this->notice( __( 'Connected to MYOB. Now choose your company file.', 'demfilz-sync-for-myob' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dwtm&tab=connection' ) );
		exit;
	}

	/**
	 * Persist the company file, building its resource URI from the active platform base.
	 *
	 * @param string $id   Company file GUID (businessId).
	 * @param string $name Company file name, if MYOB supplied one.
	 * @return void
	 */
	private function store_company_file( $id, $name = '' ) {
		Dwtm_OAuth::store_company_file( $id, $name );
	}

	/**
	 * Save the selected company file (and AccountRight cf-token if supplied).
	 *
	 * @return void
	 */
	private function save_company_file() {
		check_admin_referer( 'dwtm_company_file', 'dwtm_nonce' );
		$cf_id   = isset( $_POST['cf_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_id'] ) ) : '';
		$cf_name = isset( $_POST['cf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_name'] ) ) : '';
		$cf_uri  = isset( $_POST['cf_uri'] ) ? esc_url_raw( wp_unslash( $_POST['cf_uri'] ) ) : '';

		if ( $cf_uri ) {
			update_option(
				'dwtm_company_file',
				array(
					'id'   => $cf_id,
					'name' => $cf_name,
					'uri'  => $cf_uri,
				)
			);
		} elseif ( $cf_id ) {
			// Manual entry (no listed files): derive the resource URI from the platform base.
			$this->store_company_file( $cf_id, $cf_name );
		}

		// AccountRight cloud files: optional company-file username/password -> cf-token.
		$user = isset( $_POST['cf_user'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_user'] ) ) : '';
		// The company-file password is stored encrypted; keep the exact characters (do not sanitise).
		$pass = isset( $_POST['cf_pass'] ) ? (string) wp_unslash( $_POST['cf_pass'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $user || $pass ) {
			update_option( 'dwtm_cf_token', Dwtm_Crypto::encrypt( base64_encode( $user . ':' . $pass ) ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$this->notice( __( 'Company file saved.', 'demfilz-sync-for-myob' ) );
	}

	/**
	 * Save tax/account mapping.
	 *
	 * @return void
	 */
	private function save_mapping() {
		check_admin_referer( 'dwtm_mapping', 'dwtm_nonce' );
		Dwtm_Settings::update(
			array(
				'default_income_acct' => isset( $_POST['default_income_acct'] ) ? sanitize_text_field( wp_unslash( $_POST['default_income_acct'] ) ) : '',
				'default_tax_code'    => isset( $_POST['default_tax_code'] ) ? sanitize_text_field( wp_unslash( $_POST['default_tax_code'] ) ) : '',
				'freight_tax_code'    => isset( $_POST['freight_tax_code'] ) ? sanitize_text_field( wp_unslash( $_POST['freight_tax_code'] ) ) : '',
				'gift_card_acct'      => isset( $_POST['gift_card_acct'] ) ? sanitize_text_field( wp_unslash( $_POST['gift_card_acct'] ) ) : '',
				'gift_card_tax_code'  => isset( $_POST['gift_card_tax_code'] ) ? sanitize_text_field( wp_unslash( $_POST['gift_card_tax_code'] ) ) : '',
				'sale_type'           => isset( $_POST['sale_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sale_type'] ) ) : 'Invoice',
			)
		);
		$this->notice( __( 'Mapping saved.', 'demfilz-sync-for-myob' ) );
	}

	/**
	 * Queue an admin notice.
	 *
	 * @param string $message Message.
	 * @param string $type    success|error.
	 * @return void
	 */
	private function notice( $message, $type = 'success' ) {
		add_settings_error( 'dwtm', 'dwtm', $message, 'error' === $type ? 'error' : 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
	}

	/**
	 * Render the settings page shell + active tab.
	 *
	 * @return void
	 */
	public function render() {
		$tab  = $this->current_tab();
		$tabs = array(
			'connection' => __( 'Connection', 'demfilz-sync-for-myob' ),
			'mapping'    => __( 'Mapping', 'demfilz-sync-for-myob' ),
			'sync'       => __( 'Sync', 'demfilz-sync-for-myob' ),
			'logs'       => __( 'Logs', 'demfilz-sync-for-myob' ),
		);
		?>
		<div class="wrap dwtm-wrap">
			<h1><?php esc_html_e( 'Demfilz Sync for WooCommerce to MYOB', 'demfilz-sync-for-myob' ); ?></h1>
			<?php settings_errors( 'dwtm' ); ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=dwtm&tab=' . $slug ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<div class="dwtm-tab">
				<?php
				$view = DWTM_DIR . 'includes/admin/views/' . $tab . '.php';
				if ( file_exists( $view ) ) {
					include $view;
				}
				?>
			</div>
		</div>
		<?php
	}
}
