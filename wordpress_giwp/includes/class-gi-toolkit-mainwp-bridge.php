<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge MainWP Child intégré à GI-Toolkit (aucun plugin séparé requis sur les sites clients).
 *
 * @since 2.20.0
 */
class Gi_Toolkit_MainWP_Bridge {

	/**
	 * Enregistre le handler distant `gi_toolkit` pour MainWP Child.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! self::is_mainwp_child_site() ) {
			return;
		}

		if ( ! function_exists( 'gi_toolkit' ) ) {
			/**
			 * Handler appelé par MainWP Dashboard via mainwp_fetchurlauthed (fonction = gi_toolkit).
			 *
			 * @param array<string, mixed> $data Payload.
			 * @return array<string, mixed>
			 */
			function gi_toolkit( $data = array() ) {
				if ( ! class_exists( 'Gi_Toolkit_MainWP_API' ) ) {
					return array(
						'success' => false,
						'data'    => array(),
						'errors'  => array( __( 'GI-Toolkit API MainWP indisponible.', 'gi-toolkit' ) ),
					);
				}
				return Gi_Toolkit_MainWP_API::handle_request( is_array( $data ) ? $data : array() );
			}
		}

		add_filter( 'mainwp_child_actions', array( __CLASS__, 'register_child_action' ) );
	}

	/**
	 * @param array<string, string> $actions Actions MainWP Child.
	 * @return array<string, string>
	 */
	public static function register_child_action( $actions ) {
		if ( ! is_array( $actions ) ) {
			$actions = array();
		}
		$actions['gi_toolkit'] = 'gi_toolkit';
		return $actions;
	}

	/**
	 * Détecte si le site exécute MainWP Child.
	 *
	 * @return bool
	 */
	public static function is_mainwp_child_site() {
		return defined( 'MAINWP_CHILD_PLUGIN_URL' )
			|| defined( 'MAINWP_CHILD_FILE' )
			|| class_exists( 'MainWP\Child\MainWP_Child' )
			|| class_exists( 'MainWP_Child' );
	}
}

Gi_Toolkit_MainWP_Bridge::boot();
