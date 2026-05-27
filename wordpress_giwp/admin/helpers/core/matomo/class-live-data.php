<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Données temps réel Matomo (API Live).
 */
class Gi_Toolkit_Matomo_Live_Data {

	const REFRESH_SECONDS = 10;

	/**
	 * @param array<string, mixed> $settings Réglages module.
	 * @return array<string, mixed>
	 */
	public static function fetch( array $settings ) {
		$site_id = absint( $settings['site_id'] ?? 0 );
		if ( $site_id < 1 ) {
			return array(
				'success' => false,
				'message' => __( 'Aucun site Matomo associé.', 'gi-toolkit' ),
			);
		}

		$api  = new Gi_Toolkit_Matomo_API( $settings );
		$base = array( 'idSite' => $site_id );

		$counters_3  = $api->request( 'Live.getCounters', array_merge( $base, array( 'lastMinutes' => 3 ) ) );
		$counters_30 = $api->request( 'Live.getCounters', array_merge( $base, array( 'lastMinutes' => 30 ) ) );

		$visits = $api->request(
			'Live.getLastVisitsDetails',
			array_merge(
				$base,
				array(
					'period'              => 'day',
					'date'                => 'today',
					'filter_limit'        => 15,
					'filter_sort_column'  => 'lastActionTimestamp',
					'filter_sort_order'   => 'desc',
					'doNotFetchActions'   => 1,
				)
			)
		);

		if ( null === $counters_30 && null === $visits ) {
			return array(
				'success' => false,
				'message' => $api->get_last_error() ?: __( 'Impossible de récupérer les données en direct.', 'gi-toolkit' ),
			);
		}

		return array(
			'success'         => true,
			'mode'            => 'live',
			'period'          => array(
				'label' => __( 'En direct', 'gi-toolkit' ),
			),
			'compare_label'   => '',
			'site_id'         => $site_id,
			'site_url'        => Gi_Toolkit_Matomo_Site::get_wordpress_site_url(),
			'refresh_seconds' => self::REFRESH_SECONDS,
			'live'            => array(
				'counters'   => array(
					'3'  => self::normalize_counters( $counters_3 ),
					'30' => self::normalize_counters( $counters_30 ),
				),
				'visits'     => self::normalize_visits( $visits ),
				'updated_at' => time(),
			),
		);
	}

	/**
	 * @param mixed $raw Réponse API.
	 * @return array{visits:int, visitors:int, actions:int}
	 */
	private static function normalize_counters( $raw ) {
		$row = is_array( $raw ) && isset( $raw[0] ) && is_array( $raw[0] ) ? $raw[0] : ( is_array( $raw ) ? $raw : array() );

		return array(
			'visits'   => (int) ( $row['nb_visits'] ?? 0 ),
			'visitors' => (int) ( $row['nb_visitors'] ?? 0 ),
			'actions'  => (int) ( $row['nb_actions'] ?? 0 ),
		);
	}

	/**
	 * @param mixed $visits Liste de visites API.
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_visits( $visits ) {
		$out = array();
		if ( ! is_array( $visits ) ) {
			return $out;
		}

		foreach ( $visits as $visit ) {
			if ( ! is_array( $visit ) ) {
				continue;
			}

			$last_action = (string) ( $visit['lastActionDateTime'] ?? $visit['lastActionTimestamp'] ?? '' );
			$location    = self::format_location( $visit );
			$page        = self::format_last_page( $visit );
			$referrer    = (string) ( $visit['referrerName'] ?? '' );
			if ( '' === $referrer ) {
				$referrer = (string) ( $visit['referrerTypeName'] ?? __( 'Accès direct', 'gi-toolkit' ) );
			}

			$device = trim(
				implode(
					' · ',
					array_filter(
						array(
							(string) ( $visit['browserName'] ?? '' ),
							(string) ( $visit['deviceType'] ?? '' ),
						)
					)
				)
			);

			$out[] = array(
				'id'       => substr( md5( wp_json_encode( $visit ) ), 0, 12 ),
				'location' => $location,
				'device'   => $device ?: '—',
				'page'     => $page,
				'referrer' => $referrer,
				'time'     => self::format_time_ago( $last_action ),
				'is_new'   => self::is_recent( $last_action, 60 ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $visit Visite.
	 * @return string
	 */
	private static function format_location( array $visit ) {
		$parts = array_filter(
			array(
				(string) ( $visit['country'] ?? '' ),
				(string) ( $visit['city'] ?? '' ),
			)
		);
		return $parts ? implode( ', ', $parts ) : __( 'Inconnu', 'gi-toolkit' );
	}

	/**
	 * @param array<string, mixed> $visit Visite.
	 * @return string
	 */
	private static function format_last_page( array $visit ) {
		if ( ! empty( $visit['actionDetails'] ) && is_array( $visit['actionDetails'] ) ) {
			$last = end( $visit['actionDetails'] );
			if ( is_array( $last ) ) {
				if ( ! empty( $last['pageTitle'] ) ) {
					return (string) $last['pageTitle'];
				}
				if ( ! empty( $last['url'] ) ) {
					return self::short_url( (string) $last['url'] );
				}
			}
		}
		if ( ! empty( $visit['landingPagePath'] ) ) {
			return (string) $visit['landingPagePath'];
		}
		return '—';
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function short_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return $path ? $path : $url;
	}

	/**
	 * @param string $datetime Date/heure Matomo.
	 * @return string
	 */
	private static function format_time_ago( $datetime ) {
		$ts = strtotime( $datetime );
		if ( ! $ts ) {
			return '—';
		}
		$diff = time() - $ts;
		if ( $diff < 5 ) {
			return __( 'à l’instant', 'gi-toolkit' );
		}
		if ( $diff < 60 ) {
			/* translators: %d: seconds */
			return sprintf( __( 'il y a %ds', 'gi-toolkit' ), $diff );
		}
		return sprintf(
			/* translators: %s: human time diff */
			__( 'il y a %s', 'gi-toolkit' ),
			human_time_diff( $ts, time() )
		);
	}

	/**
	 * @param string $datetime Date/heure.
	 * @param int    $seconds  Seuil secondes.
	 * @return bool
	 */
	private static function is_recent( $datetime, $seconds ) {
		$ts = strtotime( $datetime );
		return $ts && ( time() - $ts ) <= $seconds;
	}
}
