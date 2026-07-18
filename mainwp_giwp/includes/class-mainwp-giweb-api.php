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
	/**
	 * Extrait les messages d’erreur d’une réponse import GI-Toolkit.
	 *
	 * @param array<string, mixed> $result Réponse normalisée.
	 * @return string[]
	 */
	public static function extract_import_errors( $result ) {
		if ( ! is_array( $result ) ) {
			return array();
		}

		$messages = array();
		if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
			foreach ( $result['errors'] as $err ) {
				$err = trim( wp_strip_all_tags( (string) $err ) );
				if ( '' !== $err ) {
					$messages[] = $err;
				}
			}
		}

		if ( ! empty( $result['data']['warnings'] ) && is_array( $result['data']['warnings'] ) ) {
			foreach ( $result['data']['warnings'] as $warn ) {
				$warn = trim( wp_strip_all_tags( (string) $warn ) );
				if ( '' !== $warn ) {
					$messages[] = $warn;
				}
			}
		}

		$matomo = is_array( $result['data']['matomo'] ?? null ) ? $result['data']['matomo'] : array();
		if ( ! empty( $matomo['warning'] ) ) {
			$messages[] = trim( wp_strip_all_tags( (string) $matomo['warning'] ) );
		} elseif ( empty( $matomo['site_id'] ) && ! empty( $matomo['message'] ) ) {
			$messages[] = trim( wp_strip_all_tags( (string) $matomo['message'] ) );
		}

		$kuma = is_array( $result['data']['uptime_kuma'] ?? null ) ? $result['data']['uptime_kuma'] : array();
		if ( ! empty( $kuma['warning'] ) ) {
			$messages[] = trim( wp_strip_all_tags( (string) $kuma['warning'] ) );
		} elseif ( empty( $kuma['monitor_id'] ) && ! empty( $kuma['message'] ) ) {
			$messages[] = trim( wp_strip_all_tags( (string) $kuma['message'] ) );
		}

		return array_values( array_unique( array_filter( $messages ) ) );
	}

	/**
	 * Message d’erreur lisible pour un déploiement / import.
	 *
	 * @param int                  $website_id ID site.
	 * @param string               $label      Nom affiché.
	 * @param string               $raw_error  Erreur brute.
	 * @param array<string, mixed> $result     Réponse API complète (optionnel).
	 * @return string
	 */
	public static function format_site_error( $website_id, $label, $raw_error, $result = null ) {
		$raw_error = trim( wp_strip_all_tags( (string) $raw_error ) );
		$site      = MainWP_GIWeb_Sites::find_by_id( $website_id, self::activator() );
		$label     = $label ?: ( $site['name'] ?? '' );
		$url       = $site['url'] ?: ( '#' . $website_id );

		$structured = self::extract_import_errors( is_array( $result ) ? $result : array() );
		if ( ! empty( $structured ) ) {
			$joined = implode( ' — ', $structured );
			if ( self::message_looks_like_matomo( $joined ) ) {
				$joined = '[Matomo] ' . $joined;
			} elseif ( self::message_looks_like_uptime_kuma( $joined ) ) {
				$joined = '[Uptime Kuma] ' . $joined;
			}
			return sprintf( '« %1$s » : %2$s', $label, $joined );
		}

		if ( false !== stripos( $raw_error, 'child plugin not detected' ) ) {
			return sprintf(
				/* translators: 1: site name, 2: site URL */
				__(
					'MainWP Child introuvable sur « %1$s » (%2$s). Vérifiez que le plugin est installé et actif, puis reconnectez le site dans MainWP > Sites.',
					'mainwp-giweb'
				),
				$label,
				$url
			);
		}

		if ( self::message_looks_like_timeout( $raw_error ) ) {
			return sprintf(
				/* translators: 1: site name, 2: technical detail */
				__(
					'« %1$s » : délai dépassé ou réponse invalide du site enfant (souvent Matomo ou Uptime Kuma pendant l’import). Détail : %2$s',
					'mainwp-giweb'
				),
				$label,
				$raw_error
			);
		}

		if ( '' === $raw_error ) {
			return sprintf(
				__( 'Échec de l’import sur « %1$s » (%2$s) sans message détaillé.', 'mainwp-giweb' ),
				$label,
				$url
			);
		}

		if ( self::message_looks_like_matomo( $raw_error ) ) {
			return sprintf( '« %1$s » — [Matomo] %2$s', $label, $raw_error );
		}

		if ( self::message_looks_like_uptime_kuma( $raw_error ) ) {
			return sprintf( '« %1$s » — [Uptime Kuma] %2$s', $label, $raw_error );
		}

		return sprintf( '« %1$s » : %2$s', $label, $raw_error );
	}

	/**
	 * @param string $message Message.
	 * @return bool
	 */
	private static function message_looks_like_matomo( $message ) {
		return false !== stripos( $message, 'matomo' )
			|| ( false !== stripos( $message, 'token api' ) && false === stripos( $message, 'kuma' ) )
			|| ( false !== stripos( $message, 'site_id' ) && false === stripos( $message, 'monitor' ) );
	}

	/**
	 * @param string $message Message.
	 * @return bool
	 */
	private static function message_looks_like_uptime_kuma( $message ) {
		return false !== stripos( $message, 'uptime kuma' )
			|| false !== stripos( $message, 'kuma' )
			|| false !== stripos( $message, 'monitor_id' )
			|| false !== stripos( $message, 'monitor ' );
	}

	/**
	 * @param string $message Message.
	 * @return bool
	 */
	private static function message_looks_like_timeout( $message ) {
		return false !== stripos( $message, 'could not be reached' )
			|| false !== stripos( $message, 'http request failed' )
			|| false !== stripos( $message, 'timed out' )
			|| false !== stripos( $message, 'timeout' )
			|| false !== stripos( $message, 'curl error 28' );
	}

	/**
	 * Message court pour les logs de déploiement (modal + historique).
	 *
	 * @param int                  $website_id ID site.
	 * @param string               $label      Libellé site.
	 * @param array<string, mixed> $result     Réponse import.
	 * @param bool                 $ok         Import réussi.
	 * @return string
	 */
	public static function format_deploy_result_message( $website_id, $label, $result, $ok ) {
		if ( $ok ) {
			$warnings = self::extract_import_errors( $result );
			if ( ! empty( $warnings ) ) {
				return 'OK — ' . implode( ' — ', $warnings );
			}
			if ( ! empty( $result['data']['matomo']['site_id'] ) ) {
				return sprintf(
					/* translators: %d: Matomo site ID */
					__( 'OK — Matomo site_id %d', 'mainwp-giweb' ),
					absint( $result['data']['matomo']['site_id'] )
				);
			}
			if ( ! empty( $result['data']['uptime_kuma']['monitor_id'] ) ) {
				return sprintf(
					/* translators: %d: Uptime Kuma monitor ID */
					__( 'OK — Uptime Kuma monitor_id %d', 'mainwp-giweb' ),
					absint( $result['data']['uptime_kuma']['monitor_id'] )
				);
			}
			return __( 'OK', 'mainwp-giweb' );
		}

		$raw = ! empty( $result['errors'][0] ) ? (string) $result['errors'][0] : __( 'Échec de l’import.', 'mainwp-giweb' );
		return self::format_site_error( $website_id, $label, $raw, $result );
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
	 * Statistiques Mail Catcher d’un site enfant (action API dédiée).
	 *
	 * @param int                  $website_id ID MainWP.
	 * @param array<string, mixed> $args       failures_limit (1–20).
	 * @return array<string, mixed>
	 */
	public static function get_mail( $website_id, $args = array() ) {
		$payload = array();
		if ( isset( $args['failures_limit'] ) ) {
			$payload['failures_limit'] = max( 1, min( 20, absint( $args['failures_limit'] ) ) );
		}
		return self::request( $website_id, 'mail', $payload );
	}

	/**
	 * Extrait le payload mail d’une réponse API (status ou mail).
	 *
	 * @param array<string, mixed>|null $response Réponse normalisée.
	 * @return array<string, mixed>|null
	 */
	public static function extract_mail_payload( $response ) {
		if ( ! is_array( $response ) || empty( $response['success'] ) || ! is_array( $response['data'] ?? null ) ) {
			return null;
		}

		$data = $response['data'];
		if ( ! empty( $data['mail_catcher'] ) && is_array( $data['mail_catcher'] ) ) {
			return $data['mail_catcher'];
		}
		if ( array_key_exists( 'module_active', $data ) ) {
			return $data;
		}

		return null;
	}

	/**
	 * Agrégat mail du réseau (cache dashboard, sans appel enfant).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_mail_network() {
		return MainWP_GIWeb_Mail_Stats::get_aggregate();
	}

	/**
	 * Mail d’un site : cache agrégat, sinon appel API enfant.
	 *
	 * @param int  $website_id ID MainWP.
	 * @param bool $refresh    Forcer un appel distant.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_site_mail( $website_id, $refresh = false ) {
		$website_id = absint( $website_id );
		if ( ! $website_id ) {
			return null;
		}

		if ( ! $refresh ) {
			$cached = MainWP_GIWeb_Mail_Stats::get_site_mail( $website_id );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = self::get_mail( $website_id );
		return self::extract_mail_payload( $response );
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

	/**
	 * Rafraîchit Matomo + Uptime Kuma sur le site enfant (post-déploiement).
	 *
	 * @param int                  $website_id Site ID MainWP.
	 * @param array<string, mixed> $payload    Flags optionnels refresh_matomo / refresh_kuma.
	 * @return array<string, mixed>
	 */
	public static function sync_integrations( $website_id, $payload = array() ) {
		return self::request( $website_id, 'sync_integrations', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Après un import réussi, re-lie uniquement les intégrations manquantes / en warning.
	 *
	 * @param int                  $website_id Site ID.
	 * @param array<string, mixed> $import     Résultat import.
	 * @return array{message:string, result:array<string,mixed>|null}
	 */
	public static function refresh_integrations_after_deploy( $website_id, $import = array() ) {
		if ( empty( $import['success'] ) ) {
			return array(
				'message' => '',
				'result'  => null,
			);
		}

		$matomo_import = is_array( $import['data']['matomo'] ?? null ) ? $import['data']['matomo'] : array();
		$kuma_import   = is_array( $import['data']['uptime_kuma'] ?? null ) ? $import['data']['uptime_kuma'] : array();

		$needs_matomo = empty( $matomo_import['site_id'] ) || ! empty( $matomo_import['warning'] );
		$needs_kuma   = empty( $kuma_import['monitor_id'] ) || ! empty( $kuma_import['warning'] );

		// Import sans bloc Matomo/Kuma : comportement historique (tout resynchroniser).
		if ( ! isset( $import['data']['matomo'] ) && ! isset( $import['data']['uptime_kuma'] ) ) {
			$needs_matomo = true;
			$needs_kuma   = true;
		}

		if ( ! $needs_matomo && ! $needs_kuma ) {
			return array(
				'message' => '',
				'result'  => null,
			);
		}

		$payload = array();
		if ( $needs_matomo ) {
			$payload['refresh_matomo'] = '1';
		}
		if ( $needs_kuma ) {
			$payload['refresh_kuma'] = '1';
		}

		$result = self::sync_integrations( $website_id, $payload );
		$data   = is_array( $result['data'] ?? null ) ? $result['data'] : array();
		$parts  = array();

		$matomo = is_array( $data['matomo'] ?? null ) ? $data['matomo'] : array();
		if ( empty( $matomo['skipped'] ) ) {
			if ( ! empty( $matomo['success'] ) && ! empty( $matomo['site_id'] ) ) {
				$parts[] = sprintf(
					/* translators: %d: Matomo site id */
					__( 'Matomo re-lié (site #%d)', 'mainwp-giweb' ),
					absint( $matomo['site_id'] )
				);
			} elseif ( ! empty( $matomo['message'] ) ) {
				$parts[] = sprintf(
					/* translators: %s: error */
					__( 'Matomo : %s', 'mainwp-giweb' ),
					(string) $matomo['message']
				);
			} elseif ( ! empty( $result['errors'][0] ) && self::message_looks_like_matomo( (string) $result['errors'][0] ) ) {
				$parts[] = (string) $result['errors'][0];
			}
		}

		$kuma = is_array( $data['uptime_kuma'] ?? null ) ? $data['uptime_kuma'] : array();
		if ( empty( $kuma['skipped'] ) ) {
			if ( ! empty( $kuma['success'] ) && ! empty( $kuma['monitor_id'] ) ) {
				$parts[] = sprintf(
					/* translators: %d: monitor id */
					__( 'Uptime Kuma re-lié (monitor #%d)', 'mainwp-giweb' ),
					absint( $kuma['monitor_id'] )
				);
			} elseif ( ! empty( $kuma['message'] ) ) {
				$parts[] = sprintf(
					/* translators: %s: error */
					__( 'Uptime Kuma : %s', 'mainwp-giweb' ),
					(string) $kuma['message']
				);
			}
		}

		// Ancien GI-Toolkit sans action sync_integrations : ignorer silencieusement.
		if ( empty( $parts ) && ! empty( $result['errors'][0] ) ) {
			$err = (string) $result['errors'][0];
			if ( false !== stripos( $err, 'inconnue' ) || false !== stripos( $err, 'unknown' ) ) {
				return array(
					'message' => '',
					'result'  => $result,
				);
			}
		}

		return array(
			'message' => implode( ' · ', $parts ),
			'result'  => $result,
		);
	}
}
