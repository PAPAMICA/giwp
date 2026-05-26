<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Prevent User Enumeration
 * Description: Empêche l’énumération des utilisateurs via ?author=X et l’API REST /users/.
 *
 * @package Gi_Toolkit
 * @since   1.15.0
 */
class Gi_Toolkit_Prevent_User_Enumeration {

	/**
	 * @since 1.15.0
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'set_404_for_author_pages' ) );
		add_filter( 'author_link', array( $this, 'replace_author_link' ) );
		add_action( 'init', array( $this, 'block_author_page_front' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'block_author_page_rest' ), 10, 3 );
	}

	/**
	 * @since 1.15.0
	 */
	public function set_404_for_author_pages() {
		global $wp_query;
		if ( is_author() ) {
			$wp_query->set_404();
			status_header( 404 );
			exit;
		}
	}

	/**
	 * @since 1.15.0
	 * @return string
	 */
	public function replace_author_link() {
		return home_url( '/' );
	}

	/**
	 * @since 1.15.0
	 */
	public function block_author_page_front() {
		if ( ! current_user_can( 'list_users' ) && is_author() ) {
			wp_die(
				esc_html__( 'Accès refusé.', 'gi-toolkit' ),
				esc_html__( 'Interdit', 'gi-toolkit' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * @since 1.15.0
	 * @param mixed $response Réponse REST (peut être null).
	 * @param mixed $handler  Route handler.
	 * @param mixed $request  WP_REST_Request|null.
	 * @return mixed
	 */
	public function block_author_page_rest( $response, $handler = null, $request = null ) {
		unset( $handler );
		if ( current_user_can( 'list_users' ) ) {
			return $response;
		}

		$route = '';
		if ( $request instanceof WP_REST_Request ) {
			$route = $request->get_route();
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$route = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		if ( strpos( $route, '/wp/v2/users' ) !== false ) {
			wp_send_json(
				array(
					'code'    => 'rest_cannot_access',
					'message' => __( 'L’énumération des utilisateurs via l’API est interdite.', 'gi-toolkit' ),
					'data'    => array( 'status' => 401 ),
				),
				401
			);
		}

		return $response;
	}
}
