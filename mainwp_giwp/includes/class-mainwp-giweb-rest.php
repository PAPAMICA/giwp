<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistrement REST MainWP v1 + v2 pour l’extension GI-Toolkit Manager.
 */
class MainWP_GIWeb_Rest {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'mainwp_rest_api_get_rest_namespaces', array( __CLASS__, 'register_v2_namespaces' ), 15, 1 );
		add_action( 'init', array( __CLASS__, 'bootstrap_v1' ), 10 );
	}

	/**
	 * @return void
	 */
	public static function bootstrap_v1() {
		if ( ! self::is_extension_enabled() ) {
			return;
		}

		if ( ! apply_filters( 'mainwp_rest_api_enabled', false ) ) {
			return;
		}

		require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-rest-mail-data.php';
		require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-rest-mail-v1.php';

		add_action( 'rest_api_init', array( 'MainWP_GIWeb_Rest_Mail_V1', 'register_routes' ) );
	}

	/**
	 * @param array<string, mixed> $namespaces Namespaces REST MainWP.
	 * @return array<string, mixed>
	 */
	public static function register_v2_namespaces( $namespaces ) {
		if ( ! self::is_extension_enabled() || ! class_exists( 'MainWP_REST_Controller', false ) ) {
			return is_array( $namespaces ) ? $namespaces : array();
		}

		if ( ! is_array( $namespaces ) ) {
			$namespaces = array();
		}

		require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-rest-mail-data.php';
		require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-rest-mail.php';

		if ( ! class_exists( 'MainWP_GIWeb_Rest_Mail_Controller', false ) ) {
			return $namespaces;
		}

		$namespaces['mainwp/v2/gi-toolkit-mail'] = array(
			'gi-toolkit-mail' => 'MainWP_GIWeb_Rest_Mail_Controller',
		);

		return $namespaces;
	}

	/**
	 * @return bool
	 */
	private static function is_extension_enabled() {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator ) {
			return false;
		}

		if ( empty( $mainwp_giweb_activator->childEnabled ) && method_exists( $mainwp_giweb_activator, 'activate_extension' ) ) {
			$mainwp_giweb_activator->activate_extension();
		}

		$enabled = apply_filters( 'mainwp_extension_enabled_check', MAINWP_GIWEB_PLUGIN_FILE );
		return ! empty( $enabled );
	}
}
