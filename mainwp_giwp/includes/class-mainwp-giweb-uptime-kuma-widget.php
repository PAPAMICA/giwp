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
	const CACHE_SCHEMA     = 2;
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

		$monitor_index   = self::index_monitors_by_url( $rows );
		$mainwp_sites    = self::get_mainwp_sites();
		$sites           = self::build_site_rows( $monitor_index, $mainwp_sites );
		$sync_note       = '';

		if ( empty( $sites ) && ! empty( $rows ) ) {
			$sites     = self::build_rows_from_monitors( $rows );
			$sync_note = empty( $mainwp_sites )
				? __( 'Sites MainWP non détectés — affichage des monitors Kuma.', 'mainwp-giweb' )
				: __( 'Aucune correspondance URL — affichage des monitors Kuma.', 'mainwp-giweb' );
		} elseif ( empty( $sites ) && empty( $mainwp_sites ) ) {
			$sync_note = __( 'Impossible de charger la liste des sites MainWP.', 'mainwp-giweb' );
		} elseif ( empty( $sites ) ) {
			$sync_note = __( 'Aucun monitor HTTP trouvé dans Uptime Kuma.', 'mainwp-giweb' );
		}

		update_option(
			self::CACHE_OPTION,
			array(
				'schema'            => self::CACHE_SCHEMA,
				'updated_at'        => time(),
				'sites'             => $sites,
				'monitors'          => $rows,
				'error'             => '',
				'note'              => $sync_note,
				'mainwp_site_count' => count( $mainwp_sites ),
				'monitor_count'     => count( $rows ),
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
			foreach ( self::url_lookup_keys( $url ) as $key ) {
				$index[ $key ] = $monitor;
			}
		}
		return $index;
	}

	/**
	 * @return array<int, array{id:int, label:string, url:string}>
	 */
	private static function get_mainwp_sites() {
		$sites = array();
		foreach ( MainWP_GIWeb_Sites::fetch_all( self::get_activator() ) as $site ) {
			$row = MainWP_GIWeb_Sites::normalize_one( $site );
			$url = untrailingslashit( (string) ( $row['url'] ?? '' ) );
			if ( $row['id'] < 1 || '' === $url ) {
				continue;
			}
			$sites[] = array(
				'id'    => (int) $row['id'],
				'label' => (string) ( $row['name'] ?: $url ),
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
	 * @return object|null
	 */
	private static function get_activator() {
		global $mainwp_giweb_activator;
		return $mainwp_giweb_activator ?? null;
	}

	/**
	 * Clés d’URL pour rapprocher site MainWP ↔ monitor Kuma.
	 *
	 * @param string $url URL.
	 * @return array<int, string>
	 */
	private static function url_lookup_keys( $url ) {
		$url = untrailingslashit( strtolower( trim( (string) $url ) ) );
		if ( '' === $url ) {
			return array();
		}

		$keys   = array( $url, trailingslashit( $url ) );
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return array_values( array_unique( $keys ) );
		}

		$host  = strtolower( (string) $parsed['host'] );
		$hosts = array( $host );
		if ( str_starts_with( $host, 'www.' ) ) {
			$hosts[] = substr( $host, 4 );
		} else {
			$hosts[] = 'www.' . $host;
		}

		$path = '';
		if ( ! empty( $parsed['path'] ) && '/' !== $parsed['path'] ) {
			$path = rtrim( (string) $parsed['path'], '/' );
		}

		foreach ( $hosts as $host_name ) {
			foreach ( array( 'https', 'http' ) as $scheme ) {
				$base   = $scheme . '://' . $host_name . $path;
				$keys[] = $base;
				$keys[] = trailingslashit( $base );
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $monitor_index Index URL → monitor.
	 * @param string                            $url           URL site.
	 * @return array<string, mixed>|null
	 */
	private static function find_monitor_for_url( array $monitor_index, $url ) {
		foreach ( self::url_lookup_keys( $url ) as $key ) {
			if ( isset( $monitor_index[ $key ] ) && is_array( $monitor_index[ $key ] ) ) {
				return $monitor_index[ $key ];
			}
		}
		return null;
	}

	/**
	 * @param array<string, array<string, mixed>>     $monitor_index Index monitors.
	 * @param array<int, array{id:int,label:string,url:string}> $mainwp_sites Sites MainWP.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_site_rows( array $monitor_index, array $mainwp_sites ) {
		$rows = array();

		foreach ( $mainwp_sites as $site ) {
			$url     = (string) $site['url'];
			$monitor = self::find_monitor_for_url( $monitor_index, $url );
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

		return self::sort_site_rows( $rows );
	}

	/**
	 * Affichage de secours : tous les monitors Kuma (si MainWP indisponible ou sans correspondance).
	 *
	 * @param array<int, array<string, mixed>> $monitors Monitors.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_rows_from_monitors( array $monitors ) {
		$rows = array();
		foreach ( $monitors as $monitor ) {
			if ( ! is_array( $monitor ) ) {
				continue;
			}
			$url = isset( $monitor['url'] ) ? untrailingslashit( (string) $monitor['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$uptime_24 = isset( $monitor['uptime_24h'] ) ? (float) $monitor['uptime_24h'] : null;
			$rows[]    = array(
				'site_id'     => 0,
				'label'       => (string) ( $monitor['name'] ?? $url ),
				'url'         => $url,
				'has_monitor' => true,
				'status'      => self::status_from_uptime( $uptime_24, ! empty( $monitor['active'] ) ),
				'uptime_24h'  => $uptime_24,
				'uptime_30d'  => isset( $monitor['uptime_30d'] ) ? (float) $monitor['uptime_30d'] : null,
				'avg_ping'    => (int) ( $monitor['avg_ping'] ?? 0 ),
			);
		}
		return self::sort_site_rows( $rows );
	}

	/**
	 * Priorité : hors ligne → dégradé → sans monitor → OK.
	 *
	 * @param array<int, array<string, mixed>> $rows Lignes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sort_site_rows( array $rows ) {
		$priority = array(
			'down'    => 0,
			'warn'    => 1,
			'unknown' => 2,
			'paused'  => 3,
			'missing' => 4,
			'ok'      => 5,
		);
		usort(
			$rows,
			static function ( $a, $b ) use ( $priority ) {
				$pa = $priority[ (string) ( $a['status'] ?? 'unknown' ) ] ?? 9;
				$pb = $priority[ (string) ( $b['status'] ?? 'unknown' ) ] ?? 9;
				if ( $pa !== $pb ) {
					return $pa <=> $pb;
				}
				return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			}
		);
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
		if ( ! is_array( $cached ) ) {
			return array();
		}
		if ( absint( $cached['schema'] ?? 0 ) < self::CACHE_SCHEMA ) {
			return array();
		}
		return $cached;
	}

	/**
	 * @return void
	 */
	public static function render_metabox() {
		$is_dark = MainWP_GIWeb_UI::is_dark_theme();
		$classes = 'mainwp-giweb-uptime-kuma-widget' . ( $is_dark ? ' mainwp-giweb-uptime-kuma-widget--dark' : ' mainwp-giweb-uptime-kuma-widget--light' );
		echo '<div class="' . esc_attr( $classes ) . '" id="mainwp-giweb-uptime-kuma-widget-root">';
		if ( ! MainWP_GIWeb_Uptime_Kuma::is_configured() ) {
			echo '<p class="giweb-ukw-hint">' . esc_html__( 'Configurez Uptime Kuma dans GI-Toolkit Manager → Réglages.', 'mainwp-giweb' ) . '</p>';
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
	 * @param array<int, array<string, mixed>> $sites Sites.
	 * @return array<string, mixed>
	 */
	private static function compute_summary( array $sites ) {
		$counts = array(
			'ok'      => 0,
			'warn'    => 0,
			'down'    => 0,
			'paused'  => 0,
			'missing' => 0,
			'unknown' => 0,
		);
		$ping_sum = 0;
		$ping_cnt = 0;
		$strip    = array();

		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}
			$st = (string) ( $site['status'] ?? 'unknown' );
			if ( ! isset( $counts[ $st ] ) ) {
				$st = 'unknown';
			}
			$counts[ $st ]++;
			$strip[] = $st;

			$ping = (int) ( $site['avg_ping'] ?? 0 );
			if ( $ping > 0 ) {
				$ping_sum += $ping;
				$ping_cnt++;
			}
		}

		$total     = count( $sites );
		$monitored = $total - $counts['missing'];
		$healthy   = $counts['ok'];
		$issues    = $counts['warn'] + $counts['down'] + $counts['unknown'] + $counts['paused'];
		$health    = $monitored > 0 ? round( ( $healthy / $monitored ) * 100, 1 ) : ( $total > 0 ? 0 : 100 );

		return array(
			'counts'    => $counts,
			'total'     => $total,
			'monitored' => $monitored,
			'healthy'   => $healthy,
			'issues'    => $issues,
			'health'    => $health,
			'avg_ping'  => $ping_cnt > 0 ? (int) round( $ping_sum / $ping_cnt ) : 0,
			'strip'     => $strip,
		);
	}

	/**
	 * @param int $ping_ms Ping en ms.
	 * @return string fast|medium|slow|none
	 */
	private static function ping_level( $ping_ms ) {
		$ping_ms = (int) $ping_ms;
		if ( $ping_ms < 1 ) {
			return 'none';
		}
		if ( $ping_ms < 300 ) {
			return 'fast';
		}
		if ( $ping_ms < 800 ) {
			return 'medium';
		}
		return 'slow';
	}

	/**
	 * @param float|null $uptime Uptime %.
	 * @return string
	 */
	private static function uptime_bar_level( $uptime ) {
		if ( null === $uptime ) {
			return 'none';
		}
		if ( $uptime >= 99 ) {
			return 'ok';
		}
		if ( $uptime >= 85 ) {
			return 'warn';
		}
		return 'down';
	}

	/**
	 * @param float|null $uptime Uptime.
	 * @return string
	 */
	private static function format_uptime( $uptime ) {
		if ( null === $uptime ) {
			return '—';
		}
		return number_format_i18n( (float) $uptime, 1 ) . ' %';
	}

	/**
	 * @return void
	 */
	public static function render_metabox_body() {
		$cache         = self::get_cache();
		$sites         = isset( $cache['sites'] ) && is_array( $cache['sites'] ) ? $cache['sites'] : array();
		$updated_at    = absint( $cache['updated_at'] ?? 0 );
		$error         = isset( $cache['error'] ) ? (string) $cache['error'] : '';
		$note          = isset( $cache['note'] ) ? (string) $cache['note'] : '';
		$monitor_count = absint( $cache['monitor_count'] ?? 0 );
		$summary       = self::compute_summary( $sites );
		$counts        = $summary['counts'];
		$refresh_nonce = wp_create_nonce( 'mainwp_giweb_uptime_kuma_refresh' );
		$health        = (float) ( $summary['health'] ?? 0 );
		$health_deg    = min( 100, max( 0, $health ) ) * 3.6;
		?>
		<div class="giweb-ukw">
			<header class="giweb-ukw-header">
				<div class="giweb-ukw-header__row">
					<div class="giweb-ukw-brand">
						<span class="giweb-ukw-brand__icon" aria-hidden="true"></span>
						<div>
							<p class="giweb-ukw-brand__title"><?php esc_html_e( 'Uptime Kuma', 'mainwp-giweb' ); ?></p>
							<p class="giweb-ukw-brand__sub">
								<?php
								printf(
									/* translators: 1: monitored count, 2: total sites */
									esc_html__( '%1$d / %2$d sites monitorés', 'mainwp-giweb' ),
									(int) $summary['monitored'],
									(int) $summary['total']
								);
								?>
							</p>
						</div>
					</div>
					<div class="giweb-ukw-header__actions">
						<?php if ( $updated_at ) : ?>
							<time class="giweb-ukw-sync" datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>">
								<?php
								printf(
									/* translators: %s: human time diff */
									esc_html__( 'Sync il y a %s', 'mainwp-giweb' ),
									esc_html( human_time_diff( $updated_at, time() ) )
								);
								?>
							</time>
						<?php endif; ?>
						<button type="button" class="giweb-ukw-btn giweb-ukw-refresh" data-nonce="<?php echo esc_attr( $refresh_nonce ); ?>">
							<?php esc_html_e( 'Actualiser', 'mainwp-giweb' ); ?>
						</button>
					</div>
				</div>

				<?php if ( ! empty( $sites ) ) : ?>
				<div class="giweb-ukw-overview">
					<div class="giweb-ukw-score" style="--giweb-ukw-score-deg: <?php echo esc_attr( (string) $health_deg ); ?>deg;">
						<span class="giweb-ukw-score__value"><?php echo esc_html( number_format_i18n( $health, 1 ) ); ?>%</span>
						<span class="giweb-ukw-score__label"><?php esc_html_e( 'Santé', 'mainwp-giweb' ); ?></span>
					</div>
					<div class="giweb-ukw-overview__main">
						<div class="giweb-ukw-strip" aria-hidden="true" title="<?php esc_attr_e( 'Statut par site', 'mainwp-giweb' ); ?>">
							<?php foreach ( $summary['strip'] as $strip_status ) : ?>
								<span class="giweb-ukw-strip__seg status-<?php echo esc_attr( (string) $strip_status ); ?>"></span>
							<?php endforeach; ?>
						</div>
						<div class="giweb-ukw-stats">
							<div class="giweb-ukw-stat giweb-ukw-stat--ok">
								<strong><?php echo esc_html( (string) $counts['ok'] ); ?></strong>
								<span><?php esc_html_e( 'En ligne', 'mainwp-giweb' ); ?></span>
							</div>
							<div class="giweb-ukw-stat giweb-ukw-stat--warn">
								<strong><?php echo esc_html( (string) ( $counts['warn'] + $counts['down'] ) ); ?></strong>
								<span><?php esc_html_e( 'Alertes', 'mainwp-giweb' ); ?></span>
							</div>
							<div class="giweb-ukw-stat giweb-ukw-stat--missing">
								<strong><?php echo esc_html( (string) $counts['missing'] ); ?></strong>
								<span><?php esc_html_e( 'Sans monitor', 'mainwp-giweb' ); ?></span>
							</div>
							<div class="giweb-ukw-stat giweb-ukw-stat--ping">
								<strong><?php echo esc_html( $summary['avg_ping'] > 0 ? (string) $summary['avg_ping'] . ' ms' : '—' ); ?></strong>
								<span><?php esc_html_e( 'Ping moy.', 'mainwp-giweb' ); ?></span>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</header>

			<?php if ( '' !== $error ) : ?>
				<p class="giweb-ukw-alert giweb-ukw-alert--error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $note ) : ?>
				<p class="giweb-ukw-alert giweb-ukw-alert--note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>

			<?php if ( empty( $sites ) ) : ?>
				<div class="giweb-ukw-empty-state">
					<p>
						<?php
						if ( $monitor_count > 0 ) {
							printf(
								/* translators: %d: monitor count */
								esc_html__( '%d monitors Kuma détectés — vérifiez la correspondance des URL.', 'mainwp-giweb' ),
								$monitor_count
							);
						} else {
							esc_html_e( 'Aucune donnée. Vérifiez la connexion Uptime Kuma puis actualisez.', 'mainwp-giweb' );
						}
						?>
					</p>
				</div>
			<?php else : ?>
				<div class="giweb-ukw-toolbar">
					<label class="giweb-ukw-search">
						<span class="screen-reader-text"><?php esc_html_e( 'Rechercher un site', 'mainwp-giweb' ); ?></span>
						<input type="search" class="giweb-ukw-search__input" placeholder="<?php esc_attr_e( 'Rechercher…', 'mainwp-giweb' ); ?>" autocomplete="off" />
					</label>
					<div class="giweb-ukw-filters" role="tablist">
						<button type="button" class="giweb-ukw-filter is-active" data-filter="all" role="tab"><?php esc_html_e( 'Tous', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $summary['total'] ); ?></em></button>
						<button type="button" class="giweb-ukw-filter" data-filter="ok" role="tab"><?php esc_html_e( 'En ligne', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $counts['ok'] ); ?></em></button>
						<button type="button" class="giweb-ukw-filter" data-filter="issues" role="tab"><?php esc_html_e( 'Alertes', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $summary['issues'] ); ?></em></button>
						<button type="button" class="giweb-ukw-filter" data-filter="missing" role="tab"><?php esc_html_e( 'Sans monitor', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $counts['missing'] ); ?></em></button>
					</div>
				</div>

				<div class="giweb-ukw-grid">
					<?php foreach ( $sites as $site ) : ?>
						<?php self::render_site_card( $site ); ?>
					<?php endforeach; ?>
				</div>
				<p class="giweb-ukw-no-match" hidden><?php esc_html_e( 'Aucun site ne correspond à votre recherche.', 'mainwp-giweb' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $site Ligne site.
	 * @return void
	 */
	private static function render_site_card( array $site ) {
		$status = (string) ( $site['status'] ?? 'unknown' );
		$label  = (string) ( $site['label'] ?? '' );
		$url    = (string) ( $site['url'] ?? '' );
		$uptime24 = null !== ( $site['uptime_24h'] ?? null ) ? (float) $site['uptime_24h'] : null;
		$uptime30 = null !== ( $site['uptime_30d'] ?? null ) ? (float) $site['uptime_30d'] : null;
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
		$filter_group = in_array( $status, array( 'warn', 'down', 'unknown', 'paused' ), true ) ? 'issues' : $status;
		$search_blob = strtolower( $label . ' ' . $url );
		?>
		<article
			class="giweb-ukw-card status-<?php echo esc_attr( $status ); ?>"
			data-status="<?php echo esc_attr( $filter_group ); ?>"
			data-search="<?php echo esc_attr( $search_blob ); ?>"
		>
			<header class="giweb-ukw-card__head">
				<span class="giweb-ukw-card__badge"><?php echo esc_html( $status_text ); ?></span>
				<?php if ( $ping > 0 ) : ?>
					<span class="giweb-ukw-card__ping ping-<?php echo esc_attr( self::ping_level( $ping ) ); ?>"><?php echo esc_html( (string) $ping ); ?> ms</span>
				<?php endif; ?>
			</header>

			<h3 class="giweb-ukw-card__title"><?php echo esc_html( $label ); ?></h3>

			<?php if ( 'missing' === $status ) : ?>
				<p class="giweb-ukw-card__hint"><?php esc_html_e( 'Aucun monitor Kuma associé à cette URL.', 'mainwp-giweb' ); ?></p>
			<?php else : ?>
				<div class="giweb-ukw-card__metrics">
					<?php self::render_metric_bar( __( '24 h', 'mainwp-giweb' ), $uptime24 ); ?>
					<?php self::render_metric_bar( __( '30 j', 'mainwp-giweb' ), $uptime30 ); ?>
				</div>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * @param string     $label  Libellé période.
	 * @param float|null $uptime Valeur %.
	 * @return void
	 */
	private static function render_metric_bar( $label, $uptime ) {
		$level = self::uptime_bar_level( $uptime );
		$width = null !== $uptime ? min( 100, max( 0, (float) $uptime ) ) : 0;
		?>
		<div class="giweb-ukw-metric">
			<div class="giweb-ukw-metric__head">
				<span class="giweb-ukw-metric__label"><?php echo esc_html( $label ); ?></span>
				<span class="giweb-ukw-metric__value"><?php echo esc_html( self::format_uptime( $uptime ) ); ?></span>
			</div>
			<div class="giweb-ukw-metric__track">
				<span class="giweb-ukw-metric__fill level-<?php echo esc_attr( $level ); ?>" style="width: <?php echo esc_attr( null !== $uptime ? (string) $width : '0' ); ?>%;"></span>
			</div>
		</div>
		<?php
	}
}
