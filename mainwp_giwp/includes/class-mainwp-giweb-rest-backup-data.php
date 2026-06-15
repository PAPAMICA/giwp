<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Données backup partagées entre REST MainWP v1 et v2.
 */
class MainWP_GIWeb_Rest_Backup_Data {

	/**
	 * Statut backup de tous les sites (cache agrégat MainWP).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_network_payload() {
		return MainWP_GIWeb_Backup_Stats::build_api_network_payload();
	}
}
