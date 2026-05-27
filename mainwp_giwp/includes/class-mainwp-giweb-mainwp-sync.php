<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alimente les stats mail lors de la synchronisation globale MainWP.
 */
class MainWP_GIWeb_MainWP_Sync {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'mainwp_site_synced', array( __CLASS__, 'on_site_synced' ), 20, 2 );
	}

	/**
	 * @param object               $website     Objet site MainWP.
	 * @param array<string, mixed> $information Données remontées par MainWP.
	 * @return void
	 */
	public static function on_site_synced( $website, $information ) {
		unset( $information );

		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			return;
		}

		global $mainwp_giweb_activator;
		if ( ! $mainwp_giweb_activator || empty( $mainwp_giweb_activator->childEnabled ) ) {
			return;
		}

		if ( ! is_object( $website ) || empty( $website->id ) ) {
			return;
		}

		$site_id = absint( $website->id );
		if ( ! $site_id ) {
			return;
		}

		$label = ! empty( $website->name ) ? (string) $website->name : '';
		if ( '' === $label && ! empty( $website->url ) ) {
			$label = (string) $website->url;
		}
		if ( '' === $label ) {
			$label = '#' . $site_id;
		}

		$url    = ! empty( $website->url ) ? (string) $website->url : '';
		$result = MainWP_GIWeb_Deploy::sync_site_status( $site_id, $label );

		MainWP_GIWeb_Status_Cache::set_site( $site_id, $result['api'] );

		MainWP_GIWeb_Mail_Stats::record_site_sync( $site_id, $label, $url, $result['api'] );
	}
}

MainWP_GIWeb_MainWP_Sync::init();
