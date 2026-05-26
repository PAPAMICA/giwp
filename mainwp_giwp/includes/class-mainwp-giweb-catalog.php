<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalogue des modules GI-Toolkit pour l’UI MainWP.
 */
class MainWP_GIWeb_Catalog {

	/**
	 * @return bool
	 */
	public static function load_modules_data() {
		if ( class_exists( 'Gi_Toolkit_Modules_Data' ) && function_exists( 'gi_toolkit_options' ) ) {
			return true;
		}
		$base = defined( 'MAINWP_GIWEB_GI_TOOLKIT_PATH' ) ? MAINWP_GIWEB_GI_TOOLKIT_PATH : dirname( MAINWP_GIWEB_PLUGIN_FILE ) . '/../wordpress_giwp/';
		$paths = array(
			$base . 'admin/class-modules-data.php',
			$base . 'includes/global-functions.php',
		);
		foreach ( $paths as $path ) {
			if ( $path && is_readable( $path ) ) {
				require_once $path;
			}
		}
		return class_exists( 'Gi_Toolkit_Modules_Data' ) && function_exists( 'gi_toolkit_options' );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_modules() {
		if ( ! self::load_modules_data() ) {
			return array();
		}
		return gi_toolkit_options();
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_groups() {
		if ( ! self::load_modules_data() ) {
			return array();
		}
		return gi_toolkit_settings_groups();
	}

	/**
	 * @return string[]
	 */
	public static function high_risk_modules() {
		if ( class_exists( 'Gi_Toolkit_Security' ) ) {
			return Gi_Toolkit_Security::high_risk_modules();
		}
		return array(
			'Gi_Toolkit_Code_Snippets',
			'Gi_Toolkit_File_Manager',
			'Gi_Toolkit_Adminer',
			'Gi_Toolkit_Search_Replace_In_Database',
		);
	}
}
