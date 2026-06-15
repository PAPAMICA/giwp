<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des statuts UpdraftPlus remontés via la synchro MainWP.
 */
class MainWP_GIWeb_Backup_Stats {

	const AGGREGATE_OPTION = 'mainwp_giweb_backup_aggregate';
	const STALE_DAYS       = 10;

	/**
	 * @param array<string, mixed> $data Données statut site.
	 * @return array<string, mixed>|null
	 */
	public static function extract_backup( $data ) {
		if ( ! is_array( $data ) || empty( $data['updraftplus'] ) || ! is_array( $data['updraftplus'] ) ) {
			return null;
		}
		return $data['updraftplus'];
	}

	/**
	 * Extrait le statut backup injecté par GI-Toolkit dans la synchro MainWP native.
	 *
	 * @param array<string, mixed>|null $information Données remontées par MainWP Child.
	 * @return array<string, mixed>|null
	 */
	public static function extract_backup_from_sync( $information ) {
		if ( ! is_array( $information ) ) {
			return null;
		}

		if ( ! empty( $information['gi_toolkit_updraftplus'] ) && is_array( $information['gi_toolkit_updraftplus'] ) ) {
			return $information['gi_toolkit_updraftplus'];
		}

		return self::normalize_from_mainwp_sync( $information );
	}

	/**
	 * Repli sur syncUpdraftData (MainWP Child natif) si GI-Toolkit n’a pas encore injecté le payload.
	 *
	 * @param array<string, mixed> $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function normalize_from_mainwp_sync( array $information ) {
		if ( ! empty( $information['error'] ) && 'NO_UPDRAFTPLUS' === (string) $information['error'] ) {
			return array(
				'plugin_active' => false,
			);
		}

		$sync = $information['syncUpdraftData'] ?? null;
		if ( false === $sync || null === $sync ) {
			return array(
				'plugin_active' => false,
			);
		}

		if ( ! is_array( $sync ) ) {
			return null;
		}

		$timestamp = (int) ( $sync['updraft_lastbackup_gmttime'] ?? 0 );
		$is_stale  = self::is_timestamp_stale( $timestamp );

		return array(
			'plugin_active'        => true,
			'status'               => $timestamp > 0 ? ( $is_stale ? 'stale' : 'success' ) : 'none',
			'last_backup_time'     => $timestamp,
			'last_backup_age_days' => self::age_in_days( $timestamp ),
			'is_stale'             => $is_stale,
			'size_bytes'           => 0,
			'size_human'           => '—',
			'remote_configured'    => false,
			'remote_sent'          => false,
			'remote_services'      => array(),
			'source'               => 'mainwp_sync',
		);
	}

	/**
	 * Choisit le payload backup le plus complet (API GI-Toolkit prioritaire, puis sync MainWP).
	 *
	 * @param array<string, mixed>|null $api_data    Données API GI-Toolkit.
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_backup_payload( $api_data, $information = null ) {
		$from_api  = is_array( $api_data ) ? self::extract_backup( $api_data ) : null;
		$from_sync = self::extract_backup_from_sync( $information );

		return MainWP_GIWeb_MainWP_Sync::pick_richer_payload( $from_api, $from_sync );
	}

	/**
	 * @param int $timestamp Horodatage Unix.
	 * @return bool
	 */
	private static function is_timestamp_stale( $timestamp ) {
		return self::age_in_days( $timestamp ) >= self::STALE_DAYS;
	}

	/**
	 * @param int $timestamp Horodatage Unix.
	 * @return float
	 */
	private static function age_in_days( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return 9999.0;
		}
		return max( 0, ( time() - $timestamp ) / DAY_IN_SECONDS );
	}

	/**
	 * @param int                  $site_id     ID MainWP.
	 * @param string               $label       Nom site.
	 * @param string               $url         URL site.
	 * @param array<string, mixed> $api         Réponse API complète.
	 * @param array<string, mixed>|null $information Données sync MainWP (optionnel).
	 * @return array<string, mixed>
	 */
	public static function record_site_sync( $site_id, $label, $url, $api, $information = null ) {
		$site_id = absint( $site_id );
		$ok      = ! empty( $api['success'] );
		$data    = is_array( $api['data'] ?? null ) ? $api['data'] : array();
		$backup  = self::resolve_backup_payload( $data, $information );

		$aggregate = self::get_aggregate();
		if ( ! isset( $aggregate['sites'] ) || ! is_array( $aggregate['sites'] ) ) {
			$aggregate['sites'] = array();
		}

		$aggregate['sites'][ $site_id ] = array(
			'label'     => $label,
			'url'       => $url,
			'api_ok'    => $ok,
			'synced_at' => time(),
			'backup'    => $backup,
		);

		$aggregate['updated_at'] = time();
		$aggregate['network']  = self::compute_network( $aggregate['sites'] );

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
			'sites_tracked'        => 0,
			'sites_active'         => 0,
			'sites_fresh'          => 0,
			'sites_stale'          => 0,
			'sites_no_backup'      => 0,
			'sites_in_progress'    => 0,
			'sites_remote_missing' => 0,
		);

		if ( ! is_array( $sites ) ) {
			return $network;
		}

		foreach ( $sites as $row ) {
			$backup = $row['backup'] ?? null;
			if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
				continue;
			}

			++$network['sites_active'];
			++$network['sites_tracked'];

			$status = (string) ( $backup['status'] ?? 'none' );
			if ( 'in_progress' === $status ) {
				++$network['sites_in_progress'];
				continue;
			}
			if ( 'none' === $status ) {
				++$network['sites_no_backup'];
				continue;
			}

			if ( self::is_fresh( $backup ) ) {
				++$network['sites_fresh'];
			} else {
				++$network['sites_stale'];
			}

			if ( ! empty( $backup['remote_configured'] ) && empty( $backup['remote_sent'] ) ) {
				++$network['sites_remote_missing'];
			}
		}

		return $network;
	}

	/**
	 * @param int $site_id ID MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function get_site_backup( $site_id ) {
		$site_id = absint( $site_id );
		if ( ! $site_id ) {
			return null;
		}

		$agg   = self::get_aggregate();
		$sites = $agg['sites'] ?? array();
		if ( is_array( $sites ) && isset( $sites[ $site_id ]['backup'] ) ) {
			$backup = $sites[ $site_id ]['backup'];
			return is_array( $backup ) ? $backup : null;
		}

		$cache = MainWP_GIWeb_Status_Cache::get_all();
		if ( ! isset( $cache[ $site_id ] ) || ! is_array( $cache[ $site_id ] ) ) {
			return null;
		}

		return self::extract_backup( $cache[ $site_id ]['data'] ?? array() );
	}

	/**
	 * @param array<string, mixed>|null $backup Stats backup site.
	 * @return bool
	 */
	public static function is_fresh( $backup ) {
		if ( ! is_array( $backup ) ) {
			return false;
		}
		if ( isset( $backup['is_stale'] ) ) {
			return empty( $backup['is_stale'] );
		}
		$timestamp = (int) ( $backup['last_backup_time'] ?? 0 );
		if ( $timestamp <= 0 ) {
			return false;
		}
		return ( time() - $timestamp ) < ( self::STALE_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * @param int $timestamp Horodatage Unix.
	 * @return string
	 */
	public static function format_relative_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return __( 'aucun backup', 'mainwp-giweb' );
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
	 * @param array<string, mixed>|null $backup Stats backup site.
	 * @return string
	 */
	public static function format_status_label( $backup ) {
		if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
			return __( 'UpdraftPlus inactif', 'mainwp-giweb' );
		}

		$status = (string) ( $backup['status'] ?? 'none' );
		switch ( $status ) {
			case 'in_progress':
				return __( 'Backup en cours', 'mainwp-giweb' );
			case 'none':
				return __( 'Aucun backup', 'mainwp-giweb' );
			case 'partial':
				return __( 'Backup partiel', 'mainwp-giweb' );
			case 'stale':
				return __( 'Backup ancien', 'mainwp-giweb' );
			default:
				return self::is_fresh( $backup )
					? __( 'Backup récent', 'mainwp-giweb' )
					: __( 'Backup ancien', 'mainwp-giweb' );
		}
	}

	/**
	 * @param array<string, mixed>|null $backup Stats backup site.
	 * @return string ok|stale|warn|inactive|pending
	 */
	public static function get_visual_state( $backup ) {
		if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
			return 'inactive';
		}

		$status = (string) ( $backup['status'] ?? 'none' );
		if ( 'in_progress' === $status ) {
			return 'warn';
		}
		if ( 'none' === $status ) {
			return 'stale';
		}
		if ( ! self::is_fresh( $backup ) ) {
			return 'stale';
		}
		if ( ! empty( $backup['remote_configured'] ) && empty( $backup['remote_sent'] ) ) {
			return 'warn';
		}
		return 'ok';
	}

	/**
	 * @param array<string, mixed>|null $backup Stats backup site.
	 * @return string
	 */
	public static function format_size_gb( $backup ) {
		if ( ! is_array( $backup ) ) {
			return '—';
		}

		$bytes = (int) ( $backup['size_bytes'] ?? 0 );
		if ( $bytes <= 0 ) {
			return '—';
		}

		$gb = $bytes / 1073741824;

		return sprintf(
			/* translators: %s: size in gigabytes with 2 decimals */
			__( '%s Go', 'mainwp-giweb' ),
			number_format_i18n( $gb, 2 )
		);
	}

	/**
	 * @param array<string, mixed>|null $backup Stats backup site.
	 * @return string
	 */
	public static function format_remote_label( $backup ) {
		if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
			return '—';
		}
		if ( empty( $backup['remote_configured'] ) ) {
			return __( 'Local', 'mainwp-giweb' );
		}
		if ( ! empty( $backup['remote_sent'] ) ) {
			$services = isset( $backup['remote_services'] ) && is_array( $backup['remote_services'] ) ? $backup['remote_services'] : array();
			return ! empty( $services )
				? implode( ', ', array_map( 'strtoupper', $services ) )
				: __( 'Envoyé', 'mainwp-giweb' );
		}
		return __( 'Non envoyé', 'mainwp-giweb' );
	}

	/**
	 * @param array<string, mixed>|null $backup Stats backup site.
	 * @return string HTML badge / texte.
	 */
	public static function format_site_backup_cell( $backup ) {
		if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
			return '<span class="mainwp-giweb-backup-site mainwp-giweb-backup-site--inactive"><span class="mainwp-giweb-backup-site__hint">' . esc_html__( 'UpdraftPlus inactif', 'mainwp-giweb' ) . '</span></span>';
		}

		$state     = self::get_visual_state( $backup );
		$timestamp = (int) ( $backup['last_backup_time'] ?? 0 );
		$relative  = self::format_relative_time( $timestamp );
		$size      = self::format_size_gb( $backup );
		$remote    = self::format_remote_label( $backup );

		$html  = '<div class="mainwp-giweb-backup-site mainwp-giweb-backup-site--' . esc_attr( $state ) . '">';
		$html .= '<div class="mainwp-giweb-backup-site__head">';
		$html .= '<span class="mainwp-giweb-backup-site__badge status-' . esc_attr( $state ) . '">' . esc_html( self::format_status_label( $backup ) ) . '</span>';
		$html .= '<span class="mainwp-giweb-backup-site__date">' . esc_html( $relative ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="mainwp-giweb-backup-site__metrics">';
		$html .= '<span class="mainwp-giweb-backup-site__metric" title="' . esc_attr__( 'Taille du dernier backup', 'mainwp-giweb' ) . '">' . esc_html( $size ) . '</span>';
		$html .= '<span class="mainwp-giweb-backup-site__metric mainwp-giweb-backup-site__metric--remote" title="' . esc_attr__( 'Stockage externe', 'mainwp-giweb' ) . '">' . esc_html( $remote ) . '</span>';
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Payload API REST : statut backup de tous les sites (un seul appel).
	 *
	 * @return array<string, mixed>
	 */
	public static function build_api_network_payload() {
		global $mainwp_giweb_activator;

		$aggregate = self::get_aggregate();
		$by_id     = is_array( $aggregate['sites'] ?? null ) ? $aggregate['sites'] : array();
		$sites     = array();

		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			$normalized = MainWP_GIWeb_Sites::normalize_one( $site );
			$site_id    = (int) ( $normalized['id'] ?? 0 );
			if ( ! $site_id ) {
				continue;
			}

			$row     = is_array( $by_id[ $site_id ] ?? null ) ? $by_id[ $site_id ] : array();
			$label   = (string) ( $row['label'] ?? ( $normalized['name'] ?? ( $normalized['url'] ?? ( '#' . $site_id ) ) ) );
			$url     = (string) ( $row['url'] ?? ( $normalized['url'] ?? '' ) );
			$backup  = $row['backup'] ?? self::get_site_backup( $site_id );
			$synced  = (int) ( $row['synced_at'] ?? 0 );

			$sites[] = self::format_api_site_entry( $site_id, $label, $url, $backup, $synced );
		}

		return array(
			'updated_at'  => (int) ( $aggregate['updated_at'] ?? 0 ),
			'network'     => is_array( $aggregate['network'] ?? null ) ? $aggregate['network'] : self::compute_network( $by_id ),
			'total_sites' => count( $sites ),
			'sites'       => $sites,
		);
	}

	/**
	 * @param int                       $site_id   ID MainWP.
	 * @param string                    $label     Nom site.
	 * @param string                    $url       URL site.
	 * @param array<string, mixed>|null $backup    Payload backup.
	 * @param int                       $synced_at Dernière synchro agrégat.
	 * @return array<string, mixed>
	 */
	public static function format_api_site_entry( $site_id, $label, $url, $backup, $synced_at = 0 ) {
		$site_id = absint( $site_id );

		if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
			return array(
				'site_id'        => $site_id,
				'label'          => $label,
				'url'            => $url,
				'plugin_active'  => false,
				'status'         => 'inactive',
				'status_label'   => self::format_status_label( $backup ),
				'state'          => 'inactive',
				'is_stale'       => true,
				'last_backup_time'     => 0,
				'last_backup_date'     => null,
				'last_backup_age_days' => null,
				'last_backup_label'    => '',
				'size_bytes'     => 0,
				'size_human'     => '—',
				'location'       => null,
				'synced_at'      => $synced_at,
			);
		}

		$timestamp = (int) ( $backup['last_backup_time'] ?? 0 );
		$size      = (int) ( $backup['size_bytes'] ?? 0 );

		return array(
			'site_id'              => $site_id,
			'label'                => $label,
			'url'                  => $url,
			'plugin_active'        => true,
			'status'               => (string) ( $backup['status'] ?? 'none' ),
			'status_label'         => self::format_status_label( $backup ),
			'state'                => self::get_visual_state( $backup ),
			'is_stale'             => ! self::is_fresh( $backup ),
			'last_backup_time'     => $timestamp,
			'last_backup_date'     => $timestamp > 0 ? gmdate( 'c', $timestamp ) : null,
			'last_backup_age_days' => isset( $backup['last_backup_age_days'] )
				? (float) $backup['last_backup_age_days']
				: ( $timestamp > 0 ? round( ( time() - $timestamp ) / DAY_IN_SECONDS, 1 ) : null ),
			'last_backup_label'    => (string) ( $backup['last_backup_label'] ?? '' ),
			'size_bytes'           => $size,
			'size_human'           => '' !== (string) ( $backup['size_human'] ?? '' )
				? (string) $backup['size_human']
				: self::format_size_gb( $backup ),
			'location'             => self::format_api_location( $backup ),
			'synced_at'            => $synced_at,
		);
	}

	/**
	 * @param array<string, mixed> $backup Payload backup.
	 * @return array<string, mixed>
	 */
	public static function format_api_location( $backup ) {
		$services = isset( $backup['remote_services'] ) && is_array( $backup['remote_services'] )
			? array_values( $backup['remote_services'] )
			: array();

		return array(
			'label'               => self::format_remote_label( $backup ),
			'local_path'          => (string) ( $backup['local_path'] ?? '' ),
			'remote_configured'   => ! empty( $backup['remote_configured'] ),
			'remote_sent'         => ! empty( $backup['remote_sent'] ),
			'remote_services'     => $services,
		);
	}
}
