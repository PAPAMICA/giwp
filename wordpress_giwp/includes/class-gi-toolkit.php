<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://genevois-informatique.com
 * @since      1.0.0
 *
 * @package    	Gi_Toolkit
 * @subpackage	GI-Toolkit/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    	Gi_Toolkit
 * @subpackage	Gi_Toolkit/includes
 * @author     	Genevois Informatique <contact@genevois-informatique.com>
 */
class Gi_Toolkit {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Gi_Toolkit_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'GI_TOOLKIT_VERSION' ) ) {
			$this->version = GI_TOOLKIT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'gi-toolkit';

		$this->load_dependencies();
		$this->set_locale();
		Gi_Toolkit_Security::init();
		$this->define_admin_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Gi_Toolkit_Loader. Orchestrates the hooks of the plugin.
	 * - Gi_Toolkit_i18n. Defines internationalization functionality.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	/**
	 * Site géré par MainWP Child (bridge GI-Toolkit chargé si vrai).
	 *
	 * @return bool
	 */
	private static function is_mainwp_child_site() {
		return defined( 'MAINWP_CHILD_PLUGIN_URL' )
			|| defined( 'MAINWP_CHILD_FILE' )
			|| class_exists( 'MainWP\Child\MainWP_Child' )
			|| class_exists( 'MainWP_Child' );
	}

	private function load_dependencies() {

		/**
		 * The class responsible for loading composer dependencies.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/vendor/autoload.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-loader.php';
		
		/**
		 * The global functions for this plugin
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/global-functions.php';

		require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-security.php';

		if ( self::is_mainwp_child_site() ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-mainwp-api.php';
			require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-mainwp-bridge.php';
		}

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-i18n.php';

		/**
		 * The class responsible for handling the logs.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-logs.php';

		/**
		 * The class responsible for stocking the data of the modules.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-modules-data.php';

		/**
		 * The class responsible for handling the options of the plugin.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-handle-options.php';

		/**
		 * The class responsible of settings.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-settings.php';

		/**
		 * The class responsible of stats.
		 */
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-stats.php';

		$this->loader = new Gi_Toolkit_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Gi_Toolkit_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Gi_Toolkit_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$gi_toolkit_settings = new Gi_Toolkit_Settings( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $gi_toolkit_settings, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $gi_toolkit_settings, 'add_settings_menu' );
		$this->loader->add_action( 'admin_init', $gi_toolkit_settings, 'settings_submit_button' );
		$this->loader->add_action( 'init', $gi_toolkit_settings, 'add_shortcodes' );
		$this->loader->add_action( 'init', $gi_toolkit_settings, 'schedule_cron_event' );
		$this->loader->add_action( 'gi_toolkit_daily_regenerate_assets', $gi_toolkit_settings, 'cron_regenerate_assets' );
		$this->loader->add_action( 'wp_ajax_gi_toolkit_regenerate_assets', $gi_toolkit_settings, 'ajax_regenerate_assets' );
		$this->loader->add_action( 'wp_ajax_gi_toolkit_get_system_info', $gi_toolkit_settings, 'ajax_get_system_info' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Gi_Toolkit_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
