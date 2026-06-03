<?php
/**
 * Bootstrap interne — chargé par mainwp-giwp.php (ne pas activer ce fichier seul).
 *
 * @package MainWP_GIWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MAINWP_GIWEB_VERSION' ) ) {
	define( 'MAINWP_GIWEB_VERSION', '1.6.2' );
}
if ( ! defined( 'MAINWP_GIWEB_PLUGIN_FILE' ) ) {
	define( 'MAINWP_GIWEB_PLUGIN_FILE', __DIR__ . '/mainwp-giwp.php' );
}
if ( ! defined( 'MAINWP_GIWEB_PLUGIN_PATH' ) ) {
	define( 'MAINWP_GIWEB_PLUGIN_PATH', plugin_dir_path( MAINWP_GIWEB_PLUGIN_FILE ) );
}
if ( ! defined( 'MAINWP_GIWEB_PLUGIN_URL' ) ) {
	define( 'MAINWP_GIWEB_PLUGIN_URL', plugin_dir_url( MAINWP_GIWEB_PLUGIN_FILE ) );
}
if ( ! defined( 'MAINWP_GIWEB_GI_TOOLKIT_PATH' ) ) {
	define( 'MAINWP_GIWEB_GI_TOOLKIT_PATH', MAINWP_GIWEB_PLUGIN_PATH . '../wordpress_giwp/' );
}

require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-ui.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-capabilities.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-sites.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-catalog.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-settings.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-matomo.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-uptime-kuma.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-zabbix.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-ftp-backup.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-metabox.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-widget-ui.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-uptime-kuma-widget.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-overrides.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-templates.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-zip.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-plugin-installer.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-onboarding.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-history.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-bundle.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-module-options.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-modules-ui.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-api.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-deploy.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-notices.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-mail-stats.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-backup-stats.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-backup-widget.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-status-cache.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-mainwp-sync.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-dashboard-widget.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-manage-sites.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb-sync-ajax.php';
require_once MAINWP_GIWEB_PLUGIN_PATH . 'includes/class-mainwp-giweb.php';

/**
 * Bootstrap extension MainWP.
 */
class MainWP_GIWeb_Extension_Activator {

	/** @var string */
	public $plugin_handle = 'mainwp-giwp';

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
		$this->childFile = MAINWP_GIWEB_PLUGIN_FILE;

		add_filter( 'mainwp_getextensions', array( $this, 'get_this_extension' ) );

		$this->mainwpActivated = (bool) apply_filters( 'mainwp_activated_check', false );

		if ( $this->mainwpActivated ) {
			$this->activate_extension();
		} else {
			add_action( 'mainwp_activated', array( $this, 'activate_extension' ) );
		}

		add_action( 'admin_notices', array( $this, 'admin_notice_mainwp_required' ) );

		add_filter( 'mainwp_extensions_page_top_header', array( $this, 'filter_extensions_page_title' ), 10, 2 );
		add_filter( 'admin_title', array( $this, 'filter_admin_document_title' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( 'MainWP_GIWeb', 'enqueue_assets' ) );

		register_activation_hook( MAINWP_GIWEB_PLUGIN_FILE, array( 'MainWP_GIWeb_History', 'install_tables' ) );
	}

	/**
	 * Titre en-tête MainWP (évite « mainwp_giwp » / « _giwp » dérivé du dossier plugin).
	 *
	 * @param string $title Titre courant.
	 * @param string $page  Slug page admin.
	 * @return string
	 */
	public function filter_extensions_page_title( $title, $page ) {
		if ( MainWP_GIWeb_UI::is_extension_admin_page( $page ) ) {
			return MainWP_GIWeb_UI::page_title();
		}
		return $title;
	}

	/**
	 * Titre de l’onglet navigateur.
	 *
	 * @param string $admin_title Titre admin complet.
	 * @param string $title       Partie titre.
	 * @return string
	 */
	public function filter_admin_document_title( $admin_title, $title ) {
		if ( ! MainWP_GIWeb_UI::is_extension_admin_page() ) {
			return $admin_title;
		}
		$site = get_bloginfo( 'name', 'display' );
		return MainWP_GIWeb_UI::page_title() . ( $site ? ' ‹ ' . $site : '' );
	}

	/**
	 * @param array<int, array<string, mixed>> $extensions Extensions.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_this_extension( $extensions ) {
		if ( ! is_array( $extensions ) ) {
			$extensions = array();
		}

		$plugin_file = MainWP_GIWeb_Metabox::plugin_file();
		if ( '' === $plugin_file ) {
			return $extensions;
		}

		$extensions[] = array(
			'plugin'   => $plugin_file,
			'api'      => $this->plugin_handle,
			'name'     => MainWP_GIWeb_UI::page_title(),
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
		$this->childEnabled    = apply_filters( 'mainwp_extension_enabled_check', MAINWP_GIWEB_PLUGIN_FILE );
		if ( is_array( $this->childEnabled ) && ! empty( $this->childEnabled['key'] ) ) {
			$this->childKey = $this->childEnabled['key'];
		}

		add_filter( 'mainwp_getsubpages_extensions', array( $this, 'add_submenu' ), 10, 1 );
		MainWP_GIWeb_Metabox::init();

		add_filter( 'mainwp_getmetaboxes', array( 'MainWP_GIWeb_Dashboard_Widget', 'register_metabox' ), 20, 1 );
		add_filter( 'mainwp_getmetaboxes', array( 'MainWP_GIWeb_Backup_Widget', 'register_metabox' ), 22, 1 );
		add_filter( 'mainwp_getmetaboxes', array( 'MainWP_GIWeb_Uptime_Kuma_Widget', 'register_metabox' ), 25, 1 );
		add_filter( 'mainwp_widgets_screen_options', array( 'MainWP_GIWeb_Dashboard_Widget', 'widgets_screen_options' ), 10, 1 );
		add_filter( 'mainwp_widgets_screen_options', array( 'MainWP_GIWeb_Backup_Widget', 'widgets_screen_options' ), 10, 1 );
		add_filter( 'mainwp_widgets_screen_options', array( 'MainWP_GIWeb_Uptime_Kuma_Widget', 'widgets_screen_options' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( 'MainWP_GIWeb_Dashboard_Widget', 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( 'MainWP_GIWeb_Backup_Widget', 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( 'MainWP_GIWeb_Uptime_Kuma_Widget', 'enqueue_assets' ) );

		MainWP_GIWeb_Manage_Sites::init();
		MainWP_GIWeb_Uptime_Kuma_Widget::activate_cron();
	}

	/**
	 * @param array<int, array<string, mixed>> $subpages Sous-pages.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_submenu( $subpages ) {
		$subpages[] = array(
			'title'       => MainWP_GIWeb_UI::page_title(),
			'slug'        => 'Mainwp-Gi-Toolkit-Manager',
			'sitetab'     => false,
			'menu_hidden' => false,
			'callback'    => array( $this, 'settings_page' ),
		);
		return $subpages;
	}

	/**
	 * @return void
	 */
	public function settings_page() {
		do_action( 'mainwp_pageheader_extensions', MAINWP_GIWEB_PLUGIN_FILE );
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Vous n’avez pas les droits MainWP pour accéder au GI-Toolkit Manager.', 'mainwp-giweb' );
			echo '</p></div>';
		} elseif ( $this->childEnabled ) {
			MainWP_GIWeb::render_page();
		} else {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Extension non activée dans MainWP. Activez-la dans Extensions > Settings.', 'mainwp-giweb' );
			echo '</p></div>';
		}
		do_action( 'mainwp_pagefooter_extensions', MAINWP_GIWEB_PLUGIN_FILE );
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

MainWP_GIWeb_Uptime_Kuma_Widget::register_cron_schedule();
MainWP_GIWeb_Uptime_Kuma_Widget::init();

register_activation_hook(
	MAINWP_GIWEB_PLUGIN_FILE,
	function () {
		MainWP_GIWeb_Uptime_Kuma_Widget::register_cron_schedule();
		MainWP_GIWeb_Uptime_Kuma_Widget::activate_cron();
	}
);

MainWP_GIWeb_Onboarding::init();
MainWP_GIWeb_Capabilities::init();
add_action( 'admin_notices', array( 'MainWP_GIWeb_Onboarding', 'maybe_render_notice' ) );
