<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appels distants vers les sites enfants via MainWP.
 */
class MainWP_GIWeb_API {

	const REMOTE_FUNCTION = 'gi_toolkit';

	/**
	 * @return object|null Activator global.
	 */
	private static function activator() {
		global $mainwp_giweb_activator;
		return $mainwp_giweb_activator ?? null;
	}

	/**
	 * @param int                  $website_id ID site MainWP.
	 * @param string               $action     Action API GI-Toolkit.
	 * @param array<string, mixed> $payload    Données.
	 * @return array<string, mixed>
	 */
	public static function request( $website_id, $action, $payload = array() ) {
		$act = self::activator();
		if ( ! $act || ! method_exists( $act, 'getChildFile' ) || ! method_exists( $act, 'getChildKey' ) ) {
			return array(
				'success' => false,
				'errors'  => array( __( 'Extension MainWP GI-Web non initialisée.', 'mainwp-giweb' ) ),
			);
		}

		$child_key = $act->getChildKey();
		if ( empty( $child_key ) ) {
			return array(
				'success' => false,
				'errors'  => array( __( 'Clé enfant MainWP indisponible. Vérifiez que l’extension est activée.', 'mainwp-giweb' ) ),
			);
		}

		$post_data = array_merge(
			array( 'action' => sanitize_key( $action ) ),
			is_array( $payload ) ? $payload : array()
		);

		$raw = apply_filters(
			'mainwp_fetchurlauthed',
			$act->getChildFile(),
			$child_key,
			absint( $website_id ),
			self::REMOTE_FUNCTION,
			$post_data
		);

		return self::normalize_response( $raw );
	}

	/**
	 * @param mixed $raw Réponse brute MainWP.
	 * @return array<string, mixed>
	 */
	public static function normalize_response( $raw ) {
		if ( is_array( $raw ) && isset( $raw['error'] ) ) {
			return array(
				'success' => false,
				'data'    => array(),
				'errors'  => array( (string) $raw['error'] ),
			);
		}
		if ( is_array( $raw ) && isset( $raw['success'] ) ) {
			return $raw;
		}
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize_response( $decoded );
			}
		}
		return array(
			'success' => false,
			'data'    => array(),
			'errors'  => array( __( 'Réponse enfant invalide.', 'mainwp-giweb' ) ),
		);
	}

	/**
	 * @param int $website_id Site ID.
	 * @return array<string, mixed>
	 */
	public static function get_status( $website_id ) {
		$res = self::request( $website_id, 'status' );
		return $res;
	}

	/**
	 * @param int $website_id Site ID.
	 * @return array<string, mixed>
	 */
	public static function export_site( $website_id ) {
		return self::request( $website_id, 'export' );
	}

	/**
	 * @param int                  $website_id Site ID.
	 * @param array<string, mixed> $bundle     Bundle.
	 * @param array<string, mixed> $args       Args import.
	 * @return array<string, mixed>
	 */
	public static function import_site( $website_id, $bundle, $args = array() ) {
		return self::request(
			$website_id,
			'import',
			array(
				'bundle' => $bundle,
				'args'   => $args,
			)
		);
	}
}
