<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://genevois-informatique.com
 * @since      1.0.0
 *
 * @package           Gi_Toolkit
 * @subpackage GI-Toolkit/admin
 */
class Gi_Toolkit_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name 		= $plugin_name;
		$this->version 			= $version;
	}

	/**
	 * Schedule CRON event for daily module assets regeneration
	 * 
	 * @since	2.14.0
	 */
	public function schedule_cron_event() {
		if ( ! wp_next_scheduled( 'gi_toolkit_daily_regenerate_assets' ) ) {
			wp_schedule_event( time(), 'daily', 'gi_toolkit_daily_regenerate_assets' );
		}
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since	1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

	if ( $hook_suffix === 'toplevel_page_gi-toolkit-settings' ) {

		$settings_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/settings.asset.php' );
		wp_enqueue_style( GI_TOOLKIT_PLUGIN_SETTINGS, GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/settings.css', array(), $settings_assets['version'], 'all' );
		wp_enqueue_script( GI_TOOLKIT_PLUGIN_SETTINGS, GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/settings.js', $settings_assets['dependencies'], $settings_assets['version'], true );
		wp_localize_script(
			GI_TOOLKIT_PLUGIN_SETTINGS,
			'gi_toolkit_settings',
			array(
				'pluginUrl' => GI_TOOLKIT_PLUGIN_URL,
			)
		);
		wp_localize_script(
			GI_TOOLKIT_PLUGIN_SETTINGS,
			'gi_toolkit_settings_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'gi_toolkit_settings' ),
			)
		);

		wp_enqueue_style(
			'gi-toolkit-enhancements',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/gi-toolkit-enhancements.css',
			array( GI_TOOLKIT_PLUGIN_SETTINGS ),
			GI_TOOLKIT_VERSION
		);
		wp_enqueue_script(
			'gi-toolkit-settings-enhancements',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/gi-toolkit-settings-enhancements.js',
			array(),
			GI_TOOLKIT_VERSION,
			true
		);
		wp_localize_script(
			'gi-toolkit-settings-enhancements',
			'gi_toolkit_settings_enhancements',
			array(
				'darkTheme'          => Gi_Toolkit_Security::get_option( 'admin_dark_theme' ),
				'confirmActivation'  => Gi_Toolkit_Security::get_option( 'confirm_module_activation' ),
				'allowHighRisk'      => Gi_Toolkit_Security::get_option( 'allow_high_risk_modules' ),
				'highRiskModules'    => Gi_Toolkit_Security::high_risk_modules(),
				'i18n'               => array(
					'confirmActivate'  => __( 'Activer le module « %s » ?', 'gi-toolkit' ),
					'highRiskBlocked'  => __( 'Ce module est à haut risque. Activez d’abord l’option correspondante dans l’onglet Paramètres.', 'gi-toolkit' ),
				),
			)
		);
	}
		$settings_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/global-admin.asset.php' );
		wp_enqueue_style( GI_TOOLKIT_PLUGIN_SETTINGS . '.global-admin', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.css', array(), $settings_assets['version'], 'all' );

		// En-tête / layout des pages modules (sous-menus).
		if ( false !== strpos( $hook_suffix, 'gi-toolkit' ) ) {
			wp_enqueue_style(
				'gi-toolkit-submenu-layout',
				GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/submenu-layout.css',
				array(),
				defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0',
				'all'
			);
		}
		wp_enqueue_script( GI_TOOLKIT_PLUGIN_SETTINGS . '.global-admin', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.js', $settings_assets['dependencies'], $settings_assets['version'], true );
		wp_localize_script(
			GI_TOOLKIT_PLUGIN_SETTINGS . '.global-admin',
			'gi_toolkit_global_admin_object',
			array(
				'use_wp_submenu' => get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', 1 ),
				'i18n'          => array(
					'Modules'           => esc_html__( 'Modules', 'gi-toolkit' ),
				),
			)
		);
	}
		
	/**
	 * Add the settings menu page
	 *
	 * @since	1.0.0
	 */
	public function add_settings_menu() {
		add_menu_page(
			esc_html__('GI-Toolkit Settings', 'gi-toolkit'),
			esc_html__('GI-Toolkit', 'gi-toolkit'),
			'manage_options',
			'gi-toolkit-settings',
			array( $this, 'render_settings_page' ),
			GI_TOOLKIT_PLUGIN_URL . 'admin/svg/logo-admin.svg',
			100
		);
	}
	
	/**
	 * add_submenu_page
	 *
	 * @param  mixed $parent_slug
	 * @param  mixed $page_title
	 * @param  mixed $menu_title
	 * @param  mixed $capability
	 * @param  mixed $menu_slug
	 * @param  mixed $function
	 * @return void
	 */
	public static function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
		global $gi_toolkit_module_settings_submenu_pages;
		if ( ! is_array( $gi_toolkit_module_settings_submenu_pages ) ) {
			$gi_toolkit_module_settings_submenu_pages = array();
		}
		$module_class = is_array( $function ) && isset( $function[0] ) ? get_class( $function[0] ) : false;
		if ( $module_class ) {
			$gi_toolkit_module_settings_submenu_pages[ $module_class ] = $menu_slug;
		}
		add_submenu_page(
			$parent_slug,
			$page_title,
			'↳ ' . $menu_title,
			$capability,
			$menu_slug,
			$function
		);
	}
	
	/**
	 * Render the settings page
	 *
	 * @since	1.0.0
	 */
	public function render_settings_page() {
		$db_options              = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		$use_wp_submenu_status   = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', 1 );

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings.php';
	}

	/**
	 * Handle the submit buttons
	 * 
	 * @since	1.0.0
	 */
	public function settings_submit_button() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( ! wp_verify_nonce($nonce, GI_TOOLKIT_PLUGIN_SETTINGS . '_action') ) {
			return;
		}

		$settings_upload_json 	= isset($_POST['gi_toolkit_settings_tab_upload_json_submit']);
		$settings_download_json	= isset($_POST['gi_toolkit_settings_tab_download_json_submit']);

		Gi_Toolkit_Handle_options::require_once_all_options();

		if ( $settings_upload_json ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$upload_file = gi_toolkit_clean( wp_unslash( $_FILES['gi_toolkit_settings_tab_input'] ?? '' ) );

			if ( $upload_file ) {

				$upload_file_name 		= $upload_file['name'];
				$upload_file_type 		= $upload_file['type'];
				$upload_file_tmp_name	= $upload_file['tmp_name'];
				$upload_file_error 		= $upload_file['error'];
				$upload_file_size		= $upload_file['size'];

				if ( $upload_file_error != 0 ) {
					return;
				}

				if ( $upload_file_type !== 'application/json' ) {
					return;
				}

				if ( $upload_file_size > 1000000 ) {
					return;
				}

				$upload_file_content	= file_get_contents( $upload_file_tmp_name );
				$upload_file_json		= json_decode( $upload_file_content, true );
				
				if ( $upload_file_json ) {
					self::import_config_bundle( array( 'modules' => $upload_file_json ) );

					wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
					exit;
				}
			}

		} else if ( $settings_download_json ) {

			$bundle                 = self::export_config_bundle();
			$sanitized_data         = $bundle['modules'] ?? array();
			$upload_file_name		= 'gi-toolkit-settings-' . wp_date('Y-m-d') . '.json';
			$upload_file_content	= wp_json_encode( $sanitized_data, JSON_PRETTY_PRINT );

			header('Content-Type: application/json');
			header('Content-Disposition: attachment; filename="' . $upload_file_name . '"');
			header('Content-Length: ' . strlen($upload_file_content));
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON brut en téléchargement.
			echo $upload_file_content;
			exit;

		} else {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_settings = $this->sanitize_main_settings( wp_unslash( $_POST[GI_TOOLKIT_PLUGIN_SETTINGS] ?? array() ) );
			self::save_main_settings( $new_settings );
			$this->save_security_settings();
			$this->save_credentials();
			$this->save_use_wp_submenu();

			wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
	}

	/**
	 * Add shortcodes
	 * 
	 * @since 2.14.0
	 */
	public function add_shortcodes() {
		add_shortcode( 'gi_toolkit_changelog', array( $this, 'gi_toolkit_changelog_cb' ) );
	}

	/**
	 * Changelog shortcode callback
	 * 
	 * @since 2.14.0
	 */
	public function gi_toolkit_changelog_cb( $atts ) {
		if ( ! file_exists( GI_TOOLKIT_PLUGIN_PATH . 'changelog.txt' ) ) {
			return '';
		}

		$settings_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/shortcode-changelog.asset.php' );
		wp_enqueue_style( 'Gi_Toolkit_shortcode_changelog', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/shortcode-changelog.css', array(), $settings_assets['version'], 'all' );

		$atts = shortcode_atts( array(
			'limit' => '-1',
		), $atts );

		$limit    = $atts['limit'];
		$is_limit = $limit !== '-1';
		$content  = file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'changelog.txt' );
        $parsed   = $this->parse_changelog( $content );

		if ( $is_limit ) {
			$parsed = array_slice( $parsed, 0, $limit );
		}

		ob_start();
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/changelog.php';
		return ob_get_clean();
	}

	/**
	 * Parse the changelog
	 * 
	 * @since 2.14.0
	 */
	private function parse_changelog( $text ) {
		$text  = trim( $text );
		$text  = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$lines = preg_split( "/\r\n|\n|\r/", $text );

		$versions        = array();
		$current_version = null;

		foreach ( $lines as $raw ) {
			$line = trim( $raw );

			if ( $line === '' ) {
				continue;
			}

			// skip header lines
			if ( preg_match( '/^={2,}\s*Changelog\s*={2,}$/i', $line ) ) {
				continue;
			}

			// version line like: = 2.13.0 =
			if ( preg_match( '/^=\s*([0-9a-zA-Z\.\-_]+)\s*=?\s*=$/', $line, $version_matches ) ) {
				$current_version              = $version_matches[1];
				$versions[ $current_version ] = [];
				continue;
			}

			// If we don't yet have a version, skip
			if ( ! $current_version ) {
				continue;
			}

			// 1) Extract type and the rest (type is required)
			if ( ! preg_match( '/^(Fix|Tweak|Add|Build|Docs|Test|Perf|Feat|Remove|Security|Refactor|Update)\s*:\s*(.+)$/i', $line, $type_matches ) ) {
				continue;
			}

			$type = ucfirst( strtolower( $type_matches[1] ) );
        	$rest = trim( $type_matches[2] );

			// 2) Check if it starts with "Module:" (case-insensitive)
			if ( preg_match( '/^Module\s*:\s*(.+)$/i', $rest, $module_matches ) ) {
				$afterModule = $module_matches[1];

				// module name is everything up to the FIRST ':' — description is after that
				$pos = strpos( $afterModule, ':' );

				if ( $pos === false ) {
					$module_name = trim( $afterModule );
					$text_desc   = '';
				} else {
					$module_name = trim( substr( $afterModule, 0, $pos ) );
					$text_desc   = trim( substr( $afterModule, $pos + 1 ) );
				}

			} else {
				// No "Module:" present — this is a global line: the rest is the description
				$module_name = '';
				$text_desc   = $rest;
			}

			// Push to versions
			$versions[ $current_version ][] = array(
				'type'   => $type,
				'pro'    => false,
				'module' => $module_name,
				'text'   => $text_desc,
			);
		}

		return $versions;
	}

	/**
	 * Lit les réglages d’un module (méthode publique ou privée get_settings).
	 *
	 * @since 2.20.3
	 * @param object|string $class_or_instance Instance ou nom de classe.
	 * @return array<string, mixed>|null Null si aucun réglage exportable.
	 */
	public static function invoke_module_get_settings( $class_or_instance ) {
		$class = is_object( $class_or_instance ) ? get_class( $class_or_instance ) : $class_or_instance;
		if ( ! is_string( $class ) || ! class_exists( $class ) ) {
			return null;
		}
		if ( ! method_exists( $class, 'get_settings' ) ) {
			if ( class_exists( 'Gi_Toolkit_Module_Css_Options' ) && Gi_Toolkit_Module_Css_Options::is_css_module( $class ) ) {
				return Gi_Toolkit_Module_Css_Options::get_settings_for_class( $class );
			}
			return null;
		}

		$instance = is_object( $class_or_instance ) ? $class_or_instance : new $class();

		if ( is_callable( array( $instance, 'get_settings' ) ) ) {
			$settings = $instance->get_settings();
			return is_array( $settings ) ? $settings : array();
		}

		try {
			$method = new ReflectionMethod( $class, 'get_settings' );
			$method->setAccessible( true );
			$settings = $method->invoke( $instance );
			return is_array( $settings ) ? $settings : array();
		} catch ( ReflectionException $e ) {
			if ( class_exists( 'Gi_Toolkit_Module_Css_Options' ) ) {
				return Gi_Toolkit_Module_Css_Options::get_settings_for_class( $class );
			}
			return null;
		}
	}

	/**
	 * Enregistre les réglages d’un module (méthode publique ou privée save_settings).
	 *
	 * @since 2.20.3
	 * @param object|string        $class_or_instance Instance ou nom de classe.
	 * @param array<string, mixed> $settings          Réglages.
	 * @return bool
	 */
	public static function invoke_module_save_settings( $class_or_instance, $settings ) {
		if ( ! is_array( $settings ) ) {
			return false;
		}

		$class = is_object( $class_or_instance ) ? get_class( $class_or_instance ) : $class_or_instance;
		if ( ! is_string( $class ) || ! class_exists( $class ) ) {
			return false;
		}
		if ( ! method_exists( $class, 'save_settings' ) ) {
			if ( class_exists( 'Gi_Toolkit_Module_Css_Options' ) && Gi_Toolkit_Module_Css_Options::is_css_module( $class ) ) {
				return Gi_Toolkit_Module_Css_Options::save_settings_for_class( $class, $settings );
			}
			return false;
		}

		$instance = is_object( $class_or_instance ) ? $class_or_instance : new $class();

		if ( is_callable( array( $instance, 'save_settings' ) ) ) {
			$instance->save_settings( $settings );
			return true;
		}

		try {
			$method = new ReflectionMethod( $class, 'save_settings' );
			$method->setAccessible( true );
			$method->invoke( $instance, $settings );
			return true;
		} catch ( ReflectionException $e ) {
			if ( class_exists( 'Gi_Toolkit_Module_Css_Options' ) ) {
				return Gi_Toolkit_Module_Css_Options::save_settings_for_class( $class, $settings );
			}
			return false;
		}
	}

	/**
	 * Exporte la configuration complète (modules + globaux).
	 *
	 * @since 2.20.0
	 * @return array<string, mixed>
	 */
	public static function export_config_bundle() {
		Gi_Toolkit_Handle_options::require_once_all_options();

		$old_settings     = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		$default_settings = array_keys( gi_toolkit_options() );
		$modules_data     = array();

		foreach ( $default_settings as $item ) {
			$status = sanitize_text_field( $old_settings[ $item ] ?? '0' );
			$modules_data[ $item ] = array( 'active' => $status );
			$options = self::invoke_module_get_settings( $item );
			if ( null !== $options ) {
				if ( 'Gi_Toolkit_Matomo' === $item && is_array( $options ) ) {
					$options['site_id'] = 0;
				}
				$modules_data[ $item ]['options'] = $options;
			}
		}

		return array(
			'version'        => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '',
			'exported_at'    => gmdate( 'c' ),
			'modules'        => $modules_data,
			'security'       => class_exists( 'Gi_Toolkit_Security' ) ? Gi_Toolkit_Security::get_options() : array(),
			'credentials'    => get_option( 'gi_toolkit_credentials_tab', array() ),
			'use_wp_submenu' => get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', '1' ),
		);
	}

	/**
	 * Importe un bundle de configuration.
	 *
	 * @since 2.20.0
	 * @param array<string, mixed> $bundle Bundle exporté.
	 * @param array<string, mixed> $args   excluded_modules, excluded_option_modules.
	 * @return array<string, mixed>
	 */
	public static function import_config_bundle( $bundle, $args = array() ) {
		if ( empty( $bundle['modules'] ) || ! is_array( $bundle['modules'] ) ) {
			return array(
				'success' => false,
				'data'    => array(),
				'errors'  => array( __( 'Bundle invalide : modules manquants.', 'gi-toolkit' ) ),
			);
		}

		Gi_Toolkit_Handle_options::require_once_all_options();

		$upload_file_json       = $bundle['modules'];
		$excluded_modules       = isset( $args['excluded_modules'] ) && is_array( $args['excluded_modules'] ) ? $args['excluded_modules'] : array();
		$excluded_option_mods   = isset( $args['excluded_option_modules'] ) && is_array( $args['excluded_option_modules'] ) ? $args['excluded_option_modules'] : array();
		$default_settings       = array_keys( gi_toolkit_options() );
		$sanitized_main_data    = array();
		$sanitized_items_data   = array();
		$import_errors          = array();
		$current_main           = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );

		foreach ( $default_settings as $item ) {
			if ( in_array( $item, $excluded_modules, true ) ) {
				$sanitized_main_data[ $item ] = sanitize_text_field( $current_main[ $item ] ?? '0' );
				continue;
			}
			$sanitized_main_data[ $item ] = $upload_file_json[ $item ]['active'] ?? '0';
			if ( in_array( $item, $excluded_option_mods, true ) ) {
				continue;
			}
			if ( isset( $upload_file_json[ $item ]['options'] ) && is_array( $upload_file_json[ $item ]['options'] ) ) {
				$can_save = class_exists( $item ) && method_exists( $item, 'save_settings' );
				if ( ! $can_save && class_exists( 'Gi_Toolkit_Module_Css_Options' ) ) {
					$can_save = Gi_Toolkit_Module_Css_Options::is_css_module( $item );
				}
				if ( $can_save ) {
					$sanitized_items_data[ $item ] = $upload_file_json[ $item ]['options'];
				}
			}
		}

		foreach ( $sanitized_items_data as $item_key => $item_value ) {
			if ( 'Gi_Toolkit_Matomo' === $item_key && is_array( $item_value ) && class_exists( 'Gi_Toolkit_Matomo' ) ) {
				$deploy = Gi_Toolkit_Matomo::deploy_from_mainwp( $item_value );
				if ( empty( $deploy['success'] ) ) {
					$import_errors[] = $deploy['message'] ?? __( 'Échec du déploiement Matomo.', 'gi-toolkit' );
				}
				continue;
			}
			self::invoke_module_save_settings( $item_key, $item_value );
		}

		self::save_main_settings( $sanitized_main_data );

		if ( isset( $bundle['security'] ) && is_array( $bundle['security'] ) && class_exists( 'Gi_Toolkit_Security' ) ) {
			update_option( Gi_Toolkit_Security::OPTION_KEY, Gi_Toolkit_Security::sanitize_options( $bundle['security'] ), false );
		}

		if ( isset( $bundle['credentials'] ) && is_array( $bundle['credentials'] ) ) {
			$creds = array();
			foreach ( $bundle['credentials'] as $key => $val ) {
				$creds[ sanitize_key( $key ) ] = sanitize_text_field( $val );
			}
			update_option( 'gi_toolkit_credentials_tab', $creds, false );
		}

		if ( isset( $bundle['use_wp_submenu'] ) ) {
			update_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', sanitize_text_field( $bundle['use_wp_submenu'] ), false );
		}

		return array(
			'success' => empty( $import_errors ),
			'data'    => array(
				'imported_modules' => count( $sanitized_main_data ),
			),
			'errors'  => $import_errors,
		);
	}

	/**
	 * Met à jour l’état on/off de modules.
	 *
	 * @param array<string, string> $modules Classe => 0|1.
	 * @return array<string, mixed>
	 */
	public static function set_modules_state( $modules ) {
		$current = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		foreach ( $modules as $class => $status ) {
			if ( ! is_string( $class ) ) {
				continue;
			}
			$current[ $class ] = ( '1' === (string) $status ) ? '1' : '0';
		}
		self::save_main_settings( $current );
		return array(
			'success' => true,
			'data'    => array( 'updated' => count( $modules ) ),
			'errors'  => array(),
		);
	}

	/**
	 * Save the main settings
	 *
	 * @param array<string, string> $new_settings Modules actifs.
	 */
	public static function save_main_settings( $new_settings ) {

		$old_settings = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );

		$new_settings = apply_filters( 'gi_toolkit_sanitize_main_settings', $new_settings, $old_settings );

		foreach ( $new_settings as $class => $status ) {

			if ( ( ! isset( $old_settings[ $class ] ) || $old_settings[ $class ] !== '1' ) && '1' === $status ) {
				if ( class_exists( $class ) && method_exists( $class, 'activate' ) ) {
					$class::activate();
				}
			} elseif ( isset( $old_settings[ $class ] ) && '1' === $old_settings[ $class ] && '0' === $status ) {
				if ( class_exists( $class ) && method_exists( $class, 'deactivate' ) ) {
					$class::deactivate();
				}
			}
		}

		update_option( GI_TOOLKIT_PLUGIN_SETTINGS, $new_settings );
	}

	/**
	 * save_use_wp_submenu
	 *
	 * @return void
	 */
	/**
	 * Enregistre les options de sécurité globales.
	 *
	 * @return void
	 */
	private function save_security_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['gi_toolkit_security_settings'] ) || ! is_array( $_POST['gi_toolkit_security_settings'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = wp_unslash( $_POST['gi_toolkit_security_settings'] );
		update_option( Gi_Toolkit_Security::OPTION_KEY, Gi_Toolkit_Security::sanitize_options( $raw ), false );
		Gi_Toolkit_Security::log( 'security_settings_updated', array() );
	}

	private function save_use_wp_submenu() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$use_wp_submenu = sanitize_text_field( wp_unslash( $_POST[GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu'] ?? '0' ) );
		update_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', $use_wp_submenu, false );
	}

	/**
	 * Save the credentials
	 */
	private function save_credentials() {
		$new_settings = array();
		foreach ( gi_toolkit_ai_modules() as $ai_module_key => $ai_module ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing
			$new_settings[$ai_module_key] = sanitize_text_field( wp_unslash( $_POST['gi_toolkit_credentials_tab'][$ai_module_key] ?? '' ) );
		}

		update_option( 'gi_toolkit_credentials_tab', $new_settings );
	}

	/**
	 * Sanitize the main settings
	 */
	private function sanitize_main_settings( $new_settings ) {

		$default_settings   = array_keys( gi_toolkit_options() );
		$sanitized_settings	= array();

		foreach ( $default_settings as $item ) {
			$sanitized_settings[$item] = sanitize_text_field($new_settings[$item] ?? '0');
		}

		return $sanitized_settings;
	}
	
	/**
	 * Get the changelog from the README.txt file and convert it to HTML
	 * @since	1.8.0
	 * Usage: Gi_Toolkit_Settings::get_changelog();
	 * @return void
	 */
	public static function get_changelog() {
		if( file_exists( GI_TOOLKIT_PLUGIN_PATH . 'release.json' ) ) {
			$release_json = json_decode( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'release.json' ), true );
			if ( !empty( $release_json['sections']['changelog'] ) ) {
				return $release_json['sections']['changelog'];
			}
		}

		if( file_exists( GI_TOOLKIT_PLUGIN_PATH . 'README.txt' ) ) {
			$readme_file = GI_TOOLKIT_PLUGIN_PATH . 'README.txt';
			$readme_content = file_get_contents( $readme_file );
			
			if ( ! $readme_content ) return;
	
			$changelog = explode( '= ' . GI_TOOLKIT_VERSION . ' =', $readme_content );
			$changelog = explode( '= ', $changelog[1] ?? '' );
			$changelog = $changelog[0] ?? '';
	
			if ( class_exists( 'Parsedown' ) ) {
				$Parsedown = new Parsedown();
				$changelog = $Parsedown->text( esc_html( $changelog ) );
			} else {
				$changelog = nl2br( esc_html( $changelog ) );
			}
	
			return $changelog;
		}

		return '';
	}

	/**
	 * Regenerate assets for all active modules
	 * Forces activation/deactivation hooks to regenerate module assets
	 * 
	 * @since	2.14.0
	 * @return	int|WP_Error Number of modules processed or WP_Error on failure
	 */
	public static function regenerate_module_assets() {
		try {
			// Get all active modules
			$db_options = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
			$options_data = gi_toolkit_options( 'normal' );

			// Require all module files
			Gi_Toolkit_Handle_options::require_once_all_options();

			$regenerated_count = 0;

			foreach ( $db_options as $option_key => $option_status ) {
				if ( $option_status == '1' ) {
					$option_data = $options_data[$option_key] ?? array();
					
					if ( class_exists( $option_key ) ) {
						// Check if the module has deactivate method
						if ( method_exists( $option_key, 'deactivate' ) ) {
							call_user_func( array( $option_key, 'deactivate' ) );
							$regenerated_count++;
						}

						// Check if the module has activate method
						if ( method_exists( $option_key, 'activate' ) ) {
							call_user_func( array( $option_key, 'activate' ) );
							$regenerated_count++;
						}
					}
				}
			}

			return $regenerated_count;

		} catch ( Exception $e ) {
			return new WP_Error( 'regenerate_assets_failed', $e->getMessage() );
		}
	}

	/**
	 * CRON handler to regenerate module assets
	 * 
	 * @since	2.14.0
	 */
	public function cron_regenerate_assets() {
		$result = self::regenerate_module_assets();

		if ( is_wp_error( $result ) ) {
			Gi_Toolkit_Logs::add_error( 
				esc_html__( 'Scheduled module assets regeneration failed: ', 'gi-toolkit' ) . $result->get_error_message()
			);
		} else {
			Gi_Toolkit_Logs::add_notice( 
				/* translators: %d: number of modules processed */
				esc_html__( 'Scheduled module assets regeneration completed.', 'gi-toolkit' ) . ' ' . sprintf( esc_html__( '%d modules processed.', 'gi-toolkit' ), $result )
			);
		}
	}

	/**
	 * AJAX handler to regenerate module assets
	 * 
	 * @since	2.14.0
	 */
	public function ajax_regenerate_assets() {
		// Verify nonce for security
		check_ajax_referer( 'gi_toolkit_settings', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 
				'message' => esc_html__( 'You do not have permission to perform this action.', 'gi-toolkit' ) 
			) );
		}

		$result = self::regenerate_module_assets();

		if ( is_wp_error( $result ) ) {
			Gi_Toolkit_Logs::add_error( 
				esc_html__( 'Module assets regeneration failed: ', 'gi-toolkit' ) . $result->get_error_message()
			);

			wp_send_json_error( array(
				'message' => esc_html__( 'An error occurred while regenerating module assets. Please check the logs.', 'gi-toolkit' )
			) );
		}

		// Log the action
		Gi_Toolkit_Logs::add_notice( 
			/* translators: %d: number of modules processed */
			esc_html__( 'Module assets regenerated successfully.', 'gi-toolkit' ) . ' ' . sprintf( esc_html__( '%d modules processed.', 'gi-toolkit' ), $result )
		);

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of modules processed */
				esc_html__( 'Module assets regenerated successfully. %d modules processed.', 'gi-toolkit' ),
				$result
			)
		) );
	}

	/**
	 * Get system information for support
	 * 
	 * @since	2.16.0
	 * @return array System information data
	 */
	private static function get_system_info() {
		global $wpdb;

		$system_info = array();

		// PHP & Server Information
		$system_info['php_server'] = array(
			'php_version' => phpversion(),
			'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'N/A',
			'php_memory_limit' => ini_get('memory_limit'),
			'php_max_execution_time' => ini_get('max_execution_time'),
			'php_max_input_vars' => ini_get('max_input_vars'),
			'php_post_max_size' => ini_get('post_max_size'),
			'php_upload_max_filesize' => ini_get('upload_max_filesize'),
			'mysql_version' => $wpdb->db_version(),
			'server_ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'N/A',
		);

		// WordPress Information
		$system_info['wordpress'] = array(
			'version' => get_bloginfo('version'),
			'site_url' => get_site_url(),
			'home_url' => get_home_url(),
			'is_multisite' => is_multisite(),
			'wp_debug' => defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'undefined',
			'wp_debug_log' => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'undefined',
			'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'true' : 'false') : 'undefined',
			'script_debug' => defined('SCRIPT_DEBUG') ? (SCRIPT_DEBUG ? 'true' : 'false') : 'undefined',
			'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'undefined',
			'wp_max_memory_limit' => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'undefined',
			'table_prefix' => $wpdb->prefix,
		);

		// Active Theme
		$theme = wp_get_theme();
		$system_info['active_theme'] = array(
			'name' => $theme->get('Name'),
			'version' => $theme->get('Version'),
			'author' => $theme->get('Author'),
			'template' => $theme->get('Template'),
			'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : 'N/A',
		);

		// Active Plugins
		$active_plugins = get_option('active_plugins', array());
		$system_info['active_plugins'] = array();
		
		foreach ($active_plugins as $plugin) {
			$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
			$system_info['active_plugins'][] = array(
				'name' => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'author' => $plugin_data['Author'],
				'plugin_file' => $plugin,
			);
		}

		// MU Plugins
		$mu_plugins = get_mu_plugins();
		$system_info['mu_plugins'] = array();
		
		foreach ($mu_plugins as $plugin_file => $plugin_data) {
			$system_info['mu_plugins'][] = array(
				'name' => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'plugin_file' => $plugin_file,
			);
		}

		// GI-Toolkit Configuration
		$system_info['gi-toolkit'] = array(
			'version' => GI_TOOLKIT_VERSION,
			'enabled_modules' => array(),
		);

		// Get enabled modules
		$db_options = get_option(GI_TOOLKIT_PLUGIN_SETTINGS, array());
		$modules = gi_toolkit_options();
		
		foreach ($modules as $module_key => $module_data) {
			if (isset($db_options[$module_key]) && $db_options[$module_key] === '1') {
				$system_info['gi-toolkit']['enabled_modules'][] = array(
					'key' => $module_key,
					'name' => $module_data['name'],
				);
			}
		}

		if ( '1' === Gi_Toolkit_Security::get_option( 'hide_sensitive_in_system_info' ) ) {
			$credentials = get_option( 'gi_toolkit_credentials_tab', array() );
			if ( is_array( $credentials ) ) {
				foreach ( $credentials as $cred_key => $cred_val ) {
					if ( is_string( $cred_val ) && '' !== trim( $cred_val ) ) {
						$system_info['gi-toolkit'][ 'credential_' . $cred_key ] = '[masqué]';
					}
				}
			}
		}

		return $system_info;
	}

	/**
	 * Format system information as readable text
	 * 
	 * @since	2.16.0
	 * @param array $system_info System information array
	 * @return string Formatted text
	 */
	private static function format_system_info($system_info) {
		$output = "=== GI-Toolkit System Information ===\n\n";
		
		// PHP & Server
		$output .= "--- PHP & Server ---\n";
		foreach ($system_info['php_server'] as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			$output .= "$label: $value\n";
		}
		
		// WordPress
		$output .= "\n--- WordPress ---\n";
		foreach ($system_info['wordpress'] as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			$value_display = is_bool($value) ? ($value ? 'Yes' : 'No') : $value;
			$output .= "$label: $value_display\n";
		}
		
		// Active Theme
		$output .= "\n--- Active Theme ---\n";
		foreach ($system_info['active_theme'] as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			$output .= "$label: $value\n";
		}
		
		// Active Plugins
		$output .= "\n--- Active Plugins (" . count($system_info['active_plugins']) . ") ---\n";
		foreach ($system_info['active_plugins'] as $plugin) {
			$output .= "- {$plugin['name']} (v{$plugin['version']}) by {$plugin['author']}\n";
		}
		
		// MU Plugins
		if (!empty($system_info['mu_plugins'])) {
			$output .= "\n--- Must-Use Plugins (" . count($system_info['mu_plugins']) . ") ---\n";
			foreach ($system_info['mu_plugins'] as $plugin) {
				$output .= "- {$plugin['name']} (v{$plugin['version']})\n";
			}
		}
		
		// GI-Toolkit
		$output .= "\n--- GI-Toolkit Configuration ---\n";
		$output .= "Version: {$system_info['gi-toolkit']['version']}\n";
		$output .= "Enabled Modules (" . count($system_info['gi-toolkit']['enabled_modules']) . "):\n";
		foreach ($system_info['gi-toolkit']['enabled_modules'] as $module) {
			$output .= "  - {$module['name']}\n";
		}
		
		$output .= "\n=== End System Information ===";
		
		return $output;
	}

	/**
	 * AJAX handler to get system information
	 * 
	 * @since	2.16.0
	 */
	public function ajax_get_system_info() {
		// Verify nonce for security
		check_ajax_referer('gi_toolkit_settings', 'nonce');

		// Check user capabilities
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array(
				'message' => esc_html__('You do not have permission to perform this action.', 'gi-toolkit')
			));
		}

		try {
			$system_info = self::get_system_info();
			$formatted_info = self::format_system_info($system_info);

			wp_send_json_success(array(
				'data' => $system_info,
				'formatted' => $formatted_info,
			));
		} catch (Exception $e) {
			wp_send_json_error(array(
				'message' => esc_html__('An error occurred while gathering system information.', 'gi-toolkit')
			));
		}
	}

}