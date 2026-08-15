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

		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);
		$pages = array_map( 'intval', is_array( $pages ) ? $pages : array() );
		sort( $pages, SORT_NUMERIC );

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
			$names = array();
			foreach ( $added_adm as $id => $login ) {
				$names[] = $login . ' (#' . (int) $id . ')';
			}
			$alerts[] = array(
				'type'    => 'watch_admin_user',
				'summary' => __( 'Nouvel utilisateur administrateur détecté', 'gi-toolkit' ),
				'details' => implode( ', ', $names ),
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
				);
			}
		}

		$removed_adm = array_diff_key( $old_admins, $new_admins );
		if ( ! empty( $removed_adm ) ) {
			$names = array();
			foreach ( $removed_adm as $id => $login ) {
				$names[] = $login . ' (#' . (int) $id . ')';
			}
			$alerts[] = array(
				'type'    => 'watch_user_deleted',
				'summary' => __( 'Administrateur supprimé ou rétrogradé', 'gi-toolkit' ),
				'details' => implode( ', ', $names ),
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
			$alerts[] = array(
				'type'    => 'watch_password',
				'summary' => __( 'Mot de passe modifié (compte privilégié)', 'gi-toolkit' ),
				'details' => implode( ', ', $changed ),
			);
		}

		$old_pages = isset( $old['pages'] ) && is_array( $old['pages'] ) ? $old['pages'] : array();
		$new_pages = isset( $new['pages'] ) && is_array( $new['pages'] ) ? $new['pages'] : array();
		$added_p   = array_diff( $new_pages, $old_pages );
		$del_p     = array_diff( $old_pages, $new_pages );
		if ( ! empty( $added_p ) ) {
			$alerts[] = array(
				'type'    => 'watch_pages',
				'summary' => __( 'Page(s) ajoutée(s)', 'gi-toolkit' ),
				'details' => self::page_titles( $added_p ),
			);
		}
		if ( ! empty( $del_p ) ) {
			$alerts[] = array(
				'type'    => 'watch_pages',
				'summary' => __( 'Page(s) supprimée(s)', 'gi-toolkit' ),
				'details' => __( 'IDs : ', 'gi-toolkit' ) . implode( ', ', array_map( 'intval', $del_p ) ),
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
			);
		}
		if ( ! empty( $removed_pl ) ) {
			$alerts[] = array(
				'type'    => 'watch_plugins_themes',
				'summary' => __( 'Extension(s) supprimée(s)', 'gi-toolkit' ),
				'details' => implode( ', ', $removed_pl ),
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
			);
		}
		if ( ! empty( $removed_th ) ) {
			$alerts[] = array(
				'type'    => 'watch_plugins_themes',
				'summary' => __( 'Thème(s) supprimé(s)', 'gi-toolkit' ),
				'details' => implode( ', ', $removed_th ),
			);
		}

		$old_mu = isset( $old['mu_plugins'] ) && is_array( $old['mu_plugins'] ) ? $old['mu_plugins'] : array();
		$new_mu = isset( $new['mu_plugins'] ) && is_array( $new['mu_plugins'] ) ? $new['mu_plugins'] : array();
		$mu_add = array_diff( $new_mu, $old_mu );
		$mu_del = array_diff( $old_mu, $new_mu );
		if ( ! empty( $mu_add ) ) {
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Must-use plugin ajouté', 'gi-toolkit' ),
				'details' => implode( ', ', $mu_add ),
			);
		}
		if ( ! empty( $mu_del ) ) {
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Must-use plugin supprimé', 'gi-toolkit' ),
				'details' => implode( ', ', $mu_del ),
			);
		}

		$old_drop = isset( $old['dropins'] ) && is_array( $old['dropins'] ) ? $old['dropins'] : array();
		$new_drop = isset( $new['dropins'] ) && is_array( $new['dropins'] ) ? $new['dropins'] : array();
		$drop_add = array_diff( $new_drop, $old_drop );
		$drop_del = array_diff( $old_drop, $new_drop );
		if ( ! empty( $drop_add ) ) {
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Drop-in WordPress ajouté', 'gi-toolkit' ),
				'details' => implode( ', ', $drop_add ),
			);
		}
		if ( ! empty( $drop_del ) ) {
			$alerts[] = array(
				'type'    => 'watch_mu_dropins',
				'summary' => __( 'Drop-in WordPress supprimé', 'gi-toolkit' ),
				'details' => implode( ', ', $drop_del ),
			);
		}

		$old_php = isset( $old['php_uploads'] ) && is_array( $old['php_uploads'] ) ? $old['php_uploads'] : array();
		$new_php = isset( $new['php_uploads'] ) && is_array( $new['php_uploads'] ) ? $new['php_uploads'] : array();
		$php_add = array_diff( $new_php, $old_php );
		if ( ! empty( $php_add ) ) {
			$alerts[] = array(
				'type'    => 'watch_php_uploads',
				'summary' => __( 'Fichier(s) PHP détecté(s) dans les uploads', 'gi-toolkit' ),
				'details' => implode( ', ', array_slice( $php_add, 0, 20 ) ),
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
				);
			}
		}

		foreach ( array(
			'wp_config_hash' => 'wp-config.php',
			'htaccess_hash'  => '.htaccess',
			'index_hash'     => 'index.php (racine)',
		) as $key => $file ) {
			$ov = isset( $old[ $key ] ) ? (string) $old[ $key ] : '';
			$nv = isset( $new[ $key ] ) ? (string) $new[ $key ] : '';
			if ( '' !== $ov && $ov !== $nv ) {
				$alerts[] = array(
					'type'    => 'watch_core_files',
					'summary' => sprintf(
						/* translators: %s: filename */
						__( 'Fichier sensible modifié : %s', 'gi-toolkit' ),
						$file
					),
					'details' => $file,
				);
			}
		}

		return $alerts;
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
			Gi_Toolkit_Compromise_Detection::raise_alert(
				'watch_outbound',
				__( 'Requêtes sortantes suspectes (scan d’autres sites)', 'gi-toolkit' ),
				$details
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
		set_transient( self::TRANSIENT_LOGINS, $bucket, MINUTE_IN_SECONDS );

		if ( $bucket['count'] >= self::LOGIN_SPIKE_THRESHOLD ) {
			$top = $bucket['users'];
			arsort( $top );
			$top = array_slice( $top, 0, 5, true );
			$bits = array();
			foreach ( $top as $login => $n ) {
				$bits[] = $login . '×' . (int) $n;
			}
			Gi_Toolkit_Compromise_Detection::raise_alert(
				'watch_login_spike',
				__( 'Pic de connexions échouées', 'gi-toolkit' ),
				sprintf(
					/* translators: 1: count, 2: usernames */
					__( '%1$d échecs en 1 minute (%2$s)', 'gi-toolkit' ),
					(int) $bucket['count'],
					implode( ', ', $bits )
				)
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
		);
		if ( in_array( $host, $exact, true ) ) {
			return true;
		}

		$suffixes = array(
			'.wordpress.org',
			'.w.org',
			'.wp.com',
			'.gravatar.com',
			'.googleapis.com',
			'.google.com',
			'.gstatic.com',
			'.github.com',
			'.githubusercontent.com',
			'.pushover.net',
			'.cloudflare.com',
			'.cloudfront.net',
			'.amazonaws.com',
			'.woocommerce.com',
			'.wpmudev.com',
			'.mainwp.com',
			'.elementor.com',
			'.gravity.com',
			'.paypal.com',
			'.stripe.com',
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
}
