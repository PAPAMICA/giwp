<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Données mail partagées entre REST MainWP v1 et v2.
 */
class MainWP_GIWeb_Rest_Mail_Data {

	/**
	 * @return array<string, mixed>
	 */
	public static function get_network_payload() {
		return MainWP_GIWeb_API::get_mail_network();
	}

	/**
	 * @param int  $site_id        ID MainWP.
	 * @param bool $refresh        Forcer l’appel enfant.
	 * @param int  $failures_limit Limite échecs récents (1–20).
	 * @return array{ok:bool, data?:array<string,mixed>, error?:string, status?:int}
	 */
	public static function get_site_payload( $site_id, $refresh = false, $failures_limit = 5 ) {
		$site_id = absint( $site_id );
		if ( ! $site_id ) {
			return array(
				'ok'     => false,
				'error'  => __( 'ID de site invalide.', 'mainwp-giweb' ),
				'status' => 400,
			);
		}

		global $mainwp_giweb_activator;
		$row   = MainWP_GIWeb_Sites::find_by_id( $site_id, $mainwp_giweb_activator ?? null );
		$label = (string) ( $row['name'] ?? ( $row['url'] ?? ( '#' . $site_id ) ) );
		$url   = (string) ( $row['url'] ?? '' );

		if ( empty( $row ) ) {
			return array(
				'ok'     => false,
				'error'  => __( 'Site MainWP introuvable.', 'mainwp-giweb' ),
				'status' => 404,
			);
		}

		$limit   = max( 1, min( 20, absint( $failures_limit ) ) );
		$refresh = (bool) $refresh;

		if ( $refresh ) {
			$api = MainWP_GIWeb_API::get_mail(
				$site_id,
				array(
					'failures_limit' => $limit,
				)
			);
			MainWP_GIWeb_Mail_Stats::record_site_sync( $site_id, $label, $url, $api );
			if ( empty( $api['success'] ) ) {
				$raw = ! empty( $api['errors'][0] ) ? (string) $api['errors'][0] : '';
				return array(
					'ok'     => false,
					'error'  => MainWP_GIWeb_API::format_site_error( $site_id, $label, $raw, $api ),
					'status' => 502,
				);
			}
		}

		$mail = MainWP_GIWeb_API::resolve_site_mail( $site_id, $refresh );
		if ( ! is_array( $mail ) ) {
			return array(
				'ok'     => false,
				'error'  => __( 'Aucune donnée mail disponible pour ce site.', 'mainwp-giweb' ),
				'status' => 404,
			);
		}

		return array(
			'ok'   => true,
			'data' => array(
				'site_id' => $site_id,
				'label'   => $label,
				'url'     => $url,
				'mail'    => $mail,
			),
		);
	}

	/**
	 * @param mixed $request Requête REST.
	 * @return bool
	 */
	public static function is_v1_request_valid( $request ) {
		return (bool) apply_filters( 'mainwp_rest_api_validate', false, $request );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function v1_auth_error_response() {
		$response = new WP_REST_Response(
			array(
				'ERROR' => __(
					'Clé ou secret consommateur incorrect ou manquant. Réinitialisez les identifiants dans MainWP > API Access.',
					'mainwp-giweb'
				),
			)
		);
		$response->set_status( 401 );
		return $response;
	}

	/**
	 * @param string $message Message.
	 * @param int    $status  Code HTTP.
	 * @return WP_REST_Response
	 */
	public static function v1_error_response( $message, $status = 400 ) {
		$response = new WP_REST_Response(
			array(
				'ERROR' => (string) $message,
			)
		);
		$response->set_status( $status );
		return $response;
	}
}
