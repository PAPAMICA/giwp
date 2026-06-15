<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API MainWP v1 — statuts backup UpdraftPlus (tous les sites, un appel).
 *
 * GET /wp-json/mainwp/v1/gi-toolkit-backup/get-network
 */
class MainWP_GIWeb_Rest_Backup_V1 {

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
		register_rest_route(
			'mainwp/v' . $this->api_version,
			'/gi-toolkit-backup/get-network',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'gi_toolkit_backup_rest_api_get_network_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response
	 */
	public function gi_toolkit_backup_rest_api_get_network_callback( $request ) {
		if ( ! MainWP_GIWeb_Rest_Auth::is_v1_request_valid( $request ) ) {
			return MainWP_GIWeb_Rest_Auth::v1_auth_error_response();
		}

		$response = new WP_REST_Response(
			array(
				'data' => MainWP_GIWeb_Rest_Backup_Data::get_network_payload(),
			)
		);
		$response->set_status( 200 );
		return $response;
	}
}
