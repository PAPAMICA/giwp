<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Fired during plugin activation
 *
 * @link       https://genevois-informatique.ch
 * @since      1.0.0
 *
 * @package    	Gi_Toolkit
 * @subpackage	GI-Toolkit/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package   	Gi_Toolkit
 * @subpackage	Gi_Toolkit/includes
 * @author    	Genevois Informatique <contact@genevois-informatique.ch>
 */
class Gi_Toolkit_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'class-gi-toolkit-security.php';
		if ( false === get_option( Gi_Toolkit_Security::OPTION_KEY, false ) ) {
			add_option( Gi_Toolkit_Security::OPTION_KEY, Gi_Toolkit_Security::defaults(), false );
		}
	}

}
