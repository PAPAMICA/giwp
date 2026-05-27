<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Détection thème MainWP et classes UI.
 */
class MainWP_GIWeb_UI {

	const PAGE_SLUG = 'Extensions-Mainwp-Gi-Toolkit-Manager';

	/**
	 * Titre affiché dans l’en-tête MainWP et sur la page.
	 *
	 * @return string
	 */
	public static function page_title() {
		return __( 'GI-Toolkit Manager', 'mainwp-giweb' );
	}

	/**
	 * Page admin de cette extension (slug ?page=…).
	 *
	 * @param string|null $page Slug page (défaut : $_GET['page']).
	 * @return bool
	 */
	public static function is_extension_admin_page( $page = null ) {
		if ( null === $page && isset( $_GET['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}
		if ( ! is_string( $page ) || '' === $page ) {
			return false;
		}
		if ( self::PAGE_SLUG === $page ) {
			return true;
		}
		if ( 0 !== strpos( $page, 'Extensions-' ) ) {
			return false;
		}
		return ( false !== stripos( $page, 'gi-toolkit' ) )
			|| ( false !== stripos( $page, 'giweb' ) )
			|| ( false !== stripos( $page, 'giwp' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_dark_theme() {
		if ( class_exists( 'MainWP\Dashboard\MainWP_Settings' ) ) {
			$settings = MainWP\Dashboard\MainWP_Settings::get_instance();
			if ( is_object( $settings ) && method_exists( $settings, 'get_current_user_theme' ) ) {
				$theme = $settings->get_current_user_theme();
				if ( is_string( $theme ) && '' !== $theme ) {
					if ( 'default-dark' === $theme || false !== strpos( $theme, 'dark' ) ) {
						return true;
					}
					if ( 'default' === $theme || false !== strpos( $theme, 'light' ) ) {
						return false;
					}
				}
			}
		}

		$legacy = get_user_option( 'mainwp_settings_theme' );
		if ( is_string( $legacy ) && '' !== $legacy ) {
			return false !== strpos( $legacy, 'dark' );
		}

		return false;
	}

	/**
	 * @return string Classes pour le conteneur principal.
	 */
	public static function wrap_class_attr() {
		$classes = array(
			'wrap',
			'mainwp-giweb-wrap',
			self::is_dark_theme() ? 'mainwp-giweb--dark' : 'mainwp-giweb--light',
		);
		return implode( ' ', $classes );
	}

	/**
	 * URL de la page extension (sans rawurlencode du slug).
	 *
	 * @param array<string, string> $args Query args additionnels.
	 * @return string
	 */
	public static function admin_page_url( array $args = array() ) {
		$args = array_merge( array( 'page' => self::PAGE_SLUG ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
