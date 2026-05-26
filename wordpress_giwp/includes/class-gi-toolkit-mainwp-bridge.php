<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge MainWP Child intégré à GI-Toolkit (aucun plugin séparé requis sur les sites clients).
 *
 * @since 2.20.1
 */
class Gi_Toolkit_MainWP_Bridge {

	/**
	 * Enregistre le handler distant pour MainWP Child.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! self::is_mainwp_child_site() ) {
			return;
		}

		add_filter( 'mainwp_child_extra_execution', array( __CLASS__, 'handle_extra_execution' ), 10, 2 );
	}

	/**
	 * Répond aux appels dashboard via extra_execution + gi_toolkit_request.
	 *
	 * @param array<string, mixed> $information Réponse en cours.
	 * @param array<string, mixed> $post        Données POST MainWP.
	 * @return array<string, mixed>
	 */
	public static function handle_extra_execution( $information, $post ) {
		if ( ! is_array( $post ) || empty( $post['gi_toolkit_request'] ) ) {
			return is_array( $information ) ? $information : array();
		}

		if ( ! class_exists( 'Gi_Toolkit_MainWP_API' ) ) {
			return array(
				'success' => false,
				'data'    => array(),
				'errors'  => array( __( 'GI-Toolkit API MainWP indisponible.', 'gi-toolkit' ) ),
			);
		}

		return Gi_Toolkit_MainWP_API::handle_request( $post );
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
