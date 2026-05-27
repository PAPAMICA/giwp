<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Réglages extension (onboarding, profil par défaut).
 */
class MainWP_GIWeb_Settings {

	const OPTION_KEY = 'mainwp_giweb_settings';

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'default_template_id'           => '',
			'install_checked_by_default'    => '1',
			'apply_profile_by_default'      => '1',
			'activate_after_install'        => '1',
			'mail_alert_enabled'            => '1',
			'mail_alert_min_failed'         => 1,
			'mail_alert_email'              => '',
			'client_zip_url'                => '',
			'sync_concurrency'              => 5,
			'matomo_url'                    => '',
			'matomo_api_token'              => '',
		);
	}

	/**
	 * @param array<string, mixed> $data Données brutes.
	 * @return bool
	 */
	public static function save( array $data ) {
		$current = self::get();
		$clean   = self::defaults();

		if ( isset( $data['default_template_id'] ) ) {
			$clean['default_template_id'] = sanitize_text_field( (string) $data['default_template_id'] );
		}
		$clean['install_checked_by_default'] = ! empty( $data['install_checked_by_default'] ) ? '1' : '0';
		$clean['apply_profile_by_default']   = ! empty( $data['apply_profile_by_default'] ) ? '1' : '0';
		$clean['activate_after_install']       = ! empty( $data['activate_after_install'] ) ? '1' : '0';
		$clean['mail_alert_enabled']           = ! empty( $data['mail_alert_enabled'] ) ? '1' : '0';
		$clean['mail_alert_min_failed']        = max( 1, absint( $data['mail_alert_min_failed'] ?? 1 ) );
		$email                                 = isset( $data['mail_alert_email'] ) ? sanitize_email( (string) $data['mail_alert_email'] ) : '';
		$clean['mail_alert_email']             = is_email( $email ) ? $email : '';

		$zip_url = isset( $data['client_zip_url'] ) ? esc_url_raw( trim( (string) $data['client_zip_url'] ) ) : '';
		$clean['client_zip_url']               = ( '' !== $zip_url && filter_var( $zip_url, FILTER_VALIDATE_URL ) ) ? $zip_url : '';

		$clean['sync_concurrency'] = max( 1, min( 15, absint( $data['sync_concurrency'] ?? 5 ) ) );

		if ( isset( $data['matomo_url'] ) ) {
			$url = esc_url_raw( rtrim( trim( (string) $data['matomo_url'] ), '/' ) );
			$clean['matomo_url'] = ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) ? $url : '';
		} else {
			$clean['matomo_url'] = (string) ( $current['matomo_url'] ?? '' );
		}

		if ( ! empty( $data['matomo_api_token'] ) ) {
			$clean['matomo_api_token'] = sanitize_text_field( (string) $data['matomo_api_token'] );
		} else {
			$clean['matomo_api_token'] = (string) ( $current['matomo_api_token'] ?? '' );
		}

		return update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * ID du modèle par défaut (profil « Default »).
	 *
	 * @return string
	 */
	public static function default_template_id() {
		return MainWP_GIWeb_Templates::get_default_template_id();
	}
}
