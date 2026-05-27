<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface admin de l’extension MainWP GI-Web.
 */
class MainWP_GIWeb {

	/**
	 * Enregistre CSS/JS (hook admin_enqueue_scripts).
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! MainWP_GIWeb_UI::is_extension_admin_page() ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( 'modules' === $tab ) {
			MainWP_GIWeb_Modules_UI::enqueue_assets();
		}

		wp_enqueue_style(
			'mainwp-giweb-admin',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MAINWP_GIWEB_VERSION
		);
		wp_enqueue_style(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array( 'mainwp-giweb-admin' ),
			MAINWP_GIWEB_VERSION
		);
		wp_enqueue_script(
			'mainwp-giweb-admin',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MAINWP_GIWEB_VERSION,
			true
		);

		wp_localize_script(
			'mainwp-giweb-admin',
			'mainwpGiwebAdmin',
			self::script_config()
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function script_config() {
		return array(
			'debug'   => true,
			'version' => MAINWP_GIWEB_VERSION,
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( MainWP_GIWeb_Sync_Ajax::NONCE_ACTION ),
			'i18n'    => array(
				'syncTitle'      => __( 'Synchronisation des statuts', 'mainwp-giweb' ),
				'syncStarting'   => __( 'Préparation de la synchronisation…', 'mainwp-giweb' ),
				'syncConnecting' => __( 'Interrogation de %s…', 'mainwp-giweb' ),
				'syncDone'       => __( 'Synchronisation terminée.', 'mainwp-giweb' ),
				'syncNoSites'    => __( 'Aucun site enfant à synchroniser.', 'mainwp-giweb' ),
				'syncError'      => __( 'Erreur réseau ou serveur.', 'mainwp-giweb' ),
				'syncClose'      => __( 'Fermer', 'mainwp-giweb' ),
				'syncInProgress' => __( 'Synchronisation en cours…', 'mainwp-giweb' ),
				'progressLabel'  => __( '%1$d / %2$d sites', 'mainwp-giweb' ),
				'badgeOk'        => __( 'OK', 'mainwp-giweb' ),
				'badgeErr'       => __( 'Erreur', 'mainwp-giweb' ),
				'pullLoading'    => __( 'Import en cours depuis %s…', 'mainwp-giweb' ),
				'pullSuccess'    => __( 'Configuration importée.', 'mainwp-giweb' ),
				'pullError'      => __( 'Impossible d’importer la configuration.', 'mainwp-giweb' ),
				'pullJsMissing'  => __( 'Scripts non chargés : soumission du formulaire…', 'mainwp-giweb' ),
					'bundleLoaded'   => __( 'Une configuration est chargée en mémoire sur ce dashboard. Utilisez les onglets Modèles ou Déploiement pour la pousser vers d’autres sites.', 'mainwp-giweb' ),
					'templateSaved'  => __( 'Modèle enregistré.', 'mainwp-giweb' ),
					'templateDeleted' => __( 'Modèle supprimé.', 'mainwp-giweb' ),
					'optionsLoading' => __( 'Chargement des réglages…', 'mainwp-giweb' ),
					'modulesSaved'   => __( 'Configuration enregistrée.', 'mainwp-giweb' ),
					'deployTitle'    => __( 'Déploiement de la configuration', 'mainwp-giweb' ),
					'deployStarting' => __( 'Préparation du déploiement…', 'mainwp-giweb' ),
					'deployConnecting' => __( 'Déploiement vers %s…', 'mainwp-giweb' ),
					'deployDone'       => __( 'Déploiement terminé. Consultez l’onglet Historique.', 'mainwp-giweb' ),
					'deployDoneOk'     => __( 'Déploiement réussi sur %d site(s).', 'mainwp-giweb' ),
					'deployDonePartial' => __( 'Déploiement terminé : %1$d réussi(s), %2$d en échec. Voir l’historique.', 'mainwp-giweb' ),
					'deployDoneFailed' => __( 'Échec du déploiement sur tous les sites (%d). Vérifiez la connexion MainWP de chaque site.', 'mainwp-giweb' ),
					'deployNoSites'    => __( 'Sélectionnez au moins un site.', 'mainwp-giweb' ),
					'mailAlertSync'    => __( '%d site(s) ont des emails en échec. Consultez l’onglet Vue d’ensemble ou le widget MainWP.', 'mainwp-giweb' ),
					'mailColTotal'     => __( 'Mails', 'mainwp-giweb' ),
					'mailTodayShort'   => __( 'auj.', 'mainwp-giweb' ),
				),
		);
	}

	/**
	 * Imprime les assets si MainWP a déjà envoyé le &lt;head&gt; (enqueue trop tardif).
	 *
	 * @return void
	 */
	public static function print_assets() {
		if ( ! MainWP_GIWeb_UI::is_extension_admin_page() ) {
			return;
		}

		if ( ! wp_script_is( 'mainwp-giweb-admin', 'enqueued' ) && ! wp_script_is( 'mainwp-giweb-admin', 'done' ) ) {
			self::enqueue_assets();
		}

		if ( ! wp_style_is( 'mainwp-giweb-admin', 'done' ) ) {
			wp_print_styles( 'mainwp-giweb-admin' );
		}
		if ( ! wp_script_is( 'mainwp-giweb-admin', 'done' ) ) {
			wp_print_scripts( 'mainwp-giweb-admin' );
		}
	}

	/**
	 * @return void
	 */
	public static function render_page() {
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			echo '<div class="wrap mainwp-giweb-wrap"><div class="notice notice-error"><p>';
			esc_html_e( 'Vous n’avez pas les droits MainWP pour accéder au GI-Toolkit Manager.', 'mainwp-giweb' );
			echo '</p></div></div>';
			return;
		}

		self::handle_post();

		$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : MainWP_GIWeb_UI::PAGE_SLUG;
		$act = self::activator();

		$websites = MainWP_GIWeb_Sites::fetch_all( $act );

		$status_cache = get_transient( 'mainwp_giweb_status_cache' );
		if ( ! is_array( $status_cache ) ) {
			$status_cache = array();
		}

		$working_bundle = MainWP_GIWeb_Bundle::get();

		self::enqueue_assets();

		$giweb_script_config = self::script_config();

		include MAINWP_GIWEB_PLUGIN_PATH . 'views/page-main.php';

		self::print_assets();
	}

	/**
	 * @return object|null
	 */
	private static function activator() {
		global $mainwp_giweb_activator;
		return $mainwp_giweb_activator ?? null;
	}

	/**
	 * @return void
	 */
	private static function handle_post() {
		if ( ! isset( $_POST['mainwp_giweb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mainwp_giweb_nonce'] ) ), 'mainwp_giweb_action' ) ) {
			return;
		}

		$action = isset( $_POST['mainwp_giweb_action'] ) ? sanitize_key( wp_unslash( $_POST['mainwp_giweb_action'] ) ) : '';
		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'overview';

		switch ( $action ) {
			case 'pull_config':
				$site_id = absint( $_POST['source_site_id'] ?? 0 );
				if ( $site_id ) {
					$label  = self::site_name( $site_id, MainWP_GIWeb_Sites::fetch_all( self::activator() ) );
					$res    = MainWP_GIWeb_API::export_site( $site_id );
					$bundle = MainWP_GIWeb_Bundle::from_api_response( $res );
					if ( null !== $bundle ) {
						$saved = MainWP_GIWeb_Bundle::save( $bundle );
						if ( is_wp_error( $saved ) ) {
							MainWP_GIWeb_Notices::add( 'error', $saved->get_error_message() );
						} else {
							$counts = MainWP_GIWeb_Bundle::count_modules( $bundle );
							MainWP_GIWeb_Notices::add(
								'success',
								sprintf(
									/* translators: 1: site name, 2: number of modules */
									__( 'Configuration importée depuis %1$s (%2$d modules).', 'mainwp-giweb' ),
									$label,
									$counts['total']
								)
							);
						}
					} else {
						$err = ! empty( $res['errors'][0] ) ? (string) $res['errors'][0] : __( 'Échec de l’import.', 'mainwp-giweb' );
						MainWP_GIWeb_Notices::add( 'error', $err );
					}
				}
				self::redirect_after_post( $tab );
				break;

			case 'save_template':
				$name   = sanitize_text_field( wp_unslash( $_POST['template_name'] ?? '' ) );
				$bundle = MainWP_GIWeb_Bundle::get();
				if ( $name && is_array( $bundle ) && ! empty( $bundle ) ) {
					MainWP_GIWeb_Templates::save( $name, $bundle );
					MainWP_GIWeb_Notices::add( 'success', __( 'Modèle enregistré.', 'mainwp-giweb' ) );
				} else {
					MainWP_GIWeb_Notices::add( 'warning', __( 'Aucune configuration de travail à enregistrer.', 'mainwp-giweb' ) );
				}
				self::redirect_after_post( 'templates' );
				break;

			case 'delete_template':
				$tpl_id = sanitize_text_field( wp_unslash( $_POST['template_id'] ?? '' ) );
				if ( $tpl_id ) {
					MainWP_GIWeb_Templates::delete( $tpl_id );
					MainWP_GIWeb_Notices::add( 'success', __( 'Modèle supprimé.', 'mainwp-giweb' ) );
				}
				self::redirect_after_post( 'templates' );
				break;

			case 'set_default_template':
				$tpl_id = sanitize_text_field( wp_unslash( $_POST['template_id'] ?? '' ) );
				if ( $tpl_id && MainWP_GIWeb_Templates::set_default( $tpl_id ) ) {
					MainWP_GIWeb_Notices::add( 'success', __( 'Profil par défaut mis à jour.', 'mainwp-giweb' ) );
				} else {
					MainWP_GIWeb_Notices::add( 'error', __( 'Impossible de définir ce modèle par défaut.', 'mainwp-giweb' ) );
				}
				self::redirect_after_post( 'templates' );
				break;

			case 'save_settings':
				MainWP_GIWeb_Settings::save(
					array(
						'default_template_id'        => sanitize_text_field( wp_unslash( $_POST['default_template_id'] ?? '' ) ),
						'install_checked_by_default' => ! empty( $_POST['install_checked_by_default'] ),
						'apply_profile_by_default'   => ! empty( $_POST['apply_profile_by_default'] ),
						'activate_after_install'     => ! empty( $_POST['activate_after_install'] ),
						'mail_alert_enabled'         => ! empty( $_POST['mail_alert_enabled'] ),
						'mail_alert_min_failed'      => absint( $_POST['mail_alert_min_failed'] ?? 1 ),
						'mail_alert_email'           => sanitize_email( wp_unslash( $_POST['mail_alert_email'] ?? '' ) ),
					)
				);
				MainWP_GIWeb_Notices::add( 'success', __( 'Réglages enregistrés.', 'mainwp-giweb' ) );
				self::redirect_after_post( 'settings' );
				break;

			case 'save_overrides':
				$site_id = absint( $_POST['override_site_id'] ?? 0 );
				if ( $site_id ) {
					$mods = isset( $_POST['excluded_modules'] ) && is_array( $_POST['excluded_modules'] )
						? array_map( 'sanitize_text_field', wp_unslash( $_POST['excluded_modules'] ) )
						: array();
					$opt_mods = isset( $_POST['excluded_option_modules'] ) && is_array( $_POST['excluded_option_modules'] )
						? array_map( 'sanitize_text_field', wp_unslash( $_POST['excluded_option_modules'] ) )
						: array();
					MainWP_GIWeb_Overrides::save(
						$site_id,
						array(
							'excluded_modules'        => $mods,
							'excluded_option_modules' => $opt_mods,
						)
					);
					MainWP_GIWeb_Notices::add( 'success', __( 'Exclusions enregistrées.', 'mainwp-giweb' ) );
				}
				self::redirect_after_post( 'excludes' );
				break;

			case 'save_working_modules':
				$bundle = MainWP_GIWeb_Bundle::get();
				if ( ! is_array( $bundle ) ) {
					$bundle = array();
				}
				if ( empty( $bundle['modules'] ) || ! is_array( $bundle['modules'] ) ) {
					$bundle['modules'] = array();
				}
				$states = isset( $_POST['module_states'] ) && is_array( $_POST['module_states'] )
					? wp_unslash( $_POST['module_states'] )
					: array();
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
					MainWP_GIWeb_Notices::add( 'error', $saved->get_error_message() );
				} else {
					MainWP_GIWeb_Notices::add( 'success', __( 'Configuration de travail enregistrée.', 'mainwp-giweb' ) );
				}
				self::redirect_after_post( 'modules' );
				break;

			case 'push_single_site':
				$site_id = absint( $_POST['target_site_id'] ?? 0 );
				$bundle  = MainWP_GIWeb_Bundle::get();
				if ( $site_id && is_array( $bundle ) && ! empty( $bundle ) ) {
					MainWP_GIWeb_Deploy::push_to_sites( $bundle, array( $site_id ), '', __( 'Push site unique', 'mainwp-giweb' ) );
					MainWP_GIWeb_Notices::add( 'success', __( 'Configuration envoyée au site.', 'mainwp-giweb' ) );
				}
				self::redirect_after_post( $tab );
				break;
		}
	}

	/**
	 * Redirection PRG après POST (évite double envoi).
	 *
	 * @param string $tab Onglet cible.
	 * @return void
	 */
	private static function redirect_after_post( $tab = 'overview' ) {
		$page = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : MainWP_GIWeb_UI::PAGE_SLUG;
		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : $tab;

		$url = add_query_arg(
			array(
				'page' => $page,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);

		if ( 'excludes' === $tab && ! empty( $_POST['override_site_id'] ) ) {
			$url = add_query_arg( 'site_id', absint( $_POST['override_site_id'] ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @param int $site_id Site ID.
	 * @return string
	 */
	public static function site_name( $site_id, $websites ) {
		foreach ( $websites as $site ) {
			$row = MainWP_GIWeb_Sites::normalize_one( $site );
			if ( (int) $row['id'] === (int) $site_id ) {
				return $row['name'] ?: $row['url'] ?: '#' . $site_id;
			}
		}
		return '#' . $site_id;
	}
}
