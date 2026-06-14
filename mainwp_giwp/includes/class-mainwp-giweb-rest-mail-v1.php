<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API MainWP v1 — statistiques Mail Catcher (GI-Toolkit).
 *
 * GET /wp-json/mainwp/v1/gi-toolkit-mail/get-network
 * GET /wp-json/mainwp/v1/gi-toolkit-mail/get-site-mail?site_id=1
 */
class MainWP_GIWeb_Rest_Mail_V1 {

	/** @var string */
	protected $api_version = '1';

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		self::instance()->mainwp_register_routes();
	}

	/**
	 * @return void
	 */
	public function mainwp_register_routes() {
		$endpoints = array(
			array(
				'route'    => 'gi-toolkit-mail',
				'method'   => 'GET',
				'callback' => 'get-network',
			),
			array(
				'route'    => 'gi-toolkit-mail',
				'method'   => 'GET',
				'callback' => 'get-site-mail',
			),
		);

		foreach ( $endpoints as $endpoint ) {
			$function_name = str_replace( '-', '_', $endpoint['callback'] );
			register_rest_route(
				'mainwp/v' . $this->api_version,
				'/' . $endpoint['route'] . '/' . $endpoint['callback'],
				array(
					'methods'             => $endpoint['method'],
					'callback'            => array( $this, 'gi_toolkit_mail_rest_api_' . $function_name . '_callback' ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response
	 */
	public function gi_toolkit_mail_rest_api_get_network_callback( $request ) {
		if ( ! MainWP_GIWeb_Rest_Mail_Data::is_v1_request_valid( $request ) ) {
			return MainWP_GIWeb_Rest_Mail_Data::v1_auth_error_response();
		}

		$response = new WP_REST_Response(
			array(
				'data' => MainWP_GIWeb_Rest_Mail_Data::get_network_payload(),
			)
		);
		$response->set_status( 200 );
		return $response;
	}

	/**
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response
	 */
	public function gi_toolkit_mail_rest_api_get_site_mail_callback( $request ) {
		if ( ! MainWP_GIWeb_Rest_Mail_Data::is_v1_request_valid( $request ) ) {
			return MainWP_GIWeb_Rest_Mail_Data::v1_auth_error_response();
		}

		if ( ! isset( $request['site_id'] ) || null === $request['site_id'] ) {
			return MainWP_GIWeb_Rest_Mail_Data::v1_error_response(
				__( 'Paramètre site_id requis.', 'mainwp-giweb' ),
				400
			);
		}

		$refresh = ! empty( $request['refresh'] ) && filter_var( $request['refresh'], FILTER_VALIDATE_BOOLEAN );
		$limit   = isset( $request['failures_limit'] ) ? absint( $request['failures_limit'] ) : 5;
		$result  = MainWP_GIWeb_Rest_Mail_Data::get_site_payload(
			absint( $request['site_id'] ),
			$refresh,
			$limit
		);

		if ( empty( $result['ok'] ) ) {
			return MainWP_GIWeb_Rest_Mail_Data::v1_error_response(
				(string) ( $result['error'] ?? __( 'Erreur inconnue.', 'mainwp-giweb' ) ),
				(int) ( $result['status'] ?? 400 )
			);
		}

		$response = new WP_REST_Response(
			array(
				'data' => $result['data'],
			)
		);
		$response->set_status( 200 );
		return $response;
	}
}
