<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des heartbeats Uptime Kuma pour la barre admin.
 */
class Gi_Toolkit_Uptime_Kuma_Status_Data {

	const TRANSIENT_TOOLBAR = 'gi_uptime_kuma_toolbar_';

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

		$payload = self::aggregate_hourly_bars( $beats );
		$payload['ready'] = true;
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
