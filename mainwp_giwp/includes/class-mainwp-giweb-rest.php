<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistrement des routes REST MainWP v2 pour l’extension GI-Toolkit Manager.
 */
class MainWP_GIWeb_Rest {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'mainwp_rest_api_get_rest_namespaces', array( __CLASS__, 'register_namespaces' ), 15, 1 );
	}

	/**
	 * @param array<string, mixed> $namespaces Namespaces REST MainWP.
	 * @return array<string, mixed>
	 */
	public static function register_namespaces( $namespaces ) {
		if ( ! self::is_available() ) {
			return is_array( $namespaces ) ? $namespaces : array();
		}

		if ( ! is_array( $namespaces ) ) {
			$namespaces = array();
		}

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
	private static function is_available() {
		global $mainwp_giweb_activator;

		if ( ! class_exists( 'MainWP_REST_Controller', false ) ) {
			return false;
		}

		if ( ! $mainwp_giweb_activator || empty( $mainwp_giweb_activator->childEnabled ) ) {
			return false;
		}

		return true;
	}
}
