<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authentification REST MainWP v1 partagée (mail, backups, …).
 */
class MainWP_GIWeb_Rest_Auth {

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
