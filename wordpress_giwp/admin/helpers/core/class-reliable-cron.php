<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remplace le déclenchement WP-Cron (visite + loopback HTTP) par un runner fiable.
 *
 * L’API WordPress reste identique : wp_schedule_event(), hooks, planning.
 * Seul le spawn (POST non bloquant vers wp-cron.php) est remplacé.
 *
 * @since 2.29.18
 */
class Gi_Toolkit_Reliable_Cron {

	const OPTION_TOKEN = 'gi_toolkit_cron_token';

	const OPTION_HEALTH = 'gi_toolkit_cron_health';

	const QUERY_VAR = 'gi_toolkit_cron';

	/**
	 * @var bool
	 */
	private static $ran = false;

	/**
	 * @return void
	 */
	public static function bootstrap() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_endpoint' ), 0 );
		add_action( 'init', array( __CLASS__, 'replace_default_spawn' ), 1 );
		add_action( 'shutdown', array( __CLASS__, 'maybe_run_on_shutdown' ), 0 );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		if ( defined( 'GI_TOOLKIT_DISABLE_RELIABLE_CRON' ) && GI_TOOLKIT_DISABLE_RELIABLE_CRON ) {
			return false;
		}
		return (bool) apply_filters( 'gi_toolkit_reliable_cron_enabled', true );
	}

	/**
	 * Empêche le spawn HTTP natif (souvent bloqué : SSL, Cloudflare, auth, IPv6).
	 *
	 * @return void
	 */
	public static function replace_default_spawn() {
		if ( ! self::is_enabled() ) {
			return;
		}
		remove_action( 'init', 'wp_cron' );
	}

	/**
	 * URL secrète pour un vrai crontab (`* * * * * curl …`).
	 *
	 * @return void
	 */
	public static function maybe_handle_endpoint() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! self::is_enabled() ) {
			status_header( 404 );
			exit;
		}

		$token = isset( $_GET[ self::QUERY_VAR ] ) ? (string) wp_unslash( $_GET[ self::QUERY_VAR ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! hash_equals( self::get_token(), $token ) ) {
			status_header( 403 );
			exit( 'forbidden' );
		}

		add_action(
			'wp_loaded',
			static function () {
				self::run_due( 'endpoint' );
				nocache_headers();
				header( 'Content-Type: text/plain; charset=UTF-8' );
				echo 'ok';
				exit;
			},
			99
		);
	}

	/**
	 * @return void
	 */
	public static function maybe_run_on_shutdown() {
		if ( ! self::is_enabled() || self::$ran ) {
			return;
		}
		if ( ! self::should_run_on_this_request() ) {
			return;
		}
		if ( ! self::has_due_events() ) {
			return;
		}

		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $disabled && self::overdue_seconds() < 90 ) {
			return;
		}

		self::finish_http_response();
		self::run_due( 'shutdown' );
	}

	/**
	 * Exécute les tâches dues (verrou anti-chevauchement).
	 *
	 * @param string $source shutdown|endpoint|mainwp|manual.
	 * @return bool
	 */
	public static function run_due( $source = 'manual' ) {
		if ( self::$ran ) {
			return false;
		}
		if ( ! function_exists( '_wp_cron' ) ) {
			return false;
		}
		if ( ! self::has_due_events() ) {
			self::touch_health( $source, false );
			return false;
		}
		if ( ! self::acquire_lock() ) {
			return false;
		}

		self::$ran = true;
		if ( in_array( $source, array( 'shutdown', 'endpoint' ), true ) && ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 );
		}

		_wp_cron();
		self::release_lock();
		self::touch_health( $source, true );
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_health() {
		$health = get_option( self::OPTION_HEALTH, array() );
		if ( ! is_array( $health ) ) {
			$health = array();
		}
		$next = self::next_event_timestamp();
		return array(
			'enabled'          => self::is_enabled(),
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'last_run'         => isset( $health['last_run'] ) ? (int) $health['last_run'] : 0,
			'source'           => isset( $health['source'] ) ? (string) $health['source'] : '',
			'did_run'          => ! empty( $health['did_run'] ),
			'next_event'       => $next,
			'overdue'          => self::overdue_seconds(),
			'has_due'          => self::has_due_events(),
		);
	}

	/**
	 * @return string
	 */
	public static function get_token() {
		$token = get_option( self::OPTION_TOKEN, '' );
		if ( ! is_string( $token ) || strlen( $token ) < 32 ) {
			$token = wp_generate_password( 32, false, false );
			update_option( self::OPTION_TOKEN, $token, false );
		}
		return $token;
	}

	/**
	 * @return string
	 */
	public static function endpoint_url() {
		return add_query_arg( self::QUERY_VAR, self::get_token(), home_url( '/' ) );
	}

	/**
	 * @return string
	 */
	public static function crontab_line() {
		return sprintf(
			'* * * * * curl -fsS %s >/dev/null 2>&1',
			escapeshellarg( self::endpoint_url() )
		);
	}

	/**
	 * @return bool
	 */
	private static function should_run_on_this_request() {
		if ( wp_installing() ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( wp_doing_cron() || isset( $_GET['doing_wp_cron'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && self::overdue_seconds() < 120 ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$allow  = ( 'heartbeat' === $action || false !== strpos( $action, 'mainwp' ) );
			if ( ! $allow && self::overdue_seconds() < 120 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public static function has_due_events() {
		$next = self::next_event_timestamp();
		return $next > 0 && $next <= time();
	}

	/**
	 * @return int Timestamp Unix, 0 si vide.
	 */
	public static function next_event_timestamp() {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return 0;
		}
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) || empty( $crons ) ) {
			return 0;
		}
		return (int) min( array_keys( $crons ) );
	}

	/**
	 * @return int
	 */
	public static function overdue_seconds() {
		$next = self::next_event_timestamp();
		if ( $next <= 0 ) {
			return 0;
		}
		$delta = time() - $next;
		return $delta > 0 ? $delta : 0;
	}

	/**
	 * @return bool
	 */
	private static function acquire_lock() {
		$now     = microtime( true );
		$lock    = (float) get_transient( 'doing_cron' );
		$timeout = defined( 'WP_CRON_LOCK_TIMEOUT' ) ? (int) WP_CRON_LOCK_TIMEOUT : 60;
		if ( $lock && ( $lock + $timeout ) > $now && ( $now + 10 ) > $lock ) {
			return false;
		}
		if ( get_transient( 'gi_toolkit_cron_lock' ) ) {
			return false;
		}
		set_transient( 'gi_toolkit_cron_lock', 1, $timeout );
		set_transient( 'doing_cron', sprintf( '%.22F', $now ) );
		return true;
	}

	/**
	 * @return void
	 */
	private static function release_lock() {
		delete_transient( 'gi_toolkit_cron_lock' );
		delete_transient( 'doing_cron' );
	}

	/**
	 * @param string $source Source.
	 * @param bool   $did_run Au moins une tentative d’exécution.
	 * @return void
	 */
	private static function touch_health( $source, $did_run ) {
		update_option(
			self::OPTION_HEALTH,
			array(
				'last_run' => time(),
				'source'   => sanitize_key( $source ),
				'did_run'  => $did_run ? 1 : 0,
			),
			false
		);
	}

	/**
	 * Libère la connexion HTTP avant d’exécuter les tâches.
	 *
	 * @return void
	 */
	private static function finish_http_response() {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			return;
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
	}
}

Gi_Toolkit_Reliable_Cron::bootstrap();
