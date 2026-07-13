<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Réglages de sécurité globaux, journal d’audit et garde-fous modules sensibles.
 *
 * @since 2.19.0
 */
class Gi_Toolkit_Security {

	const OPTION_KEY = 'gi_toolkit_security_settings';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'gi_toolkit_sanitize_main_settings', array( __CLASS__, 'filter_high_risk_activation' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function defaults() {
		return array(
			'allow_high_risk_modules'        => '0',
			'audit_log_enabled'              => '1',
			'search_replace_dry_run_default' => '1',
			'ajax_strict_capabilities'       => '1',
			'admin_dark_theme'               => '0',
			'hide_sensitive_in_system_info'  => '1',
			'confirm_module_activation'      => '1',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_options() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * @param string $key     Clé d’option.
	 * @param mixed  $default Valeur par défaut.
	 * @return mixed
	 */
	public static function get_option( $key, $default = null ) {
		$opts = self::get_options();
		if ( array_key_exists( $key, $opts ) ) {
			return $opts[ $key ];
		}
		$defaults = self::defaults();
		if ( null === $default && array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}
		return $default;
	}

	/**
	 * Modules nécessitant une activation explicite dans Sécurité.
	 *
	 * @return string[]
	 */
	public static function high_risk_modules() {
		return apply_filters(
			'gi_toolkit_high_risk_modules',
			array(
				'Gi_Toolkit_Code_Snippets',
				'Gi_Toolkit_File_Manager',
				'Gi_Toolkit_Adminer',
				'Gi_Toolkit_Search_Replace_In_Database',
			)
		);
	}

	/**
	 * @param string $class Nom de classe module.
	 * @return bool
	 */
	public static function is_high_risk_module( $class ) {
		return in_array( $class, self::high_risk_modules(), true );
	}

	/**
	 * @param array<string, string> $settings     Nouveaux réglages modules.
	 * @param array<string, string> $old_settings Anciens réglages.
	 * @return array<string, string>
	 */
	public static function filter_high_risk_activation( $settings, $old_settings ) {
		if ( '1' === self::get_option( 'allow_high_risk_modules' ) ) {
			return $settings;
		}
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		if ( ! is_array( $old_settings ) ) {
			$old_settings = array();
		}
		foreach ( self::high_risk_modules() as $class ) {
			if ( empty( $settings[ $class ] ) || '1' !== $settings[ $class ] ) {
				continue;
			}
			$was_active = ! empty( $old_settings[ $class ] ) && '1' === $old_settings[ $class ];
			if ( ! $was_active ) {
				$settings[ $class ] = '0';
				set_transient( 'gi_toolkit_high_risk_blocked', $class, MINUTE_IN_SECONDS );
				self::log( 'high_risk_module_blocked', array( 'module' => $class ) );
			}
		}
		return $settings;
	}

	/**
	 * @return void
	 */
	public static function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$blocked = get_transient( 'gi_toolkit_high_risk_blocked' );
		if ( ! $blocked ) {
			return;
		}
		delete_transient( 'gi_toolkit_high_risk_blocked' );
		$modules = gi_toolkit_options();
		$label   = $modules[ $blocked ]['name'] ?? $blocked;
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: module name */
					__( 'Le module « %s » est considéré à haut risque. Activez « Autoriser les modules à haut risque » dans GI-Toolkit → Réglages → Sécurité avant de l’activer.', 'gi-toolkit' ),
					$label
				)
			)
		);
	}

	/**
	 * @param array<string, mixed> $input Données POST.
	 * @return array<string, string>
	 */
	public static function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$out     = self::defaults();
		$allowed = array_keys( $out );
		foreach ( $allowed as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] ) ? '1' : '0';
		}
		return $out;
	}

	/**
	 * @param string               $action  Identifiant action.
	 * @param array<string, mixed> $context Contexte additionnel.
	 * @return void
	 */
	public static function log( $action, $context = array() ) {
		if ( '1' !== self::get_option( 'audit_log_enabled' ) ) {
			return;
		}
		$folders = apply_filters( 'gi_toolkit/folders', array() );
		if ( function_exists( 'gi_toolkit_folders' ) ) {
			gi_toolkit_folders();
		}
		$dir  = function_exists( 'gi_toolkit_folders' ) ? gi_toolkit_folders() : trailingslashit( WP_CONTENT_DIR ) . 'gi-toolkit';
		$file = $dir . '/security-audit.log';
		$user = wp_get_current_user();
		$line = wp_json_encode(
			array(
				'time'    => gmdate( 'c' ),
				'action'  => sanitize_key( $action ),
				'user_id' => $user->ID ?? 0,
				'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'context' => $context,
			)
		);
		if ( $line ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Chemin du fichier journal.
	 *
	 * @return string
	 */
	public static function get_audit_log_path() {
		return trailingslashit( function_exists( 'gi_toolkit_folders' ) ? gi_toolkit_folders() : WP_CONTENT_DIR . '/gi-toolkit' ) . 'security-audit.log';
	}
}

/**
 * Vérifie nonce AJAX + capability (sauf si désactivé dans les réglages).
 *
 * @param string $capability   Capability WordPress.
 * @param string $nonce_action Action du nonce.
 * @param string $nonce_field  Champ POST/GET du nonce.
 * @return void
 */
function gi_toolkit_ajax_require_cap( $capability, $nonce_action, $nonce_field = 'nonce' ) {
	check_ajax_referer( $nonce_action, $nonce_field );
	if ( '0' === Gi_Toolkit_Security::get_option( 'ajax_strict_capabilities' ) ) {
		return;
	}
	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error(
			array(
				'message'      => __( 'Droits insuffisants pour cette action.', 'gi-toolkit' ),
				'message_type' => 'danger',
			),
			403
		);
	}
}

/**
 * Vérifie un nonce AJAX (POST ou GET) puis la capability.
 *
 * @param string $capability   Capability WordPress.
 * @param string $nonce_action Action du nonce.
 * @param string $nonce_field  Nom du paramètre nonce.
 * @return void
 */
function gi_toolkit_ajax_verify_and_cap( $capability, $nonce_action, $nonce_field = 'nonce' ) {
	$nonce = '';
	if ( isset( $_POST[ $nonce_field ] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
	} elseif ( isset( $_GET[ $nonce_field ] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_GET[ $nonce_field ] ) );
	}
	if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
		wp_send_json_error(
			array(
				'message'      => __( 'Rechargez la page et réessayez.', 'gi-toolkit' ),
				'message_type' => 'danger',
			),
			403
		);
	}
	if ( '0' !== Gi_Toolkit_Security::get_option( 'ajax_strict_capabilities' ) && ! current_user_can( $capability ) ) {
		wp_send_json_error(
			array(
				'message'      => __( 'Droits insuffisants pour cette action.', 'gi-toolkit' ),
				'message_type' => 'danger',
			),
			403
		);
	}
}
