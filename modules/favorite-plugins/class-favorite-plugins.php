<?php

class GIWP_Favorite_Plugins extends GIWP_Module {
    protected function init_module() {
        $this->id = 'favorite-plugins';
        $this->name = 'Favorite Plugins';
        $this->description = 'Installation et configuration automatique des plugins favoris';
    }

    public function init() {
        if (!$this->is_active()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_giwp_install_plugin', [$this, 'ajax_install_plugin']);
        add_action('wp_ajax_giwp_activate_plugin', [$this, 'ajax_activate_plugin']);
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            'giwp-favorite-plugins',
            GIWP_PLUGIN_URL . 'modules/favorite-plugins/assets/js/favorite-plugins.js',
            ['jquery'],
            $this->version,
            true
        );

        wp_localize_script('giwp-favorite-plugins', 'giwpAjax', [
            'nonce' => wp_create_nonce('giwp_favorite_plugins'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    public function get_default_plugins() {
        return [
            'elementor' => [
                'name' => 'Elementor',
                'slug' => 'elementor',
                'required' => true,
                'config' => [
                    'elementor_disable_color_schemes' => 'yes',
                    'elementor_disable_typography_schemes' => 'yes'
                ]
            ],
            'wordpress-seo' => [
                'name' => 'Yoast SEO',
                'slug' => 'wordpress-seo',
                'required' => false,
                'config' => [
                    'wpseo_social' => [
                        'opengraph' => true,
                        'twitter' => true
                    ]
                ]
            ],
            'wp-super-cache' => [
                'name' => 'WP Super Cache',
                'slug' => 'wp-super-cache',
                'required' => false,
                'config' => [
                    'wp_cache_enabled' => true
                ]
            ]
        ];
    }

    public function ajax_install_plugin() {
        check_ajax_referer('giwp_favorite_plugins', 'nonce');

        if (!current_user_can('install_plugins')) {
            wp_send_json_error('Permission denied');
        }

        $plugin_slug = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        if (empty($plugin_slug)) {
            wp_send_json_error('Plugin slug is required');
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $api = plugins_api('plugin_information', [
            'slug' => $plugin_slug,
            'fields' => [
                'short_description' => false,
                'sections' => false,
                'requires' => false,
                'downloaded' => false,
                'last_updated' => false,
                'added' => false,
                'tags' => false,
                'compatibility' => false,
                'homepage' => false,
                'donate_link' => false,
            ],
        ]);

        if (is_wp_error($api)) {
            wp_send_json_error($api->get_error_message());
        }

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Plugin installed successfully');
    }

    public function ajax_activate_plugin() {
        check_ajax_referer('giwp_favorite_plugins', 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Permission denied');
        }

        $plugin_slug = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        if (empty($plugin_slug)) {
            wp_send_json_error('Plugin slug is required');
        }

        $plugins = $this->get_default_plugins();
        if (!isset($plugins[$plugin_slug])) {
            wp_send_json_error('Plugin not found in favorites');
        }

        $plugin_data = $plugins[$plugin_slug];
        $plugin_file = $this->get_plugin_file($plugin_slug);

        if (!$plugin_file) {
            wp_send_json_error('Plugin file not found');
        }

        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Appliquer la configuration par défaut
        if (isset($plugin_data['config'])) {
            $this->apply_plugin_config($plugin_slug, $plugin_data['config']);
        }

        wp_send_json_success('Plugin activated and configured successfully');
    }

    private function get_plugin_file($plugin_slug) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        foreach ($plugins as $plugin_file => $plugin_info) {
            if (strpos($plugin_file, $plugin_slug . '/') === 0) {
                return $plugin_file;
            }
        }

        return false;
    }

    private function apply_plugin_config($plugin_slug, $config) {
        foreach ($config as $option_name => $option_value) {
            if (is_array($option_value)) {
                $existing_value = get_option($option_name, []);
                $new_value = array_merge($existing_value, $option_value);
                update_option($option_name, $new_value);
            } else {
                update_option($option_name, $option_value);
            }
        }
    }

    public function render_settings_page() {
        $plugins = $this->get_default_plugins();
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($this->get_name()); ?></h2>
            <div class="giwp-plugins-list">
                <?php foreach ($plugins as $slug => $plugin) : ?>
                    <div class="giwp-plugin-card" data-plugin="<?php echo esc_attr($slug); ?>">
                        <h3><?php echo esc_html($plugin['name']); ?></h3>
                        <div class="giwp-plugin-actions">
                            <button class="button install-plugin">Installer</button>
                            <button class="button button-primary activate-plugin">Activer</button>
                        </div>
                        <div class="giwp-plugin-status"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
} 