<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Fired during plugin deactivation
 *
 * @link       https://genevois-informatique.ch
 * @since      1.0.0
 *
 * @package    	Gi_Toolkit
 * @subpackage	GI-Toolkit/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package   	Gi_Toolkit
 * @subpackage	Gi_Toolkit/includes
 * @author    	Genevois Informatique <contact@genevois-informatique.ch>
 */
class Gi_Toolkit_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		self::delete_limit_login_attempts_table();
	}

	/**
	 * Delete table
	 * 
	 * @since 1.5.0
	 */
	public static function delete_limit_login_attempts_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . GI_TOOLKIT_PLUGIN_SETTINGS . '_limit_login_attempts';
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );
	}
}
