<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statuts Uptime Kuma par site (vue compacte).
 */
class MainWP_GIWeb_Uptime_Kuma_Widget {

	const WIDGET_ID        = 'mainwp-giweb-uptime-kuma-widget';
	const CRON_HOOK        = 'mainwp_giweb_uptime_kuma_poll';
	const CACHE_OPTION     = 'mainwp_giweb_uptime_kuma_cache';
	const SYNC_LOCK_KEY    = 'mainwp_giweb_uptime_kuma_sync_lock';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_poll' ) );
		add_action( 'wp_ajax_mainwp_giweb_uptime_kuma_refresh', array( __CLASS__, 'ajax_refresh' ) );
	}

	/**
	 * @return void
	 */
	public static function activate_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function deactivate_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function register_cron_schedule() {
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				if ( ! is_array( $schedules ) ) {
					$schedules = array();
				}
				if ( ! isset( $schedules['five_minutes'] ) ) {
					$schedules['five_minutes'] = array(
						'interval' => 5 * MINUTE_IN_SECONDS,
						'display'  => __( 'Toutes les 5 minutes', 'mainwp-giweb' ),
					);
				}
				return $schedules;
			}
		);
	}

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Metaboxes.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes ) {
		return MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID,
			__( 'GI-Toolkit — Uptime Kuma', 'mainwp-giweb' ),
			array( __CLASS__, 'render_metabox' )
		);
	}

	/**
	 * @param array<string, string> $options Options widgets.
	 * @return array<string, string>
	 */
	public static function widgets_screen_options( $options ) {
		return MainWP_GIWeb_Metabox::append_screen_option(
			$options,
			self::WIDGET_ID,
			__( 'GI-Toolkit — Uptime Kuma', 'mainwp-giweb' )
		);
	}

	/**
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );
		if ( ! self::is_overview_screen() ) {
			return;
		}
		wp_enqueue_style(
			'mainwp-giweb-uptime-kuma-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/uptime-kuma-widget.css',
			array(),
			MAINWP_GIWEB_VERSION
		);
		wp_enqueue_script(
			'mainwp-giweb-uptime-kuma-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/uptime-kuma-widget.js',
			array(),
			MAINWP_GIWEB_VERSION,
			true
		);
	}

	/**
	 * @return bool
	 */
	private static function is_overview_screen() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return in_array( $page, array( 'mainwp_tab', 'mainwp-setup' ), true );
	}

	/**
	 * @return void
	 */
	public static function ajax_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'mainwp-giweb' ) ) );
		}
		check_ajax_referer( 'mainwp_giweb_uptime_kuma_refresh', 'nonce' );

		$result = self::refresh_cache( true );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $result['error'] ?? __( 'Synchronisation impossible.', 'mainwp-giweb' ),
				)
			);
		}

		ob_start();
		self::render_metabox_body();
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'       => $html,
				'updated_at' => absint( $result['updated_at'] ?? 0 ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function cron_poll() {
		self::refresh_cache( true );
	}

	/**
	 * @param bool $force Forcer même si cache récent.
	 * @return array{success:bool, error?:string, updated_at?:int}
	 */
	public static function refresh_cache( $force = false ) {
		if ( ! MainWP_GIWeb_Uptime_Kuma::is_configured() ) {
			return array(
				'success' => false,
				'error'   => __( 'Uptime Kuma non configuré.', 'mainwp-giweb' ),
			);
		}

		if ( ! $force ) {
			$cached = self::get_cache();
			$age    = time() - absint( $cached['updated_at'] ?? 0 );
			if ( $age < 5 * MINUTE_IN_SECONDS && ! empty( $cached['sites'] ) ) {
				return array(
					'success'    => true,
					'updated_at' => absint( $cached['updated_at'] ),
				);
			}
		}

		if ( get_transient( self::SYNC_LOCK_KEY ) ) {
			$cached = self::get_cache();
			if ( ! empty( $cached['updated_at'] ) ) {
				return array(
					'success'    => true,
					'updated_at' => absint( $cached['updated_at'] ),
				);
			}
			return array(
				'success' => false,
				'error'   => __( 'Synchronisation déjà en cours.', 'mainwp-giweb' ),
			);
		}

		set_transient( self::SYNC_LOCK_KEY, '1', 3 * MINUTE_IN_SECONDS );

		MainWP_GIWeb_Uptime_Kuma::load_helpers();
		if ( ! class_exists( 'Gi_Toolkit_Uptime_Kuma_API', false ) ) {
			delete_transient( self::SYNC_LOCK_KEY );
			return array(
				'success' => false,
				'error'   => __( 'Helpers GI-Toolkit introuvables.', 'mainwp-giweb' ),
			);
		}

		$creds    = MainWP_GIWeb_Uptime_Kuma::get_credentials();
		$settings = array(
			'kuma_url'           => $creds['kuma_url'],
			'kuma_username'      => $creds['kuma_username'],
			'kuma_password'      => $creds['kuma_password'],
			'disable_ssl_verify' => '0',
		);

		Gi_Toolkit_Uptime_Kuma_API::set_request_timeout( 120 );
		$api  = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		$rows = $api->get_monitors_overview();
		Gi_Toolkit_Uptime_Kuma_API::set_request_timeout( 30 );

		delete_transient( self::SYNC_LOCK_KEY );

		if ( ! is_array( $rows ) ) {
			$error = $api->get_last_error() ?: __( 'Connexion Uptime Kuma impossible.', 'mainwp-giweb' );
			update_option(
				self::CACHE_OPTION,
				array(
					'updated_at' => time(),
					'sites'      => array(),
					'monitors'   => array(),
					'error'      => $error,
				),
				false
			);
			return array(
				'success' => false,
				'error'   => $error,
			);
		}

		$monitor_index = self::index_monitors_by_url( $rows );
		$sites         = self::build_site_rows( $monitor_index );

		update_option(
			self::CACHE_OPTION,
			array(
				'updated_at' => time(),
				'sites'      => $sites,
				'monitors'   => $rows,
				'error'      => '',
			),
			false
		);

		return array(
			'success'    => true,
			'updated_at' => time(),
		);
	}

	/**
	 * @param bool $force Forcer refresh si cache vide.
	 * @return void
	 */
	private static function maybe_refresh_cache( $force = false ) {
		$cached = self::get_cache();
		if ( $force || empty( $cached['updated_at'] ) || empty( $cached['sites'] ) ) {
			self::refresh_cache( true );
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $monitors Monitors Kuma.
	 * @return array<string, array<string, mixed>>
	 */
	private static function index_monitors_by_url( array $monitors ) {
		$index = array();
		foreach ( $monitors as $monitor ) {
			if ( ! is_array( $monitor ) ) {
				continue;
			}
			$url = isset( $monitor['url'] ) ? untrailingslashit( (string) $monitor['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$index[ $url ] = $monitor;
			$index[ trailingslashit( $url ) ] = $monitor;
			$http = str_replace( 'https://', 'http://', $url );
			if ( $http !== $url ) {
				$index[ $http ] = $monitor;
				$index[ trailingslashit( $http ) ] = $monitor;
			}
		}
		return $index;
	}

	/**
	 * @return array<int, array{id:int, label:string, url:string}>
	 */
	private static function get_mainwp_sites() {
		$sites = array();
		if ( ! class_exists( 'MainWP_DB' ) || ! method_exists( 'MainWP_DB', 'instance' ) ) {
			return $sites;
		}
		$db     = MainWP_DB::instance();
		$result = method_exists( $db, 'get_websites_for_current_user' )
			? $db->get_websites_for_current_user()
			: ( method_exists( $db, 'query' ) ? $db->query( MainWP_DB::instance()->get_sql_websites_for_current_user() ) : array() );
		if ( ! is_array( $result ) ) {
			return $sites;
		}
		foreach ( $result as $site ) {
			if ( ! is_object( $site ) ) {
				continue;
			}
			$url = isset( $site->url ) ? untrailingslashit( (string) $site->url ) : '';
			if ( '' === $url ) {
				continue;
			}
			$sites[] = array(
				'id'    => absint( $site->id ?? 0 ),
				'label' => (string) ( $site->name ?? $url ),
				'url'   => $url,
			);
		}
		usort(
			$sites,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);
		return $sites;
	}

	/**
	 * @param array<string, array<string, mixed>> $monitor_index Index URL → monitor.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_site_rows( array $monitor_index ) {
		$rows  = array();
		$sites = self::get_mainwp_sites();

		foreach ( $sites as $site ) {
			$url     = (string) $site['url'];
			$monitor = $monitor_index[ $url ] ?? $monitor_index[ trailingslashit( $url ) ] ?? null;
			$row     = array(
				'site_id'     => (int) $site['id'],
				'label'       => (string) $site['label'],
				'url'         => $url,
				'has_monitor' => is_array( $monitor ),
				'status'      => 'missing',
				'uptime_24h'  => null,
				'uptime_30d'  => null,
				'avg_ping'    => 0,
			);

			if ( is_array( $monitor ) ) {
				$uptime_24 = isset( $monitor['uptime_24h'] ) ? (float) $monitor['uptime_24h'] : null;
				$row['uptime_24h'] = $uptime_24;
				$row['uptime_30d'] = isset( $monitor['uptime_30d'] ) ? (float) $monitor['uptime_30d'] : null;
				$row['avg_ping']   = (int) ( $monitor['avg_ping'] ?? 0 );
				$row['status']     = self::status_from_uptime( $uptime_24, ! empty( $monitor['active'] ) );
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @param float|null $uptime_24h Uptime 24 h (%).
	 * @param bool       $active    Monitor actif.
	 * @return string ok|warn|down|paused|missing
	 */
	private static function status_from_uptime( $uptime_24h, $active = true ) {
		if ( ! $active ) {
			return 'paused';
		}
		if ( null === $uptime_24h ) {
			return 'unknown';
		}
		if ( $uptime_24h >= 99 ) {
			return 'ok';
		}
		if ( $uptime_24h >= 85 ) {
			return 'warn';
		}
		return 'down';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_cache() {
		$cached = get_option( self::CACHE_OPTION, array() );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * @return void
	 */
	public static function render_metabox() {
		echo '<div class="mainwp-giweb-uptime-kuma-widget" id="mainwp-giweb-uptime-kuma-widget-root">';
		if ( ! MainWP_GIWeb_Uptime_Kuma::is_configured() ) {
			echo '<p class="description">' . esc_html__( 'Configurez Uptime Kuma dans GI-Toolkit Manager → Réglages.', 'mainwp-giweb' ) . '</p>';
			echo '</div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$force_refresh = ! empty( $_GET['giweb_uptime_refresh'] );
		self::maybe_refresh_cache( $force_refresh );

		self::render_metabox_body();
		echo '</div>';
	}

	/**
	 * @return void
	 */
	public static function render_metabox_body() {
		$cache      = self::get_cache();
		$sites      = isset( $cache['sites'] ) && is_array( $cache['sites'] ) ? $cache['sites'] : array();
		$updated_at = absint( $cache['updated_at'] ?? 0 );
		$error      = isset( $cache['error'] ) ? (string) $cache['error'] : '';

		$counts = array(
			'ok'      => 0,
			'warn'    => 0,
			'down'    => 0,
			'paused'  => 0,
			'missing' => 0,
			'unknown' => 0,
		);
		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}
			$st = (string) ( $site['status'] ?? 'unknown' );
			if ( ! isset( $counts[ $st ] ) ) {
				$st = 'unknown';
			}
			$counts[ $st ]++;
		}

		$refresh_nonce = wp_create_nonce( 'mainwp_giweb_uptime_kuma_refresh' );
		?>
		<div class="giweb-ukw-toolbar">
			<div class="giweb-ukw-summary" role="status">
				<span class="giweb-ukw-pill giweb-ukw-pill--ok" title="<?php esc_attr_e( 'En ligne', 'mainwp-giweb' ); ?>"><?php echo esc_html( (string) $counts['ok'] ); ?></span>
				<span class="giweb-ukw-pill giweb-ukw-pill--warn" title="<?php esc_attr_e( 'Dégradé', 'mainwp-giweb' ); ?>"><?php echo esc_html( (string) $counts['warn'] ); ?></span>
				<span class="giweb-ukw-pill giweb-ukw-pill--down" title="<?php esc_attr_e( 'Hors ligne', 'mainwp-giweb' ); ?>"><?php echo esc_html( (string) $counts['down'] ); ?></span>
				<?php if ( $counts['missing'] > 0 ) : ?>
					<span class="giweb-ukw-pill giweb-ukw-pill--missing" title="<?php esc_attr_e( 'Sans monitor', 'mainwp-giweb' ); ?>"><?php echo esc_html( (string) $counts['missing'] ); ?></span>
				<?php endif; ?>
				<span class="giweb-ukw-summary__total"><?php echo esc_html( count( $sites ) ); ?> <?php esc_html_e( 'sites', 'mainwp-giweb' ); ?></span>
			</div>
			<div class="giweb-ukw-toolbar__actions">
				<?php if ( $updated_at ) : ?>
					<span class="giweb-ukw-updated description">
						<?php
						printf(
							/* translators: %s: human time diff */
							esc_html__( 'il y a %s', 'mainwp-giweb' ),
							esc_html( human_time_diff( $updated_at, time() ) )
						);
						?>
					</span>
				<?php endif; ?>
				<button type="button" class="button button-small giweb-ukw-refresh" data-nonce="<?php echo esc_attr( $refresh_nonce ); ?>">
					<?php esc_html_e( 'Actualiser', 'mainwp-giweb' ); ?>
				</button>
			</div>
		</div>

		<?php if ( '' !== $error ) : ?>
			<p class="giweb-ukw-error"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $sites ) ) : ?>
			<p class="description giweb-ukw-empty"><?php esc_html_e( 'Aucun site MainWP ou synchronisation en cours. Cliquez sur Actualiser.', 'mainwp-giweb' ); ?></p>
		<?php else : ?>
			<div class="giweb-ukw-grid" role="list">
				<?php foreach ( $sites as $site ) : ?>
					<?php self::render_site_tile( $site ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param array<string, mixed> $site Ligne site.
	 * @return void
	 */
	private static function render_site_tile( array $site ) {
		$status = (string) ( $site['status'] ?? 'unknown' );
		$label  = (string) ( $site['label'] ?? '' );
		$uptime = null !== ( $site['uptime_24h'] ?? null ) ? (float) $site['uptime_24h'] : null;
		$ping   = (int) ( $site['avg_ping'] ?? 0 );

		$status_labels = array(
			'ok'      => __( 'En ligne', 'mainwp-giweb' ),
			'warn'    => __( 'Dégradé', 'mainwp-giweb' ),
			'down'    => __( 'Hors ligne', 'mainwp-giweb' ),
			'paused'  => __( 'Pause', 'mainwp-giweb' ),
			'missing' => __( 'Sans monitor', 'mainwp-giweb' ),
			'unknown' => __( 'Inconnu', 'mainwp-giweb' ),
		);
		$status_text = $status_labels[ $status ] ?? $status_labels['unknown'];
		?>
		<div class="giweb-ukw-tile status-<?php echo esc_attr( $status ); ?>" role="listitem" title="<?php echo esc_attr( $label . ' — ' . $status_text ); ?>">
			<div class="giweb-ukw-tile__head">
				<span class="giweb-ukw-tile__dot" aria-hidden="true"></span>
				<span class="giweb-ukw-tile__name"><?php echo esc_html( $label ); ?></span>
			</div>
			<div class="giweb-ukw-tile__stats">
				<?php if ( null !== $uptime ) : ?>
					<span class="giweb-ukw-tile__uptime"><?php echo esc_html( number_format_i18n( $uptime, 1 ) ); ?>%</span>
				<?php else : ?>
					<span class="giweb-ukw-tile__uptime giweb-ukw-tile__uptime--na">—</span>
				<?php endif; ?>
				<?php if ( $ping > 0 ) : ?>
					<span class="giweb-ukw-tile__ping"><?php echo esc_html( (string) $ping ); ?> ms</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
