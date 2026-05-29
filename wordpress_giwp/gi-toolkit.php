<?php
/**
 * @wordpress-plugin
 * Plugin Name:       GI-Toolkit
 * Plugin URI:        https://genevois-informatique.com/
 * Description:       GI-Toolkit enhances your WordPress administration experience by providing a powerful suite of features designed to optimize and streamline your website management. From media enhancements to user experience improvements and security fortifications, this toolkit is essential for any WordPress site owner looking to elevate their admin interface. With easy-to-use settings and impactful tweaks, you can tailor your site's backend to your specific needs.
 * Version:           2.23.6
 * Author:            Genevois Informatique
 * Author URI:        https://genevois-informatique.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gi-toolkit
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if you are in local or development environment.
 */
$gi_toolkit_is_local = false;
if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	$gi_toolkit_is_local = true;
} elseif ( defined( 'WP_ENVIRONMENT_TYPE' ) && in_array( WP_ENVIRONMENT_TYPE, array( 'local', 'development' ), true ) ) {
	$gi_toolkit_is_local = true;
} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) ) {
	$gi_toolkit_is_local = true;
}

$gi_toolkit_version  = get_file_data( __FILE__, array( 'Version' => 'Version' ), false )['Version'];

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'GI_TOOLKIT_VERSION', $gi_toolkit_version );

/**
 * You can use this const for check if you are in local environment
 */
define( 'GI_TOOLKIT_DEV_MOD', $gi_toolkit_is_local );

/**
 * Plugin File
 */
define( 'GI_TOOLKIT_PLUGIN_FILE', __FILE__ );

/**
 * Plugin Name Path for plugin includes.
 */
define( 'GI_TOOLKIT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin Name URL for plugin sources (css, js, images etc...).
 */
define( 'GI_TOOLKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin Name Basename for plugin sources (css, js, images etc...).
 */
define( 'GI_TOOLKIT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin Settings Name
 */
define ( 'GI_TOOLKIT_PLUGIN_SETTINGS', 'gi_toolkit_settings' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-gi-toolkit-activator.php
 */
register_activation_hook( __FILE__, function(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gi-toolkit-activator.php';
	Gi_Toolkit_Activator::activate();
} );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-gi-toolkit-deactivator.php
 */
register_deactivation_hook( __FILE__, function(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gi-toolkit-deactivator.php';
	Gi_Toolkit_Deactivator::deactivate();
} );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-gi-toolkit.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function gi_toolkit_run() {

	$plugin = new Gi_Toolkit();
	$plugin->run();

}
gi_toolkit_run();
