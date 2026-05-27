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

		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_stats' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );

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
		if ( ! empty( $result['success'] ) ) {
			$site_id = absint( $settings['site_id'] ?? 0 );
			if ( $site_id > 0 ) {
				delete_transient( 'gi_matomo_toolbar_' . $site_id );
			}
		}
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
		$data = Gi_Toolkit_Matomo_Dashboard_Data::fetch( $settings, $period_key );

		if ( empty( $data['success'] ) ) {
			wp_send_json_error( array( 'message' => $data['message'] ?? '' ) );
		}

		ob_start();
		$this->render_dashboard_markup( $data );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'            => $html,
				'period_key'      => $period_key,
				'matomoUrl'       => Gi_Toolkit_Matomo_API::get_site_dashboard_url( $settings, $period_key ),
				'is_live'         => 'live' === $period_key,
				'refresh_seconds' => 'live' === $period_key ? 10 : 0,
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
	 * Prépare les réglages pour un déploiement distant (MainWP / import JSON).
	 * Ne conserve jamais l’ID site du dashboard source.
	 *
	 * @param array<string, mixed> $settings Réglages.
	 * @return array<string, mixed>
	 */
	public static function prepare_settings_for_remote_deploy( array $settings ) {
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
		$settings['matomo_url'] = Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' );
		$settings['api_token']  = trim( (string) ( $settings['api_token'] ?? '' ) );
		$settings['site_id']    = 0;
		if ( ! isset( $settings['auto_site'] ) || '0' !== (string) $settings['auto_site'] ) {
			$settings['auto_site'] = '1';
		}
		return $settings;
	}

	/**
	 * Import MainWP / JSON : enregistre URL + token, crée ou associe le site Matomo pour cette URL WP.
	 *
	 * @param array<string, mixed> $settings Réglages importés.
	 * @return array{success:bool, message?:string, site_id?:int, sync?:array<string,mixed>}
	 */
	public static function deploy_from_mainwp( array $settings ) {
		$settings = self::prepare_settings_for_remote_deploy( $settings );

		if ( '' === $settings['matomo_url'] || '' === $settings['api_token'] ) {
			return array(
				'success' => false,
				'message' => __( 'URL Matomo et token API requis pour le déploiement.', 'gi-toolkit' ),
			);
		}

		$settings   = self::bootstrap_settings_after_import( $settings );
		$sync_error = isset( $settings['_last_sync_error'] ) ? (string) $settings['_last_sync_error'] : '';
		unset( $settings['_last_sync_error'] );

		if ( absint( $settings['site_id'] ?? 0 ) < 1 && '1' === (string) ( $settings['auto_site'] ?? '1' ) ) {
			return array(
				'success' => false,
				'message' => $sync_error ?: __( 'Connexion Matomo OK mais impossible d’associer ce site WordPress dans Matomo.', 'gi-toolkit' ),
			);
		}

		if ( ! self::$instance ) {
			new self();
		}

		$result = self::$instance->apply_settings( $settings, false );

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Échec de l’enregistrement des réglages Matomo.', 'gi-toolkit' ),
			);
		}

		$site_id = absint( $result['site_id'] ?? 0 );
		$sync    = is_array( $result['sync'] ?? null ) ? $result['sync'] : array();

		if ( $site_id < 1 && '1' === (string) ( $settings['auto_site'] ?? '1' ) ) {
			return array(
				'success' => false,
				'message' => $sync['message'] ?? __( 'Site Matomo non synchronisé.', 'gi-toolkit' ),
				'sync'    => $sync,
			);
		}

		return array(
			'success' => true,
			'site_id' => $site_id,
			'message' => ! empty( $sync['created'] )
				? __( 'Matomo configuré — site créé dans Matomo.', 'gi-toolkit' )
				: __( 'Matomo configuré — site associé.', 'gi-toolkit' ),
			'sync'    => $sync,
		);
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $sync_site  Synchroniser le site Matomo.
	 * @return array{success:bool, site_id?:int, sync?:array<string,mixed>}
	 */
	public function apply_settings( array $settings, $sync_site = true ) {
		return $this->persist_settings( $settings, $sync_site );
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
		$settings = self::prepare_settings_for_remote_deploy( $settings );

		$api = new Gi_Toolkit_Matomo_API( $settings );
		if ( ! $api->is_configured() ) {
			$settings['_last_sync_error'] = $api->get_last_error() ?: __( 'URL Matomo ou token API manquant.', 'gi-toolkit' );
			return $settings;
		}

		if ( '1' !== (string) ( $settings['auto_site'] ?? '1' ) ) {
			return $settings;
		}

		$sync = Gi_Toolkit_Matomo_Site::ensure_site_id( $settings, true );
		if ( ! empty( $sync['success'] ) && ! empty( $sync['site_id'] ) ) {
			$settings['site_id'] = (int) $sync['site_id'];
		} else {
			$settings['site_id']          = 0;
			$settings['_last_sync_error'] = $sync['message'] ?? __( 'Synchronisation Matomo impossible.', 'gi-toolkit' );
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
	/**
	 * Graphique visites dans la barre d’administration (style wp-piwik).
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Barre admin.
	 * @return void
	 */
	public function register_admin_bar_stats( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( ! self::is_dashboard_ready( $settings ) ) {
			return;
		}

		$data = Gi_Toolkit_Matomo_Dashboard_Data::fetch_toolbar_sparkline( $settings );
		if ( empty( $data['success'] ) ) {
			return;
		}

		$visits = number_format_i18n( (int) ( $data['visits'] ?? 0 ) );
		$title  = sprintf(
			'<span class="gi-matomo-ab-stats"><span class="gi-matomo-ab-count"><strong>%1$s</strong> <small>%2$s</small></span><canvas id="gi-matomo-ab-chart" width="140" height="32" aria-hidden="true"></canvas></span>',
			esc_html( $visits ),
			esc_html__( '7 j', 'gi-toolkit' )
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'gi-matomo-toolbar-stats',
				'title'  => $title,
				'href'   => admin_url( 'admin.php?page=' . self::STATS_PAGE_SLUG ),
				'meta'   => array(
					'class' => 'gi-matomo-ab-stats-menu',
					'html'  => true,
					'title' => __( 'Statistiques Matomo — 7 derniers jours', 'gi-toolkit' ),
				),
			)
		);
	}

	/**
	 * Scripts / styles barre admin (sparkline).
	 *
	 * @param string $hook_suffix Page courante.
	 * @return void
	 */
	public function enqueue_admin_bar_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( ! self::is_dashboard_ready( $settings ) ) {
			return;
		}

		$data = Gi_Toolkit_Matomo_Dashboard_Data::fetch_toolbar_sparkline( $settings );
		if ( empty( $data['success'] ) ) {
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
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);
		wp_enqueue_script(
			'gi-toolkit-matomo-admin-bar',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/matomo-admin-bar.js',
			array( 'chartjs' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-matomo-admin-bar',
			'giToolkitMatomoAdminBar',
			array(
				'sparkline' => array(
					'labels' => $data['labels'] ?? array(),
					'values' => $data['values'] ?? array(),
				),
				'statsUrl'  => admin_url( 'admin.php?page=' . self::STATS_PAGE_SLUG ),
			)
		);
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
		wp_enqueue_style(
			'jsvectormap',
			'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css',
			array(),
			'1.5.3'
		);
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);
		wp_enqueue_script(
			'jsvectormap',
			'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js',
			array(),
			'1.5.3',
			true
		);
		wp_enqueue_script(
			'jsvectormap-world',
			'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js',
			array( 'jsvectormap' ),
			'1.5.3',
			true
		);
		wp_enqueue_script(
			'gi-toolkit-matomo-dashboard',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/matomo-dashboard.js',
			array( 'jquery', 'chartjs', 'jsvectormap', 'jsvectormap-world' ),
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
				'defaultPeriod' => 'last7',
				'i18n'          => array(
					'loading'  => __( 'Chargement…', 'gi-toolkit' ),
					'error'    => __( 'Impossible de charger les statistiques.', 'gi-toolkit' ),
					'visits'   => __( 'Visites', 'gi-toolkit' ),
					'unique'   => __( 'Visiteurs uniques', 'gi-toolkit' ),
					'actions'  => __( 'Pages vues', 'gi-toolkit' ),
					'mapEmpty' => __( 'Aucune donnée géographique pour cette période.', 'gi-toolkit' ),
					'liveNow'  => __( 'Actualisation automatique', 'gi-toolkit' ),
				),
				'liveRefresh' => 10,
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
						<a id="gi-matomo-external-link" class="button button-secondary" href="<?php echo esc_url( Gi_Toolkit_Matomo_API::get_site_dashboard_url( $settings, $period_key ) ); ?>" target="_blank" rel="noopener noreferrer">
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
					$periods = Gi_Toolkit_Matomo_Dashboard_Data::period_keys();
					foreach ( $periods as $key ) {
						$p            = Gi_Toolkit_Matomo_Dashboard_Data::resolve_period( $key );
						$url          = add_query_arg( array( 'page' => self::STATS_PAGE_SLUG, 'period' => $key ), admin_url( 'admin.php' ) );
						$extra_class  = 'live' === $key ? ' gi-matomo-period-btn--live' : '';
						$live_marker  = 'live' === $key ? '<span class="gi-matomo-live-dot" aria-hidden="true"></span>' : '';
						printf(
							'<a href="%s" class="gi-matomo-period-btn%s%s" data-period="%s">%s%s</a>',
							esc_url( $url ),
							$extra_class,
							$key === $period_key ? ' is-active' : '',
							esc_attr( $key ),
							$live_marker,
							esc_html( $p['label'] )
						);
					}
					?>
				</nav>

				<div id="gi-matomo-dashboard-wrap" class="gi-matomo-dashboard-wrap" data-period="<?php echo esc_attr( $period_key ); ?>">
					<div id="gi-matomo-loader" class="gi-matomo-loader" role="status" aria-live="polite" aria-busy="false" hidden>
						<span class="gi-matomo-loader__ring" aria-hidden="true"></span>
						<span class="gi-matomo-loader__label"><?php esc_html_e( 'Chargement des statistiques…', 'gi-toolkit' ); ?></span>
					</div>
					<div id="gi-matomo-dashboard">
						<?php $this->render_dashboard_markup( $dashboard ); ?>
					</div>
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

		if ( 'live' === ( $dashboard['mode'] ?? '' ) ) {
			$this->render_live_markup( $dashboard );
			return;
		}

		$kpis          = $dashboard['kpis'] ?? array();
		$period        = $dashboard['period']['label'] ?? '';
		$compare_label = $dashboard['compare_label'] ?? '';
		?>
		<div class="gi-matomo-dash-meta">
			<p class="gi-matomo-dash-meta__period">
				<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
				<?php echo esc_html( $period ); ?>
				<?php if ( $compare_label ) : ?>
					<span class="gi-matomo-dash-meta__compare"><?php echo esc_html( $compare_label ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $dashboard['site_url'] ) ) : ?>
				<p class="gi-matomo-dash-meta__site">
					<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
					<?php echo esc_html( $dashboard['site_url'] ); ?>
					<?php if ( ! empty( $dashboard['site_id'] ) ) : ?>
						<span class="gi-matomo-dash-meta__id">#<?php echo esc_html( (string) $dashboard['site_id'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="gi-matomo-kpis">
			<?php
			$this->render_kpi_card(
				'dashicons-chart-line',
				$kpis['nb_visits'] ?? '0',
				__( 'Visites', 'gi-toolkit' ),
				$kpis['trend_visits'] ?? array(),
				'gi-matomo-kpi--primary'
			);
			$this->render_kpi_card(
				'dashicons-groups',
				$kpis['nb_uniq_visitors'] ?? '0',
				__( 'Visiteurs uniques', 'gi-toolkit' ),
				$kpis['trend_visitors'] ?? array()
			);
			$this->render_kpi_card(
				'dashicons-media-document',
				$kpis['nb_actions'] ?? '0',
				__( 'Pages vues', 'gi-toolkit' ),
				$kpis['trend_actions'] ?? array()
			);
			$this->render_kpi_card(
				'dashicons-undo',
				$kpis['bounce_rate'] ?? '0%',
				__( 'Taux de rebond', 'gi-toolkit' ),
				$kpis['trend_bounce'] ?? array()
			);
			$this->render_kpi_card(
				'dashicons-clock',
				$kpis['avg_time'] ?? '0',
				__( 'Durée moyenne', 'gi-toolkit' ),
				$kpis['trend_avg_time'] ?? array()
			);
			$this->render_kpi_card(
				'dashicons-book',
				$kpis['pages_per_visit'] ?? '0',
				__( 'Pages / visite', 'gi-toolkit' ),
				$kpis['trend_pages_visit'] ?? array()
			);
			?>
		</div>

		<div class="gi-matomo-charts-grid">
			<div class="gi-matomo-chart-panel gi-matomo-chart-panel--timeline gi-matomo-chart-panel--full">
				<h2><?php esc_html_e( 'Évolution du trafic', 'gi-toolkit' ); ?></h2>
				<div class="gi-matomo-chart-canvas-wrap">
					<canvas id="gi-matomo-chart-timeline" role="img" aria-label="<?php esc_attr_e( 'Évolution du trafic', 'gi-toolkit' ); ?>"></canvas>
				</div>
			</div>
			<div class="gi-matomo-charts-bottom">
				<div class="gi-matomo-chart-panel gi-matomo-chart-panel--map">
					<h2><?php esc_html_e( 'Origine des visiteurs', 'gi-toolkit' ); ?></h2>
					<div class="gi-matomo-map-wrap">
						<div id="gi-matomo-world-map" class="gi-matomo-world-map" role="img" aria-label="<?php esc_attr_e( 'Carte mondiale des visites', 'gi-toolkit' ); ?>"></div>
					</div>
					<p class="gi-matomo-map-legend description"><?php esc_html_e( 'Intensité = visites par pays.', 'gi-toolkit' ); ?></p>
				</div>
				<div class="gi-matomo-charts-donuts">
					<div class="gi-matomo-chart-panel">
						<h2><?php esc_html_e( 'Sources', 'gi-toolkit' ); ?></h2>
						<div class="gi-matomo-chart-canvas-wrap gi-matomo-chart-canvas-wrap--donut">
							<canvas id="gi-matomo-chart-referrers" role="img" aria-label="<?php esc_attr_e( 'Sources de trafic', 'gi-toolkit' ); ?>"></canvas>
						</div>
					</div>
					<div class="gi-matomo-chart-panel">
						<h2><?php esc_html_e( 'Pays', 'gi-toolkit' ); ?></h2>
						<div class="gi-matomo-chart-canvas-wrap gi-matomo-chart-canvas-wrap--donut">
							<canvas id="gi-matomo-chart-countries" role="img" aria-label="<?php esc_attr_e( 'Répartition par pays', 'gi-toolkit' ); ?>"></canvas>
						</div>
					</div>
					<div class="gi-matomo-chart-panel">
						<h2><?php esc_html_e( 'Appareils', 'gi-toolkit' ); ?></h2>
						<div class="gi-matomo-chart-canvas-wrap gi-matomo-chart-canvas-wrap--donut">
							<canvas id="gi-matomo-chart-devices" role="img" aria-label="<?php esc_attr_e( 'Types d’appareils', 'gi-toolkit' ); ?>"></canvas>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script type="application/json" id="gi-matomo-charts-data"><?php echo wp_json_encode( $dashboard['charts'] ?? array() ); ?></script>

		<div class="gi-matomo-tables">
			<?php $this->render_report_table( __( 'Pages les plus vues', 'gi-toolkit' ), $dashboard['pages'] ?? array(), 'dashicons-admin-page' ); ?>
			<?php $this->render_report_table( __( 'Sources de trafic', 'gi-toolkit' ), $dashboard['referrers'] ?? array(), 'dashicons-randomize' ); ?>
			<?php $this->render_report_table( __( 'Moteurs de recherche', 'gi-toolkit' ), $dashboard['search'] ?? array(), 'dashicons-search' ); ?>
			<?php $this->render_report_table( __( 'Pays', 'gi-toolkit' ), $dashboard['countries'] ?? array(), 'dashicons-location-alt' ); ?>
			<?php $this->render_report_table( __( 'Navigateurs', 'gi-toolkit' ), $dashboard['browsers'] ?? array(), 'dashicons-desktop' ); ?>
			<?php $this->render_report_table( __( 'Appareils', 'gi-toolkit' ), $dashboard['devices'] ?? array(), 'dashicons-smartphone' ); ?>
		</div>
		<?php
	}

	/**
	 * Vue temps réel (onglet En direct).
	 *
	 * @param array<string, mixed> $dashboard Données live.
	 * @return void
	 */
	private function render_live_markup( array $dashboard ) {
		$live          = $dashboard['live'] ?? array();
		$c3            = $live['counters']['3'] ?? array();
		$c30           = $live['counters']['30'] ?? array();
		$visits        = $live['visits'] ?? array();
		$updated_at    = ! empty( $live['updated_at'] ) ? (int) $live['updated_at'] : time();
		$refresh       = (int) ( $dashboard['refresh_seconds'] ?? 10 );
		$updated_label = sprintf(
			/* translators: %s: time */
			__( 'Mis à jour à %s', 'gi-toolkit' ),
			wp_date( 'H:i:s', $updated_at )
		);
		?>
		<div class="gi-matomo-live" data-refresh="<?php echo esc_attr( (string) $refresh ); ?>">
			<div class="gi-matomo-dash-meta gi-matomo-dash-meta--live">
				<p class="gi-matomo-dash-meta__period">
					<span class="gi-matomo-live-pulse" aria-hidden="true"></span>
					<?php esc_html_e( 'En direct', 'gi-toolkit' ); ?>
				</p>
				<p class="gi-matomo-live-updated">
					<span id="gi-matomo-live-updated-text"><?php echo esc_html( $updated_label ); ?></span>
					<span class="gi-matomo-live-refresh-badge"><?php echo esc_html( sprintf( __( 'Rafraîchissement %ds', 'gi-toolkit' ), $refresh ) ); ?></span>
				</p>
			</div>

			<div class="gi-matomo-live-counters">
				<div class="gi-matomo-live-counter gi-matomo-live-counter--active">
					<span class="gi-matomo-live-counter__value"><?php echo esc_html( number_format_i18n( (int) ( $c3['visitors'] ?? 0 ) ) ); ?></span>
					<span class="gi-matomo-live-counter__label"><?php esc_html_e( 'Visiteurs actifs', 'gi-toolkit' ); ?></span>
					<small><?php esc_html_e( '3 dernières minutes', 'gi-toolkit' ); ?></small>
				</div>
				<div class="gi-matomo-live-counter">
					<span class="gi-matomo-live-counter__value"><?php echo esc_html( number_format_i18n( (int) ( $c30['visits'] ?? 0 ) ) ); ?></span>
					<span class="gi-matomo-live-counter__label"><?php esc_html_e( 'Visites', 'gi-toolkit' ); ?></span>
					<small><?php esc_html_e( '30 dernières minutes', 'gi-toolkit' ); ?></small>
				</div>
				<div class="gi-matomo-live-counter">
					<span class="gi-matomo-live-counter__value"><?php echo esc_html( number_format_i18n( (int) ( $c30['visitors'] ?? 0 ) ) ); ?></span>
					<span class="gi-matomo-live-counter__label"><?php esc_html_e( 'Visiteurs uniques', 'gi-toolkit' ); ?></span>
					<small><?php esc_html_e( '30 dernières minutes', 'gi-toolkit' ); ?></small>
				</div>
				<div class="gi-matomo-live-counter">
					<span class="gi-matomo-live-counter__value"><?php echo esc_html( number_format_i18n( (int) ( $c30['actions'] ?? 0 ) ) ); ?></span>
					<span class="gi-matomo-live-counter__label"><?php esc_html_e( 'Actions', 'gi-toolkit' ); ?></span>
					<small><?php esc_html_e( '30 dernières minutes', 'gi-toolkit' ); ?></small>
				</div>
			</div>

			<div class="gi-matomo-live-feed">
				<h2><?php esc_html_e( 'Dernières visites', 'gi-toolkit' ); ?></h2>
				<?php if ( empty( $visits ) ) : ?>
					<p class="gi-matomo-live-empty description"><?php esc_html_e( 'Aucun visiteur récent. Les nouvelles visites apparaîtront ici automatiquement.', 'gi-toolkit' ); ?></p>
				<?php else : ?>
					<ul class="gi-matomo-live-list">
						<?php foreach ( $visits as $visit ) : ?>
							<li class="gi-matomo-live-visit<?php echo ! empty( $visit['is_new'] ) ? ' is-new' : ''; ?>">
								<div class="gi-matomo-live-visit__icons" aria-hidden="true">
									<?php if ( ! empty( $visit['browser_icon'] ) ) : ?>
										<img class="gi-matomo-live-visit__browser" src="<?php echo esc_url( $visit['browser_icon'] ); ?>" alt="" width="22" height="22" loading="lazy" />
									<?php else : ?>
										<span class="gi-matomo-live-visit__browser gi-matomo-live-visit__browser--fallback" title="<?php echo esc_attr( $visit['browser'] ?? '' ); ?>"></span>
									<?php endif; ?>
									<?php if ( ! empty( $visit['device_icon'] ) ) : ?>
										<img class="gi-matomo-live-visit__device" src="<?php echo esc_url( $visit['device_icon'] ); ?>" alt="" width="18" height="18" loading="lazy" />
									<?php endif; ?>
								</div>
								<div class="gi-matomo-live-visit__body">
									<div class="gi-matomo-live-visit__row">
										<div>
											<strong class="gi-matomo-live-visit__location"><?php echo esc_html( $visit['location'] ?? '' ); ?></strong>
											<span class="gi-matomo-live-visit__browser-name"><?php echo esc_html( $visit['browser'] ?? '' ); ?></span>
											<?php if ( ! empty( $visit['device'] ) ) : ?>
												<span class="gi-matomo-live-visit__device-name"><?php echo esc_html( $visit['device'] ); ?></span>
											<?php endif; ?>
										</div>
										<time class="gi-matomo-live-visit__time"><?php echo esc_html( $visit['time'] ?? '' ); ?></time>
									</div>
									<div class="gi-matomo-live-visit__ids">
										<?php if ( ! empty( $visit['ip'] ) ) : ?>
											<code class="gi-matomo-live-visit__ip" title="<?php esc_attr_e( 'Adresse IP', 'gi-toolkit' ); ?>"><?php echo esc_html( $visit['ip'] ); ?></code>
										<?php endif; ?>
										<?php if ( ! empty( $visit['visitor_id'] ) ) : ?>
											<code class="gi-matomo-live-visit__vid" title="<?php esc_attr_e( 'ID visiteur Matomo', 'gi-toolkit' ); ?>"><?php echo esc_html( $visit['visitor_id'] ); ?></code>
										<?php endif; ?>
									</div>
									<div class="gi-matomo-live-visit__meta">
										<span><?php echo esc_html( $visit['referrer'] ?? '' ); ?></span>
									</div>
									<?php if ( ! empty( $visit['pages'] ) ) : ?>
										<ol class="gi-matomo-live-pages">
											<?php foreach ( $visit['pages'] as $page ) : ?>
												<li class="gi-matomo-live-page">
													<?php if ( ! empty( $page['time'] ) ) : ?>
														<span class="gi-matomo-live-page__time"><?php echo esc_html( $page['time'] ); ?></span>
													<?php endif; ?>
													<span class="gi-matomo-live-page__title" title="<?php echo esc_attr( $page['url'] ?? '' ); ?>">
														<?php echo esc_html( $page['title'] ?? '' ); ?>
													</span>
												</li>
											<?php endforeach; ?>
										</ol>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string               $icon   Classe dashicons.
	 * @param string               $value  Valeur affichée.
	 * @param string               $label  Libellé.
	 * @param array<string, mixed> $trend  Tendance (% vs période précédente).
	 * @param string               $class  Classe CSS additionnelle.
	 * @return void
	 */
	private function render_kpi_card( $icon, $value, $label, array $trend, $class = '' ) {
		$trend_value = $trend['value'] ?? '';
		$trend_class = $trend['class'] ?? '';
		$trend_dir   = $trend['direction'] ?? 'flat';
		?>
		<div class="gi-matomo-kpi <?php echo esc_attr( trim( $class ) ); ?>">
			<div class="gi-matomo-kpi__icon">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			</div>
			<div class="gi-matomo-kpi__body">
				<span class="gi-matomo-kpi__value"><?php echo esc_html( $value ); ?></span>
				<span class="gi-matomo-kpi__label"><?php echo esc_html( $label ); ?></span>
				<?php if ( '' !== $trend_value ) : ?>
					<span class="gi-matomo-trend <?php echo esc_attr( $trend_class ); ?>" data-direction="<?php echo esc_attr( $trend_dir ); ?>">
						<?php echo esc_html( $trend_value ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'vs période précédente', 'gi-toolkit' ); ?></span>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string                                              $title Titre.
	 * @param array<int, array{label:string,value:int,percent?:float,share?:float}> $rows  Lignes.
	 * @param string                                              $icon  Dashicon.
	 * @return void
	 */
	private function render_report_table( $title, array $rows, $icon = '' ) {
		?>
		<div class="gi-matomo-table-panel">
			<h2>
				<?php if ( $icon ) : ?>
					<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				<?php endif; ?>
				<?php echo esc_html( $title ); ?>
			</h2>
			<?php if ( empty( $rows ) ) : ?>
				<p class="description"><?php esc_html_e( 'Aucune donnée pour cette période.', 'gi-toolkit' ); ?></p>
			<?php else : ?>
				<ul class="gi-matomo-bar-list">
					<?php foreach ( $rows as $row ) : ?>
						<li class="gi-matomo-bar-row">
							<span class="gi-matomo-bar-row__label" title="<?php echo esc_attr( $row['label'] ); ?>">
								<?php echo esc_html( $row['label'] ); ?>
							</span>
							<span class="gi-matomo-bar-row__track" aria-hidden="true">
								<span class="gi-matomo-bar-row__fill" style="width: <?php echo esc_attr( (string) ( $row['percent'] ?? 0 ) ); ?>%"></span>
							</span>
							<span class="gi-matomo-bar-row__value">
								<?php echo esc_html( number_format_i18n( (int) $row['value'] ) ); ?>
								<?php if ( isset( $row['share'] ) ) : ?>
									<small><?php echo esc_html( number_format_i18n( $row['share'], 1 ) ); ?>%</small>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
