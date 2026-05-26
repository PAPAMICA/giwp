<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the logs.
 *
 * @link       https://genevois-informatique.com
 * @since      2.0.0
 *
 * @package           Gi_Toolkit
 * @subpackage GI-Toolkit/admin
 */
class Gi_Toolkit_Logs {

	/**
	 * add notice
	 * 
	 * @since    2.0.0
	 */
	public static function add_notice( $message ) {
		$message = 'NOTICE: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add error
	 * 
	 * @since    2.0.0
	 */
	public static function add_error( $message ) {
		$message = 'ERROR: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add log
	 * 
	 * @since    2.0.0
	 */
	public static function add_log( $message ) {
		$this_class = new self();
		add_filter( 'gi_toolkit/folders', array( $this_class, 'create_folders' ) );

		$file_path = gi_toolkit_folders() . '/logs/gi-toolkit-' . wp_date( 'Y-m-d' ) . '.log';
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$message   = '[' . wp_date( 'd-M-Y H:i:s' ) . '] ' . print_r( $message, true ) . "\n";

		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message, 3, $file_path );
	}

	/**
     * Create logs folders
     *
     * @since    2.0.0
     */
    public function create_folders( $folders ) {
        $folders['gi-toolkit']['logs'] = array();
        return $folders;
    }
}
