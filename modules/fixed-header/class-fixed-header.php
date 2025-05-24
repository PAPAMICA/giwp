<?php

class GIWP_Fixed_Header extends GIWP_Module {
    protected function init_module() {
        $this->id = 'fixed-header';
        $this->name = 'Fixed Header';
        $this->description = 'Fixe le header en haut de la page, compatible avec Elementor';
    }

    public function init() {
        if (!$this->is_active()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'add_custom_css']);
        add_action('wp_footer', [$this, 'add_debug_info']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'giwp-fixed-header',
            GIWP_PLUGIN_URL . 'modules/fixed-header/assets/css/fixed-header.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'giwp-fixed-header',
            GIWP_PLUGIN_URL . 'modules/fixed-header/assets/js/fixed-header.js',
            ['jquery'],
            $this->version,
            true
        );

        // Passer les paramètres au JavaScript
        $settings = $this->get_settings();
        wp_localize_script('giwp-fixed-header', 'giwpFixedHeader', [
            'headerSelector' => !empty($settings['header_selector']) ? $settings['header_selector'] : '#masthead',
            'backgroundColor' => !empty($settings['background_color']) ? $settings['background_color'] : '#ffffff',
            'debug' => true
        ]);
    }

    public function add_custom_css() {
        $settings = $this->get_settings();
        $header_selector = !empty($settings['header_selector']) ? $settings['header_selector'] : '#masthead';
        $background_color = !empty($settings['background_color']) ? $settings['background_color'] : '#ffffff';
        ?>
        <style type="text/css">
            :root {
                --header-background-color: <?php echo esc_html($background_color); ?>;
            }
            
            <?php echo esc_html($header_selector); ?> {
                position: fixed !important;
                width: 100%;
                top: 0;
                left: 0;
                z-index: 999;
                transition: all 0.3s ease;
                background-color: var(--header-background-color);
            }

            .admin-bar <?php echo esc_html($header_selector); ?> {
                top: 32px;
            }

            @media screen and (max-width: 782px) {
                .admin-bar <?php echo esc_html($header_selector); ?> {
                    top: 46px;
                }
            }

            body {
                padding-top: var(--header-height, 100px);
            }
        </style>
        <?php
    }

    public function add_debug_info() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div id="giwp-fixed-header-debug" style="position: fixed; bottom: 20px; right: 20px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; z-index: 9999;">
            <div>GIWP Fixed Header Debug</div>
            <div id="giwp-header-status">Statut: En attente...</div>
            <div id="giwp-header-selector"></div>
            <div id="giwp-header-height"></div>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (isset($_POST['giwp_fixed_header_settings'])) {
            check_admin_referer('giwp_fixed_header_settings');
            $settings = [
                'header_selector' => sanitize_text_field($_POST['header_selector']),
                'background_color' => sanitize_text_field($_POST['background_color'])
            ];
            $this->update_settings($settings);
            GIWP()->add_log('Paramètres du header fixe mis à jour', 'success');
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès!</p></div>';
        }

        $settings = $this->get_settings();
        $header_selector = !empty($settings['header_selector']) ? $settings['header_selector'] : '#masthead';
        $background_color = !empty($settings['background_color']) ? $settings['background_color'] : '#ffffff';
        ?>
        <div class="wrap">
            <form method="post" action="">
                <?php wp_nonce_field('giwp_fixed_header_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="header_selector">Sélecteur CSS du header</label>
                        </th>
                        <td>
                            <input type="text" id="header_selector" name="header_selector" 
                                   value="<?php echo esc_attr($header_selector); ?>" class="regular-text">
                            <p class="description">
                                Sélecteur CSS pour cibler le header. Par défaut : #masthead
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="background_color">Couleur de fond</label>
                        </th>
                        <td>
                            <input type="color" id="background_color" name="background_color" 
                                   value="<?php echo esc_attr($background_color); ?>">
                            <p class="description">
                                Couleur de fond du header fixe
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="giwp_fixed_header_settings" class="button-primary" 
                           value="Sauvegarder les modifications">
                </p>
            </form>

            <div class="giwp-fixed-header-help">
                <h3>Aide</h3>
                <p>Pour votre structure actuelle, utilisez le sélecteur par défaut :</p>
                <code>#masthead</code>
                <p>Ce sélecteur cible l'ensemble de votre header.</p>
                <p>Si le header fixe ne fonctionne pas :</p>
                <ol>
                    <li>Vérifiez que le sélecteur correspond bien à votre header</li>
                    <li>Assurez-vous que le header n'est pas déjà fixé par Elementor</li>
                    <li>Consultez les informations de debug en bas à droite de votre site (visible uniquement pour les administrateurs)</li>
                </ol>
            </div>
        </div>
        <?php
    }
} 