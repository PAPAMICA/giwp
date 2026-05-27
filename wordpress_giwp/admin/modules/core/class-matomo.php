<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : Connect Matomo (API, suivi, dashboard Statistiques).
 */
class Gi_Toolkit_Matomo {

	const OPTION_SETTINGS = 'gi_toolkit_matomo_settings';

	const STATS_PAGE_SLUG = 'gi-statistics';

	/** @deprecated Anciens slugs (redirection automatique). */
	const LEGACY_SETTINGS_PAGE_SLUG = 'gi-toolkit-settings-matomo';

	const LEGACY_SETTINGS_PAGE_SLUG_V2 = 'gi-toolkit-analytics';

	/** Slug sous-menu GI-Toolkit (même convention que les autres modules). */
	const SETTINGS_PAGE_SLUG = 'gi-toolkit-settings-analytics';

	/** @var string */
	private $page_slug;

	private $header_title = '';

	/**
	 * @var string
	 */
	public $nonce_action = '';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		$this->page_slug    = self::SETTINGS_PAGE_SLUG;
		$this->header_title = __( 'Connect Matomo', 'gi-toolkit' );

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-site.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-tracking.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-dashboard-data.php';

		add_action( 'admin_menu', array( $this, 'register_statistics_menu' ), 9 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'plugins_loaded', array( $this, 'maybe_redirect_legacy_admin_url' ), 0 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );

		add_action( 'wp_ajax_gi_toolkit_matomo_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_gi_toolkit_matomo_sync_site', array( $this, 'ajax_sync_site' ) );
		add_action( 'wp_ajax_gi_toolkit_matomo_dashboard', array( $this, 'ajax_dashboard' ) );

		Gi_Toolkit_Matomo_Tracking::init();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings_static() {
		if ( self::$instance ) {
			return self::$instance->get_settings();
		}
		$defaults = self::default_settings();
		$stored   = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_settings() {
		return array(
			'matomo_url'          => '',
			'api_token'           => '',
			'auto_site'           => '1',
			'site_id'             => 0,
			'tracking_enabled'    => '1',
			'track_mode'          => 'auto',
			'tracking_code'       => '',
			'disable_ssl_verify'  => '0',
		);
	}

	/**
	 * Réglages du module (export JSON / MainWP).
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
	}

	/**
	 * Enregistre les réglages (formulaire admin, MainWP, import JSON).
	 *
	 * @param array<string, mixed> $settings  Réglages.
	 * @param bool                 $sync_site Synchroniser / créer le site Matomo si auto_site.
	 * @return bool
	 */
	public function save_settings( array $settings, $sync_site = true ) {
		$result = $this->persist_settings( $settings, $sync_site );
		return ! empty( $result['success'] );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $sync_site Appeler ensure_site_id.
	 * @return array{success:bool, message?:string, site_id?:int, sync?:array<string,mixed>}
	 */
	private function persist_settings( array $settings, $sync_site = true ) {
		$settings['matomo_url'] = Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' );
		$settings['site_id']    = absint( $settings['site_id'] ?? 0 );

		$sync_result = null;
		if ( $sync_site && '1' === (string) ( $settings['auto_site'] ?? '1' ) ) {
			$sync_result = Gi_Toolkit_Matomo_Site::ensure_site_id( $settings, true );
			if ( ! empty( $sync_result['success'] ) && ! empty( $sync_result['site_id'] ) ) {
				$settings['site_id'] = (int) $sync_result['site_id'];
			}
		}

		Gi_Toolkit_Matomo_Site::clear_tracking_cache( $settings );
		update_option( self::OPTION_SETTINGS, $settings, false );

		return array(
			'success' => true,
			'site_id' => absint( $settings['site_id'] ?? 0 ),
			'sync'    => $sync_result,
		);
	}

	/**
	 * URL admin de la page de réglages (format WordPress correct).
	 *
	 * @return string
	 */
	public static function get_settings_admin_url() {
		return admin_url( 'admin.php?page=' . rawurlencode( self::SETTINGS_PAGE_SLUG ) );
	}

	/**
	 * Matomo est-il prêt pour le tableau de bord (URL + token + site).
	 *
	 * @param array<string, mixed>|null $settings Réglages optionnels.
	 * @return bool
	 */
	public static function is_dashboard_ready( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_settings_static();
		}
		$api = new Gi_Toolkit_Matomo_API( $settings );
		return $api->is_configured() && absint( $settings['site_id'] ?? 0 ) > 0;
	}

	/**
	 * Redirige les URLs incorrectes /wp-admin/gi-toolkit-settings-matomo vers admin.php?page=…
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_admin_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
			$legacy_pages = array(
				self::LEGACY_SETTINGS_PAGE_SLUG,
				self::LEGACY_SETTINGS_PAGE_SLUG_V2,
			);
			if ( in_array( $page, $legacy_pages, true ) ) {
				wp_safe_redirect( self::get_settings_admin_url() );
				exit;
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $uri ) {
			return;
		}
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return;
		}
		// Ancienne URL /wp-admin/{slug} (parent null ou slug obsolète).
		$legacy_slugs = array(
			self::LEGACY_SETTINGS_PAGE_SLUG,
			self::LEGACY_SETTINGS_PAGE_SLUG_V2,
		);
		foreach ( $legacy_slugs as $slug ) {
			if ( preg_match( '#/wp-admin/' . preg_quote( $slug, '#' ) . '/?$#', $path ) ) {
				wp_safe_redirect( self::get_settings_admin_url() );
				exit;
			}
		}
	}

	/**
	 * Menu principal Statistiques (priorité haute pour apparaître en tête).
	 *
	 * @return void
	 */
	public function register_statistics_menu() {
		add_menu_page(
			__( 'Statistiques', 'gi-toolkit' ),
			__( 'Statistiques', 'gi-toolkit' ),
			'manage_options',
			self::STATS_PAGE_SLUG,
			array( $this, 'render_statistics_page' ),
			'dashicons-chart-area',
			1
		);
	}

	/**
	 * Sous-page de réglages sous GI-Toolkit (comme les autres modules).
	 *
	 * @return void
	 */
	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'render_submenu' )
		);
	}

	/**
	 * @return void
	 */
	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, 'gi_toolkit_matomo_save' ) ) {
			return;
		}

		$settings = $this->get_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['matomo_url'] = isset( $_POST['matomo_url'] )
			? sanitize_text_field( wp_unslash( $_POST['matomo_url'] ) )
			: $settings['matomo_url'];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['api_token'] ) ) {
			$settings['api_token'] = sanitize_text_field( wp_unslash( $_POST['api_token'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['auto_site'] = isset( $_POST['auto_site'] ) ? '1' : '0';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['site_id'] = isset( $_POST['site_id'] ) ? absint( wp_unslash( $_POST['site_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['tracking_enabled'] = isset( $_POST['tracking_enabled'] ) ? '1' : '0';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['track_mode'] = isset( $_POST['track_mode'] ) && 'manual' === $_POST['track_mode'] ? 'manual' : 'auto';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['tracking_code'] = isset( $_POST['tracking_code'] )
			? Gi_Toolkit_Matomo_Tracking::sanitize_tracking_code( wp_unslash( $_POST['tracking_code'] ) )
			: '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['disable_ssl_verify'] = isset( $_POST['disable_ssl_verify'] ) ? '1' : '0';

		$save = $this->persist_settings( $settings, true );

		$redirect = add_query_arg(
			array(
				'page'                => $this->page_slug,
				'gi_toolkit_pro_saved' => '1',
				'matomo_sync'         => ! empty( $save['success'] ) ? '1' : '0',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'gi_toolkit_matomo', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		$settings = $this->get_settings_from_request();
		$api      = new Gi_Toolkit_Matomo_API( $settings );
		$result   = $api->test_connection();

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? '' ) );
		}

		$messages = array(
			sprintf(
				/* translators: %s: Matomo version */
				__( 'Connexion OK (Matomo %s).', 'gi-toolkit' ),
				$result['version'] ?? ''
			),
		);

		$response = array(
			'version' => $result['version'] ?? '',
			'message' => '',
			'saved'   => false,
			'site_id' => absint( $settings['site_id'] ?? 0 ),
		);

		$auto_site = '1' === (string) ( $settings['auto_site'] ?? '1' );
		$do_sync   = $auto_site || empty( $response['site_id'] );

		if ( $do_sync && $auto_site ) {
			$sync = Gi_Toolkit_Matomo_Site::ensure_site_id( $settings, true );
			if ( ! empty( $sync['success'] ) && ! empty( $sync['site_id'] ) ) {
				$settings['site_id'] = (int) $sync['site_id'];
				$response['site_id'] = (int) $sync['site_id'];
				$messages[]        = ! empty( $sync['created'] )
					? __( 'Site Matomo créé et associé à ce WordPress.', 'gi-toolkit' )
					: __( 'Site Matomo associé à ce WordPress.', 'gi-toolkit' );
			} elseif ( empty( $sync['success'] ) ) {
				$messages[] = $sync['message'] ?? __( 'Connexion OK, mais la synchronisation du site a échoué.', 'gi-toolkit' );
			}
		}

		$persist = $this->persist_settings( $settings, false );
		if ( ! empty( $persist['success'] ) ) {
			$response['saved']   = true;
			$response['site_id'] = absint( $persist['site_id'] ?? $response['site_id'] );
			$messages[]          = __( 'Configuration enregistrée.', 'gi-toolkit' );
		}

		$response['message'] = implode( ' ', $messages );

		wp_send_json_success( $response );
	}

	/**
	 * @return void
	 */
	public function ajax_sync_site() {
		check_ajax_referer( 'gi_toolkit_matomo', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		$settings = $this->get_settings_from_request();
		$result   = Gi_Toolkit_Matomo_Site::ensure_site_id( $settings, true );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? '' ) );
		}

		$settings['site_id'] = (int) $result['site_id'];
		$this->persist_settings( $settings, false );

		wp_send_json_success(
			array(
				'site_id' => (int) $result['site_id'],
				'created' => ! empty( $result['created'] ),
				'message' => ! empty( $result['created'] )
					? __( 'Site Matomo créé.', 'gi-toolkit' )
					: __( 'Site Matomo associé.', 'gi-toolkit' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_dashboard() {
		check_ajax_referer( 'gi_toolkit_matomo_dashboard', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$period_key = isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : 'last7';
		$settings   = $this->get_settings();
		$data       = Gi_Toolkit_Matomo_Dashboard_Data::fetch( $settings, $period_key );

		if ( empty( $data['success'] ) ) {
			wp_send_json_error( array( 'message' => $data['message'] ?? '' ) );
		}

		ob_start();
		$this->render_dashboard_markup( $data );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'       => $html,
				'period_key' => $period_key,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_settings_from_request() {
		$settings = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['matomo_url'] ) ) {
			$settings['matomo_url'] = sanitize_text_field( wp_unslash( $_POST['matomo_url'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['api_token'] ) ) {
			$settings['api_token'] = sanitize_text_field( wp_unslash( $_POST['api_token'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['auto_site'] ) ) {
			$settings['auto_site'] = '1' === sanitize_text_field( wp_unslash( $_POST['auto_site'] ) ) ? '1' : '0';
		}
		return $settings;
	}

	/**
	 * Après import MainWP / JSON : synchronise le site Matomo pour l’URL de ce site enfant.
	 *
	 * @param array<string, mixed> $settings Réglages importés.
	 * @return array<string, mixed>
	 */
	public static function bootstrap_settings_after_import( array $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = wp_parse_args( $settings, self::default_settings() );
		$settings['matomo_url'] = Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' );

		$api = new Gi_Toolkit_Matomo_API( $settings );
		if ( ! $api->is_configured() ) {
			return $settings;
		}

		if ( '1' !== (string) ( $settings['auto_site'] ?? '1' ) ) {
			return $settings;
		}

		$sync = Gi_Toolkit_Matomo_Site::ensure_site_id( $settings, true );
		if ( ! empty( $sync['success'] ) && ! empty( $sync['site_id'] ) ) {
			$settings['site_id'] = (int) $sync['site_id'];
		}

		return $settings;
	}

	/**
	 * @return void
	 */
	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		wp_enqueue_style(
			'gi-toolkit-matomo',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/matomo.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-matomo-settings',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/matomo-settings.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-matomo-settings',
			'giToolkitMatomo',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gi_toolkit_matomo' ),
				'i18n'    => array(
					'testing'  => __( 'Test en cours…', 'gi-toolkit' ),
					'syncing'  => __( 'Synchronisation…', 'gi-toolkit' ),
					'saving'   => __( 'Enregistrement…', 'gi-toolkit' ),
					'error'    => __( 'Erreur', 'gi-toolkit' ),
				),
			)
		);

		$settings     = $this->get_settings();
		$has_token    = '' !== trim( (string) ( $settings['api_token'] ?? '' ) );
		$nonce_action = 'gi_toolkit_matomo_save';

		$this->nonce_action = $nonce_action;

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body gi-toolkit-matomo-settings-page">
			<input type="hidden" name="gi_toolkit_pro_save" value="1" />
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<div class="gi-matomo-settings-grid">
				<section class="gi-matomo-panel">
					<h3><?php esc_html_e( 'Connexion Matomo', 'gi-toolkit' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'URL de votre instance Matomo et token API (Préférences → Sécurité → Auth token). Ces valeurs sont incluses dans l’export JSON GI-Toolkit et le déploiement MainWP.', 'gi-toolkit' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="matomo_url"><?php esc_html_e( 'URL Matomo', 'gi-toolkit' ); ?></label></th>
							<td>
								<input type="url" class="large-text" name="matomo_url" id="matomo_url" value="<?php echo esc_attr( $settings['matomo_url'] ); ?>" placeholder="https://matomo.example.com" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="api_token"><?php esc_html_e( 'Token API', 'gi-toolkit' ); ?></label></th>
							<td>
								<input type="password" class="large-text" name="api_token" id="api_token" value="" autocomplete="new-password" placeholder="<?php echo $has_token ? esc_attr__( '•••••••• (laisser vide pour conserver)', 'gi-toolkit' ) : ''; ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Test', 'gi-toolkit' ); ?></th>
							<td>
								<button type="button" class="button" id="gi-matomo-test-connection"><?php esc_html_e( 'Tester la connexion', 'gi-toolkit' ); ?></button>
								<span id="gi-matomo-test-result" class="gi-matomo-inline-result" aria-live="polite"></span>
							</td>
						</tr>
					</table>
				</section>

				<section class="gi-matomo-panel">
					<h3><?php esc_html_e( 'Site Matomo', 'gi-toolkit' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Détecte automatiquement le site Matomo correspondant à l’URL WordPress, ou le crée s’il n’existe pas.', 'gi-toolkit' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Automatique', 'gi-toolkit' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="auto_site" value="1" <?php checked( '1', $settings['auto_site'] ); ?> />
									<?php esc_html_e( 'Détection / création automatique du site', 'gi-toolkit' ); ?>
								</label>
								<p class="description"><?php echo esc_html( Gi_Toolkit_Matomo_Site::get_wordpress_site_url() ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="site_id"><?php esc_html_e( 'ID site Matomo', 'gi-toolkit' ); ?></label></th>
							<td>
								<input type="number" min="0" name="site_id" id="site_id" value="<?php echo esc_attr( (string) $settings['site_id'] ); ?>" class="small-text" />
								<button type="button" class="button" id="gi-matomo-sync-site"><?php esc_html_e( 'Synchroniser le site', 'gi-toolkit' ); ?></button>
								<span id="gi-matomo-sync-result" class="gi-matomo-inline-result" aria-live="polite"></span>
							</td>
						</tr>
					</table>
				</section>

				<section class="gi-matomo-panel">
					<h3><?php esc_html_e( 'Suivi front', 'gi-toolkit' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Activer', 'gi-toolkit' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="tracking_enabled" value="1" <?php checked( '1', $settings['tracking_enabled'] ); ?> />
									<?php esc_html_e( 'Injecter le code de suivi Matomo sur le site public', 'gi-toolkit' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Mode', 'gi-toolkit' ); ?></th>
							<td>
								<label><input type="radio" name="track_mode" value="auto" <?php checked( 'auto', $settings['track_mode'] ); ?> /> <?php esc_html_e( 'Automatique (via API)', 'gi-toolkit' ); ?></label><br />
								<label><input type="radio" name="track_mode" value="manual" <?php checked( 'manual', $settings['track_mode'] ); ?> /> <?php esc_html_e( 'Code manuel', 'gi-toolkit' ); ?></label>
							</td>
						</tr>
						<tr class="gi-matomo-row-manual-code">
							<th scope="row"><label for="tracking_code"><?php esc_html_e( 'Code de suivi', 'gi-toolkit' ); ?></label></th>
							<td>
								<textarea name="tracking_code" id="tracking_code" rows="6" class="large-text code"><?php echo esc_textarea( $settings['tracking_code'] ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'SSL', 'gi-toolkit' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="disable_ssl_verify" value="1" <?php checked( '1', $settings['disable_ssl_verify'] ); ?> />
									<?php esc_html_e( 'Désactiver la vérification SSL (dépannage uniquement)', 'gi-toolkit' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</section>
			</div>

			<p>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::STATS_PAGE_SLUG ) ); ?>">
					<?php esc_html_e( 'Voir le tableau de bord Statistiques', 'gi-toolkit' ); ?>
				</a>
			</p>
		</div>
		<?php
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * @return void
	 */
	public function render_statistics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$version  = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		$settings = $this->get_settings();
		$api      = new Gi_Toolkit_Matomo_API( $settings );

		wp_enqueue_style(
			'gi-toolkit-matomo',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/matomo.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-matomo-dashboard',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/matomo-dashboard.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-matomo-dashboard',
			'giToolkitMatomoDashboard',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'gi_toolkit_matomo_dashboard' ),
				'settingsUrl'   => self::get_settings_admin_url(),
				'matomoUrl'     => Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' ),
				'defaultPeriod' => 'last7',
				'i18n'          => array(
					'loading' => __( 'Chargement…', 'gi-toolkit' ),
					'error'   => __( 'Impossible de charger les statistiques.', 'gi-toolkit' ),
				),
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$period_key = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'last7';
		$dashboard  = array( 'success' => false );

		$is_ready = self::is_dashboard_ready( $settings );
		if ( $is_ready ) {
			$dashboard = Gi_Toolkit_Matomo_Dashboard_Data::fetch( $settings, $period_key );
		}

		$settings_url = self::get_settings_admin_url();

		?>
		<div class="wrap gi-matomo-stats-wrap">
			<header class="gi-matomo-stats-header">
				<h1><?php esc_html_e( 'Statistiques', 'gi-toolkit' ); ?></h1>
				<div class="gi-matomo-stats-header__actions">
					<a class="button button-secondary" href="<?php echo esc_url( $settings_url ); ?>">
						<?php esc_html_e( 'Réglages Matomo', 'gi-toolkit' ); ?>
					</a>
					<?php if ( $is_ready && ! empty( $settings['matomo_url'] ) ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $settings['matomo_url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Ouvrir dans Matomo', 'gi-toolkit' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</header>

			<?php if ( ! $is_ready ) : ?>
				<?php $this->render_setup_notice( $api, $settings ); ?>
			<?php else : ?>
				<nav class="gi-matomo-period-nav" aria-label="<?php esc_attr_e( 'Période', 'gi-toolkit' ); ?>">
					<?php
					$periods = array( 'today', 'yesterday', 'last7', 'last30', 'month' );
					foreach ( $periods as $key ) {
						$p   = Gi_Toolkit_Matomo_Dashboard_Data::resolve_period( $key );
						$url = add_query_arg( array( 'page' => self::STATS_PAGE_SLUG, 'period' => $key ), admin_url( 'admin.php' ) );
						printf(
							'<a href="%s" class="gi-matomo-period-btn%s" data-period="%s">%s</a>',
							esc_url( $url ),
							$key === $period_key ? ' is-active' : '',
							esc_attr( $key ),
							esc_html( $p['label'] )
						);
					}
					?>
				</nav>

				<div id="gi-matomo-dashboard" data-period="<?php echo esc_attr( $period_key ); ?>">
					<?php $this->render_dashboard_markup( $dashboard ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Panneau d’accueil lorsque Matomo n’est pas configuré.
	 *
	 * @param Gi_Toolkit_Matomo_API $api      Client API.
	 * @param array<string, mixed>  $settings Réglages.
	 * @return void
	 */
	private function render_setup_notice( Gi_Toolkit_Matomo_API $api, array $settings ) {
		$settings_url = self::get_settings_admin_url();
		$has_conn       = $api->is_configured();
		$has_site       = absint( $settings['site_id'] ?? 0 ) > 0;

		?>
		<div class="gi-matomo-setup-panel">
			<div class="gi-matomo-setup-panel__icon dashicons dashicons-chart-area" aria-hidden="true"></div>
			<h2><?php esc_html_e( 'Matomo n’est pas encore configuré', 'gi-toolkit' ); ?></h2>
			<?php if ( ! $has_conn ) : ?>
				<p><?php esc_html_e( 'Renseignez l’URL de votre instance Matomo et votre token API pour afficher les statistiques de ce site.', 'gi-toolkit' ); ?></p>
			<?php elseif ( ! $has_site ) : ?>
				<p><?php esc_html_e( 'La connexion Matomo est enregistrée, mais aucun site n’est associé à cette URL WordPress. Lancez une synchronisation depuis les réglages.', 'gi-toolkit' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Terminez la configuration Matomo pour afficher le tableau de bord.', 'gi-toolkit' ); ?></p>
			<?php endif; ?>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $settings_url ); ?>">
					<?php esc_html_e( 'Configurer Connect Matomo', 'gi-toolkit' ); ?>
				</a>
			</p>
			<ul class="gi-matomo-setup-steps">
				<li class="<?php echo $has_conn ? 'is-done' : ''; ?>">
					<?php esc_html_e( '1. URL Matomo + token API', 'gi-toolkit' ); ?>
				</li>
				<li class="<?php echo $has_site ? 'is-done' : ''; ?>">
					<?php esc_html_e( '2. Synchroniser le site (détection ou création automatique)', 'gi-toolkit' ); ?>
				</li>
				<li>
					<?php esc_html_e( '3. Revenir ici pour consulter les statistiques', 'gi-toolkit' ); ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $dashboard Données dashboard.
	 * @return void
	 */
	private function render_dashboard_markup( array $dashboard ) {
		if ( empty( $dashboard['success'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $dashboard['message'] ?? __( 'Erreur de chargement.', 'gi-toolkit' ) ) . '</p></div>';
			return;
		}

		$kpis = $dashboard['kpis'] ?? array();
		?>
		<div class="gi-matomo-kpis">
			<div class="gi-matomo-kpi">
				<span class="gi-matomo-kpi__value"><?php echo esc_html( $kpis['nb_visits'] ?? '0' ); ?></span>
				<span class="gi-matomo-kpi__label"><?php esc_html_e( 'Visites', 'gi-toolkit' ); ?></span>
			</div>
			<div class="gi-matomo-kpi">
				<span class="gi-matomo-kpi__value"><?php echo esc_html( $kpis['nb_uniq_visitors'] ?? '0' ); ?></span>
				<span class="gi-matomo-kpi__label"><?php esc_html_e( 'Visiteurs uniques', 'gi-toolkit' ); ?></span>
			</div>
			<div class="gi-matomo-kpi">
				<span class="gi-matomo-kpi__value"><?php echo esc_html( $kpis['nb_actions'] ?? '0' ); ?></span>
				<span class="gi-matomo-kpi__label"><?php esc_html_e( 'Pages vues', 'gi-toolkit' ); ?></span>
			</div>
			<div class="gi-matomo-kpi">
				<span class="gi-matomo-kpi__value"><?php echo esc_html( $kpis['bounce_rate'] ?? '0%' ); ?></span>
				<span class="gi-matomo-kpi__label"><?php esc_html_e( 'Taux de rebond', 'gi-toolkit' ); ?></span>
			</div>
			<div class="gi-matomo-kpi">
				<span class="gi-matomo-kpi__value"><?php echo esc_html( $kpis['avg_time'] ?? '0' ); ?></span>
				<span class="gi-matomo-kpi__label"><?php esc_html_e( 'Durée moyenne', 'gi-toolkit' ); ?></span>
			</div>
		</div>

		<div class="gi-matomo-chart-panel">
			<h2><?php esc_html_e( 'Évolution des visites', 'gi-toolkit' ); ?></h2>
			<canvas id="gi-matomo-visits-chart" width="800" height="280" role="img" aria-label="<?php esc_attr_e( 'Graphique des visites', 'gi-toolkit' ); ?>"></canvas>
			<script type="application/json" id="gi-matomo-chart-data"><?php echo wp_json_encode( $dashboard['chart'] ?? array() ); ?></script>
		</div>

		<div class="gi-matomo-tables">
			<?php $this->render_report_table( __( 'Pages les plus vues', 'gi-toolkit' ), $dashboard['pages'] ?? array() ); ?>
			<?php $this->render_report_table( __( 'Sources de trafic', 'gi-toolkit' ), $dashboard['referrers'] ?? array() ); ?>
			<?php $this->render_report_table( __( 'Pays', 'gi-toolkit' ), $dashboard['countries'] ?? array() ); ?>
			<?php $this->render_report_table( __( 'Navigateurs', 'gi-toolkit' ), $dashboard['browsers'] ?? array() ); ?>
		</div>
		<?php
	}

	/**
	 * @param string                              $title Titre.
	 * @param array<int, array{label:string,value:int}> $rows  Lignes.
	 * @return void
	 */
	private function render_report_table( $title, array $rows ) {
		?>
		<div class="gi-matomo-table-panel">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( empty( $rows ) ) : ?>
				<p class="description"><?php esc_html_e( 'Aucune donnée pour cette période.', 'gi-toolkit' ); ?></p>
			<?php else : ?>
				<table class="widefat striped gi-matomo-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Libellé', 'gi-toolkit' ); ?></th>
							<th class="gi-matomo-num"><?php esc_html_e( 'Valeur', 'gi-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['label'] ); ?></td>
								<td class="gi-matomo-num"><?php echo esc_html( number_format_i18n( (int) $row['value'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
