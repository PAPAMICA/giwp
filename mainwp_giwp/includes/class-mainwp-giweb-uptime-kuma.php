<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credentials Uptime Kuma centralisés sur le dashboard MainWP et injection dans les bundles déployés.
 */
class MainWP_GIWeb_Uptime_Kuma {

	/**
	 * @return array{kuma_url:string, kuma_username:string, kuma_password:string}
	 */
	public static function get_credentials() {
		$settings = MainWP_GIWeb_Settings::get();
		return array(
			'kuma_url'      => self::normalize_url( $settings['kuma_url'] ?? '' ),
			'kuma_username' => trim( (string) ( $settings['kuma_username'] ?? '' ) ),
			'kuma_password' => (string) ( $settings['kuma_password'] ?? '' ),
		);
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		return '' !== $creds['kuma_url']
			&& '' !== $creds['kuma_username']
			&& '' !== $creds['kuma_password'];
	}

	/**
	 * @param array<string, mixed> $bundle Bundle GI-Toolkit.
	 * @return array<string, mixed>
	 */
	public static function merge_into_bundle( array $bundle ) {
		if ( ! self::is_configured() ) {
			return $bundle;
		}

		if ( empty( $bundle['modules'] ) || ! is_array( $bundle['modules'] ) ) {
			$bundle['modules'] = array();
		}

		$creds       = self::get_credentials();
		$kuma_module = $bundle['modules']['Gi_Toolkit_Uptime_Kuma'] ?? array();
		if ( ! is_array( $kuma_module ) ) {
			$kuma_module = array();
		}

		$options = isset( $kuma_module['options'] ) && is_array( $kuma_module['options'] ) ? $kuma_module['options'] : array();
		$options['kuma_url']      = $creds['kuma_url'];
		$options['kuma_username'] = $creds['kuma_username'];
		$options['kuma_password'] = $creds['kuma_password'];
		$options['monitor_id']    = 0;
		if ( ! isset( $options['auto_monitor'] ) || '0' !== (string) $options['auto_monitor'] ) {
			$options['auto_monitor'] = '1';
		}

		$kuma_module['options'] = $options;
		$kuma_module['active']  = '1';

		$bundle['modules']['Gi_Toolkit_Uptime_Kuma'] = $kuma_module;

		return $bundle;
	}

	/**
	 * Charge les helpers GI-Toolkit (copie embarquée ou plugin actif).
	 *
	 * @return void
	 */
	public static function load_helpers() {
		$base = defined( 'MAINWP_GIWEB_GI_TOOLKIT_PATH' ) ? MAINWP_GIWEB_GI_TOOLKIT_PATH : '';
		if ( '' === $base || ! is_dir( $base ) ) {
			return;
		}
		require_once $base . 'admin/helpers/core/uptime-kuma/class-socket-client.php';
		require_once $base . 'admin/helpers/core/uptime-kuma/class-monitor-payload.php';
		require_once $base . 'admin/helpers/core/uptime-kuma/class-api.php';
		require_once $base . 'admin/helpers/core/uptime-kuma/class-status-data.php';
	}

	/**
	 * @param string $url URL Kuma.
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$url = esc_url_raw( $url );
		$url = rtrim( $url, '/' );
		$url = preg_replace( '#/dashboard/?$#i', '', $url );
		return untrailingslashit( $url );
	}
}
