<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestration des déploiements multi-sites.
 */
class MainWP_GIWeb_Deploy {

	/**
	 * @param array<string, mixed> $bundle        Bundle source.
	 * @param int[]                $site_ids      Sites cibles.
	 * @param string               $template_id   ID modèle.
	 * @param string               $template_name Nom modèle.
	 * @return array{deployment_id: int, results: array<int, array<string, mixed>>}
	 */
	public static function push_to_sites( $bundle, $site_ids, $template_id = '', $template_name = '' ) {
		$hash          = md5( wp_json_encode( $bundle ) );
		$deployment_id = MainWP_GIWeb_History::create_deployment( $template_id, $template_name, $hash );
		$results       = array();

		foreach ( $site_ids as $site_id ) {
			$site_id = absint( $site_id );
			if ( ! $site_id ) {
				continue;
			}
			$args   = MainWP_GIWeb_Overrides::apply_to_bundle( $bundle, $site_id );
			$result = MainWP_GIWeb_API::import_site( $site_id, $bundle, $args );
			$status = ! empty( $result['success'] ) ? 'success' : 'error';
			$msg    = ! empty( $result['errors'][0] ) ? $result['errors'][0] : ( 'success' === $status ? __( 'OK', 'mainwp-giweb' ) : __( 'Échec', 'mainwp-giweb' ) );
			MainWP_GIWeb_History::log_site_result( $deployment_id, $site_id, $status, $msg, $result );
			$results[ $site_id ] = $result;
		}

		return array(
			'deployment_id' => $deployment_id,
			'results'       => $results,
		);
	}

	/**
	 * Rafraîchit le statut de tous les sites connectés.
	 *
	 * @param array<int, object> $websites Sites MainWP.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sync_all_status( $websites ) {
		$out = array();
		if ( ! is_array( $websites ) ) {
			return $out;
		}
		foreach ( $websites as $site ) {
			$id = isset( $site->id ) ? (int) $site->id : 0;
			if ( ! $id ) {
				continue;
			}
			$out[ $id ] = MainWP_GIWeb_API::get_status( $id );
		}
		return $out;
	}
}
