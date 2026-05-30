<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronisation des statuts GI-Toolkit (AJAX, site par site).
 */
class MainWP_GIWeb_Sync_Ajax {

	const NONCE_ACTION = 'mainwp_giweb_sync';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_mainwp_giweb_sync_init', array( __CLASS__, 'ajax_init' ) );
		add_action( 'wp_ajax_mainwp_giweb_sync_site', array( __CLASS__, 'ajax_site' ) );
		add_action( 'wp_ajax_mainwp_giweb_pull_config', array( __CLASS__, 'ajax_pull_config' ) );
		add_action( 'wp_ajax_mainwp_giweb_get_module_options', array( __CLASS__, 'ajax_get_module_options' ) );
		add_action( 'wp_ajax_mainwp_giweb_save_module_options', array( __CLASS__, 'ajax_save_module_options' ) );
		add_action( 'wp_ajax_mainwp_giweb_save_template', array( __CLASS__, 'ajax_save_template' ) );
		add_action( 'wp_ajax_mainwp_giweb_delete_template', array( __CLASS__, 'ajax_delete_template' ) );
		add_action( 'wp_ajax_mainwp_giweb_save_working_modules', array( __CLASS__, 'ajax_save_working_modules' ) );
		add_action( 'wp_ajax_mainwp_giweb_deploy_init', array( __CLASS__, 'ajax_deploy_init' ) );
		add_action( 'wp_ajax_mainwp_giweb_deploy_site', array( __CLASS__, 'ajax_deploy_site' ) );
		add_action( 'wp_ajax_mainwp_giweb_deploy_cleanup', array( __CLASS__, 'ajax_deploy_cleanup' ) );
		add_action( 'wp_ajax_mainwp_giweb_get_deployment_detail', array( __CLASS__, 'ajax_get_deployment_detail' ) );
		add_action( 'wp_ajax_mainwp_giweb_plugin_deploy_init', array( __CLASS__, 'ajax_plugin_deploy_init' ) );
		add_action( 'wp_ajax_mainwp_giweb_plugin_deploy_site', array( __CLASS__, 'ajax_plugin_deploy_site' ) );
		add_action( 'wp_ajax_mainwp_giweb_plugin_deploy_cleanup', array( __CLASS__, 'ajax_plugin_deploy_cleanup' ) );
	}

	/**
	 * @param string $template_id ID modèle ou vide.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_deploy_bundle( $template_id ) {
		$template_id = sanitize_text_field( $template_id );
		$bundle      = MainWP_GIWeb_Bundle::get();
		if ( '' !== $template_id ) {
			$tpl = MainWP_GIWeb_Templates::get( $template_id );
			if ( $tpl && ! empty( $tpl['bundle'] ) && is_array( $tpl['bundle'] ) ) {
				$bundle = $tpl['bundle'];
			}
		}
		if ( empty( $bundle ) || empty( $bundle['modules'] ) ) {
			return null;
		}
		return MainWP_GIWeb_Uptime_Kuma::merge_into_bundle( MainWP_GIWeb_Matomo::merge_into_bundle( $bundle ) );
	}

	/**
	 * @param int $deployment_id ID déploiement.
	 * @return string
	 */
	private static function deploy_transient_key( $deployment_id ) {
		return 'mainwp_giweb_deploy_' . absint( $deployment_id );
	}

	/**
	 * Import avec une nouvelle tentative si MainWP ne joint pas le site enfant.
	 *
	 * @param int                  $site_id ID site.
	 * @param array<string, mixed> $bundle  Bundle.
	 * @param array<string, mixed> $args    Args import.
	 * @return array<string, mixed>
	 */
	/**
	 * @param string $job_id ID tâche déploiement plugin.
	 * @return string
	 */
	private static function plugin_deploy_transient_key( $job_id ) {
		return 'mainwp_giweb_plugin_deploy_' . sanitize_key( $job_id );
	}

	/**
	 * Prépare l’environnement pour les requêtes AJAX longues.
	 *
	 * @return void
	 */
	private static function bootstrap_ajax() {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 );
		}
	}

	/**
	 * @return void
	 */
	private static function verify_request() {
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			wp_send_json_error(
				array( 'message' => __( 'Accès refusé.', 'mainwp-giweb' ) ),
				403
			);
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/**
	 * @return object|null
	 */
	private static function activator() {
		global $mainwp_giweb_activator;
		if ( ! $mainwp_giweb_activator ) {
			return null;
		}
		if ( empty( $mainwp_giweb_activator->childKey ) && method_exists( $mainwp_giweb_activator, 'activate_extension' ) ) {
			$mainwp_giweb_activator->activate_extension();
		}
		return $mainwp_giweb_activator;
	}

	/**
	 * @param Throwable|Exception $e Exception.
	 * @return void
	 */
	private static function send_exception( $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MainWP GI-Toolkit AJAX: ' . $e->getMessage() );
		}
		wp_send_json_error(
			array(
				'message' => __( 'Erreur serveur lors du traitement. Consultez les logs PHP.', 'mainwp-giweb' ),
			)
		);
	}

	/**
	 * Prépare la liste des sites pour une resynchronisation (conserve le cache existant).
	 *
	 * @return void
	 */
	public static function ajax_init() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$act = self::activator();
			if ( ! $act ) {
				wp_send_json_error(
					array( 'message' => __( 'Extension MainWP non initialisée.', 'mainwp-giweb' ) )
				);
			}

			$sites_out = array();
			foreach ( MainWP_GIWeb_Sites::fetch_all( $act ) as $site ) {
				$row = MainWP_GIWeb_Sites::normalize_one( $site );
				if ( $row['id'] <= 0 ) {
					continue;
				}
				$sites_out[] = array(
					'id'   => $row['id'],
					'name' => $row['name'] ?: $row['url'] ?: ( '#' . $row['id'] ),
					'url'  => $row['url'],
				);
			}

			MainWP_GIWeb_Status_Cache::mark_sync_started();
			update_option( MainWP_GIWeb_Mail_Stats::AGGREGATE_OPTION, array( 'sites' => array(), 'network' => MainWP_GIWeb_Mail_Stats::compute_network( array() ), 'updated_at' => 0 ), false );
			MainWP_GIWeb_Mail_Stats::clear_alert();

			wp_send_json_success(
				array(
					'sites' => $sites_out,
					'total' => count( $sites_out ),
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Interroge un site enfant et met à jour le cache.
	 *
	 * @return void
	 */
	public static function ajax_site() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;
			if ( ! $site_id ) {
				wp_send_json_error(
					array( 'message' => __( 'ID de site invalide.', 'mainwp-giweb' ) )
				);
			}

			$label  = isset( $_POST['site_label'] ) ? sanitize_text_field( wp_unslash( $_POST['site_label'] ) ) : ( '#' . $site_id );
			$result = MainWP_GIWeb_Deploy::sync_site_status( $site_id, $label );

			MainWP_GIWeb_Status_Cache::set_site( $site_id, $result['api'] );

			$row = MainWP_GIWeb_Sites::find_by_id( $site_id, self::activator() );
			$url = is_array( $row ) ? (string) ( $row['url'] ?? '' ) : '';
			MainWP_GIWeb_Mail_Stats::record_site_sync( $site_id, $label, $url, $result['api'] );
			MainWP_GIWeb_Uptime_Kuma_Widget::schedule_refresh_on_sync();

			$result['mail_summary'] = MainWP_GIWeb_Mail_Stats::get_client_summary();
			$result['mail_html']    = MainWP_GIWeb_Mail_Stats::format_site_mail_cell(
				MainWP_GIWeb_Mail_Stats::extract_mail( $result['data'] ?? array() )
			);

			wp_send_json_success( $result );
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Importe la configuration d’un site enfant dans le bundle de travail.
	 *
	 * @return void
	 */
	public static function ajax_pull_config() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;
			if ( ! $site_id ) {
				wp_send_json_error(
					array( 'message' => __( 'ID de site invalide.', 'mainwp-giweb' ) )
				);
			}

			$label = isset( $_POST['site_label'] ) ? sanitize_text_field( wp_unslash( $_POST['site_label'] ) ) : ( '#' . $site_id );

			$status = MainWP_GIWeb_API::get_status( $site_id );
			if ( empty( $status['success'] ) ) {
				$raw = ! empty( $status['errors'][0] ) ? (string) $status['errors'][0] : '';
				wp_send_json_error(
					array(
						'message'   => MainWP_GIWeb_API::format_site_error( $site_id, $label, $raw ),
						'code'      => 'mainwp_unreachable',
						'preflight' => 'status',
						'raw'       => $raw,
					)
				);
			}

			$res    = MainWP_GIWeb_API::export_site( $site_id );
			$bundle = MainWP_GIWeb_Bundle::from_api_response( $res );

			if ( null === $bundle ) {
				$raw = ! empty( $res['errors'][0] ) ? (string) $res['errors'][0] : '';
				wp_send_json_error(
					array(
						'message'   => MainWP_GIWeb_API::format_site_error( $site_id, $label, $raw ),
						'code'      => 'export_failed',
						'preflight' => 'export',
						'raw'       => $raw,
						'status_ok' => true,
					)
				);
			}

			$saved = MainWP_GIWeb_Bundle::save( $bundle );
			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
			}

			$counts = MainWP_GIWeb_Bundle::count_modules( $bundle );

			wp_send_json_success(
				array(
					'message'      => sprintf(
						/* translators: 1: site name, 2: module count, 3: active count */
						__( 'Configuration importée depuis %1$s : %2$d modules (%3$d actifs).', 'mainwp-giweb' ),
						$label,
						$counts['total'],
						$counts['active']
					),
					'module_count' => $counts['total'],
					'active_count' => $counts['active'],
					'site_name'    => $label,
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Retourne les réglages importés d’un module (JSON masqué).
	 *
	 * @return void
	 */
	public static function ajax_get_module_options() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$class = isset( $_POST['module_class'] ) ? sanitize_text_field( wp_unslash( $_POST['module_class'] ) ) : '';
			if ( '' === $class ) {
				wp_send_json_error( array( 'message' => __( 'Module invalide.', 'mainwp-giweb' ) ) );
			}

			$options = MainWP_GIWeb_Bundle::get_module_options( $class );
			if ( null === $options ) {
				wp_send_json_error(
					array(
						'message' => __( 'Aucun réglage importé pour ce module. Réimportez la configuration depuis un site.', 'mainwp-giweb' ),
					)
				);
			}

			$is_css = MainWP_GIWeb_Module_Options::is_css_module( $class );
			if ( $is_css ) {
				$options = MainWP_GIWeb_Module_Options::normalize_css_options( $class, $options );
			}

			$masked = MainWP_GIWeb_Bundle::mask_sensitive_for_display( $options );
			$name   = $class;
			if ( MainWP_GIWeb_Catalog::load_modules_data() ) {
				$mods = MainWP_GIWeb_Catalog::get_modules();
				$name = $mods[ $class ]['name'] ?? $class;
			}

			$payload = array(
				'module_class' => $class,
				'module_name'  => $name,
				'editor_type'  => $is_css ? 'css' : 'json',
				'json'         => wp_json_encode( $masked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			);

			if ( $is_css ) {
				$payload['css_field'] = MainWP_GIWeb_Module_Options::FIELD_KEY;
				$payload['css_label'] = MainWP_GIWeb_Module_Options::css_modules()[ $class ] ?? __( 'CSS', 'mainwp-giweb' );
				$payload['css_value'] = $options[ MainWP_GIWeb_Module_Options::FIELD_KEY ] ?? '';
				$payload['editable']  = true;
			}

			wp_send_json_success( $payload );
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Enregistre les options d’un module dans le bundle de travail (édition dashboard).
	 *
	 * @return void
	 */
	public static function ajax_save_module_options() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$class = isset( $_POST['module_class'] ) ? sanitize_text_field( wp_unslash( $_POST['module_class'] ) ) : '';
			if ( '' === $class || ! MainWP_GIWeb_Module_Options::is_css_module( $class ) ) {
				wp_send_json_error( array( 'message' => __( 'Module non éditable depuis le dashboard.', 'mainwp-giweb' ) ) );
			}

			$css = isset( $_POST['css_value'] ) ? wp_unslash( $_POST['css_value'] ) : '';
			if ( ! is_string( $css ) ) {
				$css = '';
			}

			$options = MainWP_GIWeb_Module_Options::normalize_css_options(
				$class,
				array( MainWP_GIWeb_Module_Options::FIELD_KEY => MainWP_GIWeb_Module_Options::sanitize_css( $css ) )
			);

			$saved = MainWP_GIWeb_Bundle::set_module_options( $class, $options );
			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'message'      => __( 'CSS enregistré dans la configuration de travail. Déployez pour l’appliquer aux sites.', 'mainwp-giweb' ),
					'module_class' => $class,
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Enregistre un modèle depuis le bundle de travail.
	 *
	 * @return void
	 */
	public static function ajax_save_template() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
			if ( '' === $name ) {
				wp_send_json_error( array( 'message' => __( 'Nom du modèle requis.', 'mainwp-giweb' ) ) );
			}

			$bundle = MainWP_GIWeb_Bundle::get();
			if ( empty( $bundle ) ) {
				wp_send_json_error( array( 'message' => __( 'Aucune configuration de travail à enregistrer.', 'mainwp-giweb' ) ) );
			}

			$id  = MainWP_GIWeb_Templates::save( $name, $bundle );
			$tpl = MainWP_GIWeb_Templates::get( $id );

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: template name */
						__( 'Modèle « %s » enregistré.', 'mainwp-giweb' ),
						$name
					),
					'template' => array(
						'id'         => $id,
						'name'       => $tpl['name'] ?? $name,
						'created_at' => $tpl['created_at'] ?? '',
						'hash'       => substr( $tpl['hash'] ?? '', 0, 8 ),
					),
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Supprime un modèle.
	 *
	 * @return void
	 */
	public static function ajax_delete_template() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$tpl_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';
			if ( '' === $tpl_id || ! MainWP_GIWeb_Templates::delete( $tpl_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Impossible de supprimer le modèle.', 'mainwp-giweb' ) ) );
			}

			wp_send_json_success(
				array(
					'message'     => __( 'Modèle supprimé.', 'mainwp-giweb' ),
					'template_id' => $tpl_id,
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Enregistre l’état actif/inactif des modules (bundle de travail).
	 *
	 * @return void
	 */
	public static function ajax_save_working_modules() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$states = isset( $_POST['module_states'] ) && is_array( $_POST['module_states'] )
				? wp_unslash( $_POST['module_states'] )
				: array();

			$bundle = MainWP_GIWeb_Bundle::get();
			if ( empty( $bundle['modules'] ) || ! is_array( $bundle['modules'] ) ) {
				$bundle['modules'] = array();
			}

			foreach ( $states as $class => $val ) {
				$class = sanitize_text_field( $class );
				if ( '' === $class ) {
					continue;
				}
				if ( ! isset( $bundle['modules'][ $class ] ) ) {
					$bundle['modules'][ $class ] = array();
				}
				$bundle['modules'][ $class ]['active'] = ( '1' === (string) $val || true === $val ) ? '1' : '0';
			}

			$saved = MainWP_GIWeb_Bundle::save( $bundle );
			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Configuration de travail enregistrée.', 'mainwp-giweb' ),
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Prépare un déploiement multi-sites.
	 *
	 * @return void
	 */
	public static function ajax_deploy_init() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$site_ids = array();
			if ( isset( $_POST['selected_sites'] ) && is_array( $_POST['selected_sites'] ) ) {
				$site_ids = array_map( 'absint', wp_unslash( $_POST['selected_sites'] ) );
			} elseif ( isset( $_POST['site_ids'] ) && is_array( $_POST['site_ids'] ) ) {
				$site_ids = array_map( 'absint', wp_unslash( $_POST['site_ids'] ) );
			}
			$site_ids = array_values( array_filter( $site_ids ) );

			if ( empty( $site_ids ) ) {
				wp_send_json_error( array( 'message' => __( 'Sélectionnez au moins un site cible.', 'mainwp-giweb' ) ) );
			}

			$tpl_id = isset( $_POST['deploy_template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['deploy_template_id'] ) ) : '';
			$bundle = self::resolve_deploy_bundle( $tpl_id );
			if ( null === $bundle ) {
				wp_send_json_error( array( 'message' => __( 'Aucune configuration à déployer. Importez ou choisissez un modèle.', 'mainwp-giweb' ) ) );
			}

			$tpl_name      = $tpl_id ? ( MainWP_GIWeb_Templates::get( $tpl_id )['name'] ?? '' ) : __( 'Configuration courante', 'mainwp-giweb' );
			$hash          = md5( wp_json_encode( $bundle ) );
			$deployment_id = MainWP_GIWeb_History::create_deployment( $tpl_id, $tpl_name, $hash );

			set_transient(
				self::deploy_transient_key( $deployment_id ),
				array(
					'bundle'        => $bundle,
					'template_id'   => $tpl_id,
					'template_name' => $tpl_name,
				),
				HOUR_IN_SECONDS
			);

			$act        = self::activator();
			$sites_out  = array();
			foreach ( $site_ids as $site_id ) {
				$row = MainWP_GIWeb_Sites::find_by_id( $site_id, $act );
				$sites_out[] = array(
					'id'   => $site_id,
					'name' => $row['name'] ?: $row['url'] ?: ( '#' . $site_id ),
				);
			}

			wp_send_json_success(
				array(
					'deployment_id' => $deployment_id,
					'sites'         => $sites_out,
					'total'         => count( $sites_out ),
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Déploie vers un site enfant.
	 *
	 * @return void
	 */
	public static function ajax_deploy_site() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$deployment_id = isset( $_POST['deployment_id'] ) ? absint( $_POST['deployment_id'] ) : 0;
			$site_id       = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

			if ( ! $deployment_id || ! $site_id ) {
				wp_send_json_error( array( 'message' => __( 'Paramètres de déploiement invalides.', 'mainwp-giweb' ) ) );
			}

			$ctx = get_transient( self::deploy_transient_key( $deployment_id ) );
			if ( ! is_array( $ctx ) || empty( $ctx['bundle'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Session de déploiement expirée. Relancez le déploiement.', 'mainwp-giweb' ) ) );
			}

			$label  = isset( $_POST['site_label'] ) ? sanitize_text_field( wp_unslash( $_POST['site_label'] ) ) : ( '#' . $site_id );
			$bundle = MainWP_GIWeb_Uptime_Kuma::merge_into_bundle( MainWP_GIWeb_Matomo::merge_into_bundle( $ctx['bundle'] ) );
			$args   = MainWP_GIWeb_Overrides::apply_to_bundle( $bundle, $site_id );
			$result = MainWP_GIWeb_API::import_site( $site_id, $bundle, $args );
			$ok     = ! empty( $result['success'] );
			$msg    = MainWP_GIWeb_API::format_deploy_result_message( $site_id, $label, $result, $ok );

			MainWP_GIWeb_History::log_site_result( $deployment_id, $site_id, $ok ? 'success' : 'error', $msg, $result );

			$log = sprintf(
				'[%s] %s — %s',
				$ok ? 'OK' : __( 'ERR', 'mainwp-giweb' ),
				$label,
				$msg
			);

			wp_send_json_success(
				array(
					'site_id' => $site_id,
					'success' => $ok,
					'log'     => $log,
					'message' => $msg,
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Détail d’un déploiement (évite deployment_id en query string → 403 WAF).
	 *
	 * @return void
	 */
	public static function ajax_get_deployment_detail() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$deployment_id = isset( $_POST['deployment_id'] ) ? absint( $_POST['deployment_id'] ) : 0;
			if ( ! $deployment_id ) {
				wp_send_json_error( array( 'message' => __( 'Déploiement invalide.', 'mainwp-giweb' ) ) );
			}

			$site_rows = MainWP_GIWeb_History::get_deployment_sites( $deployment_id );
			$sites_out = array();

			foreach ( $site_rows as $sr ) {
				$site_id = absint( $sr->site_id ?? 0 );
				$row     = MainWP_GIWeb_Sites::find_by_id( $site_id, self::activator() );
				$sites_out[] = array(
					'site_id' => $site_id,
					'name'    => $row['name'] ?: $row['url'] ?: ( '#' . $site_id ),
					'status'  => (string) ( $sr->status ?? '' ),
					'message' => (string) ( $sr->message ?? '' ),
				);
			}

			wp_send_json_success(
				array(
					'deployment_id' => $deployment_id,
					'sites'         => $sites_out,
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Libère le transient bundle après déploiement config parallèle.
	 *
	 * @return void
	 */
	public static function ajax_deploy_cleanup() {
		self::bootstrap_ajax();
		try {
			self::verify_request();
			$deployment_id = isset( $_POST['deployment_id'] ) ? absint( $_POST['deployment_id'] ) : 0;
			if ( $deployment_id > 0 ) {
				delete_transient( self::deploy_transient_key( $deployment_id ) );
			}
			wp_send_json_success();
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Prépare le déploiement du plugin GI-Toolkit sur tous les sites enfants.
	 *
	 * @return void
	 */
	public static function ajax_plugin_deploy_init() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$zip = MainWP_GIWeb_Plugin_Installer::validate_deploy_url();
			if ( empty( $zip['ok'] ) ) {
				wp_send_json_error( array( 'message' => $zip['message'] ) );
			}

			$act = self::activator();
			if ( ! $act ) {
				wp_send_json_error(
					array( 'message' => __( 'Extension MainWP non initialisée.', 'mainwp-giweb' ) )
				);
			}

			$sites_out = array();
			foreach ( MainWP_GIWeb_Sites::fetch_all( $act ) as $site ) {
				$row = MainWP_GIWeb_Sites::normalize_one( $site );
				if ( $row['id'] <= 0 ) {
					continue;
				}
				$sites_out[] = array(
					'id'   => $row['id'],
					'name' => $row['name'] ?: $row['url'] ?: ( '#' . $row['id'] ),
					'url'  => $row['url'],
				);
			}

			if ( empty( $sites_out ) ) {
				wp_send_json_error( array( 'message' => __( 'Aucun site enfant MainWP trouvé.', 'mainwp-giweb' ) ) );
			}

			$package_version = isset( $zip['package_version'] ) ? (string) $zip['package_version'] : MainWP_GIWeb_Zip::get_package_version();

			$job_id = wp_generate_password( 16, false, false );
			set_transient(
				self::plugin_deploy_transient_key( $job_id ),
				array(
					'zip_url'          => $zip['url'],
					'package_version'  => $package_version,
					'started_at'       => time(),
				),
				HOUR_IN_SECONDS
			);

			wp_send_json_success(
				array(
					'job_id'           => $job_id,
					'zip_url'          => $zip['url'],
					'package_version'  => $package_version,
					'sites'            => $sites_out,
					'total'            => count( $sites_out ),
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Installe ou met à jour GI-Toolkit sur un site enfant.
	 *
	 * @return void
	 */
	public static function ajax_plugin_deploy_site() {
		self::bootstrap_ajax();
		try {
			self::verify_request();

			$job_id  = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
			$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

			if ( '' === $job_id || ! $site_id ) {
				wp_send_json_error( array( 'message' => __( 'Paramètres invalides.', 'mainwp-giweb' ) ) );
			}

			$ctx = get_transient( self::plugin_deploy_transient_key( $job_id ) );
			if ( ! is_array( $ctx ) || empty( $ctx['zip_url'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Session de déploiement expirée. Relancez l’opération.', 'mainwp-giweb' ) ) );
			}

			$label           = isset( $_POST['site_label'] ) ? sanitize_text_field( wp_unslash( $_POST['site_label'] ) ) : ( '#' . $site_id );
			$target_version  = isset( $ctx['package_version'] ) ? (string) $ctx['package_version'] : MainWP_GIWeb_Zip::get_package_version();
			$result          = MainWP_GIWeb_Plugin_Installer::deploy_gi_toolkit( $site_id );
			$ok              = ! empty( $result['success'] );
			$msg             = $result['message'] ?? ( $ok ? __( 'OK', 'mainwp-giweb' ) : __( 'Échec', 'mainwp-giweb' ) );
			$installed       = isset( $result['installed_version'] ) ? (string) $result['installed_version'] : '';

			if ( ! $ok ) {
				$msg = MainWP_GIWeb_API::format_site_error( $site_id, $label, $msg );
			}

			$log = MainWP_GIWeb_Plugin_Installer::format_deploy_log_line(
				$label,
				$msg,
				$ok,
				$installed,
				$target_version
			);

			wp_send_json_success(
				array(
					'site_id'           => $site_id,
					'success'           => $ok,
					'log'               => $log,
					'message'           => $msg,
					'installed_version' => $installed,
					'target_version'    => $target_version,
				)
			);
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}

	/**
	 * Termine une session de déploiement plugin (après le pool parallèle).
	 *
	 * @return void
	 */
	public static function ajax_plugin_deploy_cleanup() {
		self::bootstrap_ajax();
		try {
			self::verify_request();
			$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
			if ( '' !== $job_id ) {
				delete_transient( self::plugin_deploy_transient_key( $job_id ) );
			}
			wp_send_json_success();
		} catch ( Throwable $e ) {
			self::send_exception( $e );
		} catch ( Exception $e ) {
			self::send_exception( $e );
		}
	}
}

MainWP_GIWeb_Sync_Ajax::init();
