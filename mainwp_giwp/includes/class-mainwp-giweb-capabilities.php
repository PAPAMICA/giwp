<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contrôle d’accès compatible MainWP Team Control.
 */
class MainWP_GIWeb_Capabilities {

	/** @var string */
	const EXTENSION_SLUG = 'mainwp-giwp';

	/** @var string Capability WordPress pour la page admin cachée. */
	const ADMIN_CAP = 'mainwp_giweb_manage';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_user_has_cap' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'register_hidden_admin_page' ), 99 );
	}

	/**
	 * Enregistre la page admin pour que les URLs ?page=Extensions-…&tab=… ne renvoient pas 403.
	 *
	 * @return void
	 */
	public static function register_hidden_admin_page() {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator || empty( $mainwp_giweb_activator->childEnabled ) ) {
			return;
		}

		add_submenu_page(
			'',
			MainWP_GIWeb_UI::page_title(),
			MainWP_GIWeb_UI::page_title(),
			self::ADMIN_CAP,
			MainWP_GIWeb_UI::PAGE_SLUG,
			array( 'MainWP_GIWeb', 'render_page' )
		);
	}

	/**
	 * Accorde la cap admin aux utilisateurs autorisés par MainWP Team Control.
	 *
	 * @param array<string, bool> $allcaps Capabilities utilisateur.
	 * @param array<int, string>  $caps    Caps demandées.
	 * @param array<int, mixed>   $args    Arguments user_has_cap.
	 * @param WP_User             $user    Utilisateur.
	 * @return array<string, bool>
	 */
	public static function filter_user_has_cap( $allcaps, $caps, $args, $user ) {
		unset( $args );

		if ( ! $user instanceof WP_User || ! self::user_can_access( $user ) ) {
			return $allcaps;
		}

		if ( ! self::is_extension_admin_request() && ! self::is_extension_ajax_request() ) {
			return $allcaps;
		}

		foreach ( (array) $caps as $cap ) {
			if ( in_array( $cap, array( self::ADMIN_CAP, 'manage_options' ), true ) ) {
				$allcaps[ $cap ] = true;
			}
		}

		$allcaps[ self::ADMIN_CAP ] = true;

		return $allcaps;
	}

	/**
	 * Accès à l’extension GI-Toolkit Manager (pages, AJAX, widget).
	 *
	 * @return bool
	 */
	public static function can_access() {
		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}
		return self::user_can_access( $user );
	}

	/**
	 * @param WP_User $user Utilisateur.
	 * @return bool
	 */
	private static function user_can_access( WP_User $user ) {
		if ( user_can( $user, 'manage_network' ) || user_can( $user, 'manage_options' ) ) {
			return true;
		}

		if ( ! function_exists( 'mainwp_current_user_can' ) ) {
			return false;
		}

		// mainwp_current_user_can() s’appuie sur l’utilisateur courant.
		if ( (int) get_current_user_id() !== (int) $user->ID ) {
			return false;
		}

		return self::mainwp_user_can_access();
	}

	/**
	 * Vérifie les droits MainWP (extension + dashboard).
	 *
	 * @return bool
	 */
	private static function mainwp_user_can_access() {
		foreach ( self::extension_slugs() as $slug ) {
			if ( mainwp_current_user_can( 'extension', $slug ) ) {
				return true;
			}
		}

		$dashboard_caps = array(
			'access_dashboard',
			'overview',
			'manage_sites',
			'manage_extensions',
			'manage_groups',
			'access_individual_dashboard',
			'manage_posts',
			'manage_pages',
		);

		foreach ( $dashboard_caps as $cap ) {
			if ( mainwp_current_user_can( 'dashboard', $cap ) ) {
				return true;
			}
		}

		/**
		 * Permet à d’autres extensions / Team Control d’autoriser l’accès.
		 *
		 * @param bool $allowed Accès courant.
		 */
		return (bool) apply_filters( 'mainwp_giweb_user_can_access', false );
	}

	/**
	 * Identifiants d’extension possibles (Team Control, clé enfant, slug API).
	 *
	 * @return array<int, string>
	 */
	private static function extension_slugs() {
		global $mainwp_giweb_activator;

		$slugs = array(
			self::EXTENSION_SLUG,
			'mainwp-giweb',
			'gi-toolkit-manager',
			'Mainwp-Gi-Toolkit-Manager',
			MainWP_GIWeb_UI::PAGE_SLUG,
		);

		if ( $mainwp_giweb_activator && ! empty( $mainwp_giweb_activator->childKey ) ) {
			$slugs[] = (string) $mainwp_giweb_activator->childKey;
		}

		if ( $mainwp_giweb_activator && ! empty( $mainwp_giweb_activator->plugin_handle ) ) {
			$slugs[] = (string) $mainwp_giweb_activator->plugin_handle;
		}

		$slugs = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $slug ) {
							return is_string( $slug ) ? trim( $slug ) : '';
						},
						$slugs
					)
				)
			)
		);

		return $slugs;
	}

	/**
	 * @return bool
	 */
	private static function is_extension_admin_request() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return MainWP_GIWeb_UI::is_extension_admin_page( $page );
	}

	/**
	 * @return bool
	 */
	private static function is_extension_ajax_request() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		return 0 === strpos( $action, 'mainwp_giweb_' );
	}
}
