<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : Connect Uptime Kuma.
 */
class Gi_Toolkit_Uptime_Kuma {

	const OPTION_SETTINGS = 'gi_toolkit_uptime_kuma_settings';

	const SETTINGS_PAGE_SLUG = 'gi-toolkit-settings-uptime-kuma';

	/** @var string */
	private $page_slug;

	/** @var string */
	private $header_title = '';

	/** @var self|null */
	private static $instance = null;

	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		$this->page_slug    = self::SETTINGS_PAGE_SLUG;
		$this->header_title = __( 'Connect Uptime Kuma', 'gi-toolkit' );

		self::load_deploy_dependencies();

		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );

		add_action( 'wp_ajax_gi_toolkit_uptime_kuma_test', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_gi_toolkit_uptime_kuma_sync', array( $this, 'ajax_sync_monitor' ) );

		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_stats' ), 101 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings_static() {
		if ( self::$instance ) {
			return self::$instance->get_settings();
		}
		$stored = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_settings() {
		return array(
			'kuma_url'            => '',
			'api_token'           => '',
			'kuma_username'       => '',
			'kuma_password'       => '',
			'auto_monitor'        => '1',
			'monitor_id'          => 0,
			'disable_ssl_verify'  => '0',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $sync_monitor Synchroniser monitor.
	 * @return array{success:bool, monitor_id?:int, sync?:array<string,mixed>}
	 */
	public function save_settings( array $settings, $sync_monitor = true ) {
		$result = $this->persist_settings( $settings, $sync_monitor );
		if ( ! empty( $result['success'] ) ) {
			$monitor_id = absint( $result['monitor_id'] ?? 0 );
			if ( $monitor_id > 0 ) {
				delete_transient( Gi_Toolkit_Uptime_Kuma_Status_Data::TRANSIENT_TOOLBAR . $monitor_id );
			}
		}
		return ! empty( $result['success'] );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $sync_monitor Sync monitor.
	 * @return array{success:bool, monitor_id?:int, sync?:array<string,mixed>}
	 */
	private function persist_settings( array $settings, $sync_monitor = true ) {
		$settings['kuma_url']   = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $settings['kuma_url'] ?? '' );
		$settings['monitor_id'] = absint( $settings['monitor_id'] ?? 0 );

		$sync_result = null;
		if ( $sync_monitor && '1' === (string) ( $settings['auto_monitor'] ?? '1' ) ) {
			$sync_result = Gi_Toolkit_Uptime_Kuma_Monitor::ensure_monitor_id( $settings, true );
			if ( ! empty( $sync_result['success'] ) && ! empty( $sync_result['monitor_id'] ) ) {
				$settings['monitor_id'] = (int) $sync_result['monitor_id'];
			}
		}

		update_option( self::OPTION_SETTINGS, $settings, false );

		return array(
			'success'    => true,
			'monitor_id' => absint( $settings['monitor_id'] ?? 0 ),
			'sync'       => $sync_result,
		);
	}

	/**
	 * @return bool
	 */
	public static function is_toolbar_ready( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_settings_static();
		}
		$api = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		return $api->is_configured() && absint( $settings['monitor_id'] ?? 0 ) > 0;
	}

	public static function get_settings_admin_url() {
		return admin_url( 'admin.php?page=' . rawurlencode( self::SETTINGS_PAGE_SLUG ) );
	}

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

	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, 'gi_toolkit_uptime_kuma_save' ) ) {
			return;
		}

		$settings = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['kuma_url'] = isset( $_POST['kuma_url'] ) ? sanitize_text_field( wp_unslash( $_POST['kuma_url'] ) ) : $settings['kuma_url'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['api_token'] ) ) {
			$settings['api_token'] = sanitize_text_field( wp_unslash( $_POST['api_token'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['kuma_username'] = isset( $_POST['kuma_username'] ) ? sanitize_text_field( wp_unslash( $_POST['kuma_username'] ) ) : $settings['kuma_username'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['kuma_password'] ) ) {
			$settings['kuma_password'] = sanitize_text_field( wp_unslash( $_POST['kuma_password'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['auto_monitor'] = isset( $_POST['auto_monitor'] ) ? '1' : '0';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['monitor_id'] = isset( $_POST['monitor_id'] ) ? absint( wp_unslash( $_POST['monitor_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['disable_ssl_verify'] = isset( $_POST['disable_ssl_verify'] ) ? '1' : '0';

		$this->persist_settings( $settings, true );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		$settings = $this->get_settings();
		$version  = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		wp_enqueue_style( 'gi-toolkit-uptime-kuma', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/uptime-kuma.css', array(), $version );
		wp_enqueue_script(
			'gi-toolkit-uptime-kuma-settings',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/uptime-kuma-settings.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-uptime-kuma-settings',
			'giToolkitUptimeKumaSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gi_toolkit_uptime_kuma' ),
				'i18n'    => array(
					'testing' => __( 'Test en cours…', 'gi-toolkit' ),
					'syncing' => __( 'Synchronisation…', 'gi-toolkit' ),
				),
			)
		);
		?>
		<div class="wrap gi-toolkit-uptime-kuma-settings">
			<h1><?php echo esc_html( $this->header_title ); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'gi_toolkit_uptime_kuma_save', 'gi_toolkit_pro_save' ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_slug ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gi_uptime_kuma_url"><?php esc_html_e( 'URL Uptime Kuma', 'gi-toolkit' ); ?></label></th>
						<td><input type="url" class="large-text code" id="gi_uptime_kuma_url" name="kuma_url" value="<?php echo esc_attr( (string) $settings['kuma_url'] ); ?>" placeholder="https://status.example.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gi_uptime_kuma_token"><?php esc_html_e( 'Token JWT', 'gi-toolkit' ); ?></label></th>
						<td>
							<input type="password" class="large-text code" id="gi_uptime_kuma_token" name="api_token" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['api_token'] ? '••••••••' : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Token JWT obtenu après connexion à Uptime Kuma (événement login). Laissez vide pour conserver le token actuel.', 'gi-toolkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ou identifiants', 'gi-toolkit' ); ?></th>
						<td>
							<p><input type="text" class="regular-text" name="kuma_username" value="<?php echo esc_attr( (string) $settings['kuma_username'] ); ?>" placeholder="<?php esc_attr_e( 'Utilisateur', 'gi-toolkit' ); ?>" /></p>
							<p><input type="password" class="regular-text" name="kuma_password" value="" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Mot de passe (laisser vide pour conserver)', 'gi-toolkit' ); ?>" /></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Monitor', 'gi-toolkit' ); ?></th>
						<td>
							<label><input type="checkbox" name="auto_monitor" value="1" <?php checked( '1', $settings['auto_monitor'] ?? '1' ); ?> /> <?php esc_html_e( 'Créer / associer automatiquement un monitor pour cette URL WordPress', 'gi-toolkit' ); ?></label>
							<p><label for="gi_uptime_kuma_monitor_id"><?php esc_html_e( 'ID monitor (optionnel)', 'gi-toolkit' ); ?></label>
							<input type="number" min="0" class="small-text" id="gi_uptime_kuma_monitor_id" name="monitor_id" value="<?php echo esc_attr( (string) (int) $settings['monitor_id'] ); ?>" /></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'SSL', 'gi-toolkit' ); ?></th>
						<td><label><input type="checkbox" name="disable_ssl_verify" value="1" <?php checked( '1', $settings['disable_ssl_verify'] ?? '0' ); ?> /> <?php esc_html_e( 'Désactiver la vérification SSL', 'gi-toolkit' ); ?></label></td>
					</tr>
				</table>
				<p>
					<button type="button" class="button" id="gi-uptime-kuma-test"><?php esc_html_e( 'Tester la connexion', 'gi-toolkit' ); ?></button>
					<button type="button" class="button" id="gi-uptime-kuma-sync"><?php esc_html_e( 'Synchroniser le monitor', 'gi-toolkit' ); ?></button>
					<?php submit_button( __( 'Enregistrer', 'gi-toolkit' ), 'primary', 'submit', false ); ?>
				</p>
				<div id="gi-uptime-kuma-notice" class="notice" style="display:none;"></div>
			</form>
		</div>
		<?php
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'gi_toolkit_uptime_kuma', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}
		$settings = $this->get_settings_from_request();
		$api      = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->test_connection() ) {
			wp_send_json_error( array( 'message' => $api->get_last_error() ?: __( 'Échec de connexion.', 'gi-toolkit' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Connexion Uptime Kuma OK.', 'gi-toolkit' ) ) );
	}

	public function ajax_sync_monitor() {
		check_ajax_referer( 'gi_toolkit_uptime_kuma', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}
		$settings = $this->get_settings_from_request();
		$result   = Gi_Toolkit_Uptime_Kuma_Monitor::ensure_monitor_id( $settings, true );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? '' ) );
		}
		$settings['monitor_id'] = (int) $result['monitor_id'];
		$this->persist_settings( $settings, false );
		wp_send_json_success(
			array(
				'monitor_id' => (int) $result['monitor_id'],
				'message'    => ! empty( $result['created'] )
					? __( 'Monitor créé dans Uptime Kuma.', 'gi-toolkit' )
					: __( 'Monitor associé.', 'gi-toolkit' ),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_settings_from_request() {
		$settings = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['kuma_url'] ) ) {
			$settings['kuma_url'] = sanitize_text_field( wp_unslash( $_POST['kuma_url'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['api_token'] ) ) {
			$settings['api_token'] = sanitize_text_field( wp_unslash( $_POST['api_token'] ) );
		}
		return $settings;
	}

	public function register_admin_bar_stats( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! self::is_toolbar_ready() ) {
			return;
		}
		$data = Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_toolbar( self::get_settings_static() );
		if ( empty( $data['ready'] ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'gi-uptime-kuma-toolbar-stats',
				'title' => '<span class="gi-uptime-kuma-ab-wrap"><span class="gi-uptime-kuma-ab-bars" id="gi-uptime-kuma-ab-bars"></span><span class="gi-uptime-kuma-ab-ping">' . esc_html( (int) ( $data['avg_ping'] ?? 0 ) ) . ' ms</span></span>',
				'href'  => self::get_settings_admin_url(),
				'meta'  => array(
					'title' => sprintf(
						/* translators: %s: uptime percent */
						__( 'Disponibilité 24 h : %s %% — cliquer pour les réglages', 'gi-toolkit' ),
						(string) ( $data['uptime_percent'] ?? 0 )
					),
				),
			)
		);
	}

	public function enqueue_admin_bar_assets() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! self::is_toolbar_ready() ) {
			return;
		}
		$data    = Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_toolbar( self::get_settings_static() );
		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		wp_enqueue_style( 'gi-toolkit-uptime-kuma', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/uptime-kuma.css', array(), $version );
		wp_enqueue_script(
			'gi-toolkit-uptime-kuma-admin-bar',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/uptime-kuma-admin-bar.js',
			array(),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-uptime-kuma-admin-bar',
			'giToolkitUptimeKumaAdminBar',
			array(
				'bars' => $data['bars'] ?? array(),
			)
		);
	}

	public static function load_deploy_dependencies() {
		if ( ! defined( 'GI_TOOLKIT_PLUGIN_PATH' ) ) {
			return;
		}
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-socket-client.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-api.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-monitor.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-status-data.php';
	}

	public static function prepare_settings_for_remote_deploy( array $settings ) {
		self::load_deploy_dependencies();
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
		$settings['kuma_url']    = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $settings['kuma_url'] ?? '' );
		$settings['api_token']   = self::sanitize_token_for_import( $settings['api_token'] ?? '' );
		$settings['monitor_id']  = 0;
		$settings['auto_monitor'] = '1';
		return $settings;
	}

	/**
	 * @param mixed $token Token.
	 * @return string
	 */
	private static function sanitize_token_for_import( $token ) {
		$token = trim( (string) $token );
		if ( '' === $token || preg_match( '/^[•*.\s]+$/u', $token ) ) {
			return '';
		}
		return $token;
	}

	public static function deploy_from_mainwp( array $settings ) {
		self::load_deploy_dependencies();
		$settings = self::prepare_settings_for_remote_deploy( $settings );

		$api = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'URL Uptime Kuma et token JWT (ou identifiants) requis pour le déploiement.', 'gi-toolkit' ),
			);
		}

		$settings['monitor_id'] = 0;
		$persist                = self::persist_settings_static( $settings, false );
		if ( empty( $persist['success'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Échec de l’enregistrement des réglages Uptime Kuma.', 'gi-toolkit' ),
			);
		}

		if ( '1' !== (string) ( $settings['auto_monitor'] ?? '1' ) ) {
			return array(
				'success'    => true,
				'monitor_id' => 0,
				'message'    => __( 'Réglages Uptime Kuma enregistrés (sans synchronisation automatique).', 'gi-toolkit' ),
			);
		}

		Gi_Toolkit_Uptime_Kuma_API::set_request_timeout( 12 );
		$sync = Gi_Toolkit_Uptime_Kuma_Monitor::ensure_monitor_id( $settings, true );
		Gi_Toolkit_Uptime_Kuma_API::set_request_timeout( 30 );

		if ( ! empty( $sync['success'] ) && ! empty( $sync['monitor_id'] ) ) {
			$settings['monitor_id'] = (int) $sync['monitor_id'];
			self::persist_settings_static( $settings, false );
			return array(
				'success'    => true,
				'monitor_id' => (int) $settings['monitor_id'],
				'message'    => ! empty( $sync['created'] )
					? __( 'Uptime Kuma configuré — monitor créé.', 'gi-toolkit' )
					: __( 'Uptime Kuma configuré — monitor associé.', 'gi-toolkit' ),
				'sync'       => $sync,
			);
		}

		$sync_msg = $sync['message'] ?? __( 'Synchronisation monitor impossible.', 'gi-toolkit' );
		return array(
			'success'    => true,
			'warning'    => $sync_msg,
			'monitor_id' => 0,
			'message'    => sprintf(
				/* translators: %s: detail */
				__( 'Réglages enregistrés. Sync reportée : %s', 'gi-toolkit' ),
				$sync_msg
			),
			'sync'       => $sync,
		);
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $sync_monitor Sync.
	 * @return array{success:bool, monitor_id?:int}
	 */
	public static function persist_settings_static( array $settings, $sync_monitor = true ) {
		$settings['kuma_url']   = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $settings['kuma_url'] ?? '' );
		$settings['monitor_id'] = absint( $settings['monitor_id'] ?? 0 );
		update_option( self::OPTION_SETTINGS, $settings, false );
		return array(
			'success'    => true,
			'monitor_id' => absint( $settings['monitor_id'] ?? 0 ),
		);
	}
}
