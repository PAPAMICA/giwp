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

		$visits_raw = $api->request(
			'Live.getLastVisitsDetails',
			array_merge(
				$base,
				array(
					'period'             => 'day',
					'date'               => 'today',
					'filter_limit'       => 12,
					'filter_sort_column' => 'lastActionTimestamp',
					'filter_sort_order'  => 'desc',
					'doNotFetchActions'  => 0,
				)
			)
		);

		$counters_3  = $api->request( 'Live.getCounters', array_merge( $base, array( 'lastMinutes' => '3' ) ) );
		$counters_30 = $api->request( 'Live.getCounters', array_merge( $base, array( 'lastMinutes' => '30' ) ) );

		if ( null === $visits_raw && null === $counters_30 ) {
			return array(
				'success' => false,
				'message' => $api->get_last_error() ?: __( 'Impossible de récupérer les données en direct.', 'gi-toolkit' ),
			);
		}

		$c3  = self::normalize_counters( $counters_3 );
		$c30 = self::normalize_counters( $counters_30 );

		if ( is_array( $visits_raw ) ) {
			if ( 0 === $c3['visitors'] && 0 === $c3['visits'] ) {
				$c3 = self::derive_counters_from_visits( $visits_raw, 3 );
			}
			if ( 0 === $c30['visitors'] && 0 === $c30['visits'] ) {
				$c30 = self::derive_counters_from_visits( $visits_raw, 30 );
			}
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
					'3'  => $c3,
					'30' => $c30,
				),
				'visits'     => self::normalize_visits( $visits_raw, $settings ),
				'updated_at' => time(),
			),
		);
	}

	/**
	 * Matomo renvoie visits/visitors/actions (sans préfixe nb_).
	 *
	 * @param mixed $raw Réponse API.
	 * @return array{visits:int, visitors:int, actions:int}
	 */
	private static function normalize_counters( $raw ) {
		$row = array();
		if ( is_array( $raw ) ) {
			if ( isset( $raw[0] ) && is_array( $raw[0] ) ) {
				$row = $raw[0];
			} elseif ( isset( $raw['visits'] ) || isset( $raw['nb_visits'] ) || isset( $raw['visitors'] ) ) {
				$row = $raw;
			}
		}

		return array(
			'visits'   => (int) ( $row['nb_visits'] ?? $row['visits'] ?? 0 ),
			'visitors' => (int) ( $row['nb_visitors'] ?? $row['visitors'] ?? 0 ),
			'actions'  => (int) ( $row['nb_actions'] ?? $row['actions'] ?? 0 ),
		);
	}

	/**
	 * Repli si Live.getCounters renvoie 0 alors que des visites existent.
	 *
	 * @param array<int, array<string, mixed>> $visits   Visites brutes API.
	 * @param int                              $minutes Fenêtre en minutes.
	 * @return array{visits:int, visitors:int, actions:int}
	 */
	private static function derive_counters_from_visits( array $visits, $minutes ) {
		$cutoff        = time() - ( $minutes * 60 );
		$visits_count  = 0;
		$actions       = 0;
		$visitor_ids   = array();

		foreach ( $visits as $visit ) {
			if ( ! is_array( $visit ) ) {
				continue;
			}
			$ts = self::visit_timestamp( $visit );
			if ( ! $ts || $ts < $cutoff ) {
				continue;
			}
			++$visits_count;
			$actions += (int) ( $visit['actions'] ?? count( $visit['actionDetails'] ?? array() ) );
			$vid      = self::visitor_id( $visit );
			if ( '' !== $vid ) {
				$visitor_ids[ $vid ] = true;
			}
		}

		return array(
			'visits'   => $visits_count,
			'visitors' => count( $visitor_ids ),
			'actions'  => $actions,
		);
	}

	/**
	 * @param mixed                $visits   Liste API.
	 * @param array<string, mixed> $settings Réglages.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_visits( $visits, array $settings ) {
		$out = array();
		if ( ! is_array( $visits ) ) {
			return $out;
		}

		foreach ( $visits as $visit ) {
			if ( ! is_array( $visit ) ) {
				continue;
			}

			$last_action = (string) ( $visit['lastActionDateTime'] ?? '' );
			$referrer    = (string) ( $visit['referrerName'] ?? '' );
			if ( '' === $referrer ) {
				$referrer = (string) ( $visit['referrerTypeName'] ?? __( 'Accès direct', 'gi-toolkit' ) );
			}

			$visitor_id = self::visitor_id( $visit );
			$browser    = (string) ( $visit['browserName'] ?? __( 'Inconnu', 'gi-toolkit' ) );

			$out[] = array(
				'id'           => $visitor_id ? substr( $visitor_id, 0, 8 ) : substr( md5( wp_json_encode( $visit ) ), 0, 8 ),
				'visitor_id'   => $visitor_id,
				'ip'           => (string) ( $visit['visitIp'] ?? $visit['locationIp'] ?? '' ),
				'location'     => self::format_location( $visit ),
				'browser'      => $browser,
				'browser_icon' => self::matomo_asset_url( $visit['browserIcon'] ?? '', $settings ),
				'device'       => (string) ( $visit['deviceType'] ?? '' ),
				'device_icon'  => self::matomo_asset_url( $visit['deviceTypeIcon'] ?? '', $settings ),
				'referrer'     => $referrer,
				'time'         => self::format_time_ago( $last_action ),
				'is_new'       => self::is_recent( $last_action, 60 ),
				'pages'        => self::normalize_page_history( $visit ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $visit Visite.
	 * @return int
	 */
	private static function visit_timestamp( array $visit ) {
		$dt = (string) ( $visit['lastActionDateTime'] ?? '' );
		$ts = strtotime( $dt );
		return $ts ? $ts : 0;
	}

	/**
	 * @param array<string, mixed> $visit Visite.
	 * @return string
	 */
	private static function visitor_id( array $visit ) {
		$id = (string) ( $visit['idVisitor'] ?? $visit['visitorId'] ?? '' );
		return $id;
	}

	/**
	 * @param string               $path     Chemin relatif Matomo.
	 * @param array<string, mixed> $settings Réglages.
	 * @return string
	 */
	private static function matomo_asset_url( $path, array $settings ) {
		$path = trim( (string) $path );
		if ( '' === $path ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			return esc_url_raw( $path );
		}
		$base = Gi_Toolkit_Matomo_API::normalize_matomo_url( $settings['matomo_url'] ?? '' );
		return $base ? esc_url_raw( $base . '/' . ltrim( $path, '/' ) ) : '';
	}

	/**
	 * @param array<string, mixed> $visit Visite.
	 * @return array<int, array{title:string, url:string, time:string}>
	 */
	private static function normalize_page_history( array $visit ) {
		$pages = array();
		$raw   = $visit['actionDetails'] ?? array();
		if ( ! is_array( $raw ) ) {
			return $pages;
		}

		foreach ( $raw as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$type = (string) ( $action['type'] ?? '' );
			if ( ! in_array( $type, array( 'action', 'outlink', 'download' ), true ) ) {
				continue;
			}

			$title = (string) ( $action['pageTitle'] ?? '' );
			$url   = (string) ( $action['url'] ?? '' );
			if ( '' === $title && '' !== $url ) {
				$title = self::short_url( $url );
			}
			if ( '' === $title ) {
				$title = '—';
			}

			$time_str = '';
			if ( ! empty( $action['timestamp'] ) ) {
				$ats = (int) $action['timestamp'];
				$diff = time() - $ats;
				if ( $diff < 60 ) {
					$time_str = sprintf(
						/* translators: %d: seconds */
						__( '%ds', 'gi-toolkit' ),
						max( 0, $diff )
					);
				} else {
					$time_str = human_time_diff( $ats, time() );
				}
			} elseif ( ! empty( $action['pageviewPosition'] ) ) {
				$time_str = '#' . (int) $action['pageviewPosition'];
			}

			$pages[] = array(
				'title' => $title,
				'url'   => $url,
				'time'  => $time_str,
			);
		}

		return array_slice( $pages, -8 );
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
