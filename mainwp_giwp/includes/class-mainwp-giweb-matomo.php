<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credentials Matomo centralisés sur le dashboard MainWP et injection dans les bundles déployés.
 */
class MainWP_GIWeb_Matomo {

	/**
	 * @return array{matomo_url:string, api_token:string}
	 */
	public static function get_credentials() {
		$settings = MainWP_GIWeb_Settings::get();
		return array(
			'matomo_url' => self::normalize_url( $settings['matomo_url'] ?? '' ),
			'api_token'  => trim( (string) ( $settings['matomo_api_token'] ?? '' ) ),
		);
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		return '' !== $creds['matomo_url'] && '' !== $creds['api_token'];
	}

	/**
	 * Fusionne URL + token API Matomo dans le bundle avant déploiement vers les sites enfants.
	 *
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

		$creds         = self::get_credentials();
		$matomo_module = $bundle['modules']['Gi_Toolkit_Matomo'] ?? array();
		if ( ! is_array( $matomo_module ) ) {
			$matomo_module = array();
		}

		$options = isset( $matomo_module['options'] ) && is_array( $matomo_module['options'] ) ? $matomo_module['options'] : array();
		$options['matomo_url'] = $creds['matomo_url'];
		$options['api_token']  = $creds['api_token'];
		$options['site_id']    = 0;
		if ( ! isset( $options['auto_site'] ) || '0' !== (string) $options['auto_site'] ) {
			$options['auto_site'] = '1';
		}

		$matomo_module['options'] = $options;
		$matomo_module['active']  = '1';

		$bundle['modules']['Gi_Toolkit_Matomo'] = $matomo_module;

		return $bundle;
	}

	/**
	 * @param string $url URL Matomo.
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url = esc_url_raw( $url );
		return rtrim( $url, '/' );
	}
}
