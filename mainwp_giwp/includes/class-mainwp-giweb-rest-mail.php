<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MainWP_REST_Controller', false ) ) {
	return;
}

/**
 * REST API MainWP v2 — statistiques Mail Catcher (GI-Toolkit).
 */
class MainWP_GIWeb_Rest_Mail_Controller extends MainWP_REST_Controller {

	/** @var self|null */
	private static $instance = null;

	/** @var string */
	protected $rest_base = 'gi-toolkit-mail';

	/** @var string */
	protected $title = 'gi-toolkit-mail';

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id_domain>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_rest_permissions_check' ),
					'args'                => $this->get_site_item_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id_domain>[a-zA-Z0-9\-\.\_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_rest_permissions_check' ),
					'args'                => $this->get_site_item_params(),
				),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_site_item_params() {
		return array(
			'refresh'        => array(
				'description'       => __( 'Forcer un appel API vers le site enfant.', 'mainwp-giweb' ),
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'failures_limit' => array(
				'description'       => __( 'Nombre d’échecs récents à inclure (1–20).', 'mainwp-giweb' ),
				'type'              => 'integer',
				'default'           => 5,
				'minimum'           => 1,
				'maximum'           => 20,
				'sanitize_callback' => 'absint',
			),
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
				'message' => __( 'Statistiques mail du réseau.', 'mainwp-giweb' ),
				'data'    => MainWP_GIWeb_Rest_Mail_Data::get_network_payload(),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$site = $this->get_site_item( $request );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$site_id = absint( $site->id ?? 0 );
		$result  = MainWP_GIWeb_Rest_Mail_Data::get_site_payload(
			$site_id,
			rest_sanitize_boolean( $request->get_param( 'refresh' ) ),
			absint( $request->get_param( 'failures_limit' ) )
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'mainwp_giweb_mail_error',
				(string) ( $result['error'] ?? __( 'Erreur mail.', 'mainwp-giweb' ) ),
				array( 'status' => (int) ( $result['status'] ?? 400 ) )
			);
		}

		return rest_ensure_response(
			array(
				'success' => 1,
				'message' => __( 'Statistiques mail du site.', 'mainwp-giweb' ),
				'data'    => $result['data'],
			)
		);
	}
}
