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
			case 'mail':
				return self::handle_mail( $data );
			case 'ai_agent_cache_purge':
				return self::handle_ai_agent_cache_purge();
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

		if ( class_exists( 'Gi_Toolkit_Matomo' ) ) {
			if ( ! class_exists( 'Gi_Toolkit_Matomo_API', false ) && defined( 'GI_TOOLKIT_PLUGIN_PATH' ) ) {
				require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
			}
			$matomo_settings   = Gi_Toolkit_Matomo::get_settings_static();
			$matomo_url        = class_exists( 'Gi_Toolkit_Matomo_API', false )
				? Gi_Toolkit_Matomo_API::normalize_matomo_url( $matomo_settings['matomo_url'] ?? '' )
				: trim( (string) ( $matomo_settings['matomo_url'] ?? '' ) );
			$payload['matomo'] = array(
				'configured' => class_exists( 'Gi_Toolkit_Matomo_API', false )
					? ( new Gi_Toolkit_Matomo_API( $matomo_settings ) )->is_configured()
					: ( '' !== $matomo_url && '' !== trim( (string) ( $matomo_settings['api_token'] ?? '' ) ) ),
				'site_id'    => absint( $matomo_settings['site_id'] ?? 0 ),
				'auto_site'  => (string) ( $matomo_settings['auto_site'] ?? '1' ),
				'has_url'    => '' !== $matomo_url,
			);
		}

		if ( class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			$kuma_settings = Gi_Toolkit_Uptime_Kuma::get_settings_static();
			$kuma_url        = Gi_Toolkit_Uptime_Kuma_API::normalize_kuma_url( $kuma_settings['kuma_url'] ?? '' );
			$payload['uptime_kuma'] = array(
				'configured'  => ( new Gi_Toolkit_Uptime_Kuma_API( $kuma_settings ) )->is_configured(),
				'monitor_id'  => absint( $kuma_settings['monitor_id'] ?? 0 ),
				'auto_monitor' => (string) ( $kuma_settings['auto_monitor'] ?? '1' ),
				'has_url'     => '' !== $kuma_url,
			);
		}

		if ( ! class_exists( 'Gi_Toolkit_UpdraftPlus_Status', false ) && defined( 'GI_TOOLKIT_PLUGIN_PATH' ) ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-updraftplus-status.php';
		}
		if ( class_exists( 'Gi_Toolkit_UpdraftPlus_Status' ) ) {
			$payload['updraftplus'] = Gi_Toolkit_UpdraftPlus_Status::get_mainwp_status_payload();
		}

		return $payload;
	}

	/**
	 * Statistiques Mail Catcher pour MainWP (action dédiée, plus légère que status).
	 *
	 * @param array<string, mixed> $data Payload requête.
	 * @return array<string, mixed>
	 */
	private static function handle_mail( $data = array() ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! class_exists( 'Gi_Toolkit_Mail_Catcher' ) ) {
			return self::success(
				array(
					'module_active' => false,
				)
			);
		}

		$limit = isset( $data['failures_limit'] ) ? absint( $data['failures_limit'] ) : 5;
		$limit = max( 1, min( 20, $limit ) );

		return self::success( Gi_Toolkit_Mail_Catcher::get_mainwp_status_payload( $limit ) );
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
		$can_save = method_exists( $class, 'save_settings' )
			|| ( class_exists( 'Gi_Toolkit_Module_Css_Options' ) && Gi_Toolkit_Module_Css_Options::is_css_module( $class ) );
		if ( ! $can_save ) {
			return self::error( __( 'Ce module ne supporte pas la sauvegarde distante.', 'gi-toolkit' ) );
		}
		if ( 'Gi_Toolkit_Matomo' === $class && is_array( $options ) && class_exists( 'Gi_Toolkit_Matomo' ) ) {
			$deploy = Gi_Toolkit_Matomo::deploy_from_mainwp( $options );
			if ( empty( $deploy['success'] ) ) {
				return self::error( $deploy['message'] ?? __( 'Échec du déploiement Matomo.', 'gi-toolkit' ) );
			}
			return self::success(
				array(
					'saved'   => true,
					'matomo'  => array(
						'site_id' => (int) ( $deploy['site_id'] ?? 0 ),
						'message' => (string) ( $deploy['message'] ?? '' ),
					),
				)
			);
		}

		if ( 'Gi_Toolkit_Uptime_Kuma' === $class && is_array( $options ) && class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			$deploy = Gi_Toolkit_Uptime_Kuma::deploy_from_mainwp( $options );
			if ( empty( $deploy['success'] ) ) {
				return self::error( $deploy['message'] ?? __( 'Échec du déploiement Uptime Kuma.', 'gi-toolkit' ) );
			}
			return self::success(
				array(
					'saved'       => true,
					'uptime_kuma' => array(
						'monitor_id' => (int) ( $deploy['monitor_id'] ?? 0 ),
						'message'    => (string) ( $deploy['message'] ?? '' ),
						'warning'    => (string) ( $deploy['warning'] ?? '' ),
					),
				)
			);
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
	 * Purge le cache via le module AI Agent Tool (accessible aussi depuis MainWP,
	 * sans passer par la clé API REST dédiée — utile pour un déclenchement
	 * groupé depuis le Dashboard MainWP).
	 *
	 * @return array<string, mixed>
	 */
	private static function handle_ai_agent_cache_purge() {
		if ( ! class_exists( 'Gi_Toolkit_AI_Agent_Tool', false ) && defined( 'GI_TOOLKIT_PLUGIN_PATH' ) ) {
			$path = gi_toolkit_resolve_module_path( 'core/class-ai-agent-tool.php' );
			if ( $path && is_file( $path ) ) {
				require_once $path;
			}
		}
		if ( ! class_exists( 'Gi_Toolkit_AI_Agent_Tool' ) ) {
			return self::error( __( 'Module AI Agent Tool indisponible.', 'gi-toolkit' ) );
		}

		$tool     = new Gi_Toolkit_AI_Agent_Tool();
		$response = $tool->route_cache_purge( new WP_REST_Request() );
		$data     = $response instanceof WP_REST_Response ? $response->get_data() : $response;

		return self::success( is_array( $data ) ? ( $data['data'] ?? $data ) : array() );
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
