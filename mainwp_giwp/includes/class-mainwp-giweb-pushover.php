<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credentials Pushover centralisés sur le dashboard MainWP et injection dans les bundles déployés.
 *
 * @since 1.7.4
 */
class MainWP_GIWeb_Pushover {

	/**
	 * @return array<string, string>
	 */
	public static function get_credentials() {
		$settings = MainWP_GIWeb_Settings::get();
		return array(
			'pushover_app_token' => trim( (string) ( $settings['pushover_app_token'] ?? '' ) ),
			'pushover_user_key'  => trim( (string) ( $settings['pushover_user_key'] ?? '' ) ),
			'pushover_device'    => trim( (string) ( $settings['pushover_device'] ?? '' ) ),
			'pushover_title'     => (string) ( $settings['pushover_title'] ?? self::default_title() ),
			'pushover_message'   => (string) ( $settings['pushover_message'] ?? self::default_message() ),
		);
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		return '' !== $creds['pushover_app_token'] && '' !== $creds['pushover_user_key'];
	}

	/**
	 * @return string
	 */
	public static function default_title() {
		return '[GI] $alert_summary — $website_name';
	}

	/**
	 * @return string
	 */
	public static function default_message() {
		return '$alert_summary' . "\n\n" . '$alert_details' . "\n\n" . 'Site : $website_name' . "\n" . 'URL : $website_url' . "\n" . 'Date : $datetime';
	}

	/**
	 * Fusionne jeton, clé et modèles de notification dans le bundle avant déploiement.
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

		$creds  = self::get_credentials();
		$module = $bundle['modules']['Gi_Toolkit_Compromise_Detection'] ?? array();
		if ( ! is_array( $module ) ) {
			$module = array();
		}

		$options = isset( $module['options'] ) && is_array( $module['options'] ) ? $module['options'] : array();
		$options['pushover_app_token'] = $creds['pushover_app_token'];
		$options['pushover_user_key']  = $creds['pushover_user_key'];
		$options['pushover_device']    = $creds['pushover_device'];
		if ( '' !== trim( $creds['pushover_title'] ) ) {
			$options['pushover_title'] = $creds['pushover_title'];
		}
		if ( '' !== trim( $creds['pushover_message'] ) ) {
			$options['pushover_message'] = $creds['pushover_message'];
		}

		$module['options'] = $options;
		$module['active']  = '1';

		$bundle['modules']['Gi_Toolkit_Compromise_Detection'] = $module;

		return $bundle;
	}
}
