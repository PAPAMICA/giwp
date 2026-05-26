<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appels distants vers les sites enfants via MainWP.
 */
class MainWP_GIWeb_API {

	/** Fonction MainWP Child whitelistée (délègue via mainwp_child_extra_execution). */
	const REMOTE_FUNCTION = 'extra_execution';

	/**
	 * @return object|null Activator global.
	 */
	private static function activator() {
		global $mainwp_giweb_activator;
		if ( ! $mainwp_giweb_activator ) {
			return null;
		}
		if ( empty( $mainwp_giweb_activator->childKey ) && method_exists( $mainwp_giweb_activator, 'activate_extension' ) ) {
			$mainwp_giweb_activator->activate_extension();
		}
		return $mainwp_giweb_activator;
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
			array(
				'gi_toolkit_request' => 1,
				'action'             => sanitize_key( $action ),
			),
			is_array( $payload ) ? $payload : array()
		);

		$timeout_cb = static function () {
			return 120;
		};
		add_filter( 'http_request_timeout', $timeout_cb, 999 );
		add_filter( 'mainwp_http_request_timeout', $timeout_cb, 999 );

		$raw = apply_filters(
			'mainwp_fetchurlauthed',
			$act->getChildFile(),
			$child_key,
			absint( $website_id ),
			self::REMOTE_FUNCTION,
			$post_data
		);

		remove_filter( 'http_request_timeout', $timeout_cb, 999 );
		remove_filter( 'mainwp_http_request_timeout', $timeout_cb, 999 );

		return self::normalize_response( $raw );
	}

	/**
	 * Message d’erreur lisible (sans HTML MainWP).
	 *
	 * @param int    $website_id ID site.
	 * @param string $label      Nom affiché.
	 * @param string $raw_error  Erreur brute.
	 * @return string
	 */
	public static function format_site_error( $website_id, $label, $raw_error ) {
		$raw_error = wp_strip_all_tags( (string) $raw_error );
		$site      = MainWP_GIWeb_Sites::find_by_id( $website_id, self::activator() );
		$url       = $site['url'] ?: ( '#' . $website_id );

		if ( false !== stripos( $raw_error, 'child plugin not detected' )
			|| false !== stripos( $raw_error, 'could not be reached' ) ) {
			return sprintf(
				/* translators: 1: site name, 2: site URL */
				__(
					'MainWP ne parvient pas à joindre « %1$s » (%2$s). Vérifiez que MainWP Child est installé et actif sur ce site, puis reconnectez le site dans MainWP > Sites. Testez aussi « Synchroniser les statuts » sur ce site.',
					'mainwp-giweb'
				),
				$label ?: $site['name'],
				$url
			);
		}

		if ( '' === $raw_error ) {
			return sprintf(
				__( 'Échec de communication avec « %1$s » (%2$s).', 'mainwp-giweb' ),
				$label ?: $site['name'],
				$url
			);
		}

		return $raw_error;
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
		if ( is_array( $raw ) && isset( $raw['response'] ) ) {
			return self::normalize_response( $raw['response'] );
		}
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize_response( $decoded );
			}
			$unserialized = maybe_unserialize( $raw );
			if ( is_array( $unserialized ) ) {
				return self::normalize_response( $unserialized );
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
