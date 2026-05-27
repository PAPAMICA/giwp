<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contrôle d’accès compatible MainWP Team Control.
 */
class MainWP_GIWeb_Capabilities {

	/** @var string */
	const EXTENSION_SLUG = 'mainwp-giwp';

	/**
	 * Accès à l’extension GI-Toolkit Manager (pages, AJAX, widget).
	 *
	 * @return bool
	 */
	public static function can_access() {
		if ( function_exists( 'mainwp_current_user_can' ) ) {
			if ( mainwp_current_user_can( 'extension', self::EXTENSION_SLUG ) ) {
				return true;
			}
			if ( mainwp_current_user_can( 'dashboard', 'manage_sites' ) ) {
				return true;
			}
			if ( mainwp_current_user_can( 'dashboard', 'manage_extensions' ) ) {
				return true;
			}
			if ( mainwp_current_user_can( 'dashboard', 'overview' ) ) {
				return true;
			}
		}

		return current_user_can( 'manage_options' ) || current_user_can( 'manage_network' );
	}
}
