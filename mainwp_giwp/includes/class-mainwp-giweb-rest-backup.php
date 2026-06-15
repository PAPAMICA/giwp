<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MainWP_REST_Controller', false ) ) {
	return;
}

/**
 * REST API MainWP v2 — statuts backup UpdraftPlus (tous les sites).
 */
class MainWP_GIWeb_Rest_Backup_Controller extends MainWP_REST_Controller {

	/** @var self|null */
	private static $instance = null;

	/** @var string */
	protected $rest_base = 'gi-toolkit-backup';

	/** @var string */
	protected $title = 'gi-toolkit-backup';

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
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_rest_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'success' => 1,
				'message' => __( 'Statuts backup du réseau.', 'mainwp-giweb' ),
				'data'    => MainWP_GIWeb_Rest_Backup_Data::get_network_payload(),
			)
		);
	}
}
