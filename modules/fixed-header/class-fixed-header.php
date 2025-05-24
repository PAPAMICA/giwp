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
    }

    public function add_custom_css() {
        $settings = $this->get_settings();
        $header_selector = !empty($settings['header_selector']) ? $settings['header_selector'] : '.site-header, header.site-header, .elementor-location-header';
        ?>
        <style type="text/css">
            <?php echo esc_html($header_selector); ?> {
                position: fixed;
                width: 100%;
                top: 0;
                left: 0;
                z-index: 999;
                transition: all 0.3s ease;
            }
            body {
                padding-top: var(--header-height, 100px);
            }
        </style>
        <?php
    }

    public function render_settings_page() {
        if (isset($_POST['giwp_fixed_header_settings'])) {
            $settings = [
                'header_selector' => sanitize_text_field($_POST['header_selector'])
            ];
            $this->update_settings($settings);
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès!</p></div>';
        }

        $settings = $this->get_settings();
        $header_selector = !empty($settings['header_selector']) ? $settings['header_selector'] : '.site-header, header.site-header, .elementor-location-header';
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($this->get_name()); ?></h2>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="header_selector">Sélecteur CSS du header</label>
                        </th>
                        <td>
                            <input type="text" id="header_selector" name="header_selector" 
                                   value="<?php echo esc_attr($header_selector); ?>" class="regular-text">
                            <p class="description">
                                Sélecteur CSS pour cibler le header. Par défaut : .site-header, header.site-header, .elementor-location-header
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="giwp_fixed_header_settings" class="button-primary" 
                           value="Sauvegarder les modifications">
                </p>
            </form>
        </div>
        <?php
    }
} 