<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshots, diffs et heuristiques réseau pour la détection de compromission.
 *
 * @since 2.29.9
 */
class Gi_Toolkit_Compromise_Detection_Monitor {

	const OPTION_SNAPSHOT = 'gi_toolkit_compromise_snapshot';

	const TRANSIENT_OUTBOUND = 'gi_toolkit_compromise_outbound';

	const TRANSIENT_LOGINS = 'gi_toolkit_compromise_logins';

	const PHP_UPLOADS_CAP = 200;

	const OUTBOUND_WINDOW = 300;

	const OUTBOUND_HOST_THRESHOLD = 3;

	const LOGIN_SPIKE_THRESHOLD = 25;

	/**
	 * @return array<string, mixed>
	 */
	public static function build_snapshot() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$admins     = array();
		$privileged = array();
		$passwords  = array();

		foreach ( self::get_privileged_users() as $user ) {
			$uid                    = (int) $user->ID;
			$roles                  = is_array( $user->roles ) ? array_values( $user->roles ) : array();
			$privileged[ $uid ]     = array(
				'login' => (string) $user->user_login,
				'roles' => $roles,
			);
			$passwords[ $uid ]      = (string) $user->user_pass;
			if ( in_array( 'administrator', $roles, true ) || user_can( $user, 'manage_options' ) ) {
				$admins[ $uid ] = (string) $user->user_login;
			}
		}

		$pages = self::snapshot_page_ids();

		$plugins = array_keys( get_plugins() );
		sort( $plugins );

		$themes = array_keys( wp_get_themes() );
		sort( $themes );

		$mu = array();
		if ( function_exists( 'get_mu_plugins' ) ) {
			$mu = array_keys( get_mu_plugins() );
			sort( $mu );
		}

		$dropins = array();
		if ( function_exists( '_get_dropins' ) ) {
			foreach ( array_keys( _get_dropins() ) as $file ) {
				if ( file_exists( WP_CONTENT_DIR . '/' . $file ) ) {
					$dropins[] = $file;
				}
			}
			sort( $dropins );
		}

		return array(
			'taken_at'           => time(),
			'admins'             => $admins,
			'privileged'         => $privileged,
			'passwords'          => $passwords,
			'pages'              => $pages,
			'plugins'            => $plugins,
			'themes'             => $themes,
			'mu_plugins'         => $mu,
			'dropins'            => $dropins,
			'php_uploads'        => self::find_php_in_uploads(),
			'siteurl'            => (string) get_option( 'siteurl' ),
			'home'               => (string) get_option( 'home' ),
			'admin_email'        => (string) get_option( 'admin_email' ),
			'users_can_register' => (string) get_option( 'users_can_register' ),
			'wp_config_hash'     => self::file_hash( self::wp_config_path() ),
			'htaccess_hash'      => self::file_hash( ABSPATH . '.htaccess' ),
			'index_hash'         => self::file_hash( ABSPATH . 'index.php' ),
			'file_contents'      => self::snapshot_file_contents(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_snapshot() {
		$stored = get_option( self::OPTION_SNAPSHOT, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return void
	 */
	public static function save_snapshot( $snapshot ) {
		update_option( self::OPTION_SNAPSHOT, $snapshot, false );
	}

	/**
	 * Compare deux snapshots et retourne des alertes structurées.
	 *
	 * @param array<string, mixed> $old Ancien.
	 * @param array<string, mixed> $new Nouveau.
	 * @return array<int, array{type:string, summary:string, details:string}>
	 */
	public static function diff_snapshots( $old, $new ) {
		$alerts = array();
		if ( empty( $old ) ) {
			return $alerts;
		}

		$old_admins = isset( $old['admins'] ) && is_array( $old['admins'] ) ? $old['admins'] : array();
		$new_admins = isset( $new['admins'] ) && is_array( $new['admins'] ) ? $new['admins'] : array();
		$added_adm  = array_diff_key( $new_admins, $old_admins );
		if ( ! empty( $added_adm ) ) {
			$names   = array();
			$context = array();
			foreach ( $added_adm as $id => $login ) {
				$names[] = $login . ' (#' . (int) $id . ')';
				$context = array_merge( $context, self::user_fact_rows( (int) $id, (string) $login ) );
			}
			$alerts[] = array(
				'type'    => 'watch_admin_user',
				'summary' => __( 'Nouvel utilisateur administrateur détecté', 'gi-toolkit' ),
				'details' => implode( ', ', $names ),
				'context' => $context,
			);
		}

		$old_priv = isset( $old['privileged'] ) && is_array( $old['privileged'] ) ? $old['privileged'] : array();
		$new_priv = isset( $new['privileged'] ) && is_array( $new['privileged'] ) ? $new['privileged'] : array();
		foreach ( $new_priv as $uid => $info ) {
			$login     = isset( $info['login'] ) ? (string) $info['login'] : '#' . $uid;
			$new_roles = isset( $info['roles'] ) && is_array( $info['roles'] ) ? $info['roles'] : array();
			if ( ! isset( $old_priv[ $uid ] ) ) {
				if ( ! isset( $added_adm[ $uid ] ) ) {
					$alerts[] = array(
						'type'    => 'watch_role_elevation',
						'summary' => __( 'Nouvel utilisateur privilégié', 'gi-toolkit' ),
						'details' => $login . ' — ' . implode( ', ', $new_roles ),
						'context' => array_merge(
							self::user_fact_rows( (int) $uid, $login ),
							array( self::fact_row( __( 'Rôles', 'gi-toolkit' ), implode( ', ', $new_roles ) ) )
						),
					);
				}
				continue;
			}
			$old_roles = isset( $old_priv[ $uid ]['roles'] ) && is_array( $old_priv[ $uid ]['roles'] ) ? $old_priv[ $uid ]['roles'] : array();
			$gained    = array_diff( $new_roles, $old_roles );
			if ( ! empty( $gained ) ) {
				$alerts[] = array(
					'type'    => 'watch_role_elevation',
					'summary' => __( 'Élévation de privilèges', 'gi-toolkit' ),
					'details' => $login . ' : +' . implode( ', ', $gained ),
					'context' => array_merge(
						self::user_fact_rows( (int) $uid, $login ),
						array(
							self::fact_row( __( 'Anciens rôles', 'gi-toolkit' ), implode( ', ', $old_roles ) ),
							self::fact_row( __( 'Rôles ajoutés', 'gi-toolkit' ), implode( ', ', $gained ) ),
						)
					),
				);
			}
		}

		$removed_adm = array_diff_key( $old_admins, $new_admins );
		if ( ! empty( $removed_adm ) ) {
			$names   = array();
			$context = array();
			foreach ( $removed_adm as $id => $login ) {
				$names[] = $login . ' (#' . (int) $id . ')';
				$context = array_merge( $context, self::user_fact_rows( (int) $id, (string) $login ) );
			}
			$alerts[] = array(
				'type'    => 'watch_user_deleted',
				'summary' => __( 'Administrateur supprimé ou rétrogradé', 'gi-toolkit' ),
				'details' => implode( ', ', $names ),
				'context' => $context,
			);
		}

		$old_pass = isset( $old['passwords'] ) && is_array( $old['passwords'] ) ? $old['passwords'] : array();
		$new_pass = isset( $new['passwords'] ) && is_array( $new['passwords'] ) ? $new['passwords'] : array();
		$changed  = array();
		foreach ( $new_pass as $uid => $hash ) {
			if ( isset( $old_pass[ $uid ] ) && $old_pass[ $uid ] !== $hash ) {
				$login     = isset( $new_priv[ $uid ]['login'] ) ? $new_priv[ $uid ]['login'] : '#' . $uid;
				$changed[] = $login;
			}
		}
		if ( ! empty( $changed ) ) {
			$context = array();
			foreach ( $new_pass as $uid => $hash ) {
				if ( ! isset( $old_pass[ $uid ] ) || $old_pass[ $uid ] === $hash ) {
					continue;
				}
				$login    = isset( $new_priv[ $uid ]['login'] ) ? (string) $new_priv[ $uid ]['login'] : '#' . $uid;
				$context  = array_merge( $context, self::user_fact_rows( (int) $uid, $login ) );
			}
			$alerts[] = array(
				'type'    => 'watch_password',
				'summary' => __( 'Mot de passe modifié (compte privilégié)', 'gi-toolkit' ),
				'details' => implode( ', ', $changed ),
				'context' => $context,
			);
		}

		$old_pages = isset( $old['pages'] ) && is_array( $old['pages'] ) ? $old['pages'] : array();
		$new_pages = isset( $new['pages'] ) && is_array( $new['pages'] ) ? $new['pages'] : array();
		$added_p   = self::filter_new_page_ids( $old_pages, $new_pages, (int) ( $old['taken_at'] ?? 0 ) );
		$del_p     = self::filter_deleted_page_ids( $old_pages, $new_pages );
		if ( ! empty( $added_p ) ) {
			$context = array();
			foreach ( array_slice( array_values( $added_p ), 0, 10 ) as $pid ) {
				$context = array_merge( $context, self::page_fact_rows( $pid ) );
			}
			$alerts[] = array(
				'type'    => 'watch_pages',
				'summary' => __( 'Page(s) ajoutée(s)', 'gi-toolkit' ),
				'details' => self::page_titles( $added_p ),
				'context' => $context,
			);
		}
		if ( ! empty( $del_p ) ) {
			$alerts[] = array(
				'type'    => 'watch_pages',
				'summary' => __( 'Page(s) supprimée(s)', 'gi-toolkit' ),
				'details' => __( 'IDs : ', 'gi-toolkit' ) . implode( ', ', array_map( 'intval', $del_p ) ),
				'context' => array(
					self::fact_row( __( 'IDs supprimés', 'gi-toolkit' ), implode( ', ', array_map( 'intval', $del_p ) ) ),
				),
			);
		}

		$old_plugins = isset( $old['plugins'] ) && is_array( $old['plugins'] ) ? $old['plugins'] : array();
		$new_plugins = isset( $new['plugins'] ) && is_array( $new['plugins'] ) ? $new['plugins'] : array();
		$added_pl    = array_diff( $new_plugins, $old_plugins );
		$removed_pl  = array_diff( $old_plugins, $new_plugins );
		if ( ! empty( $added_pl ) ) {
			$alerts[] = array(
				'type'    => 'watch_plugins_themes',
				'summary' => __( 'Extension(s) ajoutée(s)', 'gi-toolkit' ),
				'details' => implode( ', ', $added_pl ),
				'context' => self::plugin_fact_rows( $added_pl ),
			);
		}
		if ( ! empty( $removed_pl ) ) {
			$alerts[] = array(
				'type'    => 'watch_plugins_themes',
				'summary' => __( 'Extension(s) supprimée(s)', 'gi-toolkit' ),
				'details' => implode( ', ', $removed_pl ),
				'context' => self::plugin_fact_rows( $removed_pl ),
			);
		}

		$old_themes = isset( $old['themes'] ) && is_array( $old['themes'] ) ? $old['themes'] : array();
		$new_themes = isset( $new['themes'] ) && is_array( $new['themes'] ) ? $new['themes'] : array();
		$added_th   = array_diff( $new_themes, $old_themes );
		$removed_th = array_diff( $old_themes, $new_themes );
		if ( ! empty( $added_th ) ) {
			$alerts[] = array(
				'type'    => 'watch_plugins_themes',
				'summary' => __( 'Thème(s) ajouté(s)', 'gi-toolkit' ),
				'details' => implode( ', ', $added_th ),
				'context' => self::theme_fact_rows( $added_th ),
			);
		}
		if ( ! empty( $removed_th ) ) {
			$alerts[] = array(
				'type'    => 'watch_plugins_themes',
				'summary' => __( 'Thème(s) supprimé(s)', 'gi-toolkit' ),
				'details' => implode( ', ', $removed_th ),
				'context' => self::theme_fact_rows( $removed_th ),
			);
		}

		$old_mu = isset( $old['mu_plugins'] ) && is_array( $old['mu_plugins'] ) ? $old['mu_plugins'] : array();
		$new_mu = isset( $new['mu_plugins'] ) && is_array( $new['mu_plugins'] ) ? $new['mu_plugins'] : array();
		$mu_add = array_diff( $new_mu, $old_mu );
		$mu_del = array_diff( $old_mu, $new_mu );
		if ( ! empty( $mu_add ) ) {
			$abs = array();
			foreach ( $mu_add as $file ) {
				$abs[] = WPMU_PLUGIN_DIR . '/' . $file;
			}
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Must-use plugin ajouté', 'gi-toolkit' ),
				'details' => implode( ', ', $mu_add ),
				'context' => self::path_fact_rows( $mu_add, __( 'Fichier MU', 'gi-toolkit' ) ),
				'preview' => self::files_preview( $abs ),
			);
		}
		if ( ! empty( $mu_del ) ) {
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Must-use plugin supprimé', 'gi-toolkit' ),
				'details' => implode( ', ', $mu_del ),
				'context' => self::path_fact_rows( $mu_del, __( 'Fichier MU', 'gi-toolkit' ) ),
			);
		}

		$old_drop = isset( $old['dropins'] ) && is_array( $old['dropins'] ) ? $old['dropins'] : array();
		$new_drop = isset( $new['dropins'] ) && is_array( $new['dropins'] ) ? $new['dropins'] : array();
		$drop_add = array_diff( $new_drop, $old_drop );
		$drop_del = array_diff( $old_drop, $new_drop );
		if ( ! empty( $drop_add ) ) {
			$abs = array();
			foreach ( $drop_add as $file ) {
				$abs[] = WP_CONTENT_DIR . '/' . $file;
			}
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Drop-in WordPress ajouté', 'gi-toolkit' ),
				'details' => implode( ', ', $drop_add ),
				'context' => self::path_fact_rows( $drop_add, __( 'Drop-in', 'gi-toolkit' ) ),
				'preview' => self::files_preview( $abs ),
			);
		}
		if ( ! empty( $drop_del ) ) {
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Drop-in WordPress supprimé', 'gi-toolkit' ),
				'details' => implode( ', ', $drop_del ),
				'context' => self::path_fact_rows( $drop_del, __( 'Drop-in', 'gi-toolkit' ) ),
			);
		}

		$old_php = isset( $old['php_uploads'] ) && is_array( $old['php_uploads'] ) ? $old['php_uploads'] : array();
		$new_php = isset( $new['php_uploads'] ) && is_array( $new['php_uploads'] ) ? $new['php_uploads'] : array();
		$php_add = array_diff( $new_php, $old_php );
		if ( ! empty( $php_add ) ) {
			$uploads = wp_upload_dir( null, false );
			$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
			$abs     = array();
			foreach ( $php_add as $rel ) {
				$abs[] = $base . '/' . ltrim( (string) $rel, '/\\' );
			}
			$alerts[] = array(
				'type'    => 'watch_php_uploads',
				'summary' => __( 'Fichier(s) PHP détecté(s) dans les uploads', 'gi-toolkit' ),
				'details' => implode( ', ', array_slice( $php_add, 0, 20 ) ),
				'context' => self::path_fact_rows( $php_add, __( 'Fichier', 'gi-toolkit' ) ),
				'preview' => self::files_preview( $abs ),
			);
		}

		foreach ( array(
			'siteurl'            => __( 'URL du site (siteurl) modifiée', 'gi-toolkit' ),
			'home'               => __( 'URL d’accueil (home) modifiée', 'gi-toolkit' ),
			'admin_email'        => __( 'E-mail administrateur modifié', 'gi-toolkit' ),
			'users_can_register' => __( 'Option « inscription ouverte » modifiée', 'gi-toolkit' ),
		) as $key => $label ) {
			$ov = isset( $old[ $key ] ) ? (string) $old[ $key ] : '';
			$nv = isset( $new[ $key ] ) ? (string) $new[ $key ] : '';
			if ( $ov !== $nv ) {
				$alerts[] = array(
					'type'    => 'watch_site_options',
					'summary' => $label,
					'details' => $ov . ' → ' . $nv,
					'context' => array(
						self::fact_row( __( 'Ancienne valeur', 'gi-toolkit' ), $ov ),
						self::fact_row( __( 'Nouvelle valeur', 'gi-toolkit' ), $nv ),
					),
				);
			}
		}

		foreach ( self::sensitive_files() as $key => $meta ) {
			$ov = isset( $old[ $key ] ) ? (string) $old[ $key ] : '';
			$nv = isset( $new[ $key ] ) ? (string) $new[ $key ] : '';
			if ( '' === $ov || $ov === $nv ) {
				continue;
			}
			$old_body = isset( $old['file_contents'][ $key ] ) ? (string) $old['file_contents'][ $key ] : '';
			$new_body = isset( $new['file_contents'][ $key ] ) ? (string) $new['file_contents'][ $key ] : '';
			if ( 'htaccess_hash' === $key && ! self::htaccess_change_is_suspicious( $old_body, $new_body ) ) {
				continue;
			}
			$diff = self::unified_diff( $old_body, $new_body, $meta['label'] );
			if ( '' === $diff ) {
				$diff = __( 'Le fichier a changé, mais le contenu précédent n’était pas encore enregistré. Les prochaines modifications afficheront un diff ligne à ligne.', 'gi-toolkit' );
			}
			$alerts[] = array(
				'type'    => 'watch_core_files',
				'summary' => sprintf(
					/* translators: %s: filename */
					__( 'Fichier sensible modifié : %s', 'gi-toolkit' ),
					$meta['label']
				),
				'details' => $meta['label'] . self::diff_stats_suffix( $diff ),
				'diff'    => $diff,
				'context' => array(
					self::fact_row( __( 'Fichier', 'gi-toolkit' ), $meta['label'] ),
					self::fact_row( __( 'Chemin', 'gi-toolkit' ), $meta['path'] ),
				),
			);
		}

		return $alerts;
	}

	/**
	 * Fichiers sensibles suivis (clé de hash => meta).
	 *
	 * @return array<string, array{label:string, path:string, redact:bool}>
	 */
	public static function sensitive_files() {
		return array(
			'wp_config_hash' => array(
				'label'  => 'wp-config.php',
				'path'   => self::wp_config_path(),
				'redact' => true,
			),
			'htaccess_hash'  => array(
				'label'  => '.htaccess',
				'path'   => ABSPATH . '.htaccess',
				'redact' => false,
			),
			'index_hash'     => array(
				'label'  => 'index.php (racine)',
				'path'   => ABSPATH . 'index.php',
				'redact' => false,
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function snapshot_file_contents() {
		$out = array();
		foreach ( self::sensitive_files() as $key => $meta ) {
			$out[ $key ] = self::read_file_for_snapshot( $meta['path'], ! empty( $meta['redact'] ) );
		}
		return $out;
	}

	/**
	 * Diff unifié ligne à ligne.
	 *
	 * @param string $old      Ancien contenu.
	 * @param string $new      Nouveau contenu.
	 * @param string $filename Nom affiché.
	 * @return string
	 */
	public static function unified_diff( $old, $new, $filename = '' ) {
		$old = str_replace( array( "\r\n", "\r" ), "\n", (string) $old );
		$new = str_replace( array( "\r\n", "\r" ), "\n", (string) $new );
		if ( $old === $new ) {
			return '';
		}

		$a = explode( "\n", $old );
		$b = explode( "\n", $new );
		$n = count( $a );
		$m = count( $b );

		$lines = array();
		if ( '' !== $filename ) {
			$lines[] = '--- a/' . $filename;
			$lines[] = '+++ b/' . $filename;
		}

		if ( $n > 400 || $m > 400 || ( $n * $m ) > 80000 ) {
			$removed = array_values( array_diff( $a, $b ) );
			$added   = array_values( array_diff( $b, $a ) );
			foreach ( array_slice( $removed, 0, 80 ) as $line ) {
				$lines[] = '- ' . $line;
			}
			foreach ( array_slice( $added, 0, 80 ) as $line ) {
				$lines[] = '+ ' . $line;
			}
			if ( count( $removed ) > 80 || count( $added ) > 80 ) {
				$lines[] = '… (diff tronqué)';
			}
			return implode( "\n", $lines );
		}

		$lcs = array();
		for ( $i = 0; $i <= $n; $i++ ) {
			$lcs[ $i ] = array_fill( 0, $m + 1, 0 );
		}
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				if ( $a[ $i ] === $b[ $j ] ) {
					$lcs[ $i ][ $j ] = $lcs[ $i + 1 ][ $j + 1 ] + 1;
				} else {
					$lcs[ $i ][ $j ] = max( $lcs[ $i + 1 ][ $j ], $lcs[ $i ][ $j + 1 ] );
				}
			}
		}

		$i = 0;
		$j = 0;
		while ( $i < $n && $j < $m ) {
			if ( $a[ $i ] === $b[ $j ] ) {
				$lines[] = '  ' . $a[ $i ];
				++$i;
				++$j;
			} elseif ( $lcs[ $i + 1 ][ $j ] >= $lcs[ $i ][ $j + 1 ] ) {
				$lines[] = '- ' . $a[ $i ];
				++$i;
			} else {
				$lines[] = '+ ' . $b[ $j ];
				++$j;
			}
		}
		while ( $i < $n ) {
			$lines[] = '- ' . $a[ $i ];
			++$i;
		}
		while ( $j < $m ) {
			$lines[] = '+ ' . $b[ $j ];
			++$j;
		}

		if ( count( $lines ) > 450 ) {
			$lines   = array_slice( $lines, 0, 450 );
			$lines[] = '… (diff tronqué)';
		}

		return implode( "\n", $lines );
	}

	/**
	 * @param string $diff Diff.
	 * @return string
	 */
	public static function diff_stats_suffix( $diff ) {
		$add = 0;
		$del = 0;
		foreach ( explode( "\n", (string) $diff ) as $line ) {
			if ( isset( $line[0] ) && '+' === $line[0] && 0 !== strpos( $line, '+++' ) ) {
				++$add;
			} elseif ( isset( $line[0] ) && '-' === $line[0] && 0 !== strpos( $line, '---' ) ) {
				++$del;
			}
		}
		if ( ! $add && ! $del ) {
			return '';
		}
		return sprintf( ' (+%d / −%d)', $add, $del );
	}

	/**
	 * Inspecte une requête HTTP sortante (API WordPress).
	 *
	 * @param mixed                $preempt Préemption.
	 * @param array<string, mixed> $args    Arguments.
	 * @param string               $url     URL.
	 * @return mixed
	 */
	public static function inspect_http_request( $preempt, $args, $url ) {
		if ( Gi_Toolkit_Compromise_Detection_Pushover::$sending ) {
			return $preempt;
		}
		if ( ! is_string( $url ) || '' === $url ) {
			return $preempt;
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return $preempt;
		}

		$host = strtolower( (string) $parsed['host'] );
		$path = isset( $parsed['path'] ) ? strtolower( (string) $parsed['path'] ) : '';
		$port = isset( $parsed['port'] ) ? (int) $parsed['port'] : 0;

		if ( self::is_allowed_host( $host ) ) {
			return $preempt;
		}

		$suspicious = false;
		$reason     = '';

		$probe_needles = array(
			'wp-login.php',
			'xmlrpc.php',
			'wp-cron.php',
			'/wp-admin',
			'/wp-json/wp/v2/users',
			'wlwmanifest.xml',
			'readme.html',
			'/administrator',
			'wp-config.php',
		);
		foreach ( $probe_needles as $needle ) {
			if ( false !== strpos( $path, $needle ) ) {
				$suspicious = true;
				$reason     = 'probe:' . $needle;
				break;
			}
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$suspicious = true;
			$reason     = $reason ? $reason : 'ip-host';
		}

		if ( $port && ! in_array( $port, array( 80, 443, 8080, 8443 ), true ) ) {
			$suspicious = true;
			$reason     = $reason ? $reason : 'port:' . $port;
		}

		$ua = '';
		if ( isset( $args['user-agent'] ) ) {
			$ua = strtolower( (string) $args['user-agent'] );
		}
		foreach ( array( 'sqlmap', 'nikto', 'masscan', 'nmap', 'wpscan', 'dirbuster', 'gobuster' ) as $scanner ) {
			if ( '' !== $ua && false !== strpos( $ua, $scanner ) ) {
				$suspicious = true;
				$reason     = 'ua:' . $scanner;
				break;
			}
		}

		if ( ! $suspicious ) {
			return $preempt;
		}

		$bucket = get_transient( self::TRANSIENT_OUTBOUND );
		if ( ! is_array( $bucket ) ) {
			$bucket = array(
				'hosts'  => array(),
				'hits'   => array(),
				'since'  => time(),
			);
		}

		$bucket['hosts'][ $host ] = time();
		$bucket['hits'][]         = array(
			'host'   => $host,
			'url'    => $url,
			'reason' => $reason,
			'at'     => time(),
		);
		if ( count( $bucket['hits'] ) > 40 ) {
			$bucket['hits'] = array_slice( $bucket['hits'], -40 );
		}

		set_transient( self::TRANSIENT_OUTBOUND, $bucket, self::OUTBOUND_WINDOW );

		$immediate = ( 0 === strpos( $reason, 'probe:' ) || 0 === strpos( $reason, 'ua:' ) );
		$burst     = count( $bucket['hosts'] ) >= self::OUTBOUND_HOST_THRESHOLD;

		if ( $immediate || $burst ) {
			$details = sprintf(
				'%s — %s (%s)',
				$host,
				$url,
				$reason
			);
			if ( $burst ) {
				$details .= ' | ' . sprintf(
					/* translators: %d: unique host count */
					__( '%d hôtes suspects en 5 min', 'gi-toolkit' ),
					count( $bucket['hosts'] )
				);
			}
			$context = array(
				self::fact_row( __( 'Hôte', 'gi-toolkit' ), $host ),
				self::fact_row( __( 'URL', 'gi-toolkit' ), $url ),
				self::fact_row( __( 'Motif', 'gi-toolkit' ), $reason ),
			);
			$method = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET';
			$context[] = self::fact_row( __( 'Méthode', 'gi-toolkit' ), $method );
			if ( $burst ) {
				$context[] = self::fact_row(
					__( 'Hôtes suspects (5 min)', 'gi-toolkit' ),
					implode( ', ', array_keys( $bucket['hosts'] ) )
				);
			}
			Gi_Toolkit_Compromise_Detection::raise_alert(
				'watch_outbound',
				sprintf(
					/* translators: %s: hostname */
					__( 'Requêtes sortantes suspectes : %s', 'gi-toolkit' ),
					$host
				),
				$details,
				array(
					'context' => $context,
					'host'    => $host,
				)
			);
		}

		return $preempt;
	}

	/**
	 * @param string $username Login tenté.
	 * @return void
	 */
	public static function record_failed_login( $username ) {
		$bucket = get_transient( self::TRANSIENT_LOGINS );
		if ( ! is_array( $bucket ) ) {
			$bucket = array(
				'count' => 0,
				'users' => array(),
			);
		}
		$bucket['count']++;
		$user = sanitize_user( (string) $username, true );
		if ( '' !== $user ) {
			$bucket['users'][ $user ] = isset( $bucket['users'][ $user ] ) ? $bucket['users'][ $user ] + 1 : 1;
		}
		$ip = self::client_ip();
		if ( '' !== $ip ) {
			if ( ! isset( $bucket['ips'] ) || ! is_array( $bucket['ips'] ) ) {
				$bucket['ips'] = array();
			}
			$bucket['ips'][ $ip ] = isset( $bucket['ips'][ $ip ] ) ? $bucket['ips'][ $ip ] + 1 : 1;
		}
		set_transient( self::TRANSIENT_LOGINS, $bucket, MINUTE_IN_SECONDS );

		if ( $bucket['count'] >= self::LOGIN_SPIKE_THRESHOLD ) {
			$top = $bucket['users'];
			arsort( $top );
			$top = array_slice( $top, 0, 5, true );
			$bits = array();
			foreach ( $top as $login => $n ) {
				$bits[] = $login . '×' . (int) $n;
			}
			$ip_bits = array();
			if ( ! empty( $bucket['ips'] ) && is_array( $bucket['ips'] ) ) {
				$top_ips = $bucket['ips'];
				arsort( $top_ips );
				foreach ( array_slice( $top_ips, 0, 8, true ) as $addr => $n ) {
					$ip_bits[] = $addr . '×' . (int) $n;
				}
			}
			$context = array(
				self::fact_row( __( 'Échecs (1 min)', 'gi-toolkit' ), (string) (int) $bucket['count'] ),
				self::fact_row( __( 'Logins visés', 'gi-toolkit' ), implode( ', ', $bits ) ),
			);
			if ( ! empty( $ip_bits ) ) {
				$context[] = self::fact_row( __( 'Adresses IP', 'gi-toolkit' ), implode( ', ', $ip_bits ) );
			}
			Gi_Toolkit_Compromise_Detection::raise_alert(
				'watch_login_spike',
				__( 'Pic de connexions échouées', 'gi-toolkit' ),
				sprintf(
					/* translators: 1: count, 2: usernames */
					__( '%1$d échecs en 1 minute (%2$s)', 'gi-toolkit' ),
					(int) $bucket['count'],
					implode( ', ', $bits )
				),
				array( 'context' => $context )
			);
			delete_transient( self::TRANSIENT_LOGINS );
		}
	}

	/**
	 * @return WP_User[]
	 */
	public static function get_privileged_users() {
		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'editor', 'shop_manager' ),
				'fields'   => 'all',
				'number'   => 500,
			)
		);
		if ( ! is_array( $users ) ) {
			$users = array();
		}

		$by_id = array();
		foreach ( $users as $user ) {
			$by_id[ (int) $user->ID ] = $user;
		}

		$caps = array( 'manage_options', 'edit_users', 'install_plugins', 'edit_themes' );
		foreach ( $caps as $cap ) {
			$extra = get_users(
				array(
					'capability' => $cap,
					'fields'     => 'all',
					'number'     => 200,
				)
			);
			if ( is_array( $extra ) ) {
				foreach ( $extra as $user ) {
					$by_id[ (int) $user->ID ] = $user;
				}
			}
		}

		return array_values( $by_id );
	}

	/**
	 * @return string[]
	 */
	public static function find_php_in_uploads() {
		$uploads = wp_upload_dir( null, false );
		$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		if ( '' === $base || ! is_dir( $base ) ) {
			return array();
		}

		$found = array();
		self::scan_dir_for_php( $base, $base, $found, 0 );
		sort( $found );
		return $found;
	}

	/**
	 * @param string   $dir     Répertoire courant.
	 * @param string   $root    Racine uploads.
	 * @param string[] $found   Accumulé.
	 * @param int      $depth   Profondeur.
	 * @return void
	 */
	private static function scan_dir_for_php( $dir, $root, &$found, $depth ) {
		if ( $depth > 14 || count( $found ) >= self::PHP_UPLOADS_CAP ) {
			return;
		}

		$items = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $items ) ) {
			return;
		}

		$php_ext = array( 'php', 'phtml', 'php5', 'php7', 'php8', 'phar', 'php3', 'php4' );

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				self::scan_dir_for_php( $path, $root, $found, $depth + 1 );
				continue;
			}
			$ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, $php_ext, true ) ) {
				$rel     = ltrim( str_replace( $root, '', $path ), '/\\' );
				$found[] = str_replace( '\\', '/', $rel );
				if ( count( $found ) >= self::PHP_UPLOADS_CAP ) {
					return;
				}
			}
		}
	}

	/**
	 * @param string $host Hôte.
	 * @return bool
	 */
	public static function is_allowed_host( $host ) {
		$host = strtolower( $host );

		foreach ( self::user_whitelist() as $allowed ) {
			if ( self::host_matches_allowed( $host, $allowed ) ) {
				return true;
			}
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';
		if ( '' !== $site_host ) {
			if ( $host === $site_host || $host === 'www.' . $site_host || 'www.' . $host === $site_host ) {
				return true;
			}
		}

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		$exact = array(
			'api.wordpress.org',
			'downloads.wordpress.org',
			'wordpress.com',
			'api.github.com',
			'api.pushover.net',
			'fonts.googleapis.com',
			'fonts.gstatic.com',
			'www.google.com',
			'www.googleapis.com',
			'oauth2.googleapis.com',
			'accounts.google.com',
			'graph.microsoft.com',
			'login.microsoftonline.com',
			'api.sendgrid.com',
			'api.mailgun.net',
			'api.brevo.com',
			'api.stripe.com',
			'hooks.stripe.com',
			'cdn.jsdelivr.net',
			'cdnjs.cloudflare.com',
			'unpkg.com',
			'code.jquery.com',
			'noc1.wordfence.com',
			'noc2.wordfence.com',
			'api.wordfence.com',
			'wp-rocket.me',
		);
		if ( in_array( $host, $exact, true ) ) {
			return true;
		}

		$suffixes = array(
			'.wordpress.org',
			'.w.org',
			'.wp.com',
			'.wordpress.com',
			'.gravatar.com',
			'.googleapis.com',
			'.google.com',
			'.gstatic.com',
			'.google-analytics.com',
			'.googletagmanager.com',
			'.recaptcha.net',
			'.github.com',
			'.githubusercontent.com',
			'.pushover.net',
			'.cloudflare.com',
			'.cloudflareinsights.com',
			'.cloudfront.net',
			'.amazonaws.com',
			'.woocommerce.com',
			'.wpmudev.com',
			'.mainwp.com',
			'.elementor.com',
			'.elementor.cloud',
			'.wp-rocket.me',
			'.wp-media.me',
			'.imagify.io',
			'.wordfence.com',
			'.yoast.com',
			'.rankmath.com',
			'.jetpack.com',
			'.automattic.com',
			'.akismet.com',
			'.gravity.com',
			'.gravityforms.com',
			'.wpml.org',
			'.polylang.pro',
			'.wpforms.com',
			'.ninjaforms.com',
			'.fluentforms.com',
			'.advancedcustomfields.com',
			'.deliciousbrains.com',
			'.updraftplus.com',
			'.blogvault.net',
			'.malcare.com',
			'.really-simple-ssl.com',
			'.ithemes.com',
			'.solidwp.com',
			'.patchstack.com',
			'.sucuri.net',
			'.wpengine.com',
			'.wpenginepowered.com',
			'.kinsta.com',
			'.siteground.com',
			'.sgvps.net',
			'.cloudways.com',
			'.o2switch.fr',
			'.infomaniak.com',
			'.ovh.net',
			'.ovh.com',
			'.hostinger.com',
			'.litespeedtech.com',
			'.quic.cloud',
			'.paypal.com',
			'.stripe.com',
			'.mollie.com',
			'.klarna.com',
			'.brevo.com',
			'.sendinblue.com',
			'.mailchimp.com',
			'.hubspot.com',
			'.intercom.io',
			'.crisp.chat',
			'.zendesk.com',
			'.helpscout.net',
			'.facebook.com',
			'.facebook.net',
			'.fontawesome.com',
			'.typekit.net',
			'.adobe.com',
			'.hotjar.com',
			'.clarity.ms',
			'.sentry.io',
			'.datadoghq.com',
			'.newrelic.com',
			'.matomo.org',
			'.matomo.cloud',
			'.innocraft.cloud',
			'.cookiebot.com',
			'.iubenda.com',
			'.tarteaucitron.io',
			'.tinymce.com',
			'.tiny.cloud',
			'.bootstrapcdn.com',
			'.maxcdn.com',
			'.jsdelivr.net',
			'.genevois-informatique.com',
		);
		foreach ( $suffixes as $suffix ) {
			$bare = ltrim( $suffix, '.' );
			if ( $host === $bare || substr( $host, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		if ( class_exists( 'Gi_Toolkit_Matomo' ) ) {
			$matomo = Gi_Toolkit_Matomo::get_settings_static();
			if ( ! empty( $matomo['matomo_url'] ) ) {
				$mh = wp_parse_url( (string) $matomo['matomo_url'], PHP_URL_HOST );
				if ( is_string( $mh ) && strtolower( $mh ) === $host ) {
					return true;
				}
			}
		}
		if ( class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			$kuma = Gi_Toolkit_Uptime_Kuma::get_settings_static();
			if ( ! empty( $kuma['kuma_url'] ) ) {
				$kh = wp_parse_url( (string) $kuma['kuma_url'], PHP_URL_HOST );
				if ( is_string( $kh ) && strtolower( $kh ) === $host ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param string $host Hôte brut.
	 * @return string
	 */
	public static function sanitize_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = preg_replace( '#^https?://#', '', $host );
		$host = is_string( $host ) ? $host : '';
		$host = preg_replace( '#/.*$#', '', $host );
		$host = is_string( $host ) ? $host : '';
		$host = preg_replace( '/:\d+$/', '', $host );
		$host = is_string( $host ) ? $host : '';
		$host = preg_replace( '/^\*\./', '', $host );
		$host = is_string( $host ) ? $host : '';
		$host = trim( $host, '.' );
		if ( '' === $host || strlen( $host ) > 253 ) {
			return '';
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $host;
		}
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host ) ) {
			return '';
		}
		return $host;
	}

	/**
	 * @param string $host    Hôte testé.
	 * @param string $allowed Domaine autorisé.
	 * @return bool
	 */
	public static function host_matches_allowed( $host, $allowed ) {
		$host    = strtolower( (string) $host );
		$allowed = strtolower( (string) $allowed );
		if ( '' === $host || '' === $allowed ) {
			return false;
		}
		if ( $host === $allowed ) {
			return true;
		}
		$suffix = '.' . $allowed;
		return substr( $host, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * @return string[]
	 */
	public static function user_whitelist() {
		if ( ! class_exists( 'Gi_Toolkit_Compromise_Detection' ) ) {
			return array();
		}
		$settings = Gi_Toolkit_Compromise_Detection::read_settings();
		$list     = isset( $settings['outbound_whitelist'] ) ? $settings['outbound_whitelist'] : array();
		return Gi_Toolkit_Compromise_Detection::sanitize_whitelist_list( $list );
	}

	/**
	 * @param string $label Label.
	 * @param string $value Valeur.
	 * @param string $url   Lien optionnel.
	 * @return array{label:string,value:string,url?:string}
	 */
	public static function fact_row( $label, $value, $url = '' ) {
		$row = array(
			'label' => (string) $label,
			'value' => (string) $value,
		);
		if ( '' !== $url ) {
			$row['url'] = (string) $url;
		}
		return $row;
	}

	/**
	 * @param int    $user_id        ID.
	 * @param string $login_fallback Login si l’utilisateur n’existe plus.
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	public static function user_fact_rows( $user_id, $login_fallback = '' ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return array(
				self::fact_row( __( 'Identifiant', 'gi-toolkit' ), $login_fallback ? $login_fallback : '#' . $user_id ),
				self::fact_row( __( 'ID utilisateur', 'gi-toolkit' ), (string) $user_id ),
			);
		}
		$edit = admin_url( 'user-edit.php?user_id=' . $user_id );
		return array(
			self::fact_row( __( 'Identifiant', 'gi-toolkit' ), (string) $user->user_login ),
			self::fact_row( __( 'ID utilisateur', 'gi-toolkit' ), (string) $user_id, $edit ),
			self::fact_row( __( 'E-mail', 'gi-toolkit' ), (string) $user->user_email ),
			self::fact_row( __( 'Rôles', 'gi-toolkit' ), implode( ', ', is_array( $user->roles ) ? $user->roles : array() ) ),
		);
	}

	/**
	 * @param int|WP_Post $post_or_id Page.
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	public static function page_fact_rows( $post_or_id ) {
		$post = $post_or_id instanceof WP_Post ? $post_or_id : get_post( (int) $post_or_id );
		if ( ! $post instanceof WP_Post ) {
			return array( self::fact_row( __( 'ID page', 'gi-toolkit' ), (string) (int) $post_or_id ) );
		}
		$edit = get_edit_post_link( $post->ID, 'raw' );
		$rows = array(
			self::fact_row( __( 'Titre', 'gi-toolkit' ), (string) $post->post_title ),
			self::fact_row( __( 'ID', 'gi-toolkit' ), (string) $post->ID, is_string( $edit ) ? $edit : '' ),
			self::fact_row( __( 'Statut', 'gi-toolkit' ), (string) $post->post_status ),
			self::fact_row( __( 'Slug', 'gi-toolkit' ), (string) $post->post_name ),
		);
		$link = get_permalink( $post );
		if ( is_string( $link ) && '' !== $link ) {
			$rows[] = self::fact_row( __( 'URL', 'gi-toolkit' ), $link, $link );
		}
		$author = get_userdata( (int) $post->post_author );
		if ( $author ) {
			$rows[] = self::fact_row( __( 'Auteur', 'gi-toolkit' ), $author->user_login . ' (#' . (int) $author->ID . ')' );
		}
		return $rows;
	}

	/**
	 * @param string[] $files Fichiers plugin.
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	public static function plugin_fact_rows( $files ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all  = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$rows = array();
		foreach ( (array) $files as $file ) {
			$file = (string) $file;
			if ( isset( $all[ $file ] ) ) {
				$name   = isset( $all[ $file ]['Name'] ) ? (string) $all[ $file ]['Name'] : $file;
				$ver    = isset( $all[ $file ]['Version'] ) ? (string) $all[ $file ]['Version'] : '';
				$rows[] = self::fact_row( __( 'Extension', 'gi-toolkit' ), $name . ( '' !== $ver ? ' ' . $ver : '' ) );
				$rows[] = self::fact_row( __( 'Fichier', 'gi-toolkit' ), $file );
			} else {
				$rows[] = self::fact_row( __( 'Extension', 'gi-toolkit' ), $file );
			}
		}
		return $rows;
	}

	/**
	 * @param string[] $slugs Slugs de thèmes.
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	public static function theme_fact_rows( $slugs ) {
		$rows = array();
		foreach ( (array) $slugs as $slug ) {
			$slug  = (string) $slug;
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				$rows[] = self::fact_row( __( 'Thème', 'gi-toolkit' ), (string) $theme->get( 'Name' ) . ' ' . (string) $theme->get( 'Version' ) );
				$rows[] = self::fact_row( __( 'Dossier', 'gi-toolkit' ), $slug );
			} else {
				$rows[] = self::fact_row( __( 'Thème', 'gi-toolkit' ), $slug );
			}
		}
		return $rows;
	}

	/**
	 * @param string[] $paths Chemins.
	 * @param string   $label Label.
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	public static function path_fact_rows( $paths, $label ) {
		$rows = array();
		foreach ( array_slice( (array) $paths, 0, 20 ) as $path ) {
			$rows[] = self::fact_row( $label, (string) $path );
		}
		return $rows;
	}

	/**
	 * @param string[] $abs_paths Chemins absolus.
	 * @return string
	 */
	public static function files_preview( $abs_paths ) {
		$chunks = array();
		foreach ( array_slice( (array) $abs_paths, 0, 3 ) as $path ) {
			$path = (string) $path;
			$body = self::read_file_preview( $path );
			if ( '' === $body ) {
				continue;
			}
			$chunks[] = '----- ' . $path . " -----\n" . $body;
		}
		return implode( "\n\n", $chunks );
	}

	/**
	 * @param string $path      Chemin.
	 * @param int    $max_lines Lignes max.
	 * @return string
	 */
	public static function read_file_preview( $path, $max_lines = 50 ) {
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $path );
		if ( ! is_string( $content ) ) {
			return '';
		}
		if ( strlen( $content ) > 20000 ) {
			$content = substr( $content, 0, 20000 );
		}
		$lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $content ) );
		$out   = implode( "\n", array_slice( $lines, 0, $max_lines ) );
		if ( count( $lines ) > $max_lines ) {
			$out .= "\n…";
		}
		return $out;
	}

	/**
	 * @return string
	 */
	public static function client_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	/**
	 * IDs des pages suivies, via SQL (sans filtres Polylang / pre_get_posts).
	 *
	 * @return int[]
	 */
	private static function snapshot_page_ids() {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN (%s,%s,%s) ORDER BY ID ASC",
				'page',
				'publish',
				'private',
				'future'
			)
		);
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$ids = array_map( 'intval', $ids );
		$ids = array_values( array_filter( $ids ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/**
	 * Nouvelles pages réellement créées (ignore brouillons, corbeille, pages déjà anciennes).
	 *
	 * @param int[] $old_pages Anciens IDs.
	 * @param int[] $new_pages Nouveaux IDs.
	 * @param int   $taken_at  Horodatage du snapshot précédent.
	 * @return int[]
	 */
	private static function filter_new_page_ids( $old_pages, $new_pages, $taken_at ) {
		$added = array_diff(
			array_map( 'intval', is_array( $new_pages ) ? $new_pages : array() ),
			array_map( 'intval', is_array( $old_pages ) ? $old_pages : array() )
		);
		$out      = array();
		$taken_at = (int) $taken_at;
		foreach ( $added as $pid ) {
			$pid  = (int) $pid;
			$post = get_post( $pid );
			if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
				continue;
			}
			if ( ! in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) ) {
				continue;
			}
			$created = strtotime( (string) $post->post_date_gmt );
			if ( $taken_at > 0 && $created > 0 && $created < ( $taken_at - 120 ) ) {
				continue;
			}
			$out[] = $pid;
		}
		return $out;
	}

	/**
	 * Pages vraiment supprimées (ignore corbeille, dépublication, brouillon).
	 *
	 * @param int[] $old_pages Anciens IDs.
	 * @param int[] $new_pages Nouveaux IDs.
	 * @return int[]
	 */
	private static function filter_deleted_page_ids( $old_pages, $new_pages ) {
		$removed = array_diff(
			array_map( 'intval', is_array( $old_pages ) ? $old_pages : array() ),
			array_map( 'intval', is_array( $new_pages ) ? $new_pages : array() )
		);
		$out = array();
		foreach ( $removed as $pid ) {
			$pid  = (int) $pid;
			$post = get_post( $pid );
			if ( $post instanceof WP_Post ) {
				continue;
			}
			$out[] = $pid;
		}
		return $out;
	}

	/**
	 * @param int[] $ids IDs de pages.
	 * @return string
	 */
	private static function page_titles( $ids ) {
		$bits = array();
		foreach ( array_slice( array_values( $ids ), 0, 15 ) as $id ) {
			$id    = (int) $id;
			$title = get_the_title( $id );
			$bits[] = ( $title ? $title : '#' . $id ) . ' (#' . $id . ')';
		}
		return implode( ', ', $bits );
	}

	/**
	 * @return string
	 */
	private static function wp_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$parent = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $parent ) ) {
			return $parent;
		}
		return '';
	}

	/**
	 * Ignore les flush rewrite / blocs plugins légitimes ; alerte si charge utile suspecte.
	 *
	 * @param string $old Ancien contenu.
	 * @param string $new Nouveau contenu.
	 * @return bool
	 */
	private static function htaccess_change_is_suspicious( $old, $new ) {
		$old = self::normalize_htaccess( $old );
		$new = self::normalize_htaccess( $new );
		if ( $old === $new ) {
			return false;
		}

		$old_payload = self::htaccess_malicious_payload( $old );
		$new_payload = self::htaccess_malicious_payload( $new );
		if ( $new_payload === $old_payload ) {
			return false;
		}

		return '' !== $new_payload;
	}

	/**
	 * @param string $content Contenu .htaccess.
	 * @return string
	 */
	private static function normalize_htaccess( $content ) {
		$content = str_replace( array( "\r\n", "\r" ), "\n", (string) $content );
		$stripped = preg_replace_callback(
			'/^[ \t]*# BEGIN (.+?)[ \t]*\n.*?\n[ \t]*# END \1[ \t]*$/ims',
			static function ( $m ) {
				$block = $m[0];
				if ( self::htaccess_malicious_payload( $block ) ) {
					return $block;
				}
				return "\n";
			},
			$content
		);
		if ( is_string( $stripped ) ) {
			$content = $stripped;
		}
		$content = preg_replace( '/\n{2,}/', "\n", $content );
		return trim( is_string( $content ) ? $content : '' );
	}

	/**
	 * Signatures malveillantes présentes (hors Wordfence WAF).
	 *
	 * @param string $content Contenu.
	 * @return string
	 */
	private static function htaccess_malicious_payload( $content ) {
		$content = (string) $content;
		if ( '' === $content ) {
			return '';
		}

		$found = array();
		$rules = array(
			'php_open'     => '/<\?(?:php|=)/i',
			'sethandler'   => '/\b(?:SetHandler|AddHandler)\s+application\/x-httpd-(?:php|cgi)/i',
			'addtype_php'  => '/\bAddType\s+(?:application\/x-httpd-php|text\/x-php)/i',
			'engine_on'    => '/\bphp_flag\s+engine\s+on\b/i',
			'eval'         => '/\b(?:eval|assert|gzinflate|str_rot13|base64_decode)\s*\(/i',
		);

		foreach ( $rules as $key => $regex ) {
			if ( preg_match( $regex, $content ) ) {
				$found[] = $key;
			}
		}

		if ( preg_match_all( '/\b(?:php_value|php_admin_value)\s+auto_(?:prepend|append)_file\s+(\S+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $target ) {
				$target = trim( (string) $target, "\"'" );
				if ( false === stripos( $target, 'wordfence-waf.php' ) ) {
					$found[] = 'auto_prepend';
					break;
				}
			}
		}

		$found = array_values( array_unique( $found ) );
		sort( $found );
		return implode( ',', $found );
	}

	/**
	 * @param string $path Chemin.
	 * @return string
	 */
	private static function file_hash( $path ) {
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		$hash = md5_file( $path );
		return is_string( $hash ) ? $hash : '';
	}

	/**
	 * @param string $path   Chemin.
	 * @param bool   $redact Masquer secrets.
	 * @return string
	 */
	public static function read_file_for_snapshot( $path, $redact ) {
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $path );
		if ( ! is_string( $content ) ) {
			return '';
		}
		if ( strlen( $content ) > 100000 ) {
			$content = substr( $content, 0, 100000 ) . "\n… (tronqué)";
		}
		if ( $redact ) {
			$content = self::redact_secrets( $content );
		}
		return $content;
	}

	/**
	 * @param string $content Contenu.
	 * @return string
	 */
	private static function redact_secrets( $content ) {
		$redacted = preg_replace(
			"/((?:define\s*\(\s*['\"][^'\"]*(?:PASSWORD|SECRET|KEY|SALT|NONCE)[^'\"]*['\"]\s*,\s*)(['\"])).+?(\\2\\s*\\))/is",
			'$1[redacted]$3',
			$content
		);
		return is_string( $redacted ) ? $redacted : $content;
	}
}
