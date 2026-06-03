<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache persistant des statuts GI-Toolkit par site (survit aux rechargements).
 */
class MainWP_GIWeb_Status_Cache {

	const OPTION_KEY = 'mainwp_giweb_status_cache';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all() {
		$cache = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		$legacy = get_transient( 'mainwp_giweb_status_cache' );
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			foreach ( $legacy as $site_id => $api ) {
				if ( is_numeric( $site_id ) && is_array( $api ) ) {
					$cache[ (int) $site_id ] = $api;
				}
			}
			if ( ! isset( $cache['_meta'] ) || ! is_array( $cache['_meta'] ) ) {
				$cache['_meta'] = array();
			}
			$cache['_meta']['updated_at'] = time();
			update_option( self::OPTION_KEY, $cache, false );
			delete_transient( 'mainwp_giweb_status_cache' );
		}

		unset( $cache['_meta'] );
		return $cache;
	}

	/**
	 * @param int                  $site_id ID site.
	 * @param array<string, mixed> $api     Réponse API.
	 * @return void
	 */
	public static function set_site( $site_id, $api ) {
		$site_id = absint( $site_id );
		if ( $site_id <= 0 || ! is_array( $api ) ) {
			return;
		}

		$cache = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		$cache[ $site_id ] = $api;
		if ( ! isset( $cache['_meta'] ) || ! is_array( $cache['_meta'] ) ) {
			$cache['_meta'] = array();
		}
		$cache['_meta']['updated_at'] = time();

		update_option( self::OPTION_KEY, $cache, false );
	}

	/**
	 * @return int Timestamp dernière mise à jour.
	 */
	public static function get_updated_at() {
		$cache = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $cache ) || empty( $cache['_meta']['updated_at'] ) ) {
			return 0;
		}
		return (int) $cache['_meta']['updated_at'];
	}

	/**
	 * Marque le début d’une resynchronisation (ne vide pas les données existantes).
	 *
	 * @return void
	 */
	public static function mark_sync_started() {
		$cache = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		if ( ! isset( $cache['_meta'] ) || ! is_array( $cache['_meta'] ) ) {
			$cache['_meta'] = array();
		}
		$cache['_meta']['sync_started_at'] = time();
		update_option( self::OPTION_KEY, $cache, false );
	}

	/**
	 * Marque la fin d’une synchronisation globale MainWP.
	 *
	 * @return void
	 */
	public static function mark_sync_completed() {
		$cache = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		if ( ! isset( $cache['_meta'] ) || ! is_array( $cache['_meta'] ) ) {
			$cache['_meta'] = array();
		}
		$cache['_meta']['updated_at']        = time();
		$cache['_meta']['sync_completed_at'] = time();
		update_option( self::OPTION_KEY, $cache, false );
	}
}
