<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injection du code de suivi Matomo sur le front.
 */
class Gi_Toolkit_Matomo_Tracking {

	/** @var string Suffixe cache (invalidation après correctif wp_kses). */
	const TRANSIENT_SUFFIX = '_v3_';

	/** @var bool */
	private static $hooks_registered = false;

	/** @var bool */
	private static $printed = false;

	/**
	 * Enregistre les hooks front (appelé depuis init).
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		add_action( 'wp_head', array( __CLASS__, 'output_tracking_code' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'output_tracking_code' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'output_tracking_code' ), 1 );
	}

	/**
	 * @deprecated Utiliser register_hooks().
	 * @return void
	 */
	public static function init() {
		self::register_hooks();
	}

	/**
	 * @param mixed $value Valeur option.
	 * @return bool
	 */
	public static function is_option_enabled( $value ) {
		if ( true === $value || 1 === $value ) {
			return true;
		}
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * @return bool
	 */
	public static function should_track() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( is_preview() || wp_doing_cron() ) {
			return false;
		}

		$settings = self::get_settings();
		if ( ! self::is_option_enabled( $settings['tracking_enabled'] ?? '0' ) ) {
			return false;
		}

		if ( absint( $settings['site_id'] ?? 0 ) < 1 ) {
			return false;
		}

		/**
		 * Autoriser ou non le suivi pour l’utilisateur courant.
		 *
		 * @param bool $track Suivre.
		 */
		return (bool) apply_filters( 'gi_toolkit_matomo_track_for_user', true );
	}

	/**
	 * Diagnostic pour l’admin (pourquoi le suivi est actif ou non).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_tracking_status() {
		$settings = self::get_settings();
		$reasons  = array();
		$checks   = array(
			'module_active'    => self::is_module_active(),
			'tracking_enabled' => self::is_option_enabled( $settings['tracking_enabled'] ?? '0' ),
			'site_id'          => absint( $settings['site_id'] ?? 0 ) > 0,
			'matomo_url'       => '' !== Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' ),
			'hooks_registered' => self::$hooks_registered,
			'front_context'    => ! is_admin() || wp_doing_ajax(),
		);

		if ( ! $checks['module_active'] ) {
			$reasons[] = __( 'Le module Connect Matomo n’est pas activé dans GI-Toolkit.', 'gi-toolkit' );
		}
		if ( ! $checks['tracking_enabled'] ) {
			$reasons[] = __( 'L’injection front est désactivée dans les réglages.', 'gi-toolkit' );
		}
		if ( ! $checks['site_id'] ) {
			$reasons[] = __( 'Aucun ID site Matomo — synchronisez le site.', 'gi-toolkit' );
		}
		if ( ! $checks['matomo_url'] ) {
			$reasons[] = __( 'URL Matomo manquante (requis pour générer le snippet).', 'gi-toolkit' );
		}

		$code   = self::get_tracking_code();
		$checks['has_code'] = '' !== $code;

		if ( ! $checks['has_code'] ) {
			$mode = (string) ( $settings['track_mode'] ?? 'auto' );
			if ( 'manual' === $mode ) {
				$reasons[] = __( 'Mode manuel : code de suivi vide ou invalide.', 'gi-toolkit' );
			} else {
				$reasons[] = __( 'Impossible de générer le snippet automatique.', 'gi-toolkit' );
			}
		}

		return array(
			'ready'   => empty( $reasons ) && $checks['has_code'],
			'checks'  => $checks,
			'reasons' => $reasons,
			'code'    => $code,
			'mode'    => (string) ( $settings['track_mode'] ?? 'auto' ),
		);
	}

	/**
	 * @return bool
	 */
	private static function is_module_active() {
		$modules = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		return is_array( $modules ) && self::is_option_enabled( $modules['Gi_Toolkit_Matomo'] ?? '0' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_settings() {
		self::ensure_dependencies();
		if ( ! class_exists( 'Gi_Toolkit_Matomo' ) ) {
			return array();
		}
		return Gi_Toolkit_Matomo::get_settings_static();
	}

	/**
	 * @return void
	 */
	public static function output_tracking_code() {
		if ( self::$printed || ! self::should_track() ) {
			return;
		}

		$code = self::get_tracking_code();
		if ( '' === $code ) {
			return;
		}

		self::$printed = true;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- script Matomo validé ou généré localement.
		echo "\n" . $code . "\n";
	}

	/**
	 * @return string
	 */
	public static function get_tracking_code() {
		self::ensure_dependencies();

		$settings = self::get_settings();
		$mode     = (string) ( $settings['track_mode'] ?? 'auto' );

		if ( 'manual' === $mode ) {
			$manual = (string) ( $settings['tracking_code'] ?? '' );
			$manual = self::sanitize_tracking_code( $manual );
			/**
			 * Code de suivi Matomo (mode manuel).
			 *
			 * @param string               $manual  Code.
			 * @param array<string, mixed> $settings Réglages.
			 */
			return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $manual, $settings );
		}

		$site_id = absint( $settings['site_id'] ?? 0 );
		$key     = Gi_Toolkit_Matomo_Site::TRANSIENT_TRACKING . self::TRANSIENT_SUFFIX . $site_id;
		$cached  = get_transient( $key );
		if ( is_string( $cached ) && self::is_valid_tracking_output( $cached ) ) {
			return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $cached, $settings );
		}

		if ( false !== $cached ) {
			delete_transient( $key );
		}

		$tag = self::build_tracking_code( $settings );
		if ( ! self::is_valid_tracking_output( $tag ) ) {
			return '';
		}

		set_transient( $key, $tag, DAY_IN_SECONDS );

		return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $tag, $settings );
	}

	/**
	 * @param string $code Code HTML.
	 * @return bool
	 */
	private static function is_valid_tracking_output( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return false;
		}
		return false !== strpos( $code, '_paq' ) || false !== strpos( $code, 'matomo.js' ) || false !== strpos( $code, 'piwik.js' );
	}

	/**
	 * Génère le snippet Matomo standard à partir de l’URL et de l’ID site.
	 *
	 * @param array<string, mixed> $settings Réglages.
	 * @return string
	 */
	public static function build_tracking_code( array $settings ) {
		self::ensure_dependencies();

		$base    = Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' );
		$site_id = absint( $settings['site_id'] ?? 0 );

		if ( '' === $base || $site_id < 1 ) {
			return '';
		}

		$tracker_base = trailingslashit( $base );
		$pixel_url    = $tracker_base . 'matomo.php?idsite=' . $site_id . '&rec=1';

		ob_start();
		?>
<!-- Matomo -->
<script>
var _paq = window._paq = window._paq || [];
_paq.push(['trackPageView']);
_paq.push(['enableLinkTracking']);
(function () {
	var u = <?php echo wp_json_encode( $tracker_base ); ?>;
	_paq.push(['setTrackerUrl', u + 'matomo.php']);
	_paq.push(['setSiteId', <?php echo wp_json_encode( (string) $site_id ); ?>]);
	var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
	g.async = true;
	g.src = u + 'matomo.js';
	s.parentNode.insertBefore(g, s);
})();
</script>
<noscript><p><img referrerpolicy="no-referrer-when-downgrade" src="<?php echo esc_url( $pixel_url ); ?>" style="border:0;" alt="" /></p></noscript>
<!-- End Matomo Code -->
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * @param string $code Code brut.
	 * @return string
	 */
	public static function sanitize_tracking_code( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return '';
		}

		if ( ! self::is_allowed_tracking_code( $code ) ) {
			return '';
		}

		return $code;
	}

	/**
	 * Valide un snippet Matomo sans wp_kses (qui supprime le JS inline).
	 *
	 * @param string $code Code brut.
	 * @return bool
	 */
	public static function is_allowed_tracking_code( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return false;
		}

		if ( ! preg_match( '/_paq|matomo\.js|piwik\.js|matomo\.php|piwik\.php/i', $code ) ) {
			return false;
		}

		if ( preg_match( '/\bon\w+\s*=|javascript\s*:/i', $code ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return void
	 */
	private static function ensure_dependencies() {
		if ( ! class_exists( 'Gi_Toolkit_Matomo_API', false ) ) {
			$path = GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
		if ( ! class_exists( 'Gi_Toolkit_Matomo_Site', false ) ) {
			$path = GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-site.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}
}
