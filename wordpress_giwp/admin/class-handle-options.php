<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the options of the plugin.
 *
 * @link       https://genevois-informatique.ch
 * @since      1.0.0
 *
 * @package           Gi_Toolkit
 * @subpackage GI-Toolkit/admin
 */
class Gi_Toolkit_Handle_options {

    /**
	 * Include all active options classes
	 *
	 * @since    1.0.0
	 */
    public function __construct() {
        $this->instantiate_active_options();
        $this->instantiate_custom_options();
    }
    
    /**
     * instantiate_active_options
     *
     * @return void
     */
    private function instantiate_active_options(){

        $db_options     = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
        $options_data   = gi_toolkit_options( 'normal' );
        
        /**
         * If you want debug the plugin, you can set the constant GI_TOOLKIT_SAFE_MODE to true.
         * This will prevent the plugin from loading all modules classes.
         *
         * @since 2.10.0
         */
        if( defined( 'GI_TOOLKIT_SAFE_MODE' ) && GI_TOOLKIT_SAFE_MODE === true ) return;

        foreach ( $db_options as $option_key => $option_status ) {
            
            if ( $option_status == '1' ) {

                $option_data = $options_data[$option_key] ?? array();
                $option_path = $option_data['path'] ?? '';
                
                /**
                 * Check if is relative path in plugin folder.
                 */
                if ( strpos( $option_path, 'pro/' ) === 0 || strpos( $option_path, 'core/' ) === 0 ) {
                    $option_path = gi_toolkit_resolve_module_path( $option_path );
                }
                
                if ( $option_path && is_file( $option_path ) ) {
                    require_once $option_path;

                    // check if the class exists
                    if ( class_exists( $option_key ) ) {
                        new $option_key;
                    }
                }
            }
        }
    }

    /**
     * Instantiate Custom Classes
     */
    private function instantiate_custom_options() {

        $options_path   = GI_TOOLKIT_PLUGIN_PATH . 'admin/modules/';
        $custom_options = array(
            'Gi_Toolkit_Nginx_Code_Snippets' => 'core/class-nginx-code-snippets.php'
        );

        foreach ( $custom_options as $class_name => $class_path ) {

            $class_path = gi_toolkit_resolve_module_path( $class_path );

            if ( $class_path && is_file( $class_path ) ) {
                require_once $class_path;

                // check if the class exists
                if ( class_exists( $class_name ) ) {
                    new $class_name;
                }
            }
        }

    }
    
    /**
     * require_once_all_options
     *
     * @return void
     */
    public static function require_once_all_options(){

        $options_data = gi_toolkit_options( 'normal' );

        foreach ( $options_data as $option_key => $option_data ) {
            
            $option_path = $option_data['path'] ?? '';
            
            /**
             * Check if is relative path in plugin folder.
             */
            if ( strpos( $option_path, 'pro/' ) === 0 || strpos( $option_path, 'core/' ) === 0 ) {
                $option_path = gi_toolkit_resolve_module_path( $option_path );
            }

            if ( $option_path && is_file( $option_path ) ) {
                require_once $option_path;
            }
        }

    }

}
new Gi_Toolkit_Handle_options;