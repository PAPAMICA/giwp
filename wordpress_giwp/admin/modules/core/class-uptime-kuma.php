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
			'kuma_url'           => '',
			'kuma_username'      => '',
			'kuma_password'      => '',
			'auto_monitor'       => '1',
			'monitor_id'         => 0,
			'disable_ssl_verify' => '0',
		);
	}

	/**
	 * Options exportables dans le bundle JSON (sans secrets).
	 *
	 * @param array<string, mixed> $settings Réglages bruts.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings_for_export( array $settings ) {
		$settings = wp_parse_args( $settings, self::default_settings() );
		$settings['kuma_url']           = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $settings['kuma_url'] ?? '' );
		$settings['kuma_username']      = sanitize_text_field( (string) ( $settings['kuma_username'] ?? '' ) );
		$settings['auto_monitor']       = '1' === (string) ( $settings['auto_monitor'] ?? '1' ) ? '1' : '0';
		$settings['disable_ssl_verify'] = '1' === (string) ( $settings['disable_ssl_verify'] ?? '0' ) ? '1' : '0';
		$settings['monitor_id']         = 0;
		unset( $settings['kuma_password'], $settings['api_token'], $settings['kuma_api_key'] );
		return $settings;
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
		$settings = self::strip_legacy_auth_keys( $settings );
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
		self::clear_dashboard_cache( absint( $settings['monitor_id'] ?? 0 ) );

		return array(
			'success'    => true,
			'monitor_id' => absint( $settings['monitor_id'] ?? 0 ),
			'sync'       => $sync_result,
		);
	}

	/**
	 * @param int $monitor_id ID monitor (0 = tous).
	 * @return void
	 */
	private static function clear_dashboard_cache( $monitor_id ) {
		$monitor_id = absint( $monitor_id );
		if ( $monitor_id < 1 ) {
			return;
		}
		delete_transient( Gi_Toolkit_Uptime_Kuma_Status_Data::TRANSIENT_DASHBOARD . $monitor_id );
		delete_transient( Gi_Toolkit_Uptime_Kuma_Status_Data::TRANSIENT_TOOLBAR . $monitor_id );
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
		$settings['kuma_url'] = isset( $_POST['kuma_url'] )
			? Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( sanitize_text_field( wp_unslash( $_POST['kuma_url'] ) ) )
			: $settings['kuma_url'];
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

		$save = $this->persist_settings( $settings, true );

		$redirect_args = array(
			'page'                 => $this->page_slug,
			'gi_toolkit_pro_saved' => '1',
		);
		if ( ! empty( $save['sync'] ) && empty( $save['sync']['success'] ) ) {
			$redirect_args['gi_uptime_kuma_sync'] = '0';
			$sync_msg = isset( $save['sync']['message'] ) ? (string) $save['sync']['message'] : '';
			if ( '' !== $sync_msg ) {
				$redirect_args['gi_uptime_kuma_sync_msg'] = rawurlencode( $sync_msg );
			}
		} elseif ( ! empty( $save['sync']['success'] ) ) {
			$redirect_args['gi_uptime_kuma_sync'] = '1';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_submenu() {
		$settings = $this->get_settings();
		$version  = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';

		$force_refresh = ! empty( $_GET['gi_uptime_kuma_refresh'] );
		$dashboard     = Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_dashboard( $settings, $force_refresh );

		self::enqueue_dashboard_assets( $dashboard, $version );
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
			<div class="gi-uptime-kuma-page-header">
				<h1><?php echo esc_html( $this->header_title ); ?></h1>
				<?php if ( ! empty( $dashboard['ready'] ) ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'gi_uptime_kuma_refresh', '1' ) ); ?>">
						<?php esc_html_e( 'Actualiser', 'gi-toolkit' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php self::render_dashboard_markup( $dashboard, $settings ); ?>
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Réglages enregistrés.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['gi_uptime_kuma_sync'] ) && '0' === (string) $_GET['gi_uptime_kuma_sync'] ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<?php
						$sync_msg = isset( $_GET['gi_uptime_kuma_sync_msg'] )
							? sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['gi_uptime_kuma_sync_msg'] ) ) )
							: '';
						echo esc_html(
							'' !== $sync_msg
								? sprintf(
									/* translators: %s: error detail */
									__( 'Réglages enregistrés, mais la synchronisation du monitor a échoué : %s', 'gi-toolkit' ),
									$sync_msg
								)
								: __( 'Réglages enregistrés, mais la synchronisation du monitor a échoué.', 'gi-toolkit' )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<div class="gi-uptime-kuma-panel gi-uptime-kuma-panel--settings">
				<h2 class="gi-uptime-kuma-panel__title"><?php esc_html_e( 'Configuration', 'gi-toolkit' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( 'gi_toolkit_uptime_kuma_save' ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1" />
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_slug ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gi_uptime_kuma_url"><?php esc_html_e( 'URL Uptime Kuma', 'gi-toolkit' ); ?></label></th>
						<td>
							<input type="url" class="large-text code" id="gi_uptime_kuma_url" name="kuma_url" value="<?php echo esc_attr( (string) $settings['kuma_url'] ); ?>" placeholder="https://status.example.com" />
							<p class="description"><?php esc_html_e( 'URL racine du serveur Uptime Kuma 2.3.x (sans /dashboard).', 'gi-toolkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Identifiants admin', 'gi-toolkit' ); ?></th>
						<td>
							<p><label for="gi_uptime_kuma_username"><strong><?php esc_html_e( 'Utilisateur', 'gi-toolkit' ); ?></strong></label></p>
							<p><input type="text" class="regular-text" id="gi_uptime_kuma_username" name="kuma_username" value="<?php echo esc_attr( (string) $settings['kuma_username'] ); ?>" autocomplete="username" /></p>
							<p><label for="gi_uptime_kuma_password"><strong><?php esc_html_e( 'Mot de passe', 'gi-toolkit' ); ?></strong></label></p>
							<p><input type="password" class="regular-text" id="gi_uptime_kuma_password" name="kuma_password" value="" autocomplete="current-password" placeholder="<?php esc_attr_e( 'Laisser vide pour conserver', 'gi-toolkit' ); ?>" /></p>
							<p class="description"><?php esc_html_e( 'Compte administrateur Uptime Kuma 2.3.x (Socket.IO). Les clés API « uk… » (Prometheus) ne sont pas utilisées ici.', 'gi-toolkit' ); ?></p>
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
		</div>
		<?php
	}

	/**
	 * Styles + script graphique ping (Chart.js).
	 *
	 * @param array<string, mixed> $dashboard      Données dashboard.
	 * @param string|null          $version      Version assets.
	 * @param string               $chart_canvas ID canvas.
	 * @return void
	 */
	public static function enqueue_dashboard_assets( array $dashboard, $version = null, $chart_canvas = 'gi-uptime-kuma-ping-chart' ) {
		$version = $version ?: ( defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0' );
		wp_enqueue_style(
			'gi-toolkit-uptime-kuma',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/uptime-kuma.css',
			array(),
			$version
		);

		if ( empty( $dashboard['ready'] ) || empty( $dashboard['chart']['data'] ) ) {
			return;
		}

		if ( ! wp_script_is( 'chartjs', 'registered' ) ) {
			wp_register_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
				array(),
				'4.4.1',
				true
			);
		}
		wp_enqueue_script( 'chartjs' );
		wp_enqueue_script(
			'gi-toolkit-uptime-kuma-dashboard',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/uptime-kuma-dashboard.js',
			array( 'chartjs' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-uptime-kuma-dashboard',
			'giToolkitUptimeKumaDashboard',
			array(
				'chart'      => $dashboard['chart'],
				'canvasId'   => $chart_canvas,
				'i18n'       => array(
					'pingLabel' => __( 'Temps de réponse (ms)', 'gi-toolkit' ),
				),
			)
		);
	}

	/**
	 * @param array<string, mixed> $dashboard Données dashboard.
	 * @param array<string, mixed> $settings  Réglages.
	 * @param array<string, mixed> $args      show_section_heading, chart_canvas_id, settings_url.
	 * @return void
	 */
	public static function render_dashboard_markup( array $dashboard, array $settings, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_section_heading' => false,
				'chart_canvas_id'      => 'gi-uptime-kuma-ping-chart',
				'settings_url'         => self::get_settings_admin_url(),
			)
		);

		if ( ! empty( $args['show_section_heading'] ) ) {
			echo '<h2 class="gi-uptime-kuma-section-title">' . esc_html__( 'Disponibilité (Uptime Kuma)', 'gi-toolkit' ) . '</h2>';
		}

		if ( empty( $dashboard['ready'] ) ) {
			$msg = ! empty( $dashboard['message'] ) ? (string) $dashboard['message'] : __( 'Configurez la connexion et synchronisez un monitor pour afficher les statistiques.', 'gi-toolkit' );
			?>
			<div class="gi-uptime-kuma-setup-panel">
				<span class="dashicons dashicons-chart-line gi-uptime-kuma-setup-panel__icon" aria-hidden="true"></span>
				<h2><?php esc_html_e( 'Statistiques Uptime Kuma', 'gi-toolkit' ); ?></h2>
				<p><?php echo esc_html( $msg ); ?></p>
				<?php if ( ! empty( $args['settings_url'] ) ) : ?>
					<p>
						<a class="button button-secondary" href="<?php echo esc_url( (string) $args['settings_url'] ); ?>">
							<?php esc_html_e( 'Configurer Uptime Kuma', 'gi-toolkit' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		$status_level = (string) ( $dashboard['status_level'] ?? 'unknown' );
		$interval     = absint( $dashboard['interval'] ?? 60 );
		$fetched_label = ! empty( $dashboard['fetched_at'] )
			? sprintf(
				/* translators: %s: relative time */
				__( 'Mis à jour il y a %s', 'gi-toolkit' ),
				human_time_diff( (int) $dashboard['fetched_at'], time() )
			)
			: '';
		?>
		<div class="gi-uptime-kuma-dashboard">
			<?php if ( '' !== $fetched_label ) : ?>
				<p class="gi-uptime-kuma-dashboard__meta description"><?php echo esc_html( $fetched_label ); ?></p>
			<?php endif; ?>

			<div class="gi-uptime-kuma-status-panel">
				<div class="gi-uptime-kuma-status-panel__main">
					<div class="gi-uptime-kuma-status-strip" aria-hidden="true">
						<?php foreach ( (array) ( $dashboard['check_bars'] ?? array() ) as $bar ) : ?>
							<span class="gi-uptime-kuma-status-strip__bar level-<?php echo esc_attr( (string) ( $bar['level'] ?? 'unknown' ) ); ?>"></span>
						<?php endforeach; ?>
					</div>
					<div class="gi-uptime-kuma-status-panel__footer">
						<span class="gi-uptime-kuma-status-panel__range">
							<?php
							echo esc_html( (string) ( $dashboard['strip_from'] ?? '' ) );
							if ( ! empty( $dashboard['strip_from'] ) ) {
								echo ' — ';
							}
							echo esc_html( (string) ( $dashboard['strip_to'] ?? __( 'Maintenant', 'gi-toolkit' ) ) );
							?>
						</span>
						<span class="gi-uptime-kuma-status-panel__interval">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: seconds */
									_n(
										'Vérification toutes les %d seconde',
										'Vérification toutes les %d secondes',
										$interval,
										'gi-toolkit'
									),
									$interval
								)
							);
							?>
						</span>
					</div>
				</div>
				<div class="gi-uptime-kuma-status-badge status-<?php echo esc_attr( $status_level ); ?>">
					<?php echo esc_html( (string) ( $dashboard['status_label'] ?? '' ) ); ?>
				</div>
			</div>

			<div class="gi-uptime-kuma-kpis">
				<div class="gi-uptime-kuma-kpi">
					<span class="gi-uptime-kuma-kpi__label"><?php esc_html_e( 'Temps de réponse (actuel)', 'gi-toolkit' ); ?></span>
					<strong class="gi-uptime-kuma-kpi__value">
						<?php
						$ping = (int) ( $dashboard['current_ping'] ?? 0 );
						echo esc_html( $ping > 0 ? $ping . ' ms' : '—' );
						?>
					</strong>
				</div>
				<div class="gi-uptime-kuma-kpi">
					<span class="gi-uptime-kuma-kpi__label"><?php esc_html_e( 'Réponse moyenne (24 h)', 'gi-toolkit' ); ?></span>
					<strong class="gi-uptime-kuma-kpi__value">
						<?php
						$avg = (int) ( $dashboard['avg_ping'] ?? 0 );
						echo esc_html( $avg > 0 ? $avg . ' ms' : '—' );
						?>
					</strong>
				</div>
				<div class="gi-uptime-kuma-kpi gi-uptime-kuma-kpi--uptime">
					<span class="gi-uptime-kuma-kpi__label"><?php esc_html_e( 'Disponibilité (24 h)', 'gi-toolkit' ); ?></span>
					<strong class="gi-uptime-kuma-kpi__value"><?php echo esc_html( (string) ( $dashboard['uptime_percent'] ?? 0 ) ); ?>%</strong>
				</div>
				<div class="gi-uptime-kuma-kpi gi-uptime-kuma-kpi--uptime">
					<span class="gi-uptime-kuma-kpi__label"><?php esc_html_e( 'Disponibilité (30 jours)', 'gi-toolkit' ); ?></span>
					<strong class="gi-uptime-kuma-kpi__value">
						<?php
						echo esc_html(
							null !== ( $dashboard['uptime_30d'] ?? null )
								? (string) $dashboard['uptime_30d'] . '%'
								: '—'
						);
						?>
					</strong>
				</div>
				<div class="gi-uptime-kuma-kpi gi-uptime-kuma-kpi--uptime">
					<span class="gi-uptime-kuma-kpi__label"><?php esc_html_e( 'Disponibilité (1 an)', 'gi-toolkit' ); ?></span>
					<strong class="gi-uptime-kuma-kpi__value">
						<?php
						echo esc_html(
							null !== ( $dashboard['uptime_1y'] ?? null )
								? (string) $dashboard['uptime_1y'] . '%'
								: '—'
						);
						?>
					</strong>
				</div>
				<div class="gi-uptime-kuma-kpi">
					<span class="gi-uptime-kuma-kpi__label"><?php esc_html_e( 'Dernière vérification', 'gi-toolkit' ); ?></span>
					<strong class="gi-uptime-kuma-kpi__value gi-uptime-kuma-kpi__value--text">
						<?php
						echo esc_html(
							! empty( $dashboard['last_check_ago'] )
								? (string) $dashboard['last_check_ago']
								: '—'
						);
						?>
					</strong>
				</div>
			</div>

			<?php if ( ! empty( $dashboard['chart']['data'] ) ) : ?>
				<div class="gi-uptime-kuma-chart-panel">
					<h3><?php esc_html_e( 'Temps de réponse (24 h)', 'gi-toolkit' ); ?></h3>
					<div class="gi-uptime-kuma-chart-panel__canvas-wrap">
						<canvas id="<?php echo esc_attr( (string) $args['chart_canvas_id'] ); ?>" height="120" aria-hidden="true"></canvas>
					</div>
				</div>
			<?php endif; ?>
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
		$this->persist_settings( $settings, false );
		wp_send_json_success(
			array(
				'message' => __( 'Connexion Uptime Kuma OK.', 'gi-toolkit' ),
			)
		);
	}

	public function ajax_sync_monitor() {
		check_ajax_referer( 'gi_toolkit_uptime_kuma', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}
		$settings = $this->get_settings_from_request();
		$api      = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->test_connection() ) {
			wp_send_json_error( array( 'message' => $api->get_last_error() ?: __( 'Connexion impossible.', 'gi-toolkit' ) ) );
		}
		$result = Gi_Toolkit_Uptime_Kuma_Monitor::ensure_monitor_id( $settings, true );
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
			$settings['kuma_url'] = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url(
				sanitize_text_field( wp_unslash( $_POST['kuma_url'] ) )
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['kuma_username'] ) ) {
			$settings['kuma_username'] = sanitize_text_field( wp_unslash( $_POST['kuma_username'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['kuma_password'] ) ) {
			$settings['kuma_password'] = sanitize_text_field( wp_unslash( $_POST['kuma_password'] ) );
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

		$ping_display = (int) ( $data['current_ping'] ?? 0 );
		if ( $ping_display < 1 ) {
			$ping_display = (int) ( $data['avg_ping'] ?? 0 );
		}

		$title = sprintf(
			'<span class="gi-uptime-kuma-ab-wrap">%s<span class="gi-uptime-kuma-ab-ping">%s</span></span>',
			self::render_admin_bar_bars_html( $data['bars'] ?? array() ),
			esc_html( $ping_display > 0 ? $ping_display . ' ms' : '—' )
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'gi-uptime-kuma-toolbar-stats',
				'title' => $title,
				'href'  => self::get_settings_admin_url(),
				'meta'  => array(
					'html'  => true,
					'class' => 'gi-uptime-kuma-ab-menu',
					'title' => self::build_admin_bar_tooltip( $data ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'gi-uptime-kuma-toolbar-stats',
				'id'     => 'gi-uptime-kuma-toolbar-flyout',
				'title'  => self::render_admin_bar_flyout_html( $data ),
				'href'   => self::get_settings_admin_url(),
				'meta'   => array(
					'html'  => true,
					'class' => 'gi-uptime-kuma-ab-flyout-item',
				),
			)
		);
	}

	/**
	 * Bandeau de barres pour la barre admin (rendu serveur).
	 *
	 * @param array<int, array{level?: string}> $bars Barres.
	 * @return string
	 */
	public static function render_admin_bar_bars_html( array $bars ) {
		if ( empty( $bars ) ) {
			return '<span class="gi-uptime-kuma-ab-bars gi-uptime-kuma-ab-bars--empty" aria-hidden="true"></span>';
		}

		$html = '<span class="gi-uptime-kuma-ab-bars" aria-hidden="true">';
		foreach ( $bars as $bar ) {
			if ( ! is_array( $bar ) ) {
				continue;
			}
			$level = sanitize_html_class( (string) ( $bar['level'] ?? 'unknown' ) );
			$html .= sprintf(
				'<span class="gi-uptime-kuma-ab-bar level-%s"></span>',
				esc_attr( $level )
			);
		}
		$html .= '</span>';

		return $html;
	}

	/**
	 * Infobulle native (attribut title) sur l’entrée barre admin.
	 *
	 * @param array<string, mixed> $data Données toolbar.
	 * @return string
	 */
	public static function build_admin_bar_tooltip( array $data ) {
		$parts = array(
			sprintf(
				/* translators: %s: status label */
				__( 'Statut : %s', 'gi-toolkit' ),
				(string) ( $data['status_label'] ?? '' )
			),
			sprintf(
				/* translators: %s: uptime percent */
				__( 'Disponibilité 24 h : %s %%', 'gi-toolkit' ),
				(string) ( $data['uptime_percent'] ?? 0 )
			),
		);

		$current_ping = (int) ( $data['current_ping'] ?? 0 );
		if ( $current_ping > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: milliseconds */
				__( 'Ping actuel : %d ms', 'gi-toolkit' ),
				$current_ping
			);
		}

		$avg_ping = (int) ( $data['avg_ping'] ?? 0 );
		if ( $avg_ping > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: milliseconds */
				__( 'Ping moyen 24 h : %d ms', 'gi-toolkit' ),
				$avg_ping
			);
		}

		if ( ! empty( $data['last_check_ago'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: human time */
				__( 'Dernière vérif. : %s', 'gi-toolkit' ),
				(string) $data['last_check_ago']
			);
		}

		$strip_from = (string) ( $data['strip_from'] ?? '' );
		$strip_to   = (string) ( $data['strip_to'] ?? '' );
		if ( '' !== $strip_from && '' !== $strip_to ) {
			$parts[] = sprintf(
				/* translators: 1: from label, 2: to label */
				__( 'Historique : %1$s → %2$s', 'gi-toolkit' ),
				$strip_from,
				$strip_to
			);
		}

		return implode( "\n", array_filter( $parts ) );
	}

	/**
	 * Panneau détaillé (sous-menu au survol).
	 *
	 * @param array<string, mixed> $data Données toolbar.
	 * @return string
	 */
	public static function render_admin_bar_flyout_html( array $data ) {
		$status_level = sanitize_html_class( (string) ( $data['status_level'] ?? 'unknown' ) );
		$status_label = esc_html( (string) ( $data['status_label'] ?? '' ) );
		$uptime       = esc_html( (string) ( $data['uptime_percent'] ?? 0 ) );
		$current_ping = (int) ( $data['current_ping'] ?? 0 );
		$avg_ping     = (int) ( $data['avg_ping'] ?? 0 );
		$last_check   = esc_html( (string) ( $data['last_check_ago'] ?? '' ) );
		$strip_from   = esc_html( (string) ( $data['strip_from'] ?? '' ) );
		$strip_to     = esc_html( (string) ( $data['strip_to'] ?? __( 'Maintenant', 'gi-toolkit' ) ) );

		ob_start();
		?>
		<div class="gi-uptime-kuma-ab-flyout">
			<p class="gi-uptime-kuma-ab-flyout__status status-<?php echo esc_attr( $status_level ); ?>">
				<?php echo $status_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html above. ?>
			</p>
			<ul class="gi-uptime-kuma-ab-flyout__stats">
				<li>
					<span class="gi-uptime-kuma-ab-flyout__label"><?php esc_html_e( 'Disponibilité 24 h', 'gi-toolkit' ); ?></span>
					<strong><?php echo $uptime; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>%</strong>
				</li>
				<?php if ( $current_ping > 0 ) : ?>
				<li>
					<span class="gi-uptime-kuma-ab-flyout__label"><?php esc_html_e( 'Ping actuel', 'gi-toolkit' ); ?></span>
					<strong><?php echo esc_html( (string) $current_ping ); ?> ms</strong>
				</li>
				<?php endif; ?>
				<?php if ( $avg_ping > 0 ) : ?>
				<li>
					<span class="gi-uptime-kuma-ab-flyout__label"><?php esc_html_e( 'Ping moyen 24 h', 'gi-toolkit' ); ?></span>
					<strong><?php echo esc_html( (string) $avg_ping ); ?> ms</strong>
				</li>
				<?php endif; ?>
				<?php if ( '' !== $last_check ) : ?>
				<li>
					<span class="gi-uptime-kuma-ab-flyout__label"><?php esc_html_e( 'Dernière vérif.', 'gi-toolkit' ); ?></span>
					<strong><?php echo $last_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>
				<?php endif; ?>
			</ul>
			<?php if ( '' !== $strip_from ) : ?>
			<p class="gi-uptime-kuma-ab-flyout__range">
				<?php
				printf(
					/* translators: 1: from label, 2: to label */
					esc_html__( 'Période affichée : %1$s → %2$s', 'gi-toolkit' ),
					$strip_from,
					$strip_to
				);
				?>
			</p>
			<?php endif; ?>
			<p class="gi-uptime-kuma-ab-flyout__link"><?php esc_html_e( 'Voir le tableau de bord →', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function enqueue_admin_bar_assets() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! self::is_toolbar_ready() ) {
			return;
		}
		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		wp_enqueue_style( 'gi-toolkit-uptime-kuma', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/uptime-kuma.css', array(), $version );
	}

	public static function load_deploy_dependencies() {
		if ( ! defined( 'GI_TOOLKIT_PLUGIN_PATH' ) ) {
			return;
		}
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-socket-client.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-monitor-payload.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-api.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-monitor.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-status-data.php';
	}

	public static function prepare_settings_for_remote_deploy( array $settings ) {
		self::load_deploy_dependencies();
		$existing = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
		$settings = self::strip_legacy_auth_keys( $settings );
		$settings['kuma_url']      = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $settings['kuma_url'] ?? '' );
		$settings['kuma_username'] = sanitize_text_field( (string) ( $settings['kuma_username'] ?? '' ) );
		$settings['kuma_password'] = self::sanitize_secret_for_import(
			$settings['kuma_password'] ?? '',
			(string) ( $existing['kuma_password'] ?? '' )
		);
		$settings['monitor_id']   = 0;
		$settings['auto_monitor'] = '1';
		if ( '' === $settings['kuma_username'] && ! empty( $existing['kuma_username'] ) ) {
			$settings['kuma_username'] = sanitize_text_field( (string) $existing['kuma_username'] );
		}
		return $settings;
	}

	/**
	 * @param mixed  $value    Valeur importée.
	 * @param string $fallback Valeur locale si vide ou masquée.
	 * @return string
	 */
	private static function sanitize_secret_for_import( $value, $fallback = '' ) {
		$value = trim( (string) $value );
		if ( '' === $value || preg_match( '/^[•*.\s]+$/u', $value ) ) {
			return (string) $fallback;
		}
		return $value;
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return array<string, mixed>
	 */
	private static function strip_legacy_auth_keys( array $settings ) {
		unset( $settings['api_token'], $settings['kuma_api_key'] );
		return $settings;
	}

	public static function deploy_from_mainwp( array $settings ) {
		self::load_deploy_dependencies();
		$settings = self::prepare_settings_for_remote_deploy( $settings );

		$api = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'URL, utilisateur et mot de passe Uptime Kuma requis pour le déploiement.', 'gi-toolkit' ),
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
		$settings = self::strip_legacy_auth_keys( $settings );
		$settings['kuma_url']   = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $settings['kuma_url'] ?? '' );
		$settings['monitor_id'] = absint( $settings['monitor_id'] ?? 0 );
		update_option( self::OPTION_SETTINGS, $settings, false );
		self::clear_dashboard_cache( absint( $settings['monitor_id'] ?? 0 ) );
		return array(
			'success'    => true,
			'monitor_id' => absint( $settings['monitor_id'] ?? 0 ),
		);
	}
}
