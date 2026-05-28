<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des heartbeats Uptime Kuma pour la barre admin.
 */
class Gi_Toolkit_Uptime_Kuma_Status_Data {

	const TRANSIENT_TOOLBAR    = 'gi_uptime_kuma_toolbar_v2_';
	const TRANSIENT_DASHBOARD  = 'gi_uptime_kuma_dashboard_';

	/**
	 * Données tableau de bord (page réglages).
	 *
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $force    Ignorer le cache transient.
	 * @return array<string, mixed>
	 */
	public static function fetch_dashboard( array $settings, $force = false ) {
		$monitor_id = absint( $settings['monitor_id'] ?? 0 );
		if ( $monitor_id < 1 ) {
			return array(
				'ready'   => false,
				'message' => __( 'Associez un monitor (synchronisation automatique ou ID manuel).', 'gi-toolkit' ),
			);
		}

		$cache_key = self::TRANSIENT_DASHBOARD . $monitor_id;
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$api = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->is_configured() ) {
			return array(
				'ready'   => false,
				'message' => __( 'Configurez l’URL et les identifiants Uptime Kuma.', 'gi-toolkit' ),
			);
		}

		$beats = $api->get_monitor_beats( $monitor_id, 24 );
		if ( ! is_array( $beats ) ) {
			return array(
				'ready'   => false,
				'message' => $api->get_last_error() ?: __( 'Impossible de charger les heartbeats.', 'gi-toolkit' ),
			);
		}

		$hourly     = self::aggregate_hourly_bars( $beats );
		$check_bars = self::aggregate_check_bars( $beats, 90 );
		$latest     = self::latest_beat_summary( $beats );
		$chart      = self::build_ping_chart_series( $beats, 48 );

		$uptime_bundle = $api->get_monitor_uptime_stats( $monitor_id );
		$interval      = 60;
		$monitor       = ( is_array( $uptime_bundle ) && is_array( $uptime_bundle['monitor'] ?? null ) )
			? $uptime_bundle['monitor']
			: null;
		if ( is_array( $monitor ) && ! empty( $monitor['interval'] ) ) {
			$interval = max( 20, absint( $monitor['interval'] ) );
		}

		$uptime_30d = null;
		$uptime_1y  = null;
		$uptime_raw = ( is_array( $uptime_bundle ) && is_array( $uptime_bundle['stats'] ?? null ) )
			? $uptime_bundle['stats']
			: array();
		if ( isset( $uptime_raw['720'] ) ) {
			$uptime_30d = Gi_Toolkit_Uptime_Kuma_API::uptime_ratio_to_percent( $uptime_raw['720'] );
		} elseif ( isset( $uptime_raw[720] ) ) {
			$uptime_30d = Gi_Toolkit_Uptime_Kuma_API::uptime_ratio_to_percent( $uptime_raw[720] );
		}
		if ( isset( $uptime_raw['1y'] ) ) {
			$uptime_1y = Gi_Toolkit_Uptime_Kuma_API::uptime_ratio_to_percent( $uptime_raw['1y'] );
		}

		$payload = array(
			'ready'          => true,
			'monitor_id'     => $monitor_id,
			'interval'       => $interval,
			'uptime_30d'     => $uptime_30d,
			'uptime_1y'      => $uptime_1y,
			'status'         => $latest['status'],
			'status_label'   => $latest['status_label'],
			'status_level'   => $latest['status_level'],
			'current_ping'   => $latest['ping'],
			'last_check'     => $latest['last_check'],
			'last_check_ago' => $latest['last_check_ago'],
			'avg_ping'       => (int) ( $hourly['avg_ping'] ?? 0 ),
			'uptime_percent' => (float) ( $hourly['uptime_percent'] ?? 0 ),
			'hourly_bars'    => $hourly['bars'] ?? array(),
			'check_bars'     => $check_bars['bars'],
			'strip_from'     => $check_bars['from_label'],
			'strip_to'       => $check_bars['to_label'],
			'chart'          => $chart,
			'fetched_at'     => time(),
		);

		set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

		return $payload;
	}

	/**
	 * Barres par heartbeat (bandeau type Uptime Kuma).
	 *
	 * @param array<int, array<string, mixed>> $beats Heartbeats.
	 * @param int                               $max   Nombre max.
	 * @return array{bars: array<int, array{level:string}>, from_label: string, to_label: string}
	 */
	public static function aggregate_check_bars( array $beats, $max = 90 ) {
		$rows = array();
		foreach ( $beats as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			$ts = self::beat_timestamp( $beat );
			if ( $ts < 1 ) {
				continue;
			}
			$status = (int) ( $beat['status'] ?? -1 );
			$level  = 'red';
			if ( self::is_up_status( $status ) ) {
				$level = 'green';
			} elseif ( Gi_Toolkit_Uptime_Kuma_API::STATUS_PENDING === $status ) {
				$level = 'orange';
			} elseif ( Gi_Toolkit_Uptime_Kuma_API::STATUS_MAINTENANCE === $status ) {
				$level = 'unknown';
			}
			$rows[] = array(
				'ts'    => $ts,
				'level' => $level,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['ts'] <=> $b['ts'];
			}
		);

		if ( count( $rows ) > $max ) {
			$rows = array_slice( $rows, -$max );
		}

		$bars_only = array();
		foreach ( $rows as $row ) {
			$bars_only[] = array( 'level' => $row['level'] );
		}

		$from_label = '';
		$to_label   = __( 'Maintenant', 'gi-toolkit' );
		if ( ! empty( $rows ) ) {
			$from_label = self::human_time_ago( $rows[0]['ts'] );
		}

		return array(
			'bars'       => $bars_only,
			'from_label' => $from_label,
			'to_label'   => $to_label,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $beats Heartbeats.
	 * @return array{status:string, status_label:string, status_level:string, ping:int, last_check:string, last_check_ago:string}
	 */
	public static function latest_beat_summary( array $beats ) {
		$latest = null;
		$latest_ts = 0;
		foreach ( $beats as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			$ts = self::beat_timestamp( $beat );
			if ( $ts >= $latest_ts ) {
				$latest_ts = $ts;
				$latest    = $beat;
			}
		}

		if ( ! is_array( $latest ) ) {
			return array(
				'status'         => 'unknown',
				'status_label'   => __( 'Inconnu', 'gi-toolkit' ),
				'status_level'   => 'unknown',
				'ping'           => 0,
				'last_check'     => '',
				'last_check_ago' => '',
			);
		}

		$status = (int) ( $latest['status'] ?? -1 );
		if ( self::is_up_status( $status ) ) {
			$label = __( 'En ligne', 'gi-toolkit' );
			$level = 'up';
		} elseif ( Gi_Toolkit_Uptime_Kuma_API::STATUS_DOWN === $status ) {
			$label = __( 'Hors ligne', 'gi-toolkit' );
			$level = 'down';
		} elseif ( Gi_Toolkit_Uptime_Kuma_API::STATUS_MAINTENANCE === $status ) {
			$label = __( 'Maintenance', 'gi-toolkit' );
			$level = 'maintenance';
		} else {
			$label = __( 'En attente', 'gi-toolkit' );
			$level = 'pending';
		}

		$ping = isset( $latest['ping'] ) ? (int) round( (float) $latest['ping'] ) : 0;

		return array(
			'status'         => $level,
			'status_label'   => $label,
			'status_level'   => $level,
			'ping'           => $ping,
			'last_check'     => (string) ( $latest['time'] ?? '' ),
			'last_check_ago' => $latest_ts > 0 ? self::human_time_ago( $latest_ts ) : '',
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $beats Heartbeats.
	 * @param int                               $max_points Points max.
	 * @return array{labels: array<int, string>, data: array<int, int>}
	 */
	public static function build_ping_chart_series( array $beats, $max_points = 48 ) {
		$points = array();
		foreach ( $beats as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			if ( ! self::is_up_status( $beat['status'] ?? null ) ) {
				continue;
			}
			$ping = isset( $beat['ping'] ) ? (float) $beat['ping'] : 0;
			if ( $ping <= 0 ) {
				continue;
			}
			$ts = self::beat_timestamp( $beat );
			if ( $ts < 1 ) {
				continue;
			}
			$points[] = array(
				'ts'   => $ts,
				'ping' => (int) round( $ping ),
			);
		}

		usort(
			$points,
			static function ( $a, $b ) {
				return $a['ts'] <=> $b['ts'];
			}
		);

		if ( count( $points ) > $max_points ) {
			$points = array_slice( $points, -$max_points );
		}

		$labels = array();
		$data   = array();
		foreach ( $points as $point ) {
			$labels[] = wp_date( 'H:i', $point['ts'] );
			$data[]   = $point['ping'];
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function human_time_ago( $timestamp ) {
		$diff = max( 0, time() - absint( $timestamp ) );
		if ( $diff < MINUTE_IN_SECONDS ) {
			return __( 'À l’instant', 'gi-toolkit' );
		}
		if ( $diff < HOUR_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: minutes */
				_n( '%d min', '%d min', (int) floor( $diff / MINUTE_IN_SECONDS ), 'gi-toolkit' ),
				(int) floor( $diff / MINUTE_IN_SECONDS )
			);
		}
		if ( $diff < DAY_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: hours */
				_n( '%d h', '%d h', (int) floor( $diff / HOUR_IN_SECONDS ), 'gi-toolkit' ),
				(int) floor( $diff / HOUR_IN_SECONDS )
			);
		}
		return wp_date( 'd/m H:i', $timestamp );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return array<string, mixed>
	 */
	public static function fetch_toolbar( array $settings ) {
		$monitor_id = absint( $settings['monitor_id'] ?? 0 );
		if ( $monitor_id < 1 ) {
			return array(
				'ready'   => false,
				'message' => __( 'Monitor non configuré.', 'gi-toolkit' ),
			);
		}

		$cache_key = self::TRANSIENT_TOOLBAR . $monitor_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$api = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->is_configured() ) {
			return array(
				'ready'   => false,
				'message' => __( 'Uptime Kuma non configuré.', 'gi-toolkit' ),
			);
		}

		$beats = $api->get_monitor_beats( $monitor_id, 24 );
		if ( ! is_array( $beats ) ) {
			return array(
				'ready'   => false,
				'message' => $api->get_last_error() ?: __( 'Données indisponibles.', 'gi-toolkit' ),
			);
		}

		$hourly = self::aggregate_hourly_bars( $beats );
		$check  = self::aggregate_check_bars( $beats, 50 );
		$latest = self::latest_beat_summary( $beats );
		$chart  = self::build_ping_chart_series( $beats, 36 );

		$uptime_30d = null;
		$uptime_1y  = null;
		$uptime_raw = $api->get_monitor_uptime_stats( $monitor_id );
		if ( is_array( $uptime_raw ) && is_array( $uptime_raw['stats'] ?? null ) ) {
			$stats = $uptime_raw['stats'];
			if ( isset( $stats['720'] ) ) {
				$uptime_30d = Gi_Toolkit_Uptime_Kuma_API::uptime_ratio_to_percent( $stats['720'] );
			} elseif ( isset( $stats[720] ) ) {
				$uptime_30d = Gi_Toolkit_Uptime_Kuma_API::uptime_ratio_to_percent( $stats[720] );
			}
			if ( isset( $stats['1y'] ) ) {
				$uptime_1y = Gi_Toolkit_Uptime_Kuma_API::uptime_ratio_to_percent( $stats['1y'] );
			}
		}

		$payload = array(
			'ready'          => true,
			'bars'           => $check['bars'] ?? array(),
			'strip_from'     => $check['from_label'] ?? '',
			'strip_to'       => $check['to_label'] ?? '',
			'avg_ping'       => (int) ( $hourly['avg_ping'] ?? 0 ),
			'uptime_percent' => (float) ( $hourly['uptime_percent'] ?? 0 ),
			'uptime_30d'     => $uptime_30d,
			'uptime_1y'      => $uptime_1y,
			'current_ping'   => (int) ( $latest['ping'] ?? 0 ),
			'status_label'   => (string) ( $latest['status_label'] ?? '' ),
			'status_level'   => (string) ( $latest['status_level'] ?? 'unknown' ),
			'last_check_ago' => (string) ( $latest['last_check_ago'] ?? '' ),
			'chart_labels'   => $chart['labels'] ?? array(),
			'chart_data'     => $chart['data'] ?? array(),
			'fetched_at'     => time(),
		);

		set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

		return $payload;
	}

	/**
	 * @param array<int, array<string, mixed>> $beats Heartbeats bruts.
	 * @return array{bars: array<int, array{level:string,uptime:float}>, avg_ping:int, uptime_percent:float}
	 */
	public static function aggregate_hourly_bars( array $beats ) {
		$now   = time();
		$bars  = array();
		$total = 0;
		$up    = 0;

		for ( $i = 23; $i >= 0; $i-- ) {
			$hour_start = $now - ( ( $i + 1 ) * HOUR_IN_SECONDS );
			$hour_end   = $now - ( $i * HOUR_IN_SECONDS );
			$stats      = self::stats_for_window( $beats, $hour_start, $hour_end );
			$bars[]     = array(
				'level'  => $stats['level'],
				'uptime' => $stats['uptime'],
			);
			$total += $stats['total'];
			$up    += $stats['up'];
		}

		$avg_ping = self::average_ping( $beats, $now - DAY_IN_SECONDS, $now );

		return array(
			'bars'           => $bars,
			'avg_ping'       => $avg_ping,
			'uptime_percent' => $total > 0 ? round( ( $up / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $beats Heartbeats.
	 * @param int                               $start  Timestamp début.
	 * @param int                               $end    Timestamp fin.
	 * @return array{level:string, uptime:float, total:int, up:int}
	 */
	private static function stats_for_window( array $beats, $start, $end ) {
		$total = 0;
		$up    = 0;

		foreach ( $beats as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			$ts = self::beat_timestamp( $beat );
			if ( $ts < $start || $ts >= $end ) {
				continue;
			}
			$total++;
			if ( self::is_up_status( $beat['status'] ?? null ) ) {
				$up++;
			}
		}

		if ( 0 === $total ) {
			return array(
				'level'  => 'unknown',
				'uptime' => 0,
				'total'  => 0,
				'up'     => 0,
			);
		}

		$ratio = $up / $total;
		$level = 'red';
		if ( $ratio >= 0.99 ) {
			$level = 'green';
		} elseif ( $ratio >= 0.85 ) {
			$level = 'orange';
		}

		return array(
			'level'  => $level,
			'uptime' => round( $ratio * 100, 1 ),
			'total'  => $total,
			'up'     => $up,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $beats Heartbeats.
	 * @param int                               $start  Début.
	 * @param int                               $end    Fin.
	 * @return int
	 */
	private static function average_ping( array $beats, $start, $end ) {
		$sum = 0;
		$cnt = 0;
		foreach ( $beats as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			$ts = self::beat_timestamp( $beat );
			if ( $ts < $start || $ts >= $end ) {
				continue;
			}
			if ( ! self::is_up_status( $beat['status'] ?? null ) ) {
				continue;
			}
			$ping = isset( $beat['ping'] ) ? (float) $beat['ping'] : 0;
			if ( $ping > 0 ) {
				$sum += $ping;
				$cnt++;
			}
		}
		return $cnt > 0 ? (int) round( $sum / $cnt ) : 0;
	}

	/**
	 * @param array<string, mixed> $beat Heartbeat.
	 * @return int
	 */
	private static function beat_timestamp( array $beat ) {
		if ( empty( $beat['time'] ) ) {
			return 0;
		}
		$ts = strtotime( (string) $beat['time'] );
		return $ts ? (int) $ts : 0;
	}

	/**
	 * @param mixed $status Statut Kuma.
	 * @return bool
	 */
	private static function is_up_status( $status ) {
		return (int) $status === Gi_Toolkit_Uptime_Kuma_API::STATUS_UP;
	}
}
