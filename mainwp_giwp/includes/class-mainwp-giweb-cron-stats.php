<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des statuts cron fiable GI-Toolkit remontés via la synchro MainWP.
 */
class MainWP_GIWeb_Cron_Stats {

	const AGGREGATE_OPTION = 'mainwp_giweb_cron_aggregate';

	const WARN_SECONDS = 120;

	const DOWN_SECONDS = 600;

	/**
	 * @param array<string, mixed> $data Données statut site.
	 * @return array<string, mixed>|null
	 */
	public static function extract_cron( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( ! empty( $data['cron'] ) && is_array( $data['cron'] ) ) {
			return $data['cron'];
		}
		if ( array_key_exists( 'overdue', $data ) && array_key_exists( 'enabled', $data ) ) {
			return $data;
		}
		return null;
	}

	/**
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function extract_cron_from_sync( $information ) {
		if ( ! is_array( $information ) ) {
			return null;
		}
		if ( ! empty( $information['gi_toolkit_cron'] ) && is_array( $information['gi_toolkit_cron'] ) ) {
			return $information['gi_toolkit_cron'];
		}
		if ( ! empty( $information['gi_toolkit_sync']['cron'] ) && is_array( $information['gi_toolkit_sync']['cron'] ) ) {
			return $information['gi_toolkit_sync']['cron'];
		}
		return null;
	}

	/**
	 * @param array<string, mixed>|null $api_data    Données API GI-Toolkit.
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_cron_payload( $api_data, $information = null ) {
		$from_api  = is_array( $api_data ) ? self::extract_cron( $api_data ) : null;
		$from_sync = self::extract_cron_from_sync( $information );
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
		$cron    = self::resolve_cron_payload( $data, $information );

		$aggregate = self::get_aggregate();
		if ( ! isset( $aggregate['sites'] ) || ! is_array( $aggregate['sites'] ) ) {
			$aggregate['sites'] = array();
		}

		$aggregate['sites'][ $site_id ] = array(
			'label'     => $label,
			'url'       => $url,
			'api_ok'    => $ok,
			'synced_at' => time(),
			'cron'      => $cron,
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
			'sites_tracked'  => 0,
			'sites_ok'       => 0,
			'sites_warn'     => 0,
			'sites_down'     => 0,
			'sites_missing'  => 0,
			'sites_disabled' => 0,
		);

		if ( ! is_array( $sites ) ) {
			return $network;
		}

		foreach ( $sites as $row ) {
			++$network['sites_tracked'];
			$cron  = is_array( $row['cron'] ?? null ) ? $row['cron'] : null;
			$state = self::get_visual_state( $cron );
			if ( 'ok' === $state ) {
				++$network['sites_ok'];
			} elseif ( 'warn' === $state ) {
				++$network['sites_warn'];
			} elseif ( 'down' === $state ) {
				++$network['sites_down'];
			} else {
				++$network['sites_missing'];
			}
			if ( is_array( $cron ) && empty( $cron['enabled'] ) ) {
				++$network['sites_disabled'];
			}
		}

		return $network;
	}

	/**
	 * @param int $site_id ID MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function get_site_cron( $site_id ) {
		$site_id = absint( $site_id );
		if ( ! $site_id ) {
			return null;
		}

		$agg   = self::get_aggregate();
		$sites = $agg['sites'] ?? array();
		if ( is_array( $sites ) && isset( $sites[ $site_id ]['cron'] ) ) {
			$cron = $sites[ $site_id ]['cron'];
			return is_array( $cron ) ? $cron : null;
		}

		$cache = MainWP_GIWeb_Status_Cache::get_all();
		if ( ! isset( $cache[ $site_id ] ) || ! is_array( $cache[ $site_id ] ) ) {
			return null;
		}

		return self::extract_cron( $cache[ $site_id ]['data'] ?? array() );
	}

	/**
	 * @param array<string, mixed>|null $cron Payload cron.
	 * @return string ok|warn|down|missing
	 */
	public static function get_visual_state( $cron ) {
		if ( ! is_array( $cron ) ) {
			return 'missing';
		}
		if ( empty( $cron['enabled'] ) ) {
			return 'warn';
		}

		$overdue = (int) ( $cron['overdue'] ?? 0 );
		if ( $overdue >= self::DOWN_SECONDS ) {
			return 'down';
		}
		if ( $overdue >= self::WARN_SECONDS ) {
			return 'warn';
		}

		$last = (int) ( $cron['last_run'] ?? 0 );
		if ( $last <= 0 && ! empty( $cron['has_due'] ) ) {
			return 'warn';
		}

		return 'ok';
	}

	/**
	 * @param array<string, mixed>|null $cron Payload cron.
	 * @return string
	 */
	public static function format_status_label( $cron ) {
		$state = self::get_visual_state( $cron );
		switch ( $state ) {
			case 'ok':
				return __( 'À jour', 'mainwp-giweb' );
			case 'warn':
				if ( is_array( $cron ) && empty( $cron['enabled'] ) ) {
					return __( 'Runner désactivé', 'mainwp-giweb' );
				}
				return __( 'Retard', 'mainwp-giweb' );
			case 'down':
				return __( 'En panne', 'mainwp-giweb' );
			default:
				return __( 'Non remonté', 'mainwp-giweb' );
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
	 * @param array<string, mixed>|null $cron Payload cron.
	 * @return string
	 */
	public static function format_overdue( $cron ) {
		$overdue = is_array( $cron ) ? (int) ( $cron['overdue'] ?? 0 ) : 0;
		if ( $overdue <= 0 ) {
			return __( 'aucun', 'mainwp-giweb' );
		}
		return human_time_diff( time() - $overdue, time() );
	}

	/**
	 * @param array<string, mixed>|null $cron Payload cron.
	 * @return string
	 */
	public static function format_source( $cron ) {
		$source = is_array( $cron ) ? sanitize_key( (string) ( $cron['source'] ?? '' ) ) : '';
		$labels = array(
			'shutdown' => __( 'visite', 'mainwp-giweb' ),
			'endpoint' => __( 'crontab', 'mainwp-giweb' ),
			'mainwp'   => __( 'MainWP', 'mainwp-giweb' ),
			'manual'   => __( 'manuel', 'mainwp-giweb' ),
			'heartbeat' => __( 'heartbeat', 'mainwp-giweb' ),
		);
		if ( isset( $labels[ $source ] ) ) {
			return $labels[ $source ];
		}
		return '' !== $source ? $source : '—';
	}

	/**
	 * @param array<string, mixed>|null $cron Payload cron.
	 * @return string HTML.
	 */
	public static function format_site_cron_cell( $cron ) {
		$state = self::get_visual_state( $cron );
		if ( 'missing' === $state ) {
			return '<span class="mainwp-giweb-cron-site mainwp-giweb-cron-site--inactive"><span class="mainwp-giweb-cron-site__hint">' . esc_html__( 'Cron non remonté', 'mainwp-giweb' ) . '</span></span>';
		}

		$html  = '<div class="mainwp-giweb-cron-site mainwp-giweb-cron-site--' . esc_attr( $state ) . '">';
		$html .= '<div class="mainwp-giweb-cron-site__head">';
		$html .= '<span class="mainwp-giweb-cron-site__badge status-' . esc_attr( $state ) . '">' . esc_html( self::format_status_label( $cron ) ) . '</span>';
		$html .= '<span class="mainwp-giweb-cron-site__date">' . esc_html( self::format_relative_time( (int) ( $cron['last_run'] ?? 0 ) ) ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="mainwp-giweb-cron-site__metrics">';
		$html .= '<span class="mainwp-giweb-cron-site__metric" title="' . esc_attr__( 'Retard', 'mainwp-giweb' ) . '">' . esc_html( self::format_overdue( $cron ) ) . '</span>';
		$html .= '<span class="mainwp-giweb-cron-site__metric" title="' . esc_attr__( 'Source du dernier passage', 'mainwp-giweb' ) . '">' . esc_html( self::format_source( $cron ) ) . '</span>';
		$html .= '</div></div>';

		return $html;
	}
}
