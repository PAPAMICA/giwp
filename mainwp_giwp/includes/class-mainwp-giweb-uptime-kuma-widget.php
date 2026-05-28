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
	 * @return void
	 */
	public static function render_metabox_body() {
		$cache         = self::get_cache();
		$sites         = isset( $cache['sites'] ) && is_array( $cache['sites'] ) ? $cache['sites'] : array();
		$updated_at    = absint( $cache['updated_at'] ?? 0 );
		$error         = isset( $cache['error'] ) ? (string) $cache['error'] : '';
		$note          = isset( $cache['note'] ) ? (string) $cache['note'] : '';
		$monitor_count = absint( $cache['monitor_count'] ?? 0 );

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
			<p class="giweb-ukw-error" role="alert"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $note ) : ?>
			<p class="giweb-ukw-note"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $sites ) ) : ?>
			<p class="giweb-ukw-empty">
				<?php
				if ( $monitor_count > 0 ) {
					printf(
						/* translators: %d: monitor count */
						esc_html__( '%d monitors Kuma chargés mais aucune ligne à afficher. Vérifiez les URL des monitors.', 'mainwp-giweb' ),
						$monitor_count
					);
				} else {
					esc_html_e( 'Aucune donnée. Vérifiez la connexion Uptime Kuma puis cliquez sur Actualiser.', 'mainwp-giweb' );
				}
				?>
			</p>
		<?php else : ?>
			<div class="giweb-ukw-table-wrap">
				<table class="giweb-ukw-table">
					<thead>
						<tr>
							<th scope="col" class="giweb-ukw-col-status"><span class="screen-reader-text"><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></span></th>
							<th scope="col" class="giweb-ukw-col-site"><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
							<th scope="col" class="giweb-ukw-col-uptime"><?php esc_html_e( '24 h', 'mainwp-giweb' ); ?></th>
							<th scope="col" class="giweb-ukw-col-ping"><?php esc_html_e( 'Ping', 'mainwp-giweb' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sites as $site ) : ?>
							<?php self::render_site_row( $site ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param array<string, mixed> $site Ligne site.
	 * @return void
	 */
	private static function render_site_row( array $site ) {
		$status = (string) ( $site['status'] ?? 'unknown' );
		$label  = (string) ( $site['label'] ?? '' );
		$url    = (string) ( $site['url'] ?? '' );
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
		$title       = $label . ' — ' . $status_text;
		if ( '' !== $url ) {
			$title .= "\n" . $url;
		}
		?>
		<tr class="giweb-ukw-row status-<?php echo esc_attr( $status ); ?>" title="<?php echo esc_attr( $title ); ?>">
			<td class="giweb-ukw-col-status">
				<span class="giweb-ukw-row__dot" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php echo esc_html( $status_text ); ?></span>
			</td>
			<td class="giweb-ukw-col-site">
				<span class="giweb-ukw-row__name"><?php echo esc_html( $label ); ?></span>
			</td>
			<td class="giweb-ukw-col-uptime">
				<?php if ( null !== $uptime ) : ?>
					<strong><?php echo esc_html( number_format_i18n( $uptime, 1 ) ); ?>%</strong>
				<?php else : ?>
					<span class="giweb-ukw-na">—</span>
				<?php endif; ?>
			</td>
			<td class="giweb-ukw-col-ping">
				<?php if ( $ping > 0 ) : ?>
					<?php echo esc_html( (string) $ping ); ?> <span class="giweb-ukw-unit">ms</span>
				<?php else : ?>
					<span class="giweb-ukw-na">—</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
