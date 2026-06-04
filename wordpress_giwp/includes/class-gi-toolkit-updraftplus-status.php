<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remonte le statut UpdraftPlus vers MainWP via l’API GI-Toolkit.
 */
class Gi_Toolkit_UpdraftPlus_Status {

	const STALE_DAYS = 10;

	/**
	 * @return array<string, mixed>
	 */
	public static function get_mainwp_status_payload() {
		if ( ! self::is_plugin_active() ) {
			return array(
				'plugin_active' => false,
			);
		}

		$history     = self::get_backup_history();
		$in_progress = self::is_backup_in_progress();
		$last        = self::get_last_backup_entry( $history );
		$remote_cfg  = self::has_remote_destinations_configured();

		if ( $in_progress ) {
			return array_merge(
				self::base_payload( $last, $remote_cfg ),
				array(
					'status' => 'in_progress',
				)
			);
		}

		if ( empty( $last ) ) {
			return array_merge(
				self::base_payload( null, $remote_cfg ),
				array(
					'status' => 'none',
				)
			);
		}

		$timestamp = (int) ( $last['timestamp'] ?? 0 );
		$entry     = is_array( $last['entry'] ?? null ) ? $last['entry'] : array();
		$remote    = self::resolve_remote_status( $entry, $remote_cfg );
		$size      = self::calculate_backup_size( $entry );
		$age_days  = self::age_in_days( $timestamp );

		return array_merge(
			self::base_payload( $last, $remote_cfg ),
			array(
				'status'               => self::resolve_status( $entry, $timestamp ),
				'last_backup_time'     => $timestamp,
				'last_backup_label'    => self::format_backup_label( $entry ),
				'last_backup_age_days' => $age_days,
				'is_stale'             => $age_days >= self::STALE_DAYS,
				'size_bytes'           => $size,
				'size_human'           => size_format( $size, 2 ),
				'remote_configured'    => $remote_cfg,
				'remote_sent'          => $remote['sent'],
				'remote_services'      => $remote['services'],
				'remote_required'      => $remote['required'],
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function is_plugin_active() {
		if ( class_exists( 'UpdraftPlus' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'updraftplus/updraftplus.php' );
	}

	/**
	 * @return array<int|string, array<string, mixed>>
	 */
	private static function get_backup_history() {
		if ( class_exists( 'UpdraftPlus_Options' ) ) {
			$history = UpdraftPlus_Options::get_updraft_option( 'updraft_backup_history', array() );
			return is_array( $history ) ? $history : array();
		}

		$history = get_option( 'updraft_backup_history', array() );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Détecte un backup UpdraftPlus réellement actif (pas les jobdata orphelins en base).
	 *
	 * @return bool
	 */
	private static function is_backup_in_progress() {
		if ( self::has_scheduled_backup_resume() ) {
			return true;
		}

		foreach ( self::get_jobdata_option_names() as $option_name ) {
			$job_id  = self::extract_job_id_from_option_name( $option_name );
			$jobdata = self::read_jobdata( $job_id );
			if ( self::jobdata_indicates_active_backup( $jobdata ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	private static function has_scheduled_backup_resume() {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return false;
		}

		$cron = _get_cron_array();
		if ( ! is_array( $cron ) ) {
			return false;
		}

		foreach ( $cron as $hooks ) {
			if ( is_array( $hooks ) && ! empty( $hooks['updraft_backup_resume'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<int, string>
	 */
	private static function get_jobdata_option_names() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof wpdb ) {
			return array();
		}

		$like = $wpdb->esc_like( 'updraft_jobdata_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		return is_array( $rows ) ? array_values( array_filter( array_map( 'strval', $rows ) ) ) : array();
	}

	/**
	 * @param string $option_name Nom d’option (updraft_jobdata_xxx).
	 * @return string
	 */
	private static function extract_job_id_from_option_name( $option_name ) {
		$prefix = 'updraft_jobdata_';
		if ( 0 !== strpos( $option_name, $prefix ) ) {
			return '';
		}

		return substr( $option_name, strlen( $prefix ) );
	}

	/**
	 * @param string $job_id Identifiant job UpdraftPlus.
	 * @return array<string, mixed>
	 */
	private static function read_jobdata( $job_id ) {
		$job_id = sanitize_text_field( (string) $job_id );
		if ( '' === $job_id ) {
			return array();
		}

		$data = get_site_option( 'updraft_jobdata_' . $job_id, array() );
		if ( ! is_array( $data ) || empty( $data ) ) {
			$fallback = get_option( 'updraft_jobdata_' . $job_id, array() );
			$data     = is_array( $fallback ) ? $fallback : array();
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $jobdata Données job UpdraftPlus.
	 * @return bool
	 */
	private static function jobdata_indicates_active_backup( $jobdata ) {
		if ( empty( $jobdata ) || ! is_array( $jobdata ) ) {
			return false;
		}

		$job_type = sanitize_key( (string) ( $jobdata['job_type'] ?? 'backup' ) );
		if ( '' !== $job_type && 'backup' !== $job_type ) {
			return false;
		}

		$jobstatus = (string) ( $jobdata['jobstatus'] ?? '' );
		if ( '' === $jobstatus || 'finished' === $jobstatus ) {
			return false;
		}

		$backup_time = (int) ( $jobdata['backup_time'] ?? 0 );
		if ( $backup_time > 0 && ( time() - $backup_time ) > DAY_IN_SECONDS ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<int|string, array<string, mixed>> $history Historique UpdraftPlus.
	 * @return array{timestamp:int, entry:array<string, mixed>}|null
	 */
	private static function get_last_backup_entry( array $history ) {
		if ( empty( $history ) ) {
			return null;
		}

		$timestamps = array();
		foreach ( array_keys( $history ) as $key ) {
			$ts = is_numeric( $key ) ? (int) $key : 0;
			if ( $ts > 0 ) {
				$timestamps[] = $ts;
			}
		}

		if ( empty( $timestamps ) ) {
			return null;
		}

		rsort( $timestamps, SORT_NUMERIC );
		$timestamp = (int) $timestamps[0];
		$entry     = $history[ $timestamp ] ?? $history[ (string) $timestamp ] ?? null;

		if ( ! is_array( $entry ) ) {
			return null;
		}

		return array(
			'timestamp' => $timestamp,
			'entry'     => $entry,
		);
	}

	/**
	 * @param array{timestamp:int, entry:array<string, mixed>}|null $last       Dernier backup.
	 * @param bool                                                  $remote_cfg Remote configuré.
	 * @return array<string, mixed>
	 */
	private static function base_payload( $last, $remote_cfg ) {
		$version = '';
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_file = WP_PLUGIN_DIR . '/updraftplus/updraftplus.php';
		if ( file_exists( $plugin_file ) ) {
			$data    = get_plugin_data( $plugin_file, false, false );
			$version = (string) ( $data['Version'] ?? '' );
		}

		$payload = array(
			'plugin_active'     => true,
			'plugin_version'    => $version,
			'remote_configured' => $remote_cfg,
		);

		if ( is_array( $last ) && ! empty( $last['timestamp'] ) ) {
			$payload['last_backup_time'] = (int) $last['timestamp'];
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $entry Entrée historique.
	 * @param int                    $timestamp Horodatage backup.
	 * @return string
	 */
	private static function resolve_status( array $entry, $timestamp ) {
		if ( $timestamp <= 0 ) {
			return 'none';
		}

		$entities = array( 'db', 'plugins', 'themes', 'uploads', 'others', 'more' );
		$present  = 0;
		foreach ( $entities as $entity ) {
			if ( ! empty( $entry[ $entity ] ) ) {
				++$present;
			}
		}

		if ( $present <= 0 ) {
			return 'partial';
		}

		if ( self::age_in_days( $timestamp ) >= self::STALE_DAYS ) {
			return 'stale';
		}

		return 'success';
	}

	/**
	 * @param array<string, mixed> $entry Entrée historique.
	 * @return string
	 */
	private static function format_backup_label( array $entry ) {
		$parts = array();
		if ( ! empty( $entry['db'] ) ) {
			$parts[] = 'DB';
		}
		foreach ( array( 'plugins', 'themes', 'uploads', 'others', 'more' ) as $entity ) {
			if ( ! empty( $entry[ $entity ] ) ) {
				$parts[] = ucfirst( $entity );
			}
		}

		return ! empty( $parts ) ? implode( ' + ', $parts ) : __( 'Backup', 'gi-toolkit' );
	}

	/**
	 * @param array<string, mixed> $entry Entrée historique.
	 * @return int
	 */
	private static function calculate_backup_size( array $entry ) {
		$total = 0;

		foreach ( $entry as $key => $value ) {
			if ( is_string( $key ) && preg_match( '/-size$/', $key ) ) {
				$total += max( 0, (int) $value );
			}
		}

		if ( $total > 0 ) {
			return $total;
		}

		return self::calculate_size_from_files( $entry );
	}

	/**
	 * @param array<string, mixed> $entry Entrée historique.
	 * @return int
	 */
	private static function calculate_size_from_files( array $entry ) {
		$dir = self::get_backup_directory();
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return 0;
		}

		$total = 0;
		foreach ( self::collect_backup_filenames( $entry ) as $filename ) {
			$path = $dir . '/' . $filename;
			if ( is_file( $path ) ) {
				$total += (int) filesize( $path );
			}
		}

		return $total;
	}

	/**
	 * @param array<string, mixed> $entry Entrée historique.
	 * @return array<int, string>
	 */
	private static function collect_backup_filenames( array $entry ) {
		$files = array();
		foreach ( array( 'db', 'plugins', 'themes', 'uploads', 'others', 'more' ) as $entity ) {
			if ( empty( $entry[ $entity ] ) ) {
				continue;
			}
			$value = $entry[ $entity ];
			if ( is_string( $value ) ) {
				$files[] = $value;
				continue;
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $file ) {
					if ( is_string( $file ) && '' !== $file ) {
						$files[] = $file;
					}
				}
			}
		}
		return $files;
	}

	/**
	 * @return string
	 */
	private static function get_backup_directory() {
		if ( class_exists( 'UpdraftPlus' ) ) {
			global $updraftplus;
			if ( is_object( $updraftplus ) && method_exists( $updraftplus, 'backups_dir_location' ) ) {
				return untrailingslashit( (string) $updraftplus->backups_dir_location() );
			}
		}

		return WP_CONTENT_DIR . '/updraft';
	}

	/**
	 * @return bool
	 */
	private static function has_remote_destinations_configured() {
		$methods = array(
			'updraft_s3',
			'updraft_dropbox',
			'updraft_googledrive',
			'updraft_onedrive',
			'updraft_ftp',
			'updraft_sftp',
			'updraft_webdav',
			'updraft_openstack',
			'updraft_azure',
			'updraft_backblaze',
			'updraft_pcloud',
		);

		foreach ( $methods as $option ) {
			$value = self::get_updraft_option( $option );
			if ( self::option_has_remote_credentials( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $value Option UpdraftPlus.
	 * @return bool
	 */
	private static function option_has_remote_credentials( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::option_has_remote_credentials( $item ) ) {
					return true;
				}
			}
			return ! empty( $value );
		}

		if ( is_string( $value ) ) {
			return '' !== trim( $value );
		}

		return ! empty( $value );
	}

	/**
	 * @param string $name Nom option.
	 * @return mixed
	 */
	private static function get_updraft_option( $name ) {
		if ( class_exists( 'UpdraftPlus_Options' ) ) {
			return UpdraftPlus_Options::get_updraft_option( $name );
		}
		return get_option( $name );
	}

	/**
	 * @param array<string, mixed> $entry      Entrée historique.
	 * @param bool                 $remote_cfg Remote configuré globalement.
	 * @return array{sent:bool, required:bool, services:array<int, string>}
	 */
	private static function resolve_remote_status( array $entry, $remote_cfg ) {
		$services = self::collect_remote_services_from_entry( $entry );

		if ( ! empty( $entry['meta_remote_sent'] ) || ! empty( $entry['meta']['remote_sent'] ) ) {
			return array(
				'sent'     => true,
				'required' => $remote_cfg,
				'services' => $services,
			);
		}

		if ( ! empty( $services ) ) {
			return array(
				'sent'     => true,
				'required' => $remote_cfg,
				'services' => $services,
			);
		}

		return array(
			'sent'     => false,
			'required' => $remote_cfg,
			'services' => array(),
		);
	}

	/**
	 * Services distants enregistrés pour un backup (historique + jobdata lié).
	 *
	 * @param array<string, mixed> $entry Entrée historique.
	 * @return array<int, string>
	 */
	private static function collect_remote_services_from_entry( array $entry ) {
		$services = self::normalize_service_list( $entry['service'] ?? array() );

		if ( ! empty( $entry['nonce'] ) ) {
			$jobdata = self::read_jobdata( (string) $entry['nonce'] );
			if ( ! empty( $jobdata['service'] ) ) {
				$services = array_merge( $services, self::normalize_service_list( $jobdata['service'] ) );
			}
		}

		return array_values( array_unique( $services ) );
	}

	/**
	 * @param mixed $services Liste brute UpdraftPlus.
	 * @return array<int, string>
	 */
	private static function normalize_service_list( $services ) {
		if ( is_string( $services ) ) {
			$services = array( $services );
		}
		if ( ! is_array( $services ) ) {
			return array();
		}

		$ignored = array( 'none', 'email', 'remotesend', 'local' );

		return array_values(
			array_filter(
				array_map(
					static function ( $service ) {
						$service = sanitize_key( (string) $service );
						return '' !== $service ? $service : '';
					},
					$services
				),
				static function ( $service ) use ( $ignored ) {
					return ! in_array( $service, $ignored, true );
				}
			)
		);
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
}
