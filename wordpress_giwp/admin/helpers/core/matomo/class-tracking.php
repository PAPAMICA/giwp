<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injection du code de suivi Matomo sur le front.
 */
class Gi_Toolkit_Matomo_Tracking {

	/** @var string Suffixe cache (invalidation après correctif wp_kses). */
	const TRANSIENT_SUFFIX = '_v2_';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_tracking_code' ), 99 );
	}

	/**
	 * @return bool
	 */
	public static function should_track() {
		if ( is_admin() || is_preview() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		$settings = self::get_settings();
		if ( '1' !== (string) ( $settings['tracking_enabled'] ?? '0' ) ) {
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
	 * @return array<string, mixed>
	 */
	private static function get_settings() {
		if ( ! class_exists( 'Gi_Toolkit_Matomo' ) ) {
			return array();
		}
		return Gi_Toolkit_Matomo::get_settings_static();
	}

	/**
	 * @return void
	 */
	public static function output_tracking_code() {
		if ( ! self::should_track() ) {
			return;
		}

		$code = self::get_tracking_code();
		if ( '' === $code ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- script Matomo validé ou généré localement.
		echo "\n" . $code . "\n";
	}

	/**
	 * @return string
	 */
	public static function get_tracking_code() {
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
		if ( is_string( $cached ) && '' !== $cached ) {
			return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $cached, $settings );
		}

		$tag = self::build_tracking_code( $settings );
		if ( '' === $tag ) {
			return '';
		}

		set_transient( $key, $tag, DAY_IN_SECONDS );

		return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $tag, $settings );
	}

	/**
	 * Génère le snippet Matomo standard à partir de l’URL et de l’ID site.
	 *
	 * @param array<string, mixed> $settings Réglages.
	 * @return string
	 */
	public static function build_tracking_code( array $settings ) {
		self::ensure_api_loaded();

		$base    = Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' );
		$site_id = absint( $settings['site_id'] ?? 0 );

		if ( '' === $base || $site_id < 1 ) {
			return '';
		}

		$tracker_base = trailingslashit( $base );
		$pixel_url    = esc_url( $tracker_base . 'matomo.php?idsite=' . $site_id . '&rec=1' );

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
	private static function ensure_api_loaded() {
		if ( class_exists( 'Gi_Toolkit_Matomo_API', false ) ) {
			return;
		}
		$path = GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
