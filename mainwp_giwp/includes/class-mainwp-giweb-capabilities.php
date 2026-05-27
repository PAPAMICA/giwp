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

	/** @var string Capability WordPress pour l’extension. */
	const ADMIN_CAP = 'mainwp_giweb_manage';

	/** @var int Profondeur d’appel MainWP (évite récursion). */
	private static $mainwp_check_depth = 0;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'filter_map_meta_cap' ), 10, 4 );
	}

	/**
	 * Autorise la capability custom sans hook user_has_cap (évite boucle infinie / OOM).
	 *
	 * @param array<int, string> $caps    Primitives requises.
	 * @param string             $cap     Capability demandée.
	 * @param int                $user_id ID utilisateur.
	 * @param array<int, mixed>  $args    Arguments.
	 * @return array<int, string>
	 */
	public static function filter_map_meta_cap( $caps, $cap, $user_id, $args ) {
		unset( $args );

		if ( self::ADMIN_CAP !== $cap ) {
			return $caps;
		}

		if ( self::user_id_has_access( (int) $user_id ) ) {
			return array( 'exist' );
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Accès à l’extension GI-Toolkit Manager (pages, AJAX, widget).
	 *
	 * @return bool
	 */
	public static function can_access() {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		return self::user_id_has_access( $user_id );
	}

	/**
	 * @param int $user_id ID utilisateur.
	 * @return bool
	 */
	private static function user_id_has_access( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		return self::user_has_access_raw( $user );
	}

	/**
	 * Vérifie l’accès sans appeler user_can() (évite récursion capabilities.php).
	 *
	 * @param WP_User $user Utilisateur.
	 * @return bool
	 */
	private static function user_has_access_raw( WP_User $user ) {
		if ( ! empty( $user->allcaps['manage_network'] ) || ! empty( $user->allcaps['manage_options'] ) ) {
			return true;
		}

		if ( ! function_exists( 'mainwp_current_user_can' ) ) {
			return false;
		}

		if ( (int) get_current_user_id() !== (int) $user->ID ) {
			return false;
		}

		if ( self::$mainwp_check_depth > 0 ) {
			return false;
		}

		++self::$mainwp_check_depth;
		$allowed = self::mainwp_user_can_access();
		--self::$mainwp_check_depth;

		return $allowed;
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
		);

		foreach ( $dashboard_caps as $cap ) {
			if ( mainwp_current_user_can( 'dashboard', $cap ) ) {
				return true;
			}
		}

		/**
		 * Permet à Team Control / autres extensions d’autoriser l’accès.
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

		return array_values(
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
	}
}
