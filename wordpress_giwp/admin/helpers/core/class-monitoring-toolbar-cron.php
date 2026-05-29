<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Préchauffage des caches barre admin Matomo / Uptime Kuma (évite les appels API au chargement).
 */
class Gi_Toolkit_Monitoring_Toolbar_Cron {

	const CRON_HOOK = 'gi_toolkit_refresh_monitoring_toolbars';

	/**
	 * @return void
	 */
	public static function bootstrap() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh_all' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules Schedules WP.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_schedule( $schedules ) {
		if ( ! isset( $schedules['gi_toolkit_ten_minutes'] ) ) {
			$schedules['gi_toolkit_ten_minutes'] = array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Toutes les 10 minutes (GI-Toolkit)', 'gi-toolkit' ),
			);
		}
		return $schedules;
	}

	/**
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( ! self::should_run() ) {
			self::unschedule();
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'gi_toolkit_ten_minutes', self::CRON_HOOK );
		}

		if ( self::cache_is_cold() && ! get_transient( 'gi_toolkit_toolbar_warming' ) ) {
			set_transient( 'gi_toolkit_toolbar_warming', 1, MINUTE_IN_SECONDS );
			wp_schedule_single_event( time() + 2, self::CRON_HOOK );
		}
	}

	/**
	 * @return bool
	 */
	private static function cache_is_cold() {
		if ( class_exists( 'Gi_Toolkit_Matomo' ) ) {
			$settings = Gi_Toolkit_Matomo::get_settings_static();
			if ( Gi_Toolkit_Matomo::is_dashboard_ready( $settings ) ) {
				$site_id = absint( $settings['site_id'] ?? 0 );
				if ( $site_id > 0 && false === get_transient( 'gi_matomo_toolbar_v3_' . $site_id ) ) {
					return true;
				}
			}
		}

		if ( class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			if ( Gi_Toolkit_Uptime_Kuma::is_toolbar_ready() ) {
				$monitor_id = absint( Gi_Toolkit_Uptime_Kuma::get_settings_static()['monitor_id'] ?? 0 );
				if ( $monitor_id > 0 && false === get_transient( Gi_Toolkit_Uptime_Kuma_Status_Data::TRANSIENT_TOOLBAR . $monitor_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * @return bool
	 */
	private static function should_run() {
		$modules = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		if ( ! is_array( $modules ) ) {
			return false;
		}

		if ( ! empty( $modules['Gi_Toolkit_Matomo'] ) && '1' === (string) $modules['Gi_Toolkit_Matomo'] ) {
			return true;
		}

		if ( ! empty( $modules['Gi_Toolkit_Uptime_Kuma'] ) && '1' === (string) $modules['Gi_Toolkit_Uptime_Kuma'] ) {
			return true;
		}

		return false;
	}

	/**
	 * @return void
	 */
	public static function refresh_all() {
		if ( class_exists( 'Gi_Toolkit_Matomo' ) ) {
			if ( ! class_exists( 'Gi_Toolkit_Matomo_Dashboard_Data', false ) ) {
				require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
				require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-dashboard-data.php';
			}
			$settings = Gi_Toolkit_Matomo::get_settings_static();
			if ( Gi_Toolkit_Matomo::is_dashboard_ready( $settings ) ) {
				Gi_Toolkit_Matomo_Dashboard_Data::fetch_toolbar_sparkline( $settings, true );
			}
		}

		if ( class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			if ( class_exists( 'Gi_Toolkit_Uptime_Kuma_Status_Data' ) && Gi_Toolkit_Uptime_Kuma::is_toolbar_ready() ) {
				Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_toolbar( Gi_Toolkit_Uptime_Kuma::get_settings_static(), true );
			}
		}
	}
}

Gi_Toolkit_Monitoring_Toolbar_Cron::bootstrap();