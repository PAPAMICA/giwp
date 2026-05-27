<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modules dont les réglages sont du CSS éditable depuis le dashboard.
 */
class MainWP_GIWeb_Module_Options {

	const FIELD_KEY = 'code_snippet';

	/**
	 * @return array<string, string> class => label
	 */
	public static function css_modules() {
		return array(
			'Gi_Toolkit_Custom_Login_Design'   => __( 'CSS page de connexion', 'mainwp-giweb' ),
			'Gi_Toolkit_Custom_Admin_CSS'     => __( 'CSS administration', 'mainwp-giweb' ),
			'Gi_Toolkit_Custom_Frontend_CSS' => __( 'CSS front-end', 'mainwp-giweb' ),
		);
	}

	/**
	 * @param string $class Classe module.
	 * @return bool
	 */
	public static function is_css_module( $class ) {
		return isset( self::css_modules()[ $class ] );
	}

	/**
	 * @param string               $class   Classe module.
	 * @param array<string, mixed> $options Options brutes.
	 * @return array{code_snippet: string}
	 */
	public static function normalize_css_options( $class, $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$css = '';
		if ( isset( $options[ self::FIELD_KEY ] ) ) {
			$css = (string) $options[ self::FIELD_KEY ];
		} elseif ( isset( $options['custom_css'] ) ) {
			$css = (string) $options['custom_css'];
		}

		return array( self::FIELD_KEY => $css );
	}

	/**
	 * @param string $css CSS brut.
	 * @return string
	 */
	public static function sanitize_css( $css ) {
		return wp_strip_all_tags( (string) $css );
	}
}
