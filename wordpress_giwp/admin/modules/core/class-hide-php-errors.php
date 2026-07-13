<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Hide PHP Errors
 * Description: Force hiding PHP errors from output (ini + WP_DEBUG_DISPLAY).
 *
 * @since 2.28.1
 */
class Gi_Toolkit_Hide_PHP_Errors {

	const MODULE_ID = 'Hide PHP Errors';

	/**
	 * @return void
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'enforce' ), PHP_INT_MAX );
		add_action( 'init', array( __CLASS__, 'enforce' ), PHP_INT_MAX );
		add_action( 'wp_loaded', array( __CLASS__, 'enforce' ), PHP_INT_MAX );
	}

	/**
	 * @return void
	 */
	public static function activate() {
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

		Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', false );
		self::enforce();
	}

	/**
	 * @return void
	 */
	public static function deactivate() {
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

		Gi_Toolkit_WP_Config::remove_constant( 'WP_DEBUG_DISPLAY' );
	}

	/**
	 * Force l’absence d’affichage des erreurs PHP (écrase wp-config / autres modules sur la requête).
	 *
	 * @return void
	 */
	public static function enforce() {
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.display_errors_Disallowed
			@ini_set( 'display_startup_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'html_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
}
