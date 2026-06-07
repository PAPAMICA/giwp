<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dossiers de backup WordPress sur FTP — création, vérification et statistiques.
 */
class MainWP_GIWeb_Ftp_Backup {

	const PATH_TOKEN_SITEURL  = '%siteurl%';
	const PATH_TOKEN_SITENAME = '%sitename%';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_mainwp_giweb_ftp_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_mainwp_giweb_ftp_verify_all', array( __CLASS__, 'ajax_verify_all' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		return '' !== $creds['host']
			&& '' !== $creds['username']
			&& '' !== $creds['password']
			&& '' !== $creds['path_template'];
	}

	/**
	 * @return bool
	 */
	public static function is_auto_on_deploy_enabled() {
		if ( ! self::is_configured() ) {
			return false;
		}
		$settings = MainWP_GIWeb_Settings::get();
		return '1' === (string) ( $settings['ftp_auto_on_deploy'] ?? '1' );
	}

	/**
	 * @return array{host:string, port:int, username:string, password:string, path_template:string, passive:bool, ssl:bool}
	 */
	public static function get_credentials() {
		$settings = MainWP_GIWeb_Settings::get();
		return array(
			'host'          => self::normalize_host( $settings['ftp_host'] ?? '' ),
			'port'          => max( 1, min( 65535, absint( $settings['ftp_port'] ?? 21 ) ) ),
			'username'      => trim( (string) ( $settings['ftp_username'] ?? '' ) ),
			'password'      => (string) ( $settings['ftp_password'] ?? '' ),
			'path_template' => self::normalize_path_template( $settings['ftp_path'] ?? '' ),
			'passive'       => '1' === (string) ( $settings['ftp_passive'] ?? '1' ),
			'ssl'           => '1' === (string) ( $settings['ftp_ssl'] ?? '0' ),
		);
	}

	/**
	 * @param string $host Hôte FTP.
	 * @return string
	 */
	private static function normalize_host( $host ) {
		$host = trim( (string) $host );
		if ( '' === $host ) {
			return '';
		}
		$host = preg_replace( '#^ftps?://#i', '', $host );
		$host = untrailingslashit( $host );
		$host = preg_replace( '#[:/].*$#', '', $host );
		return sanitize_text_field( $host );
	}

	/**
	 * @param string $template Chemin avec variables.
	 * @return string
	 */
	private static function normalize_path_template( $template ) {
		$template = trim( str_replace( '\\', '/', (string) $template ) );
		if ( '' === $template ) {
			return '';
		}
		if ( '/' !== $template[0] ) {
			$template = '/' . $template;
		}
		return preg_replace( '#/+#', '/', $template );
	}

	/**
	 * @param string $url URL du site.
	 * @return string
	 */
	public static function resolve_siteurl_token( $url ) {
		return MainWP_GIWeb_Zabbix::extract_domain( $url );
	}

	/**
	 * @param string $name Nom MainWP.
	 * @return string
	 */
	public static function sanitize_path_segment( $name ) {
		$name = remove_accents( trim( (string) $name ) );
		$name = strtolower( $name );
		$name = preg_replace( '/[^a-z0-9._-]+/', '-', $name );
		$name = trim( (string) $name, '-.' );
		return '' !== $name ? $name : 'site';
	}

	/**
	 * @param string               $template Modèle de chemin.
	 * @param array<string, mixed> $row      Site normalisé.
	 * @return string
	 */
	public static function resolve_path_template( $template, array $row ) {
		$template = self::normalize_path_template( $template );
		if ( '' === $template ) {
			return '';
		}

		$siteurl  = self::resolve_siteurl_token( (string) ( $row['url'] ?? '' ) );
		$sitename = self::sanitize_path_segment( (string) ( $row['name'] ?? '' ) );

		$path = str_replace(
			array( self::PATH_TOKEN_SITEURL, self::PATH_TOKEN_SITENAME ),
			array( $siteurl, $sitename ),
			$template
		);

		$parts = array();
		foreach ( explode( '/', trim( $path, '/' ) ) as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part || '.' === $part || '..' === $part ) {
				continue;
			}
			$parts[] = $part;
		}

		return '/' . implode( '/', $parts );
	}

	/**
	 * @param array<string, mixed> $row Site normalisé.
	 * @return string
	 */
	public static function path_for_site( array $row ) {
		$creds = self::get_credentials();
		return self::resolve_path_template( $creds['path_template'], $row );
	}

	/**
	 * @param int  $timestamp Horodatage Unix.
	 * @return string
	 */
	public static function format_relative_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return __( 'aucun fichier', 'mainwp-giweb' );
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
	 * @return array{success:bool, message:string, error?:string}
	 */
	public static function test_connection() {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'FTP non configuré.', 'mainwp-giweb' ),
			);
		}

		$result = self::with_connection(
			function ( $conn ) {
				$pwd = @ftp_pwd( $conn );
				if ( ! is_string( $pwd ) || '' === $pwd ) {
					return array(
						'success' => false,
						'error'   => __( 'Connexion FTP établie mais répertoire courant illisible.', 'mainwp-giweb' ),
					);
				}

				return array(
					'success' => true,
					'pwd'     => $pwd,
				);
			}
		);

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'message' => $result['error'] ?? __( 'Connexion FTP impossible.', 'mainwp-giweb' ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: current FTP directory */
				__( 'Connexion FTP OK — répertoire courant : %s', 'mainwp-giweb' ),
				(string) ( $result['pwd'] ?? '/' )
			),
		);
	}

	/**
	 * @param array<string, mixed> $row Site normalisé.
	 * @return array<string, mixed>
	 */
	public static function ensure_folder_for_site( array $row ) {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'code'    => 'not_configured',
				'message' => __( 'FTP non configuré.', 'mainwp-giweb' ),
			);
		}

		$path = self::path_for_site( $row );
		if ( '' === $path || false !== strpos( $path, '%' ) ) {
			return array(
				'success' => false,
				'code'    => 'invalid_path',
				'message' => __( 'Chemin FTP invalide (variables non résolues).', 'mainwp-giweb' ),
			);
		}

		$result = self::with_connection(
			function ( $conn ) use ( $path ) {
				$ensure = self::ensure_directory( $conn, $path );
				if ( empty( $ensure['success'] ) ) {
					return $ensure;
				}

				$stats = self::collect_directory_stats( $conn, $path );
				if ( empty( $stats['success'] ) ) {
					return $stats;
				}

				return array_merge(
					$ensure,
					$stats,
					array( 'success' => true )
				);
			}
		);

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'code'    => 'ftp_error',
				'path'    => $path,
				'message' => $result['error'] ?? __( 'Opération FTP impossible.', 'mainwp-giweb' ),
			);
		}

		$created = ! empty( $result['created'] );
		$size    = (int) ( $result['size'] ?? 0 );
		$last    = (int) ( $result['last_mtime'] ?? 0 );

		return array(
			'success'            => true,
			'code'               => $created ? 'created' : 'exists',
			'path'               => $path,
			'created'            => $created,
			'size'               => $size,
			'size_human'         => size_format( $size, 2 ),
			'last_mtime'         => $last,
			'last_file_relative' => self::format_relative_time( $last ),
			'message'            => $created
				? sprintf(
					/* translators: 1: remote path, 2: size, 3: relative time */
					__( 'Dossier créé : %1$s — %2$s — dernier fichier %3$s', 'mainwp-giweb' ),
					$path,
					size_format( $size, 2 ),
					self::format_relative_time( $last )
				)
				: sprintf(
					/* translators: 1: remote path, 2: size, 3: relative time */
					__( 'Dossier OK : %1$s — %2$s — dernier fichier %3$s', 'mainwp-giweb' ),
					$path,
					size_format( $size, 2 ),
					self::format_relative_time( $last )
				),
		);
	}

	/**
	 * @param int                  $site_id   ID MainWP.
	 * @param bool                 $deploy_ok Déploiement GI réussi.
	 * @return string
	 */
	public static function maybe_ensure_on_deploy( $site_id, $deploy_ok ) {
		if ( ! $deploy_ok ) {
			return '';
		}

		$result = self::ensure_for_site_row( absint( $site_id ) );
		return self::format_result_note( $result );
	}

	/**
	 * @param array{id:int, name:string, url:string} $row Site normalisé.
	 * @return array<string, mixed>
	 */
	public static function ensure_for_site_row( array $row ) {
		if ( ! self::is_auto_on_deploy_enabled() ) {
			return array(
				'success' => false,
				'message' => '',
			);
		}

		if ( empty( $row['url'] ) && empty( $row['id'] ) ) {
			return array(
				'success' => false,
				'message' => '',
			);
		}

		if ( '' === (string) ( $row['url'] ?? '' ) && ! empty( $row['id'] ) ) {
			global $mainwp_giweb_activator;
			$row = MainWP_GIWeb_Sites::find_by_id( (int) $row['id'], $mainwp_giweb_activator ?? null );
		}

		return self::ensure_folder_for_site( $row );
	}

	/**
	 * @param array<string, mixed> $result Résultat ensure_folder_for_site.
	 * @return string
	 */
	public static function format_result_note( $result ) {
		return ! empty( $result['message'] ) ? '[FTP] ' . (string) $result['message'] : '';
	}

	/**
	 * @param object|null $activator Activator MainWP.
	 * @return array{success:bool, created:int, exists:int, failed:int, sites:array<int, array<string, mixed>>, message:string}
	 */
	public static function verify_all_sites( $activator = null ) {
		$summary = array(
			'success' => true,
			'created' => 0,
			'exists'  => 0,
			'failed'  => 0,
			'sites'   => array(),
			'message' => '',
		);

		if ( ! self::is_configured() ) {
			$summary['success'] = false;
			$summary['message'] = __( 'FTP non configuré.', 'mainwp-giweb' );
			return $summary;
		}

		foreach ( MainWP_GIWeb_Sites::fetch_all( $activator ) as $site ) {
			$row    = MainWP_GIWeb_Sites::normalize_one( $site );
			$label  = (string) ( $row['name'] ?: $row['url'] ?: ( '#' . (int) ( $row['id'] ?? 0 ) ) );
			$result = self::ensure_folder_for_site( $row );

			$site_out = array(
				'label'              => $label,
				'success'            => ! empty( $result['success'] ),
				'created'            => ! empty( $result['created'] ),
				'path'               => (string) ( $result['path'] ?? '' ),
				'size'               => (int) ( $result['size'] ?? 0 ),
				'size_human'         => (string) ( $result['size_human'] ?? size_format( 0, 2 ) ),
				'last_mtime'         => (int) ( $result['last_mtime'] ?? 0 ),
				'last_file_relative' => (string) ( $result['last_file_relative'] ?? self::format_relative_time( 0 ) ),
				'message'            => (string) ( $result['message'] ?? '' ),
			);

			if ( empty( $result['success'] ) ) {
				++$summary['failed'];
				$summary['success'] = false;
			} elseif ( ! empty( $result['created'] ) ) {
				++$summary['created'];
			} else {
				++$summary['exists'];
			}

			$summary['sites'][] = $site_out;
		}

		$summary['message'] = sprintf(
			/* translators: 1: created, 2: already present, 3: failed */
			__( 'FTP : %1$d créé(s), %2$d déjà présent(s), %3$d échec(s).', 'mainwp-giweb' ),
			(int) $summary['created'],
			(int) $summary['exists'],
			(int) $summary['failed']
		);

		return $summary;
	}

	/**
	 * @param callable $callback Reçoit la ressource FTP.
	 * @return array<string, mixed>
	 */
	private static function with_connection( callable $callback ) {
		if ( ! function_exists( 'ftp_connect' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Extension PHP FTP indisponible sur ce serveur.', 'mainwp-giweb' ),
			);
		}

		$creds = self::get_credentials();
		$conn  = null;

		try {
			if ( $creds['ssl'] ) {
				if ( ! function_exists( 'ftp_ssl_connect' ) ) {
					return array(
						'success' => false,
						'error'   => __( 'FTPS indisponible (ftp_ssl_connect absent).', 'mainwp-giweb' ),
					);
				}
				$conn = @ftp_ssl_connect( $creds['host'], $creds['port'], 45 );
			} else {
				$conn = @ftp_connect( $creds['host'], $creds['port'], 45 );
			}

			if ( ! $conn ) {
				return array(
					'success' => false,
					'error'   => sprintf(
						/* translators: 1: host, 2: port */
						__( 'Connexion FTP impossible (%1$s:%2$d).', 'mainwp-giweb' ),
						$creds['host'],
						$creds['port']
					),
				);
			}

			if ( ! @ftp_login( $conn, $creds['username'], $creds['password'] ) ) {
				return array(
					'success' => false,
					'error'   => __( 'Identifiants FTP refusés.', 'mainwp-giweb' ),
				);
			}

			if ( $creds['passive'] ) {
				@ftp_pasv( $conn, true );
			}

			$result = $callback( $conn );
			return is_array( $result ) ? $result : array( 'success' => false, 'error' => __( 'Erreur FTP interne.', 'mainwp-giweb' ) );
		} finally {
			if ( is_resource( $conn ) ) {
				@ftp_close( $conn );
			}
		}
	}

	/**
	 * @param resource $conn Connexion FTP.
	 * @param string   $path Chemin absolu.
	 * @return bool
	 */
	private static function directory_exists( $conn, $path ) {
		$current = @ftp_pwd( $conn );
		$exists  = @ftp_chdir( $conn, $path );
		if ( $exists && is_string( $current ) && '' !== $current ) {
			@ftp_chdir( $conn, $current );
		}
		return (bool) $exists;
	}

	/**
	 * @param resource $conn Connexion FTP.
	 * @param string   $path Chemin absolu.
	 * @return array{success:bool, created?:bool, error?:string}
	 */
	private static function ensure_directory( $conn, $path ) {
		if ( self::directory_exists( $conn, $path ) ) {
			return array(
				'success' => true,
				'created' => false,
			);
		}

		$parts   = array_filter( explode( '/', trim( $path, '/' ) ) );
		$current = '';
		$created = false;

		foreach ( $parts as $part ) {
			$current .= '/' . $part;
			if ( self::directory_exists( $conn, $current ) ) {
				continue;
			}
			if ( ! @ftp_mkdir( $conn, $current ) ) {
				return array(
					'success' => false,
					'error'   => sprintf(
						/* translators: %s: remote directory path */
						__( 'Impossible de créer le dossier %s.', 'mainwp-giweb' ),
						$current
					),
				);
			}
			$created = true;
		}

		return array(
			'success' => true,
			'created' => $created,
		);
	}

	/**
	 * @param resource $conn Connexion FTP.
	 * @param string   $path Chemin absolu.
	 * @return array{success:bool, size?:int, last_mtime?:int, error?:string}
	 */
	private static function collect_directory_stats( $conn, $path ) {
		$size       = 0;
		$last_mtime = 0;
		$ok         = self::scan_directory( $conn, $path, $size, $last_mtime, 0 );

		if ( ! $ok ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: remote directory path */
					__( 'Impossible de lire le contenu de %s.', 'mainwp-giweb' ),
					$path
				),
			);
		}

		return array(
			'success'    => true,
			'size'       => $size,
			'last_mtime' => $last_mtime,
		);
	}

	/**
	 * @param resource $conn       Connexion FTP.
	 * @param string   $path       Chemin absolu.
	 * @param int      $size       Taille cumulée (ref).
	 * @param int      $last_mtime Dernier mtime (ref).
	 * @param int      $depth      Profondeur récursive.
	 * @return bool
	 */
	private static function scan_directory( $conn, $path, &$size, &$last_mtime, $depth ) {
		if ( $depth > 25 ) {
			return false;
		}

		if ( function_exists( 'ftp_mlsd' ) ) {
			$entries = @ftp_mlsd( $conn, $path );
			if ( is_array( $entries ) ) {
				foreach ( $entries as $entry ) {
					if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
						continue;
					}
					$name = (string) $entry['name'];
					if ( '.' === $name || '..' === $name ) {
						continue;
					}

					$child = rtrim( $path, '/' ) . '/' . $name;
					$type  = strtolower( (string) ( $entry['type'] ?? '' ) );

					if ( 'dir' === $type || 'cdir' === $type || 'pdir' === $type ) {
						if ( 'dir' === $type && ! self::scan_directory( $conn, $child, $size, $last_mtime, $depth + 1 ) ) {
							return false;
						}
						continue;
					}

					$file_size = isset( $entry['size'] ) ? (int) $entry['size'] : (int) @ftp_size( $conn, $child );
					if ( $file_size > 0 ) {
						$size += $file_size;
					}

					$mtime = self::entry_mtime( $entry, $conn, $child );
					if ( $mtime > $last_mtime ) {
						$last_mtime = $mtime;
					}
				}
				return true;
			}
		}

		$list = @ftp_nlist( $conn, $path );
		if ( ! is_array( $list ) ) {
			return false;
		}

		foreach ( $list as $item ) {
			$name = basename( str_replace( '\\', '/', (string) $item ) );
			if ( '' === $name || '.' === $name || '..' === $name ) {
				continue;
			}

			$child = rtrim( $path, '/' ) . '/' . $name;
			$raw   = (int) @ftp_size( $conn, $child );

			if ( $raw >= 0 ) {
				$size += $raw;
				$mtime = (int) @ftp_mdtm( $conn, $child );
				if ( $mtime > $last_mtime ) {
					$last_mtime = $mtime;
				}
				continue;
			}

			if ( ! self::scan_directory( $conn, $child, $size, $last_mtime, $depth + 1 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $entry Entrée MLSD.
	 * @param resource             $conn  Connexion FTP.
	 * @param string               $path  Chemin fichier.
	 * @return int
	 */
	private static function entry_mtime( array $entry, $conn, $path ) {
		if ( ! empty( $entry['modify'] ) && preg_match( '/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', (string) $entry['modify'], $m ) ) {
			return (int) gmmktime( (int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1] );
		}

		return (int) @ftp_mdtm( $conn, $path );
	}

	/**
	 * @return void
	 */
	public static function ajax_test() {
		self::verify_ajax();

		$test = self::test_connection();
		if ( empty( $test['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $test['message'] ?? __( 'Connexion impossible.', 'mainwp-giweb' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => $test['message'],
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_verify_all() {
		self::verify_ajax();

		global $mainwp_giweb_activator;
		$summary = self::verify_all_sites( $mainwp_giweb_activator ?? null );

		wp_send_json_success(
			array(
				'summary' => $summary,
				'message' => $summary['message'],
				'sites'   => $summary['sites'],
			)
		);
	}

	/**
	 * @return void
	 */
	private static function verify_ajax() {
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'mainwp-giweb' ) ), 403 );
		}
		check_ajax_referer( MainWP_GIWeb_Sync_Ajax::NONCE_ACTION, 'nonce' );
	}
}

MainWP_GIWeb_Ftp_Backup::init();
