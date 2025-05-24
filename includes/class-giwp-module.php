<?php

abstract class GIWP_Module {
    protected $id;
    protected $name;
    protected $description;
    protected $version = '1.0.0';

    public function __construct() {
        $this->init_module();
    }

    abstract protected function init_module();

    public function get_id() {
        return $this->id;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_description() {
        return $this->description;
    }

    public function is_active() {
        return get_option('giwp_module_' . $this->id . '_active', false);
    }

    public function activate() {
        update_option('giwp_module_' . $this->id . '_active', true);
        $this->on_activation();
    }

    public function deactivate() {
        update_option('giwp_module_' . $this->id . '_active', false);
        $this->on_deactivation();
    }

    protected function on_activation() {
        // À surcharger dans les modules enfants si nécessaire
    }

    protected function on_deactivation() {
        // À surcharger dans les modules enfants si nécessaire
    }

    public function get_settings() {
        return get_option('giwp_module_' . $this->id . '_settings', []);
    }

    public function update_settings($settings) {
        return update_option('giwp_module_' . $this->id . '_settings', $settings);
    }

    public function import_settings($settings) {
        return $this->update_settings($settings);
    }

    abstract public function init();

    public function render_settings_page() {
        // À surcharger dans les modules enfants
        echo '<div class="wrap">';
        echo '<h2>' . esc_html($this->get_name()) . ' Settings</h2>';
        echo '<p>' . esc_html($this->get_description()) . '</p>';
        echo '</div>';
    }
} 