<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Send data to Gi_Toolkit if the user is allowed to do so.
 * No personal information is collected, only general Gi_Toolkit settings.
 *
 * @link       https://genevois-informatique.com
 * @since      1.0.0
 *
 * @package           Gi_Toolkit
 * @subpackage GI-Toolkit/admin
 */
class Gi_Toolkit_Stats {

	/**
	 * URL to the Gi_Toolkit API endpoint.
	 *
	 * @since    1.12.0
	 * @access   private
	 * @var string
	 */
	private $update_url;

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		$this->update_url = 'https://genevois-informatique.com/wp-json/gi_toolkit-opt-in/v1/update';

		if ( wp_get_environment_type() == 'local' || GI_TOOLKIT_DEV_MOD ) {
			$this->update_url = false;
		}
	}

	/**
	 * Send data to Gi_Toolkit if the user is allowed to do so.
	 */
	public function send_stats() {
		$settings  = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_opt_in', array() );
		$value     = $settings['value'] ?? '1';

		if ( $value != '1' ) {
			return;
		}

		$options = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );

		$body = array(
			'options' => $options,
			'version' => GI_TOOLKIT_VERSION,
		);

		if ( $this->update_url ) {
			wp_remote_post( $this->update_url, array(
				'body'    => $body,
				'timeout' => 60,
			));
		}
	}
}
