<?php
/**
 * Plugin Name: GIWP - Gestionnaire d'Installation WordPress
 * Plugin URI: https://github.com/papamica/giwp
 * Description: Plugin modulaire pour faciliter la gestion et la création de sites WordPress
 * Version: 1.0.0
 * Author: PapaMica
 * Author URI: https://github.com/papamica
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: giwp
 */

if (!defined('ABSPATH')) {
    exit;
}

// Définition des constantes
define('GIWP_VERSION', '1.0.0');
define('GIWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIWP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Classe principale du plugin
class GIWP {
    private static $instance = null;
    private $modules = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_modules']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Chargement des modules
        $this->load_modules();
    }

    public function add_admin_menu() {
        add_menu_page(
            'GIWP',
            'GIWP',
            'manage_options',
            'giwp',
            [$this, 'render_admin_page'],
            'dashicons-admin-generic',
            100
        );
    }

    public function init_modules() {
        // Initialisation des modules activés
        foreach ($this->modules as $module) {
            if ($module->is_active()) {
                $module->init();
            }
        }
    }

    private function load_modules() {
        // Chargement automatique des modules
        require_once GIWP_PLUGIN_DIR . 'includes/class-giwp-module.php';
        
        $modules_dir = GIWP_PLUGIN_DIR . 'modules/';
        if (is_dir($modules_dir)) {
            $modules = scandir($modules_dir);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..') continue;
                
                if (is_dir($modules_dir . $module)) {
                    $module_file = $modules_dir . $module . '/class-' . $module . '.php';
                    if (file_exists($module_file)) {
                        require_once $module_file;
                        $class_name = 'GIWP_' . str_replace('-', '_', ucfirst($module));
                        if (class_exists($class_name)) {
                            $this->modules[$module] = new $class_name();
                        }
                    }
                }
            }
        }
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style('giwp-admin', GIWP_PLUGIN_URL . 'assets/css/admin.css', [], GIWP_VERSION);
        wp_enqueue_script('giwp-admin', GIWP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], GIWP_VERSION, true);
    }

    public function render_admin_page() {
        include GIWP_PLUGIN_DIR . 'templates/admin-page.php';
    }

    // Méthodes pour l'import/export des paramètres
    public function export_settings() {
        $settings = [];
        foreach ($this->modules as $module) {
            $settings[$module->get_id()] = $module->get_settings();
        }
        return json_encode($settings);
    }

    public function import_settings($settings_json) {
        $settings = json_decode($settings_json, true);
        if (!$settings) return false;

        foreach ($settings as $module_id => $module_settings) {
            if (isset($this->modules[$module_id])) {
                $this->modules[$module_id]->import_settings($module_settings);
            }
        }
        return true;
    }
}

// Initialisation du plugin
function GIWP() {
    return GIWP::get_instance();
}

// Démarrage du plugin
add_action('plugins_loaded', 'GIWP'); 