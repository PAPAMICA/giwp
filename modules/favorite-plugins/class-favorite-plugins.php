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
        add_action('wp_ajax_giwp_save_plugin_list', [$this, 'ajax_save_plugin_list']);
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
                'required' => false,
                'config' => [
                    'elementor_disable_color_schemes' => [
                        'value' => 'yes',
                        'description' => 'Désactiver les schémas de couleurs par défaut',
                        'enabled' => true
                    ],
                    'elementor_disable_typography_schemes' => [
                        'value' => 'yes',
                        'description' => 'Désactiver les schémas de typographie par défaut',
                        'enabled' => true
                    ]
                ]
            ],
            'wordpress-seo' => [
                'name' => 'Yoast SEO',
                'slug' => 'wordpress-seo',
                'required' => false,
                'config' => [
                    'wpseo_social' => [
                        'value' => [
                            'opengraph' => true,
                            'twitter' => true
                        ],
                        'description' => 'Activer les fonctionnalités sociales',
                        'enabled' => true
                    ]
                ]
            ],
            'wp-super-cache' => [
                'name' => 'WP Super Cache',
                'slug' => 'wp-super-cache',
                'required' => false,
                'config' => [
                    'wp_cache_enabled' => [
                        'value' => true,
                        'description' => 'Activer le cache',
                        'enabled' => true
                    ]
                ]
            ],
            'updraftplus' => [
                'name' => 'UpdraftPlus',
                'slug' => 'updraftplus',
                'required' => false,
                'config' => [
                    'updraft_interval' => [
                        'value' => 'daily',
                        'description' => 'Sauvegardes quotidiennes',
                        'enabled' => true
                    ],
                    'updraft_retain' => [
                        'value' => '7',
                        'description' => 'Conserver 7 sauvegardes',
                        'enabled' => true
                    ],
                    'updraft_include_files' => [
                        'value' => true,
                        'description' => 'Inclure les fichiers dans la sauvegarde',
                        'enabled' => true
                    ],
                    'updraft_include_database' => [
                        'value' => true,
                        'description' => 'Inclure la base de données dans la sauvegarde',
                        'enabled' => true
                    ],
                    'updraft_include_plugins' => [
                        'value' => true,
                        'description' => 'Inclure les plugins dans la sauvegarde',
                        'enabled' => true
                    ],
                    'updraft_include_themes' => [
                        'value' => true,
                        'description' => 'Inclure les thèmes dans la sauvegarde',
                        'enabled' => true
                    ],
                    'updraft_include_uploads' => [
                        'value' => true,
                        'description' => 'Inclure les uploads dans la sauvegarde',
                        'enabled' => true
                    ],
                    'updraft_include_others' => [
                        'value' => true,
                        'description' => 'Inclure les autres fichiers WP dans la sauvegarde',
                        'enabled' => true
                    ],
                    'updraft_compression_level' => [
                        'value' => '1',
                        'description' => 'Niveau de compression (1 = rapide)',
                        'enabled' => true
                    ]
                ]
            ]
        ];
    }

    public function get_saved_plugins() {
        $saved_plugins = get_option('giwp_favorite_plugins', []);
        $default_plugins = $this->get_default_plugins();

        // Fusionner les plugins sauvegardés avec les plugins par défaut
        foreach ($default_plugins as $slug => $plugin) {
            if (!isset($saved_plugins[$slug])) {
                $saved_plugins[$slug] = $plugin;
            }
        }

        return $saved_plugins;
    }

    public function ajax_save_plugin_list() {
        check_ajax_referer('giwp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $plugins = isset($_POST['plugins']) ? json_decode(stripslashes($_POST['plugins']), true) : [];
        
        if (!is_array($plugins)) {
            wp_send_json_error('Invalid plugin data');
        }

        update_option('giwp_favorite_plugins', $plugins);
        GIWP()->add_log('Liste des plugins favoris mise à jour', 'success');
        wp_send_json_success();
    }

    public function ajax_install_plugin() {
        check_ajax_referer('giwp_admin', 'nonce');

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
            GIWP()->add_log(sprintf('Erreur lors de l\'installation de %s: %s', $plugin_slug, $api->get_error_message()), 'error');
            wp_send_json_error($api->get_error_message());
        }

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            GIWP()->add_log(sprintf('Erreur lors de l\'installation de %s: %s', $plugin_slug, $result->get_error_message()), 'error');
            wp_send_json_error($result->get_error_message());
        }

        GIWP()->add_log(sprintf('Plugin %s installé avec succès', $plugin_slug), 'success');
        wp_send_json_success('Plugin installed successfully');
    }

    public function ajax_activate_plugin() {
        check_ajax_referer('giwp_admin', 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Permission denied');
        }

        $plugin_slug = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        $apply_config = isset($_POST['apply_config']) ? (bool)$_POST['apply_config'] : false;

        if (empty($plugin_slug)) {
            wp_send_json_error('Plugin slug is required');
        }

        $plugins = $this->get_saved_plugins();
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
            GIWP()->add_log(sprintf('Erreur lors de l\'activation de %s: %s', $plugin_slug, $result->get_error_message()), 'error');
            wp_send_json_error($result->get_error_message());
        }

        // Appliquer la configuration si demandé
        if ($apply_config && isset($plugin_data['config'])) {
            foreach ($plugin_data['config'] as $option_name => $config) {
                if ($config['enabled']) {
                    if (is_array($config['value'])) {
                        $existing_value = get_option($option_name, []);
                        $new_value = array_merge($existing_value, $config['value']);
                        update_option($option_name, $new_value);
                    } else {
                        update_option($option_name, $config['value']);
                    }
                }
            }
            GIWP()->add_log(sprintf('Configuration appliquée pour %s', $plugin_slug), 'success');
        }

        GIWP()->add_log(sprintf('Plugin %s activé avec succès', $plugin_slug), 'success');
        wp_send_json_success('Plugin activated successfully');
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

    public function render_settings_page() {
        $plugins = $this->get_saved_plugins();
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($this->get_name()); ?></h2>
            <div class="giwp-plugins-list">
                <?php foreach ($plugins as $slug => $plugin) : ?>
                    <div class="giwp-plugin-card" data-plugin="<?php echo esc_attr($slug); ?>">
                        <div class="giwp-plugin-header">
                            <h3><?php echo esc_html($plugin['name']); ?></h3>
                            <label class="giwp-switch">
                                <input type="checkbox" class="giwp-plugin-enabled" 
                                       <?php checked(isset($plugin['enabled']) && $plugin['enabled']); ?>>
                                <span class="giwp-slider"></span>
                            </label>
                        </div>

                        <?php if (!empty($plugin['config'])) : ?>
                            <div class="giwp-plugin-config">
                                <h4>Configuration</h4>
                                <?php foreach ($plugin['config'] as $option_name => $config) : ?>
                                    <div class="giwp-config-option">
                                        <label>
                                            <input type="checkbox" class="giwp-config-enabled" 
                                                   data-option="<?php echo esc_attr($option_name); ?>"
                                                   <?php checked($config['enabled']); ?>>
                                            <?php echo esc_html($config['description']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="giwp-plugin-actions">
                            <button class="button install-plugin">Installer</button>
                            <button class="button button-primary activate-plugin">Activer</button>
                        </div>
                        <div class="giwp-plugin-status"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="giwp-plugin-save">
                <button class="button button-primary" id="save-plugin-settings">Enregistrer les modifications</button>
            </div>
        </div>
        <?php
    }
} 