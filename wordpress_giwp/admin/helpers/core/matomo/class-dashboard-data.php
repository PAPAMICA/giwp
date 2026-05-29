<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des données pour le dashboard Statistiques.
 */
class Gi_Toolkit_Matomo_Dashboard_Data {

	/**
	 * Clés de période disponibles dans l’interface.
	 *
	 * @return array<int, string>
	 */
	public static function period_keys() {
		return array(
			'live',
			'today',
			'yesterday',
			'last7',
			'last30',
			'month',
			'last3months',
			'lastyear',
		);
	}

	/**
	 * @param string $period_key Clé période UI.
	 * @return array{period:string, date:string, label:string, previous_date?:string, compare_label?:string}
	 */
	public static function resolve_period( $period_key ) {
		$map = array(
			'live'        => array(
				'period'        => 'day',
				'date'          => 'today',
				'label'         => __( 'En direct', 'gi-toolkit' ),
				'compare_label' => '',
			),
			'today'       => array(
				'period'         => 'day',
				'date'           => 'today',
				'label'          => __( 'Aujourd’hui', 'gi-toolkit' ),
				'previous_date'  => 'yesterday',
				'compare_label'  => __( 'vs hier', 'gi-toolkit' ),
			),
			'yesterday'   => array(
				'period'         => 'day',
				'date'           => 'yesterday',
				'label'          => __( 'Hier', 'gi-toolkit' ),
				'previous_date'  => 'day,yesterday2',
				'compare_label'  => __( 'vs avant-hier', 'gi-toolkit' ),
			),
			'last7'       => array(
				'period'         => 'range',
				'date'           => 'last7',
				'label'          => __( '7 derniers jours', 'gi-toolkit' ),
				'previous_date'  => 'previous7',
				'compare_label'  => __( 'vs 7 jours précédents', 'gi-toolkit' ),
			),
			'last30'      => array(
				'period'         => 'range',
				'date'           => 'last30',
				'label'          => __( '30 derniers jours', 'gi-toolkit' ),
				'previous_date'  => 'previous30',
				'compare_label'  => __( 'vs 30 jours précédents', 'gi-toolkit' ),
			),
			'month'       => array(
				'period'         => 'month',
				'date'           => 'today',
				'label'          => __( 'Ce mois', 'gi-toolkit' ),
				'previous_date'  => 'previous1',
				'compare_label'  => __( 'vs mois précédent', 'gi-toolkit' ),
			),
			'last3months' => array(
				'period'         => 'range',
				'date'           => 'last90',
				'label'          => __( '3 derniers mois', 'gi-toolkit' ),
				'previous_date'  => 'previous90',
				'compare_label'  => __( 'vs 90 jours précédents', 'gi-toolkit' ),
			),
			'lastyear'    => array(
				'period'         => 'range',
				'date'           => 'last365',
				'label'          => __( 'Dernière année', 'gi-toolkit' ),
				'previous_date'  => 'previous365',
				'compare_label'  => __( 'vs année précédente', 'gi-toolkit' ),
			),
		);

		return $map[ $period_key ] ?? $map['last7'];
	}

	/**
	 * Paramètres API pour la série temporelle (graphique).
	 *
	 * @param int    $site_id    ID Matomo.
	 * @param string $period_key Clé UI.
	 * @return array<string, mixed>
	 */
	public static function chart_period_params( $site_id, $period_key ) {
		$map = array(
			'today'       => array( 'period' => 'hour', 'date' => 'today' ),
			'yesterday'   => array( 'period' => 'hour', 'date' => 'yesterday' ),
			'last7'       => array( 'period' => 'day', 'date' => 'last7' ),
			'last30'      => array( 'period' => 'day', 'date' => 'last30' ),
			'month'       => array( 'period' => 'day', 'date' => wp_date( 'Y-m' ) ),
			'last3months' => array( 'period' => 'day', 'date' => 'last90' ),
			'lastyear'    => array( 'period' => 'month', 'date' => 'last12' ),
		);
		$p = $map[ $period_key ] ?? $map['last7'];

		return array(
			'idSite' => $site_id,
			'period' => $p['period'],
			'date'   => $p['date'],
		);
	}

	/**
	 * Granularité du graphique d’évolution pour une période UI.
	 *
	 * @param string $period_key Clé UI.
	 * @return string hour|day|month
	 */
	public static function chart_granularity( $period_key ) {
		$params = self::chart_period_params( 0, $period_key );
		$period = (string) ( $params['period'] ?? 'day' );
		if ( in_array( $period, array( 'hour', 'day', 'month' ), true ) ) {
			return $period;
		}
		return 'day';
	}

	/**
	 * Données sparkline pour la barre d’administration (cache 15 min, préchauffé par cron).
	 *
	 * @param array<string, mixed> $settings      Réglages.
	 * @param bool                 $allow_network Autoriser un appel API (sinon cache / stale uniquement).
	 * @return array{success:bool, visits?:int, labels?:array<int,string>, values?:array<int,int>, message?:string, stale?:bool}
	 */
	public static function fetch_toolbar_sparkline( array $settings, $allow_network = true ) {
		static $memo = array();

		$site_id = absint( $settings['site_id'] ?? 0 );
		if ( $site_id < 1 ) {
			return array( 'success' => false );
		}

		$memo_key = $site_id . '|' . ( $allow_network ? '1' : '0' );
		if ( isset( $memo[ $memo_key ] ) ) {
			return $memo[ $memo_key ];
		}

		$cache_key = 'gi_matomo_toolbar_v3_' . $site_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached['success'] ) ) {
			$memo[ $memo_key ] = $cached;
			return $cached;
		}

		$stale_key = 'gi_matomo_toolbar_stale_' . $site_id;
		$stale     = get_option( $stale_key, array() );
		if ( ! $allow_network ) {
			if ( is_array( $stale ) && ! empty( $stale['success'] ) ) {
				$stale['stale']      = true;
				$memo[ $memo_key ] = $stale;
				return $stale;
			}
			$memo[ $memo_key ] = array( 'success' => false );
			return $memo[ $memo_key ];
		}

		$api    = new Gi_Toolkit_Matomo_API( $settings );
		$series = $api->request(
			'VisitsSummary.get',
			array(
				'idSite' => $site_id,
				'period' => 'day',
				'date'   => 'last14',
			)
		);

		if ( null === $series ) {
			if ( is_array( $stale ) && ! empty( $stale['success'] ) ) {
				$stale['stale']      = true;
				$memo[ $memo_key ] = $stale;
				return $stale;
			}
			return array(
				'success' => false,
				'message' => $api->get_last_error(),
			);
		}

		$chart    = self::normalize_timeline_chart( $series );
		$values   = $chart['visits'] ?? array();
		$unique   = $chart['unique'] ?? array();
		$actions  = $chart['actions'] ?? array();
		$labels   = $chart['labels'] ?? array();
		$visits7  = array_slice( $values, -7 );
		$labels7  = array_slice( $labels, -7 );
		$unique7  = array_slice( $unique, -7 );
		$actions7 = array_slice( $actions, -7 );
		$total7   = array_sum( $visits7 );

		$result = array(
			'success'       => true,
			'visits'        => $total7,
			'unique_7d'     => array_sum( $unique7 ),
			'actions_7d'    => array_sum( $actions7 ),
			'today_visits'  => ! empty( $visits7 ) ? (int) $visits7[ count( $visits7 ) - 1 ] : 0,
			'labels'        => $labels7,
			'values'        => $visits7,
			'chart_unique'  => $unique7,
			'chart_actions' => $actions7,
			'fetched_at'    => time(),
		);

		set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
		update_option( $stale_key, $result, false );

		$memo[ $memo_key ] = $result;
		return $result;
	}

	/**
	 * @param array<string, mixed> $settings   Réglages.
	 * @param string               $period_key Période.
	 * @return array<string, mixed>
	 */
	public static function fetch( array $settings, $period_key = 'last7' ) {
		if ( 'live' === $period_key ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-live-data.php';
			return Gi_Toolkit_Matomo_Live_Data::fetch( $settings );
		}

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

		$previous_summary = null;
		if ( ! empty( $period['previous_date'] ) ) {
			$previous_summary = $api->request(
				'VisitsSummary.get',
				array(
					'idSite' => $site_id,
					'period' => $period['period'],
					'date'   => $period['previous_date'],
				)
			);
		}

		$chart_period = self::chart_period_params( $site_id, $period_key );
		$visits_series  = $api->request( 'VisitsSummary.get', $chart_period );
		$pages          = $api->request( 'Actions.getPageUrls', array_merge( $base, array( 'filter_limit' => 8, 'expanded' => 0 ) ) );
		$referrers      = $api->request( 'Referrers.getReferrerType', array_merge( $base, array( 'filter_limit' => 8 ) ) );
		$countries      = $api->request( 'UserCountry.getCountry', array_merge( $base, array( 'filter_limit' => 8 ) ) );
		$countries_map  = $api->request( 'UserCountry.getCountry', array_merge( $base, array( 'filter_limit' => 50 ) ) );
		$browsers       = $api->request( 'DevicesDetection.getBrowsers', array_merge( $base, array( 'filter_limit' => 6 ) ) );
		$devices        = $api->request( 'DevicesDetection.getType', array_merge( $base, array( 'filter_limit' => 6 ) ) );
		$search_engines = $api->request( 'Referrers.getSearchEngines', array_merge( $base, array( 'filter_limit' => 6 ) ) );

		$kpis = self::normalize_summary( $summary, $previous_summary );

		return array(
			'success'      => true,
			'period'       => $period,
			'period_key'   => $period_key,
			'site_id'      => $site_id,
			'site_url'     => Gi_Toolkit_Matomo_Site::get_wordpress_site_url(),
			'kpis'         => $kpis,
			'charts'       => array(
				'timeline'  => array_merge(
					self::normalize_timeline_chart( $visits_series ),
					array(
						'granularity' => self::chart_granularity( $period_key ),
					)
				),
				'referrers' => self::normalize_pie_chart( self::normalize_report_rows( $referrers, 'label', 'nb_visits' ) ),
				'countries' => self::normalize_pie_chart( self::normalize_report_rows( $countries, 'label', 'nb_visits' ) ),
				'devices'   => self::normalize_pie_chart( self::normalize_report_rows( $devices, 'label', 'nb_visits' ) ),
				'world_map' => self::normalize_world_map( $countries_map ),
			),
			'compare_label' => $period['compare_label'] ?? '',
			'pages'        => self::normalize_report_rows( $pages, 'label', 'nb_hits' ),
			'referrers'    => self::normalize_report_rows( $referrers, 'label', 'nb_visits' ),
			'countries'    => self::normalize_report_rows( $countries, 'label', 'nb_visits' ),
			'browsers'     => self::normalize_report_rows( $browsers, 'label', 'nb_visits' ),
			'devices'      => self::normalize_report_rows( $devices, 'label', 'nb_visits' ),
			'search'       => self::normalize_report_rows( $search_engines, 'label', 'nb_visits' ),
		);
	}

	/**
	 * @param mixed $summary         Réponse période courante.
	 * @param mixed $previous_summary Réponse période précédente.
	 * @return array<string, mixed>
	 */
	private static function normalize_summary( $summary, $previous_summary = null ) {
		$summary = self::aggregate_summary_row( $summary );
		$prev    = self::aggregate_summary_row( $previous_summary );

		$visits  = (int) ( $summary['nb_visits'] ?? 0 );
		$bounce  = (int) ( $summary['bounce_count'] ?? 0 );
		$length  = (int) ( $summary['sum_visit_length'] ?? 0 );
		$actions = (int) ( $summary['nb_actions'] ?? 0 );

		$bounce_rate = $visits > 0 ? round( ( $bounce / $visits ) * 100, 1 ) : 0;
		$avg_time    = $visits > 0 ? (int) round( $length / $visits ) : 0;
		$pages_visit = $visits > 0 ? round( $actions / $visits, 1 ) : 0;

		$prev_visits    = (int) ( $prev['nb_visits'] ?? 0 );
		$prev_actions   = (int) ( $prev['nb_actions'] ?? 0 );
		$prev_visitors  = (int) ( $prev['nb_uniq_visitors'] ?? 0 );
		$prev_bounce    = (int) ( $prev['bounce_count'] ?? 0 );
		$prev_length    = (int) ( $prev['sum_visit_length'] ?? 0 );
		$prev_bounce_rt = $prev_visits > 0 ? round( ( $prev_bounce / $prev_visits ) * 100, 1 ) : 0;
		$prev_avg_time  = $prev_visits > 0 ? (int) round( $prev_length / $prev_visits ) : 0;
		$prev_pages_v   = $prev_visits > 0 ? round( $prev_actions / $prev_visits, 1 ) : 0;

		return array(
			'nb_visits'          => self::format_int( $visits ),
			'nb_visits_raw'      => $visits,
			'nb_uniq_visitors'   => self::format_int( $summary['nb_uniq_visitors'] ?? 0 ),
			'nb_actions'         => self::format_int( $actions ),
			'bounce_rate'        => $bounce_rate . '%',
			'avg_time'           => self::format_duration( $avg_time ),
			'pages_per_visit'    => number_format_i18n( $pages_visit, 1 ),
			'trend_visits'       => self::format_trend( $visits, $prev_visits ),
			'trend_visitors'     => self::format_trend( (int) ( $summary['nb_uniq_visitors'] ?? 0 ), $prev_visitors ),
			'trend_actions'      => self::format_trend( $actions, $prev_actions ),
			'trend_bounce'       => self::format_trend( $bounce_rate, $prev_bounce_rt, true ),
			'trend_avg_time'     => self::format_trend( $avg_time, $prev_avg_time ),
			'trend_pages_visit'  => self::format_trend( $pages_visit, $prev_pages_v ),
		);
	}

	/**
	 * @param mixed $summary Ligne ou série API.
	 * @return array<string, mixed>
	 */
	private static function aggregate_summary_row( $summary ) {
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
			return $agg;
		}

		return is_array( $summary ) ? $summary : array();
	}

	/**
	 * @param float|int $current Valeur actuelle.
	 * @param float|int $previous Valeur précédente.
	 * @param bool      $lower_is_better Pour le rebond, une baisse est positive.
	 * @return array{value:string, direction:string, class:string}
	 */
	private static function format_trend( $current, $previous, $lower_is_better = false ) {
		$current  = (float) $current;
		$previous = (float) $previous;

		if ( $previous <= 0 ) {
			return array(
				'value'     => '',
				'direction' => 'flat',
				'class'     => 'gi-matomo-trend--flat',
			);
		}

		$diff = round( ( ( $current - $previous ) / $previous ) * 100, 1 );
		if ( 0.0 === $diff ) {
			return array(
				'value'     => '0%',
				'direction' => 'flat',
				'class'     => 'gi-matomo-trend--flat',
			);
		}

		$sign      = $diff > 0 ? '+' : '';
		$is_up     = $diff > 0;
		$is_good   = $lower_is_better ? ! $is_up : $is_up;

		return array(
			'value'     => $sign . number_format_i18n( $diff, 1 ) . '%',
			'direction' => $is_up ? 'up' : 'down',
			'class'     => $is_good ? 'gi-matomo-trend--up' : 'gi-matomo-trend--down',
		);
	}

	/**
	 * @param mixed $countries Réponse UserCountry.getCountry.
	 * @return array{values:array<string,int>, max:int}
	 */
	private static function normalize_world_map( $countries ) {
		$values = array();
		if ( ! is_array( $countries ) ) {
			return array(
				'values' => $values,
				'max'    => 0,
			);
		}

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-country-codes.php';

		foreach ( $countries as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$iso = Gi_Toolkit_Matomo_Country_Codes::to_iso2(
				$row['code'] ?? '',
				$row['label'] ?? ''
			);
			if ( '' === $iso ) {
				continue;
			}
			$values[ $iso ] = ( $values[ $iso ] ?? 0 ) + (int) ( $row['nb_visits'] ?? 0 );
		}

		return array(
			'values' => $values,
			'max'    => ! empty( $values ) ? max( $values ) : 0,
		);
	}

	/**
	 * @param mixed $data Données VisitsSummary.get (série).
	 * @return array{labels:array<int,string>, visits:array<int,int>, unique:array<int,int>, actions:array<int,int>}
	 */
	private static function normalize_timeline_chart( $data ) {
		$labels  = array();
		$visits  = array();
		$unique  = array();
		$actions = array();

		if ( ! is_array( $data ) ) {
			return compact( 'labels', 'visits', 'unique', 'actions' );
		}

		if ( self::is_assoc_date_series( $data ) ) {
			$data = self::sort_series_by_date_key( $data );
			foreach ( $data as $date => $row ) {
				$labels[]  = self::format_chart_label( (string) $date );
				$visits[]  = is_array( $row ) ? (int) ( $row['nb_visits'] ?? 0 ) : 0;
				$unique[]  = is_array( $row ) ? (int) ( $row['nb_uniq_visitors'] ?? 0 ) : 0;
				$actions[] = is_array( $row ) ? (int) ( $row['nb_actions'] ?? 0 ) : 0;
			}
			return compact( 'labels', 'visits', 'unique', 'actions' );
		}

		if ( isset( $data['nb_visits'] ) ) {
			$labels[]  = '';
			$visits[]  = (int) $data['nb_visits'];
			$unique[]  = (int) ( $data['nb_uniq_visitors'] ?? 0 );
			$actions[] = (int) ( $data['nb_actions'] ?? 0 );
		}

		return compact( 'labels', 'visits', 'unique', 'actions' );
	}

	/**
	 * @param array<int, array{label:string, value:int}> $rows Lignes.
	 * @return array{labels:array<int,string>, values:array<int,int>}
	 */
	private static function normalize_pie_chart( array $rows ) {
		$labels = array();
		$values = array();
		foreach ( $rows as $row ) {
			$labels[] = $row['label'];
			$values[] = $row['value'];
		}
		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * Tri chronologique des clés Matomo (jour, heure, mois).
	 *
	 * @param array<string, mixed> $data Série API.
	 * @return array<string, mixed>
	 */
	private static function sort_series_by_date_key( array $data ) {
		uksort(
			$data,
			static function ( $a, $b ) {
				$ta = self::parse_series_key_timestamp( $a );
				$tb = self::parse_series_key_timestamp( $b );
				if ( null === $ta || null === $tb ) {
					return strcmp( $a, $b );
				}
				return $ta <=> $tb;
			}
		);
		return $data;
	}

	/**
	 * @param string $key Clé Matomo.
	 * @return int|null Timestamp Unix.
	 */
	private static function parse_series_key_timestamp( $key ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2}),(\d{1,2})$/', $key, $matches ) ) {
			$ts = strtotime( $matches[1] . ' ' . (int) $matches[2] . ':00:00' );
			return $ts ? (int) $ts : null;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $key ) ) {
			$ts = strtotime( $key );
			return $ts ? (int) $ts : null;
		}
		if ( preg_match( '/^\d{4}-\d{2}$/', $key ) ) {
			$ts = strtotime( $key . '-01' );
			return $ts ? (int) $ts : null;
		}
		return null;
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
			if ( ! is_string( $key ) ) {
				return false;
			}
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $key ) ) {
				continue;
			}
			if ( preg_match( '/^\d{4}-\d{2}-\d{2},\d{1,2}$/', $key ) ) {
				continue;
			}
			if ( preg_match( '/^\d{4}-\d{2}$/', $key ) ) {
				continue;
			}
			return false;
		}
		return ! empty( $data );
	}

	/**
	 * @param mixed  $rows       Lignes API.
	 * @param string $label_key  Clé libellé.
	 * @param string $value_key  Clé valeur.
	 * @return array<int, array{label:string, value:int, percent:float}>
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

		$max   = ! empty( $out ) ? (int) $out[0]['value'] : 0;
		$total = array_sum( array_column( $out, 'value' ) );

		foreach ( $out as $i => $row ) {
			$out[ $i ]['percent'] = $max > 0 ? round( ( $row['value'] / $max ) * 100, 1 ) : 0;
			if ( $total > 0 ) {
				$out[ $i ]['share'] = round( ( $row['value'] / $total ) * 100, 1 );
			} else {
				$out[ $i ]['share'] = 0;
			}
		}

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
	 * @param string $date Clé Matomo (jour, heure ou mois).
	 * @return string
	 */
	private static function format_chart_label( $date ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2}),(\d{1,2})$/', $date, $matches ) ) {
			return sprintf( '%02d:00', (int) $matches[2] );
		}

		if ( preg_match( '/^\d{4}-\d{2}$/', $date ) ) {
			$ts = strtotime( $date . '-01' );
			return $ts ? wp_date( 'M Y', $ts ) : $date;
		}

		$ts = strtotime( $date );
		if ( ! $ts ) {
			return $date;
		}

		return wp_date( 'j M', $ts );
	}
}
