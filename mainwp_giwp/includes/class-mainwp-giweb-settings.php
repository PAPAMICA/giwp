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
			'kuma_url'                      => '',
			'kuma_username'                 => '',
			'kuma_password'                 => '',
			'zabbix_url'                    => '',
			'zabbix_api_token'              => '',
			'zabbix_auto_create'            => '0',
			'ftp_host'                      => '',
			'ftp_port'                      => 21,
			'ftp_username'                  => '',
			'ftp_password'                  => '',
			'ftp_path'                      => '/BACKUPS_WORDPRESS/%siteurl%',
			'ftp_passive'                   => '1',
			'ftp_ssl'                       => '0',
			'ftp_auto_on_deploy'            => '1',
			'mail_widget_list_mode'         => 'cards',
			'backup_widget_list_mode'       => 'cards',
			'kuma_widget_list_mode'         => 'cards',
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

		if ( isset( $data['kuma_url'] ) ) {
			$kurl = esc_url_raw( rtrim( trim( (string) $data['kuma_url'] ), '/' ) );
			$clean['kuma_url'] = ( '' !== $kurl && filter_var( $kurl, FILTER_VALIDATE_URL ) ) ? $kurl : '';
		} else {
			$clean['kuma_url'] = (string) ( $current['kuma_url'] ?? '' );
		}

		if ( isset( $data['kuma_username'] ) ) {
			$clean['kuma_username'] = sanitize_text_field( (string) $data['kuma_username'] );
		} else {
			$clean['kuma_username'] = (string) ( $current['kuma_username'] ?? '' );
		}

		if ( ! empty( $data['kuma_password'] ) ) {
			$clean['kuma_password'] = sanitize_text_field( (string) $data['kuma_password'] );
		} else {
			$clean['kuma_password'] = (string) ( $current['kuma_password'] ?? '' );
		}

		if ( isset( $data['zabbix_url'] ) ) {
			$zurl = esc_url_raw( rtrim( trim( (string) $data['zabbix_url'] ), '/' ) );
			$clean['zabbix_url'] = ( '' !== $zurl && filter_var( $zurl, FILTER_VALIDATE_URL ) ) ? $zurl : '';
		} else {
			$clean['zabbix_url'] = (string) ( $current['zabbix_url'] ?? '' );
		}

		if ( ! empty( $data['zabbix_api_token'] ) ) {
			$clean['zabbix_api_token'] = sanitize_text_field( (string) $data['zabbix_api_token'] );
		} else {
			$clean['zabbix_api_token'] = (string) ( $current['zabbix_api_token'] ?? '' );
		}

		$clean['zabbix_auto_create'] = ! empty( $data['zabbix_auto_create'] ) ? '1' : '0';

		if ( isset( $data['ftp_host'] ) ) {
			$clean['ftp_host'] = sanitize_text_field( (string) $data['ftp_host'] );
		} else {
			$clean['ftp_host'] = (string) ( $current['ftp_host'] ?? '' );
		}

		$clean['ftp_port'] = max( 1, min( 65535, absint( $data['ftp_port'] ?? ( $current['ftp_port'] ?? 21 ) ) ) );

		if ( isset( $data['ftp_username'] ) ) {
			$clean['ftp_username'] = sanitize_text_field( (string) $data['ftp_username'] );
		} else {
			$clean['ftp_username'] = (string) ( $current['ftp_username'] ?? '' );
		}

		if ( ! empty( $data['ftp_password'] ) ) {
			$clean['ftp_password'] = sanitize_text_field( (string) $data['ftp_password'] );
		} else {
			$clean['ftp_password'] = (string) ( $current['ftp_password'] ?? '' );
		}

		if ( isset( $data['ftp_path'] ) ) {
			$path = trim( str_replace( '\\', '/', (string) $data['ftp_path'] ) );
			$clean['ftp_path'] = '' !== $path ? $path : '/BACKUPS_WORDPRESS/%siteurl%';
		} else {
			$clean['ftp_path'] = (string) ( $current['ftp_path'] ?? '/BACKUPS_WORDPRESS/%siteurl%' );
		}

		$clean['ftp_passive']        = ! empty( $data['ftp_passive'] ) ? '1' : '0';
		$clean['ftp_ssl']            = ! empty( $data['ftp_ssl'] ) ? '1' : '0';
		$clean['ftp_auto_on_deploy'] = ! empty( $data['ftp_auto_on_deploy'] ) ? '1' : '0';

		$mail_mode = isset( $data['mail_widget_list_mode'] ) ? (string) $data['mail_widget_list_mode'] : (string) ( $current['mail_widget_list_mode'] ?? 'cards' );
		$clean['mail_widget_list_mode'] = in_array( $mail_mode, array( 'cards', 'table' ), true ) ? $mail_mode : 'cards';

		$backup_mode = isset( $data['backup_widget_list_mode'] ) ? (string) $data['backup_widget_list_mode'] : (string) ( $current['backup_widget_list_mode'] ?? 'cards' );
		$clean['backup_widget_list_mode'] = in_array( $backup_mode, array( 'cards', 'table' ), true ) ? $backup_mode : 'cards';

		$kuma_mode = isset( $data['kuma_widget_list_mode'] ) ? (string) $data['kuma_widget_list_mode'] : (string) ( $current['kuma_widget_list_mode'] ?? 'cards' );
		$clean['kuma_widget_list_mode'] = in_array( $kuma_mode, array( 'cards', 'table' ), true ) ? $kuma_mode : 'cards';

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
