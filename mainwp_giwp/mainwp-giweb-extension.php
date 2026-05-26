<?php
/**
 * Plugin Name: MainWP GI-Web Extension
 * Plugin URI: https://genevois-informatique.ch/
 * Description: Gérez et déployez la configuration GI-Toolkit sur tous vos sites MainWP.
 * Version: 1.0.0
 * Author: Genevois Informatique
 * Author URI: https://genevois-informatique.ch
 * License: GPL-2.0+
 * Text Domain: mainwp-giweb
 *
 * @package MainWP_GIWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAINWP_GIWEB_VERSION', '1.0.0' );
define( 'MAINWP_GIWEB_PLUGIN_FILE', __FILE__ );
define( 'MAINWP_GIWEB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAINWP_GIWEB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MAINWP_GIWEB_GI_TOOLKIT_PATH', MAINWP_GIWEB_PLUGIN_PATH . '../wordpress_giwp/' );

require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-catalog.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-overrides.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-templates.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-history.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-api.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-deploy.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb.php';

/**
 * Bootstrap extension MainWP.
 */
class MainWP_GIWeb_Extension_Activator {

	/** @var string */
	public $plugin_handle = 'mainwp-giweb-extension';

	/** @var string */
	public $childFile;

	/** @var array<string, mixed>|false */
	public $childEnabled;

	/** @var string */
	public $childKey = '';

	/** @var bool */
	public $mainwpActivated = false;

	/**
	 * @return void
	 */
	public function __construct() {
		$this->childFile = __FILE__;

		add_filter( 'mainwp_getextensions', array( $this, 'get_this_extension' ) );

		$this->mainwpActivated = (bool) apply_filters( 'mainwp_activated_check', false );

		if ( $this->mainwpActivated ) {
			$this->activate_extension();
		} else {
			add_action( 'mainwp_activated', array( $this, 'activate_extension' ) );
		}

		add_action( 'admin_notices', array( $this, 'admin_notice_mainwp_required' ) );

		register_activation_hook( __FILE__, array( 'MainWP_GIWeb_History', 'install_tables' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $extensions Extensions.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_this_extension( $extensions ) {
		$extensions[] = array(
			'plugin'   => __FILE__,
			'api'      => $this->plugin_handle,
			'mainwp'   => true,
			'callback' => array( $this, 'settings_page' ),
		);
		return $extensions;
	}

	/**
	 * @return void
	 */
	public function activate_extension() {
		$this->mainwpActivated = true;
		$this->childEnabled    = apply_filters( 'mainwp_extension_enabled_check', __FILE__ );
		if ( is_array( $this->childEnabled ) && ! empty( $this->childEnabled['key'] ) ) {
			$this->childKey = $this->childEnabled['key'];
		}
		add_filter( 'mainwp_getsubpages_extensions', array( $this, 'add_submenu' ), 10, 1 );
	}

	/**
	 * @param array<int, array<string, mixed>> $subpages Sous-pages.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_submenu( $subpages ) {
		$subpages[] = array(
			'title'      => __( 'GI-Toolkit Manager', 'mainwp-giweb' ),
			'slug'       => 'Mainwp-Giweb-Extension',
			'sitetab'    => false,
			'menu_hidden' => false,
			'callback'   => array( 'MainWP_GIWeb', 'render_page' ),
		);
		return $subpages;
	}

	/**
	 * @return void
	 */
	public function settings_page() {
		do_action( 'mainwp_pageheader_extensions', __FILE__ );
		if ( $this->childEnabled ) {
			MainWP_GIWeb::render_page();
		} else {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Extension non activée dans MainWP. Activez-la dans Extensions > Settings.', 'mainwp-giweb' );
			echo '</p></div>';
		}
		do_action( 'mainwp_pagefooter_extensions', __FILE__ );
	}

	/**
	 * @return void
	 */
	public function admin_notice_mainwp_required() {
		if ( $this->mainwpActivated ) {
			return;
		}
		global $current_screen;
		if ( isset( $current_screen->parent_base ) && 'plugins' === $current_screen->parent_base ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'MainWP GI-Web Extension nécessite MainWP Dashboard.', 'mainwp-giweb' );
			echo '</p></div>';
		}
	}

	/**
	 * @return string
	 */
	public function getChildKey() {
		return $this->childKey;
	}

	/**
	 * @return string
	 */
	public function getChildFile() {
		return $this->childFile;
	}
}

global $mainwp_giweb_activator;
$mainwp_giweb_activator = new MainWP_GIWeb_Extension_Activator();
