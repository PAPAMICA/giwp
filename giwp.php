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
    private $logs = [];

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
        add_action('wp_ajax_giwp_toggle_module', [$this, 'ajax_toggle_module']);
        add_action('wp_ajax_giwp_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_giwp_import_settings', [$this, 'ajax_import_settings']);
        
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

    public function add_log($message, $type = 'info') {
        $this->logs[] = [
            'message' => $message,
            'type' => $type,
            'time' => current_time('mysql')
        ];
        update_option('giwp_logs', array_slice($this->logs, -100)); // Garde les 100 derniers logs
    }

    public function get_logs() {
        return get_option('giwp_logs', []);
    }

    public function ajax_toggle_module() {
        check_ajax_referer('giwp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $module_id = isset($_POST['module']) ? sanitize_text_field($_POST['module']) : '';
        $active = isset($_POST['active']) ? (bool)$_POST['active'] : false;

        if (empty($module_id) || !isset($this->modules[$module_id])) {
            wp_send_json_error('Module not found');
        }

        $module = $this->modules[$module_id];
        
        if ($active) {
            $module->activate();
            $this->add_log(sprintf('Module "%s" activé', $module->get_name()), 'success');
        } else {
            $module->deactivate();
            $this->add_log(sprintf('Module "%s" désactivé', $module->get_name()), 'info');
        }

        wp_send_json_success();
    }

    public function ajax_export_settings() {
        check_ajax_referer('giwp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $settings = [];
        foreach ($this->modules as $module) {
            $settings[$module->get_id()] = [
                'active' => $module->is_active(),
                'settings' => $module->get_settings()
            ];
        }

        $this->add_log('Export des paramètres effectué', 'info');
        wp_send_json_success($settings);
    }

    public function ajax_import_settings() {
        check_ajax_referer('giwp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : null;
        
        if (!$settings) {
            wp_send_json_error('Invalid settings data');
        }

        foreach ($settings as $module_id => $module_data) {
            if (isset($this->modules[$module_id])) {
                if ($module_data['active']) {
                    $this->modules[$module_id]->activate();
                } else {
                    $this->modules[$module_id]->deactivate();
                }
                $this->modules[$module_id]->import_settings($module_data['settings']);
            }
        }

        $this->add_log('Import des paramètres effectué', 'success');
        wp_send_json_success();
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
        
        wp_localize_script('giwp-admin', 'giwpAjax', [
            'nonce' => wp_create_nonce('giwp_admin'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    public function render_admin_page() {
        include GIWP_PLUGIN_DIR . 'templates/admin-page.php';
    }
}

// Initialisation du plugin
function GIWP() {
    return GIWP::get_instance();
}

// Démarrage du plugin
add_action('plugins_loaded', 'GIWP'); 