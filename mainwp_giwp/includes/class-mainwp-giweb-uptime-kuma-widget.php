<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statuts Uptime Kuma (rafraîchi toutes les 5 minutes).
 */
class MainWP_GIWeb_Uptime_Kuma_Widget {

	const WIDGET_ID   = 'mainwp-giweb-uptime-kuma-widget';
	const CRON_HOOK   = 'mainwp_giweb_uptime_kuma_poll';
	const CACHE_OPTION = 'mainwp_giweb_uptime_kuma_cache';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_poll' ) );
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
	public static function cron_poll() {
		if ( ! MainWP_GIWeb_Uptime_Kuma::is_configured() ) {
			return;
		}

		MainWP_GIWeb_Uptime_Kuma::load_helpers();
		if ( ! class_exists( 'Gi_Toolkit_Uptime_Kuma_API', false ) ) {
			return;
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
		$rows = $api->get_monitors_dashboard_data();
		Gi_Toolkit_Uptime_Kuma_API::set_request_timeout( 30 );

		if ( ! is_array( $rows ) ) {
			return;
		}

		$site_map = self::build_mainwp_site_url_map();
		foreach ( $rows as &$row ) {
			$url = isset( $row['url'] ) ? untrailingslashit( (string) $row['url'] ) : '';
			$row['mainwp_site_id'] = 0;
			$row['mainwp_label']   = '';
			if ( '' !== $url && isset( $site_map[ $url ] ) ) {
				$row['mainwp_site_id'] = (int) $site_map[ $url ]['id'];
				$row['mainwp_label']   = (string) $site_map[ $url ]['label'];
			}
		}
		unset( $row );

		update_option(
			self::CACHE_OPTION,
			array(
				'updated_at' => time(),
				'monitors'   => $rows,
				'error'      => $api->get_last_error(),
			),
			false
		);
	}

	/**
	 * @return array<string, array{id:int, label:string}>
	 */
	private static function build_mainwp_site_url_map() {
		$map = array();
		if ( ! class_exists( 'MainWP_DB' ) || ! method_exists( 'MainWP_DB', 'instance' ) ) {
			return $map;
		}
		$db    = MainWP_DB::instance();
		$sites = method_exists( $db, 'get_websites_for_current_user' )
			? $db->get_websites_for_current_user()
			: ( method_exists( $db, 'query' ) ? $db->query( MainWP_DB::instance()->get_sql_websites_for_current_user() ) : array() );
		if ( ! is_array( $sites ) ) {
			return $map;
		}
		foreach ( $sites as $site ) {
			if ( ! is_object( $site ) ) {
				continue;
			}
			$url = isset( $site->url ) ? untrailingslashit( (string) $site->url ) : '';
			if ( '' === $url ) {
				continue;
			}
			$map[ $url ] = array(
				'id'    => absint( $site->id ?? 0 ),
				'label' => (string) ( $site->name ?? $url ),
			);
			$map[ trailingslashit( $url ) ] = $map[ $url ];
			$http = str_replace( 'https://', 'http://', $url );
			if ( $http !== $url ) {
				$map[ $http ] = $map[ $url ];
				$map[ trailingslashit( $http ) ] = $map[ $url ];
			}
		}
		return $map;
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
		$cache      = self::get_cache();
		$monitors   = isset( $cache['monitors'] ) && is_array( $cache['monitors'] ) ? $cache['monitors'] : array();
		$updated_at = absint( $cache['updated_at'] ?? 0 );
		$error      = isset( $cache['error'] ) ? (string) $cache['error'] : '';

		echo '<div class="mainwp-giweb-uptime-kuma-widget">';
		if ( ! MainWP_GIWeb_Uptime_Kuma::is_configured() ) {
			echo '<p class="description">' . esc_html__( 'Configurez l’URL et le token JWT Uptime Kuma dans GI-Toolkit Manager > Réglages.', 'mainwp-giweb' ) . '</p>';
			echo '</div>';
			return;
		}

		if ( $updated_at ) {
			echo '<p class="mainwp-giweb-uptime-kuma-updated description">';
			printf(
				/* translators: %s: human time diff */
				esc_html__( 'Dernière mise à jour : il y a %s', 'mainwp-giweb' ),
				esc_html( human_time_diff( $updated_at, (int) current_time( 'timestamp' ) ) )
			);
			echo '</p>';
		} elseif ( '' !== $error ) {
			echo '<p class="description" style="color:#b91c1c;">' . esc_html( $error ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'En attente de la première synchronisation (cron 5 min).', 'mainwp-giweb' ) . '</p>';
		}

		if ( empty( $monitors ) ) {
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped mainwp-giweb-uptime-kuma-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Site / Monitor', 'mainwp-giweb' ) . '</th>';
		echo '<th>' . esc_html__( '24 h', 'mainwp-giweb' ) . '</th>';
		echo '<th>' . esc_html__( 'Uptime', 'mainwp-giweb' ) . '</th>';
		echo '<th>' . esc_html__( 'Ping', 'mainwp-giweb' ) . '</th>';
		echo '</tr></thead><tbody>';

		usort(
			$monitors,
			function ( $a, $b ) {
				$la = (string) ( $a['mainwp_label'] ?? $a['name'] ?? '' );
				$lb = (string) ( $b['mainwp_label'] ?? $b['name'] ?? '' );
				return strcasecmp( $la, $lb );
			}
		);

		foreach ( $monitors as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label  = (string) ( $row['mainwp_label'] ?? $row['name'] ?? '' );
			$url    = (string) ( $row['url'] ?? '' );
			$uptime = (float) ( $row['uptime_percent'] ?? 0 );
			$ping   = (int) ( $row['avg_ping'] ?? 0 );
			$active = ! empty( $row['active'] );
			$level  = $uptime >= 99 ? 'ok' : ( $uptime >= 85 ? 'warn' : 'down' );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $label ) . '</strong>';
			if ( $url ) {
				echo '<br /><span class="description">' . esc_html( $url ) . '</span>';
			}
			echo '<br /><span class="mainwp-giweb-uptime-kuma-badge status-' . esc_attr( $level ) . '">';
			echo $active ? esc_html__( 'Actif', 'mainwp-giweb' ) : esc_html__( 'Inactif', 'mainwp-giweb' );
			echo '</span></td>';
			echo '<td><span class="mainwp-giweb-uptime-kuma-mini-bars" data-bars="' . esc_attr( wp_json_encode( $row['bars'] ?? array() ) ) . '"></span></td>';
			echo '<td>' . esc_html( number_format_i18n( $uptime, 1 ) ) . ' %</td>';
			echo '<td>' . esc_html( $ping > 0 ? $ping . ' ms' : '—' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
