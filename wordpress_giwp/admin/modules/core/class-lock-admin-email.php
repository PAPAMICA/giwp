<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Lock Admin Email
 * Description: Lock the admin email to prevent the admin email from being changed
 * @since 1.0.0
 */
class Gi_Toolkit_Lock_Admin_Email {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_action( 'admin_head-options-general.php', array( $this, 'lock_admin_email' ) );
        add_filter( 'pre_option_admin_email', array( $this,'lock_admin_email_value' ) ) ;
        add_filter( 'pre_option_new_admin_email', array( $this,'lock_admin_email_value' ) ) ;
        add_filter( 'pre_update_option', array( $this, 'prevent_admin_email_change' ), 10, 3 );
    }

    /**
     * Run on option activation.
     * 
     * @since   1.0.0
     */
    public static function activate(){
        require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        if( !defined('GI_TOOLKIT_ADMIN_EMAIL') )    Gi_Toolkit_WP_Config::replace_or_add_constant( 'GI_TOOLKIT_ADMIN_EMAIL', get_option('admin_email'), 'string' );
    }

    /**
     * Run on option deactivation.
     * 
     * @since   1.0.0
     */
    public static function deactivate(){
        require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        Gi_Toolkit_WP_Config::remove_constant('GI_TOOLKIT_ADMIN_EMAIL');
    }
    
    /**
     * Disable the users can register.
     *
     * @return void
     */
    public function lock_admin_email() {
        $submenu_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/wp-options-general.asset.php' );
        wp_enqueue_style( 'Gi_Toolkit_wp-options-general', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wp-options-general.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'Gi_Toolkit_wp-options-general', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wp-options-general.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        wp_localize_script( 'Gi_Toolkit_wp-options-general', 'gi_toolkit_lock_admin_email', array(
            'i18n' => array(
                'disable_email_edit' => esc_js( esc_html__( '🔒 Locked for better security', 'gi-toolkit' ) ),
            ),
        ) );
    }
    
    /**
     * lock_admin_email_value
     *
     * @param  mixed $email
     * @return void
     */
    public function lock_admin_email_value( $email ) {
        return $this->get_locked_admin_email();
    }
    
    /**
     * prevent_admin_email_change
     *
     * @param  mixed $value
     * @param  mixed $option
     * @param  mixed $old_value
     * @return void
     */
    public function prevent_admin_email_change( $value, $option, $old_value ) {
        if ( 'admin_email' == $option || 'new_admin_email' == $option ) {
            return $this->get_locked_admin_email();
        }
        return $value;
    }
    
    /**
     * get_locked_admin_email
     *
     * @return void
     */
    private function get_locked_admin_email(){
        global $wpdb;
        if(defined( 'GI_TOOLKIT_ADMIN_EMAIL' )){
            return GI_TOOLKIT_ADMIN_EMAIL;
        }
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'admin_email'" );
    }
}
