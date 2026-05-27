<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injection du code de suivi Matomo sur le front.
 */
class Gi_Toolkit_Matomo_Tracking {

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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- script Matomo fourni par l’API ou saisi par l’admin.
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
		$key     = Gi_Toolkit_Matomo_Site::TRANSIENT_TRACKING . '_' . $site_id;
		$cached  = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $cached, $settings );
		}

		$api = new Gi_Toolkit_Matomo_API( $settings );
		$tag = $api->request(
			'SitesManager.getJavascriptTag',
			array(
				'idSite' => $site_id,
			)
		);

		if ( ! is_string( $tag ) || '' === $tag ) {
			return '';
		}

		$tag = self::sanitize_tracking_code( $tag );
		set_transient( $key, $tag, DAY_IN_SECONDS );

		return (string) apply_filters( 'gi_toolkit_matomo_tracking_code', $tag, $settings );
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
		// Autoriser script, noscript, commentaires Matomo.
		$allowed = array(
			'script'   => array(
				'type'    => true,
				'src'     => true,
				'async'   => true,
				'defer'   => true,
				'charset' => true,
			),
			'noscript' => array(),
			'img'      => array(
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
				'style'  => true,
			),
			'p'        => array(),
		);
		return wp_kses( $code, $allowed );
	}
}
