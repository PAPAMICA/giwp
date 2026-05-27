<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registre des modules dont les réglages exportables sont du CSS personnalisé.
 */
class Gi_Toolkit_Module_Css_Options {

	const FIELD_KEY = 'code_snippet';

	/**
	 * @return array<string, array{option: string, label: string}>
	 */
	public static function modules() {
		$modules = array(
			'Gi_Toolkit_Custom_Login_Design'   => array(
				'option' => 'gi_toolkit_login_custom_css',
				'label'  => __( 'CSS page de connexion', 'gi-toolkit' ),
			),
			'Gi_Toolkit_Custom_Admin_CSS'     => array(
				'option' => '',
				'label'  => __( 'CSS administration', 'gi-toolkit' ),
			),
			'Gi_Toolkit_Custom_Frontend_CSS' => array(
				'option' => '',
				'label'  => __( 'CSS front-end', 'gi-toolkit' ),
			),
		);

		if ( defined( 'GI_TOOLKIT_PLUGIN_SETTINGS' ) ) {
			$modules['Gi_Toolkit_Custom_Admin_CSS']['option']     = GI_TOOLKIT_PLUGIN_SETTINGS . '_custom_admin_css';
			$modules['Gi_Toolkit_Custom_Frontend_CSS']['option'] = GI_TOOLKIT_PLUGIN_SETTINGS . '_custom_frontend_css';
		}

		return $modules;
	}

	/**
	 * @param string $class Nom de classe module.
	 * @return bool
	 */
	public static function is_css_module( $class ) {
		return isset( self::modules()[ $class ] );
	}

	/**
	 * @param string $class Nom de classe.
	 * @return array<string, mixed>|null
	 */
	public static function get_settings_for_class( $class ) {
		if ( ! self::is_css_module( $class ) ) {
			return null;
		}

		if ( class_exists( $class ) && method_exists( $class, 'get_settings' ) ) {
			try {
				$instance = new $class();
				$method   = new ReflectionMethod( $class, 'get_settings' );
				$method->setAccessible( true );
				$settings = $method->invoke( $instance );
				if ( is_array( $settings ) ) {
					return self::normalize_settings( $settings );
				}
			} catch ( ReflectionException $e ) {
				// Lecture directe de l’option ci-dessous.
			}
		}

		$meta   = self::modules()[ $class ];
		$stored = get_option( $meta['option'], array() );

		if ( is_array( $stored ) && array_key_exists( self::FIELD_KEY, $stored ) ) {
			return array( self::FIELD_KEY => (string) $stored[ self::FIELD_KEY ] );
		}

		if ( is_string( $stored ) ) {
			return array( self::FIELD_KEY => $stored );
		}

		return array( self::FIELD_KEY => '' );
	}

	/**
	 * @param array<string, mixed> $settings Réglages bruts.
	 * @return array{code_snippet: string}
	 */
	private static function normalize_settings( $settings ) {
		if ( isset( $settings[ self::FIELD_KEY ] ) ) {
			return array( self::FIELD_KEY => (string) $settings[ self::FIELD_KEY ] );
		}
		if ( isset( $settings['custom_css'] ) ) {
			return array( self::FIELD_KEY => (string) $settings['custom_css'] );
		}
		return array( self::FIELD_KEY => '' );
	}

	/**
	 * @param string               $class    Classe module.
	 * @param array<string, mixed> $settings Réglages.
	 * @return bool
	 */
	public static function save_settings_for_class( $class, $settings ) {
		if ( ! self::is_css_module( $class ) || ! is_array( $settings ) ) {
			return false;
		}

		$css = '';
		if ( isset( $settings[ self::FIELD_KEY ] ) ) {
			$css = (string) $settings[ self::FIELD_KEY ];
		} elseif ( isset( $settings['custom_css'] ) ) {
			$css = (string) $settings['custom_css'];
		}

		$payload = array( self::FIELD_KEY => self::sanitize_css( $css ) );

		if ( class_exists( $class ) && method_exists( $class, 'save_settings' ) ) {
			return Gi_Toolkit_Settings::invoke_module_save_settings( $class, $payload );
		}

		$meta = self::modules()[ $class ];
		if ( '' === $meta['option'] ) {
			return false;
		}

		if ( 'gi_toolkit_login_custom_css' === $meta['option'] ) {
			update_option( $meta['option'], $payload[ self::FIELD_KEY ], false );
			return true;
		}

		update_option( $meta['option'], $payload, false );
		return true;
	}

	/**
	 * @param string $css CSS brut.
	 * @return string
	 */
	public static function sanitize_css( $css ) {
		return wp_strip_all_tags( (string) $css );
	}
}
