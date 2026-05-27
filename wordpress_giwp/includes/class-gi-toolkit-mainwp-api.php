<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API distante pour MainWP (sites enfants).
 *
 * @since 2.20.0
 */
class Gi_Toolkit_MainWP_API {

	const MIN_VERSION = '2.19.0';

	/**
	 * Point d’entrée appelé par MainWP Child (fonction gi_toolkit).
	 *
	 * @param array<string, mixed> $data Payload POST.
	 * @return array<string, mixed>
	 */
	public static function handle_request( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$action = isset( $data['action'] ) ? sanitize_key( $data['action'] ) : '';

		switch ( $action ) {
			case 'status':
				return self::success( self::get_status() );
			case 'export':
				return self::handle_export();
			case 'import':
				$bundle = isset( $data['bundle'] ) && is_array( $data['bundle'] ) ? $data['bundle'] : array();
				$args   = isset( $data['args'] ) && is_array( $data['args'] ) ? $data['args'] : array();
				$result = Gi_Toolkit_Settings::import_config_bundle( $bundle, $args );
				if ( class_exists( 'Gi_Toolkit_Security' ) ) {
					Gi_Toolkit_Security::log( 'mainwp_import', array( 'success' => ! empty( $result['success'] ) ) );
				}
				return $result;
			case 'set_modules':
				$modules = isset( $data['modules'] ) && is_array( $data['modules'] ) ? $data['modules'] : array();
				return Gi_Toolkit_Settings::set_modules_state( $modules );
			case 'get_module_options':
				$class = isset( $data['module_class'] ) ? sanitize_text_field( $data['module_class'] ) : '';
				return self::get_module_options( $class );
			case 'set_module_options':
				$class   = isset( $data['module_class'] ) ? sanitize_text_field( $data['module_class'] ) : '';
				$options = isset( $data['options'] ) && is_array( $data['options'] ) ? $data['options'] : array();
				return self::set_module_options( $class, $options );
			default:
				return self::error( __( 'Action MainWP inconnue.', 'gi-toolkit' ) );
		}
	}

	/**
	 * Export configuration (limite temps / mémoire, erreurs explicites).
	 *
	 * @return array<string, mixed>
	 */
	private static function handle_export() {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}

		try {
			$bundle = Gi_Toolkit_Settings::export_config_bundle();
			if ( ! is_array( $bundle ) || empty( $bundle['modules'] ) ) {
				return self::error( __( 'Export vide ou invalide.', 'gi-toolkit' ) );
			}
			return self::success( $bundle );
		} catch ( Throwable $e ) {
			return self::error(
				sprintf(
					/* translators: %s: exception message */
					__( 'Erreur export GI-Toolkit : %s', 'gi-toolkit' ),
					$e->getMessage()
				)
			);
		} catch ( Exception $e ) {
			return self::error(
				sprintf(
					__( 'Erreur export GI-Toolkit : %s', 'gi-toolkit' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$db_options = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		$active     = 0;
		if ( is_array( $db_options ) ) {
			foreach ( $db_options as $status ) {
				if ( '1' === $status ) {
					++$active;
				}
			}
		}

		$payload = array(
			'gi_toolkit_version' => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '',
			'min_api_version'    => self::MIN_VERSION,
			'api_compatible'     => version_compare( defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '0', self::MIN_VERSION, '>=' ),
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => phpversion(),
			'active_modules'     => $active,
			'site_url'           => get_site_url(),
		);

		if ( class_exists( 'Gi_Toolkit_Mail_Catcher' ) ) {
			$payload['mail_catcher'] = Gi_Toolkit_Mail_Catcher::get_mainwp_status_payload();
		}

		return $payload;
	}

	/**
	 * @param string $class Nom de classe module.
	 * @return array<string, mixed>
	 */
	private static function get_module_options( $class ) {
		if ( '' === $class || ! class_exists( $class ) ) {
			return self::error( __( 'Module introuvable.', 'gi-toolkit' ) );
		}
		Gi_Toolkit_Handle_options::require_once_all_options();
		$options = Gi_Toolkit_Settings::invoke_module_get_settings( $class );
		return self::success( array( 'options' => null !== $options ? $options : array() ) );
	}

	/**
	 * @param string               $class   Classe module.
	 * @param array<string, mixed> $options Options.
	 * @return array<string, mixed>
	 */
	private static function set_module_options( $class, $options ) {
		if ( '' === $class || ! class_exists( $class ) ) {
			return self::error( __( 'Module introuvable.', 'gi-toolkit' ) );
		}
		Gi_Toolkit_Handle_options::require_once_all_options();
		if ( ! method_exists( $class, 'save_settings' ) ) {
			return self::error( __( 'Ce module ne supporte pas la sauvegarde distante.', 'gi-toolkit' ) );
		}
		if ( ! Gi_Toolkit_Settings::invoke_module_save_settings( $class, $options ) ) {
			return self::error( __( 'Échec de la sauvegarde des réglages du module.', 'gi-toolkit' ) );
		}
		return self::success( array( 'saved' => true ) );
	}

	/**
	 * @param array<string, mixed> $data Données.
	 * @return array<string, mixed>
	 */
	private static function success( $data ) {
		return array(
			'success' => true,
			'data'    => $data,
			'errors'  => array(),
		);
	}

	/**
	 * @param string $message Message.
	 * @return array<string, mixed>
	 */
	private static function error( $message ) {
		return array(
			'success' => false,
			'data'    => array(),
			'errors'  => array( $message ),
		);
	}
}
