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
		add_filter( 'mainwp_site_sync_others_data', array( __CLASS__, 'inject_sync_others_data' ), 20, 2 );
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
	 * Injecte le statut UpdraftPlus dans la synchro globale MainWP (même requête que stats).
	 *
	 * @param array<string, mixed> $information Données sync en cours.
	 * @param array<string, mixed> $others_data Données additionnelles MainWP.
	 * @return array<string, mixed>
	 */
	public static function inject_sync_others_data( $information, $others_data ) {
		unset( $others_data );

		if ( ! is_array( $information ) ) {
			$information = array();
		}

		if ( ! class_exists( 'Gi_Toolkit_MainWP_API', false ) && defined( 'GI_TOOLKIT_PLUGIN_PATH' ) ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-mainwp-api.php';
		}

		if ( ! class_exists( 'Gi_Toolkit_MainWP_API' ) ) {
			return $information;
		}

		$status = Gi_Toolkit_MainWP_API::get_status();
		if ( ! is_array( $status ) || empty( $status ) ) {
			return $information;
		}

		$information['gi_toolkit_sync'] = $status;

		if ( ! empty( $status['mail_catcher'] ) && is_array( $status['mail_catcher'] ) ) {
			$information['gi_toolkit_mail_catcher'] = $status['mail_catcher'];
		}

		if ( ! empty( $status['updraftplus'] ) && is_array( $status['updraftplus'] ) ) {
			$information['gi_toolkit_updraftplus'] = $status['updraftplus'];
		}

		return $information;
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
