<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des données pour le dashboard Statistiques.
 */
class Gi_Toolkit_Matomo_Dashboard_Data {

	/**
	 * Paramètres API pour le graphique d’évolution.
	 *
	 * @param int    $site_id    ID Matomo.
	 * @param string $period_key Clé UI.
	 * @return array<string, mixed>
	 */
	public static function chart_period_params( $site_id, $period_key ) {
		$map = array(
			'today'     => array( 'period' => 'day', 'date' => 'last7' ),
			'yesterday' => array( 'period' => 'day', 'date' => 'last7' ),
			'last7'     => array( 'period' => 'day', 'date' => 'last7' ),
			'last30'    => array( 'period' => 'day', 'date' => 'last30' ),
			'month'     => array( 'period' => 'day', 'date' => 'last30' ),
		);
		$p = $map[ $period_key ] ?? $map['last7'];
		return array(
			'idSite' => $site_id,
			'period' => $p['period'],
			'date'   => $p['date'],
		);
	}

	/**
	 * @param string $period_key Clé période UI.
	 * @return array{period:string, date:string, label:string}
	 */
	public static function resolve_period( $period_key ) {
		$map = array(
			'today'     => array(
				'period' => 'day',
				'date'   => 'today',
				'label'  => __( 'Aujourd’hui', 'gi-toolkit' ),
			),
			'yesterday' => array(
				'period' => 'day',
				'date'   => 'yesterday',
				'label'  => __( 'Hier', 'gi-toolkit' ),
			),
			'last7'     => array(
				'period' => 'range',
				'date'   => 'last7',
				'label'  => __( '7 derniers jours', 'gi-toolkit' ),
			),
			'last30'    => array(
				'period' => 'range',
				'date'   => 'last30',
				'label'  => __( '30 derniers jours', 'gi-toolkit' ),
			),
			'month'     => array(
				'period' => 'month',
				'date'   => 'today',
				'label'  => __( 'Ce mois', 'gi-toolkit' ),
			),
		);

		return $map[ $period_key ] ?? $map['last7'];
	}

	/**
	 * @param array<string, mixed> $settings   Réglages.
	 * @param string               $period_key Période.
	 * @return array<string, mixed>
	 */
	public static function fetch( array $settings, $period_key = 'last7' ) {
		$site_id = absint( $settings['site_id'] ?? 0 );
		if ( $site_id < 1 ) {
			return array(
				'success' => false,
				'message' => __( 'Aucun site Matomo associé.', 'gi-toolkit' ),
			);
		}

		$api    = new Gi_Toolkit_Matomo_API( $settings );
		$period = self::resolve_period( $period_key );
		$base   = array(
			'idSite' => $site_id,
			'period' => $period['period'],
			'date'   => $period['date'],
		);

		$summary = $api->request( 'VisitsSummary.get', $base );
		if ( null === $summary ) {
			return array(
				'success' => false,
				'message' => $api->get_last_error() ?: __( 'Impossible de récupérer les statistiques.', 'gi-toolkit' ),
			);
		}

		$chart_period = self::chart_period_params( $site_id, $period_key );

		$visits_series = $api->request( 'VisitsSummary.get', $chart_period );
		$pages         = $api->request( 'Actions.getPageUrls', array_merge( $base, array( 'filter_limit' => 10 ) ) );
		$referrers     = $api->request( 'Referrers.getReferrerType', array_merge( $base, array( 'filter_limit' => 10 ) ) );
		$countries     = $api->request( 'UserCountry.getCountry', array_merge( $base, array( 'filter_limit' => 10 ) ) );
		$browsers      = $api->request( 'DevicesDetection.getBrowsers', array_merge( $base, array( 'filter_limit' => 10 ) ) );

		$kpis = self::normalize_summary( $summary, $period_key );

		return array(
			'success'    => true,
			'period'     => $period,
			'period_key' => $period_key,
			'kpis'       => $kpis,
			'chart'      => self::normalize_chart( $visits_series, $chart_period ),
			'pages'      => self::normalize_report_rows( $pages, 'label', 'nb_hits' ),
			'referrers'  => self::normalize_report_rows( $referrers, 'label', 'nb_visits' ),
			'countries'  => self::normalize_report_rows( $countries, 'label', 'nb_visits' ),
			'browsers'   => self::normalize_report_rows( $browsers, 'label', 'nb_visits' ),
		);
	}

	/**
	 * @param mixed  $summary    Réponse API.
	 * @param string $period_key Clé période.
	 * @return array<string, mixed>
	 */
	private static function normalize_summary( $summary, $period_key ) {
		if ( is_array( $summary ) && isset( $summary[0] ) && is_array( $summary[0] ) ) {
			$agg = array();
			foreach ( $summary as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( $row as $key => $val ) {
					if ( is_numeric( $val ) ) {
						$agg[ $key ] = ( $agg[ $key ] ?? 0 ) + (float) $val;
					}
				}
			}
			$summary = $agg;
		}

		if ( ! is_array( $summary ) ) {
			$summary = array();
		}

		$visits  = (int) ( $summary['nb_visits'] ?? 0 );
		$bounce  = (int) ( $summary['bounce_count'] ?? 0 );
		$length  = (int) ( $summary['sum_visit_length'] ?? 0 );

		$bounce_rate = $visits > 0 ? round( ( $bounce / $visits ) * 100, 1 ) : 0;
		$avg_time    = $visits > 0 ? (int) round( $length / $visits ) : 0;

		return array(
			'nb_visits'        => self::format_int( $summary['nb_visits'] ?? 0 ),
			'nb_uniq_visitors' => self::format_int( $summary['nb_uniq_visitors'] ?? 0 ),
			'nb_actions'       => self::format_int( $summary['nb_actions'] ?? 0 ),
			'bounce_rate'      => $bounce_rate . '%',
			'avg_time'         => self::format_duration( $avg_time ),
			'avg_time_raw'     => $avg_time,
		);
	}

	/**
	 * @param mixed                $data   Données.
	 * @param array<string, mixed> $period Période API.
	 * @return array{labels:array<int,string>, values:array<int,int>}
	 */
	private static function normalize_chart( $data, array $period ) {
		$labels = array();
		$values = array();

		if ( ! is_array( $data ) ) {
			return array(
				'labels' => $labels,
				'values' => $values,
			);
		}

		// Série journalière : clés date YYYY-MM-DD.
		if ( self::is_assoc_date_series( $data ) ) {
			ksort( $data );
			foreach ( $data as $date => $row ) {
				$labels[] = self::format_chart_label( (string) $date );
				$values[] = is_array( $row ) ? (int) ( $row['nb_visits'] ?? 0 ) : 0;
			}
			return array(
				'labels' => $labels,
				'values' => $values,
			);
		}

		// Valeur unique.
		if ( isset( $data['nb_visits'] ) ) {
			$labels[] = $period['date'] ?? '';
			$values[] = (int) $data['nb_visits'];
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * @param array<string, mixed> $data Données.
	 * @return bool
	 */
	private static function is_assoc_date_series( array $data ) {
		if ( isset( $data['nb_visits'] ) ) {
			return false;
		}
		foreach ( array_keys( $data ) as $key ) {
			if ( ! is_string( $key ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $key ) ) {
				return false;
			}
		}
		return ! empty( $data );
	}

	/**
	 * @param mixed  $rows       Lignes API.
	 * @param string $label_key  Clé libellé.
	 * @param string $value_key  Clé valeur.
	 * @return array<int, array{label:string, value:int}>
	 */
	private static function normalize_report_rows( $rows, $label_key, $value_key ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = (string) ( $row[ $label_key ] ?? '' );
			if ( '' === $label && isset( $row['url'] ) ) {
				$label = (string) $row['url'];
			}
			$out[] = array(
				'label' => $label ?: '—',
				'value' => (int) ( $row[ $value_key ] ?? 0 ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['value'] <=> $a['value'];
			}
		);
		return array_slice( $out, 0, 10 );
	}

	/**
	 * @param mixed $value Valeur.
	 * @return string
	 */
	private static function format_int( $value ) {
		return number_format_i18n( (int) $value );
	}

	/**
	 * @param int $seconds Secondes.
	 * @return string
	 */
	private static function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( $seconds < 60 ) {
			return sprintf(
				/* translators: %d: seconds */
				__( '%ds', 'gi-toolkit' ),
				$seconds
			);
		}
		$mins = (int) floor( $seconds / 60 );
		$sec  = $seconds % 60;
		if ( $mins < 60 ) {
			return sprintf(
				/* translators: 1: minutes, 2: seconds */
				__( '%1$dmin %2$ds', 'gi-toolkit' ),
				$mins,
				$sec
			);
		}
		$hours = (int) floor( $mins / 60 );
		$mins  = $mins % 60;
		return sprintf(
			/* translators: 1: hours, 2: minutes */
			__( '%1$dh %2$dmin', 'gi-toolkit' ),
			$hours,
			$mins
		);
	}

	/**
	 * @param string $date Date YYYY-MM-DD.
	 * @return string
	 */
	private static function format_chart_label( $date ) {
		$ts = strtotime( $date );
		if ( ! $ts ) {
			return $date;
		}
		return wp_date( 'j M', $ts );
	}
}
