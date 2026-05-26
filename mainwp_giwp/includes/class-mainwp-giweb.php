<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface admin de l’extension MainWP GI-Web.
 */
class MainWP_GIWeb {

	/**
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'mainwp-giweb' ) );
		}

		self::handle_post();

		$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'Extensions-Mainwp-Giweb-Extension';
		$act = self::activator();

		$websites = array();
		if ( $act ) {
			$websites = apply_filters( 'mainwp_getsites', $act->getChildFile(), $act->getChildKey(), null );
		}
		if ( ! is_array( $websites ) ) {
			$websites = array();
		}

		$status_cache = get_transient( 'mainwp_giweb_status_cache' );
		if ( ! is_array( $status_cache ) ) {
			$status_cache = array();
		}

		$working_bundle = get_option( 'mainwp_giweb_working_bundle', array() );
		if ( ! is_array( $working_bundle ) ) {
			$working_bundle = array();
		}

		wp_enqueue_style( 'mainwp-giweb-admin', MAINWP_GIWEB_PLUGIN_URL . 'assets/css/admin.css', array(), MAINWP_GIWEB_VERSION );
		wp_enqueue_script( 'mainwp-giweb-admin', MAINWP_GIWEB_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), MAINWP_GIWEB_VERSION, true );

		include MAINWP_GIWEB_PLUGIN_PATH . 'views/page-main.php';
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

		switch ( $action ) {
			case 'sync_status':
				$act = self::activator();
				if ( $act ) {
					$sites = apply_filters( 'mainwp_getsites', $act->getChildFile(), $act->getChildKey(), null );
					set_transient( 'mainwp_giweb_status_cache', MainWP_GIWeb_Deploy::sync_all_status( $sites ), 15 * MINUTE_IN_SECONDS );
				}
				break;

			case 'pull_config':
				$site_id = absint( $_POST['source_site_id'] ?? 0 );
				if ( $site_id ) {
					$res = MainWP_GIWeb_API::export_site( $site_id );
					if ( ! empty( $res['success'] ) && ! empty( $res['data'] ) ) {
						update_option( 'mainwp_giweb_working_bundle', $res['data'], false );
					}
				}
				break;

			case 'save_template':
				$name   = sanitize_text_field( wp_unslash( $_POST['template_name'] ?? '' ) );
				$bundle = get_option( 'mainwp_giweb_working_bundle', array() );
				if ( $name && is_array( $bundle ) && ! empty( $bundle ) ) {
					MainWP_GIWeb_Templates::save( $name, $bundle );
				}
				break;

			case 'delete_template':
				$tpl_id = sanitize_text_field( wp_unslash( $_POST['template_id'] ?? '' ) );
				if ( $tpl_id ) {
					MainWP_GIWeb_Templates::delete( $tpl_id );
				}
				break;

			case 'deploy':
				$site_ids = array();
				if ( isset( $_POST['selected_sites'] ) && is_array( $_POST['selected_sites'] ) ) {
					$site_ids = array_map( 'absint', wp_unslash( $_POST['selected_sites'] ) );
				} elseif ( isset( $_POST['site_ids'] ) && is_array( $_POST['site_ids'] ) ) {
					$site_ids = array_map( 'absint', wp_unslash( $_POST['site_ids'] ) );
				}
				$tpl_id   = sanitize_text_field( wp_unslash( $_POST['deploy_template_id'] ?? '' ) );
				$bundle   = get_option( 'mainwp_giweb_working_bundle', array() );
				if ( $tpl_id ) {
					$tpl = MainWP_GIWeb_Templates::get( $tpl_id );
					if ( $tpl && ! empty( $tpl['bundle'] ) ) {
						$bundle = $tpl['bundle'];
					}
				}
				if ( is_array( $bundle ) && ! empty( $bundle ) && ! empty( $site_ids ) ) {
					$tpl_name = $tpl_id ? ( MainWP_GIWeb_Templates::get( $tpl_id )['name'] ?? '' ) : __( 'Configuration courante', 'mainwp-giweb' );
					MainWP_GIWeb_Deploy::push_to_sites( $bundle, $site_ids, $tpl_id, $tpl_name );
				}
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
				}
				break;

			case 'toggle_module_working':
				$class  = sanitize_text_field( wp_unslash( $_POST['module_class'] ?? '' ) );
				$active = ! empty( $_POST['module_active'] ) ? '1' : '0';
				$bundle = get_option( 'mainwp_giweb_working_bundle', array() );
				if ( is_array( $bundle ) && $class ) {
					if ( empty( $bundle['modules'] ) ) {
						$bundle['modules'] = array();
					}
					if ( ! isset( $bundle['modules'][ $class ] ) ) {
						$bundle['modules'][ $class ] = array();
					}
					$bundle['modules'][ $class ]['active'] = $active;
					update_option( 'mainwp_giweb_working_bundle', $bundle, false );
				}
				break;

			case 'push_single_site':
				$site_id = absint( $_POST['target_site_id'] ?? 0 );
				$bundle  = get_option( 'mainwp_giweb_working_bundle', array() );
				if ( $site_id && is_array( $bundle ) && ! empty( $bundle ) ) {
					MainWP_GIWeb_Deploy::push_to_sites( $bundle, array( $site_id ), '', __( 'Push site unique', 'mainwp-giweb' ) );
				}
				break;
		}
	}

	/**
	 * @param int $site_id Site ID.
	 * @return string
	 */
	public static function site_name( $site_id, $websites ) {
		foreach ( $websites as $site ) {
			if ( isset( $site->id ) && (int) $site->id === (int) $site_id ) {
				return $site->name ?? ( $site->url ?? '#' );
			}
		}
		return '#' . $site_id;
	}
}
