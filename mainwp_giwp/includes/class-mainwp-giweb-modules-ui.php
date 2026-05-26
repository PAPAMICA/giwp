<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UI modules (même présentation que GI-Toolkit sur les sites).
 */
class MainWP_GIWeb_Modules_UI {

	/**
	 * @return string URL de base des assets GI-Toolkit sur le serveur dashboard.
	 */
	public static function gi_toolkit_assets_url() {
		if ( defined( 'GI_TOOLKIT_PLUGIN_URL' ) ) {
			return trailingslashit( GI_TOOLKIT_PLUGIN_URL );
		}
		return trailingslashit( plugins_url( '../wordpress_giwp/', MAINWP_GIWEB_PLUGIN_FILE ) );
	}

	/**
	 * @return void
	 */
	public static function enqueue_assets() {
		$base_path = trailingslashit( MAINWP_GIWEB_GI_TOOLKIT_PATH ) . 'admin/assets/build/core/';
		$base_url  = self::gi_toolkit_assets_url() . 'admin/assets/build/core/';
		$css       = $base_path . 'settings.css';
		if ( is_readable( $css ) ) {
			wp_enqueue_style(
				'mainwp-giweb-gi-toolkit-settings',
				$base_url . 'settings.css',
				array(),
				(string) filemtime( $css )
			);
		}

		wp_enqueue_script(
			'mainwp-giweb-modules-ui',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/modules-ui.js',
			array(),
			MAINWP_GIWEB_VERSION,
			true
		);
	}

	/**
	 * @param array<string, mixed> $bundle Bundle de travail.
	 * @return array<string, string> État modules (classe => '0'|'1').
	 */
	public static function module_states_from_bundle( $bundle ) {
		$states  = array();
		$modules = $bundle['modules'] ?? array();
		if ( ! is_array( $modules ) ) {
			return $states;
		}
		foreach ( $modules as $class => $mod ) {
			$states[ $class ] = ( is_array( $mod ) && ! empty( $mod['active'] ) && '1' === (string) $mod['active'] ) ? '1' : '0';
		}
		return $states;
	}
}
