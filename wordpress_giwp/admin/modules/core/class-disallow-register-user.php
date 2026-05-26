<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disallow Register User
 * Description: Disallow register user option in the admin area for better security
 * @since 1.0.0
 */
class Gi_Toolkit_Disallow_Register_User {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_filter( 'pre_option_users_can_register', '__return_zero' );
        add_action( 'admin_head-options-general.php', array( $this, 'disable_users_can_register' ) );
    }
    
    /**
     * Disable the users can register.
     *
     * @return void
     */
    public function disable_users_can_register() {
        $submenu_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/wp-options-general.asset.php' );
        wp_enqueue_style( 'Gi_Toolkit_wp-options-general', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wp-options-general.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'Gi_Toolkit_wp-options-general', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wp-options-general.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        wp_localize_script( 'Gi_Toolkit_wp-options-general', 'gi_toolkit_disallow_register_user', array(
            'i18n' => array(
                'disable_users_can_register' => esc_js( esc_html__( '🔒 Disabled for better security', 'gi-toolkit' ) ),
            ),
        ) );
    }
}