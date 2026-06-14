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
		if ( $request instanceof WP_REST_Request && class_exists( 'MainWP_REST_Authentication', false ) ) {
			$valid = MainWP_REST_Authentication::get_instance()->is_valid_permissions( $request );
			if ( true === $valid ) {
				return true;
			}
		}

		self::inject_v1_credentials( $request );

		return (bool) apply_filters( 'mainwp_rest_api_validate', false, $request );
	}

	/**
	 * Injecte consumer_key / consumer_secret pour mainwp_rest_api_validate (v1 legacy).
	 *
	 * MainWP v1 ne lit pas curl -u nativement ; on réutilise d’abord l’auth v2 (Basic/Bearer),
	 * puis on alimente la validation legacy depuis Basic Auth ou query string.
	 *
	 * @param mixed $request Requête REST.
	 * @return void
	 */
	private static function inject_v1_credentials( $request ) {
		$consumer_key    = null;
		$consumer_secret = null;

		if ( $request instanceof WP_REST_Request ) {
			$consumer_key    = $request->get_param( 'consumer_key' );
			$consumer_secret = $request->get_param( 'consumer_secret' );
		} elseif ( is_array( $request ) ) {
			$consumer_key    = $request['consumer_key'] ?? null;
			$consumer_secret = $request['consumer_secret'] ?? null;
		}

		if ( ( empty( $consumer_key ) || empty( $consumer_secret ) ) && ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ) {
			$consumer_key    = wp_unslash( $_GET['consumer_key'] );
			$consumer_secret = wp_unslash( $_GET['consumer_secret'] );
		}

		if ( ( empty( $consumer_key ) || empty( $consumer_secret ) ) && ! empty( $_SERVER['PHP_AUTH_USER'] ) && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
			$consumer_key    = wp_unslash( $_SERVER['PHP_AUTH_USER'] );
			$consumer_secret = wp_unslash( $_SERVER['PHP_AUTH_PW'] );
		}

		if ( ( empty( $consumer_key ) || empty( $consumer_secret ) ) && class_exists( 'MainWP_REST_Authentication', false ) ) {
			$auth   = MainWP_REST_Authentication::get_instance();
			$header = method_exists( $auth, 'get_authorization_header' ) ? $auth->get_authorization_header() : '';
			if ( is_string( $header ) && 0 === stripos( $header, 'Basic ' ) ) {
				$decoded = base64_decode( substr( $header, 6 ), true );
				if ( is_string( $decoded ) && false !== strpos( $decoded, ':' ) ) {
					list( $consumer_key, $consumer_secret ) = explode( ':', $decoded, 2 );
				}
			}
		}

		if ( ! $request instanceof WP_REST_Request || empty( $consumer_key ) || empty( $consumer_secret ) ) {
			return;
		}

		$request->set_param( 'consumer_key', $consumer_key );
		$request->set_param( 'consumer_secret', $consumer_secret );
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
