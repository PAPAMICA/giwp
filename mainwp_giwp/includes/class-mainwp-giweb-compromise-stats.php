<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des statuts Détection de compromission remontés via MainWP.
 */
class MainWP_GIWeb_Compromise_Stats {

	const AGGREGATE_OPTION = 'mainwp_giweb_compromise_aggregate';

	/**
	 * @param array<string, mixed> $data Données statut site.
	 * @return array<string, mixed>|null
	 */
	public static function extract_compromise( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( ! empty( $data['compromise'] ) && is_array( $data['compromise'] ) ) {
			return $data['compromise'];
		}
		if ( array_key_exists( 'open_count', $data ) && array_key_exists( 'module_active', $data ) ) {
			return $data;
		}
		return null;
	}

	/**
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function extract_compromise_from_sync( $information ) {
		if ( ! is_array( $information ) ) {
			return null;
		}
		if ( ! empty( $information['gi_toolkit_compromise'] ) && is_array( $information['gi_toolkit_compromise'] ) ) {
			return $information['gi_toolkit_compromise'];
		}
		if ( ! empty( $information['gi_toolkit_sync']['compromise'] ) && is_array( $information['gi_toolkit_sync']['compromise'] ) ) {
			return $information['gi_toolkit_sync']['compromise'];
		}
		return null;
	}

	/**
	 * @param array<string, mixed>|null $api_data    Données API GI-Toolkit.
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_compromise_payload( $api_data, $information = null ) {
		$from_api  = is_array( $api_data ) ? self::extract_compromise( $api_data ) : null;
		$from_sync = self::extract_compromise_from_sync( $information );
		return MainWP_GIWeb_MainWP_Sync::pick_richer_payload( $from_api, $from_sync );
	}

	/**
	 * @param int                       $site_id     ID MainWP.
	 * @param string                    $label       Nom site.
	 * @param string                    $url         URL site.
	 * @param array<string, mixed>      $api         Réponse API complète.
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>
	 */
	public static function record_site_sync( $site_id, $label, $url, $api, $information = null ) {
		$site_id = absint( $site_id );
		$ok      = ! empty( $api['success'] );
		$data    = is_array( $api['data'] ?? null ) ? $api['data'] : array();
		$cd      = self::resolve_compromise_payload( $data, $information );

		$aggregate = self::get_aggregate();
		if ( ! isset( $aggregate['sites'] ) || ! is_array( $aggregate['sites'] ) ) {
			$aggregate['sites'] = array();
		}

		$aggregate['sites'][ $site_id ] = array(
			'label'       => $label,
			'url'         => $url,
			'api_ok'      => $ok,
			'synced_at'   => time(),
			'compromise'  => $cd,
		);

		$aggregate['updated_at'] = time();
		$aggregate['network']    = self::compute_network( $aggregate['sites'] );

		update_option( self::AGGREGATE_OPTION, $aggregate, false );

		return $aggregate;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_aggregate() {
		$saved = get_option( self::AGGREGATE_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		if ( empty( $saved['sites'] ) || ! is_array( $saved['sites'] ) ) {
			$saved['sites'] = array();
		}
		if ( empty( $saved['network'] ) || ! is_array( $saved['network'] ) ) {
			$saved['network'] = self::compute_network( $saved['sites'] );
		}
		return $saved;
	}

	/**
	 * @param array<int, array<string, mixed>> $sites Sites indexés par ID.
	 * @return array<string, mixed>
	 */
	public static function compute_network( $sites ) {
		$network = array(
			'sites_tracked' => 0,
			'sites_ok'      => 0,
			'sites_warn'    => 0,
			'sites_down'    => 0,
			'sites_missing' => 0,
			'open_total'    => 0,
		);

		if ( ! is_array( $sites ) ) {
			return $network;
		}

		foreach ( $sites as $row ) {
			++$network['sites_tracked'];
			$cd    = is_array( $row['compromise'] ?? null ) ? $row['compromise'] : null;
			$state = self::get_visual_state( $cd );
			if ( 'ok' === $state ) {
				++$network['sites_ok'];
			} elseif ( 'warn' === $state ) {
				++$network['sites_warn'];
			} elseif ( 'down' === $state ) {
				++$network['sites_down'];
			} else {
				++$network['sites_missing'];
			}
			$network['open_total'] += is_array( $cd ) ? (int) ( $cd['open_count'] ?? 0 ) : 0;
		}

		return $network;
	}

	/**
	 * @param int $site_id ID MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function get_site_compromise( $site_id ) {
		$site_id = absint( $site_id );
		if ( ! $site_id ) {
			return null;
		}

		$agg   = self::get_aggregate();
		$sites = $agg['sites'] ?? array();
		if ( is_array( $sites ) && isset( $sites[ $site_id ]['compromise'] ) ) {
			$cd = $sites[ $site_id ]['compromise'];
			return is_array( $cd ) ? $cd : null;
		}

		$cache = MainWP_GIWeb_Status_Cache::get_all();
		if ( ! isset( $cache[ $site_id ] ) || ! is_array( $cache[ $site_id ] ) ) {
			return null;
		}

		return self::extract_compromise( $cache[ $site_id ]['data'] ?? array() );
	}

	/**
	 * @param array<string, mixed>|null $cd Payload compromission.
	 * @return string ok|warn|down|missing
	 */
	public static function get_visual_state( $cd ) {
		if ( ! is_array( $cd ) ) {
			return 'missing';
		}
		if ( empty( $cd['module_active'] ) ) {
			return 'missing';
		}
		if ( (int) ( $cd['open_count'] ?? 0 ) > 0 ) {
			return 'down';
		}
		if ( ! empty( $cd['paused'] ) || empty( $cd['pushover_ok'] ) ) {
			return 'warn';
		}
		return 'ok';
	}

	/**
	 * @param array<string, mixed>|null $cd Payload.
	 * @return string
	 */
	public static function format_status_label( $cd ) {
		$state = self::get_visual_state( $cd );
		switch ( $state ) {
			case 'ok':
				return __( 'Aucune alerte', 'mainwp-giweb' );
			case 'warn':
				if ( is_array( $cd ) && ! empty( $cd['paused'] ) ) {
					return __( 'En pause', 'mainwp-giweb' );
				}
				if ( is_array( $cd ) && empty( $cd['pushover_ok'] ) ) {
					return __( 'Pushover incomplet', 'mainwp-giweb' );
				}
				return __( 'Attention', 'mainwp-giweb' );
			case 'down':
				$n = is_array( $cd ) ? (int) ( $cd['open_count'] ?? 0 ) : 0;
				return sprintf(
					_n( '%d alerte', '%d alertes', $n, 'mainwp-giweb' ),
					$n
				);
			default:
				return __( 'Module inactif', 'mainwp-giweb' );
		}
	}

	/**
	 * @param int $timestamp Horodatage Unix.
	 * @return string
	 */
	public static function format_relative_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return __( 'jamais', 'mainwp-giweb' );
		}

		$diff = max( 0, time() - $timestamp );
		if ( $diff < MINUTE_IN_SECONDS ) {
			return __( 'à l’instant', 'mainwp-giweb' );
		}
		if ( $diff < HOUR_IN_SECONDS ) {
			$mins = (int) floor( $diff / MINUTE_IN_SECONDS );
			return sprintf(
				_n( 'il y a %d minute', 'il y a %d minutes', $mins, 'mainwp-giweb' ),
				$mins
			);
		}
		if ( $diff < DAY_IN_SECONDS ) {
			$hours = (int) floor( $diff / HOUR_IN_SECONDS );
			return sprintf(
				_n( 'il y a %d heure', 'il y a %d heures', $hours, 'mainwp-giweb' ),
				$hours
			);
		}

		$days = (int) floor( $diff / DAY_IN_SECONDS );
		return sprintf(
			_n( 'il y a %d jour', 'il y a %d jours', $days, 'mainwp-giweb' ),
			$days
		);
	}

	/**
	 * @param array<string, mixed>|null $cd Payload.
	 * @return string
	 */
	public static function format_open_count( $cd ) {
		if ( ! is_array( $cd ) || empty( $cd['module_active'] ) ) {
			return '—';
		}
		$n = (int) ( $cd['open_count'] ?? 0 );
		return sprintf(
			_n( '%d alerte', '%d alertes', $n, 'mainwp-giweb' ),
			$n
		);
	}

	/**
	 * @param array<string, mixed>|null $cd Payload.
	 * @return string
	 */
	public static function format_types( $cd ) {
		if ( ! is_array( $cd ) || empty( $cd['module_active'] ) ) {
			return '—';
		}

		$types = $cd['types'] ?? array();
		if ( ! is_array( $types ) || empty( $types ) ) {
			return self::format_latest( $cd );
		}

		$parts = array();
		foreach ( $types as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$n     = (int) ( $row['count'] ?? 0 );
			if ( '' === $label ) {
				continue;
			}
			$parts[] = $n > 1 ? sprintf( '%s ×%d', $label, $n ) : $label;
		}

		return $parts ? implode( ', ', $parts ) : '—';
	}

	/**
	 * @param array<string, mixed>|null $cd Payload.
	 * @return string
	 */
	public static function format_flags( $cd ) {
		if ( ! is_array( $cd ) || empty( $cd['module_active'] ) ) {
			return '—';
		}

		$bits = array();
		if ( ! empty( $cd['paused'] ) ) {
			$until = (int) ( $cd['pause_until'] ?? 0 );
			if ( $until > time() && function_exists( 'wp_date' ) ) {
				$bits[] = sprintf(
					/* translators: %s: heure de fin de pause */
					__( 'Pause jusqu’à %s', 'mainwp-giweb' ),
					wp_date( get_option( 'time_format', 'H:i' ), $until )
				);
			} else {
				$bits[] = __( 'En pause', 'mainwp-giweb' );
			}
		}
		if ( ! empty( $cd['maintenance'] ) ) {
			$bits[] = __( 'Maintenance', 'mainwp-giweb' );
		}
		if ( empty( $cd['pushover_ok'] ) ) {
			$bits[] = __( 'Pushover incomplet', 'mainwp-giweb' );
		}

		return $bits ? implode( ' · ', $bits ) : '—';
	}

	/**
	 * @param array<string, mixed>|null $cd Payload.
	 * @return string
	 */
	public static function format_latest( $cd ) {
		if ( ! is_array( $cd ) ) {
			return '—';
		}
		$latest = trim( (string) ( $cd['latest_summary'] ?? '' ) );
		if ( '' === $latest ) {
			return '—';
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $latest ) > 48 ) {
			return mb_substr( $latest, 0, 45 ) . '…';
		}
		if ( strlen( $latest ) > 48 ) {
			return substr( $latest, 0, 45 ) . '…';
		}
		return $latest;
	}

	/**
	 * @param array<string, mixed>|null $cd Payload.
	 * @return string HTML.
	 */
	public static function format_site_compromise_cell( $cd ) {
		$state = self::get_visual_state( $cd );
		if ( 'missing' === $state ) {
			return '<span class="mainwp-giweb-cd-site mainwp-giweb-cd-site--inactive"><span class="mainwp-giweb-cd-site__hint">' . esc_html__( 'Module inactif', 'mainwp-giweb' ) . '</span></span>';
		}

		$html  = '<div class="mainwp-giweb-cd-site mainwp-giweb-cd-site--' . esc_attr( $state ) . '">';
		$html .= '<div class="mainwp-giweb-cd-site__head">';
		$html .= '<span class="mainwp-giweb-cd-site__badge status-' . esc_attr( $state ) . '">' . esc_html( self::format_status_label( $cd ) ) . '</span>';
		$html .= '<span class="mainwp-giweb-cd-site__date">' . esc_html( self::format_relative_time( (int) ( $cd['last_scan'] ?? 0 ) ) ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="mainwp-giweb-cd-site__metrics">';
		$html .= '<span class="mainwp-giweb-cd-site__metric" title="' . esc_attr__( 'Dernière alerte', 'mainwp-giweb' ) . '">' . esc_html( self::format_latest( $cd ) ) . '</span>';
		$html .= '</div></div>';

		return $html;
	}
}
