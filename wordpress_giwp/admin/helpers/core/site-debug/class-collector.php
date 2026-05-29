<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collecte exhaustive d’informations de diagnostic pour un site WordPress.
 */
class Gi_Toolkit_Site_Debug_Collector {

	/**
	 * @return array<string, mixed>
	 */
	public static function collect() {
		global $wpdb;

		$data = array(
			'meta'       => self::collect_meta(),
			'summary'    => array(),
			'wordpress'  => self::collect_wordpress(),
			'php'        => self::collect_php(),
			'server'     => self::collect_server(),
			'constants'  => self::collect_constants(),
			'database'   => self::collect_database(),
			'theme'      => self::collect_theme(),
			'plugins'    => self::collect_plugins(),
			'users'      => self::collect_users(),
			'content'    => self::collect_content(),
			'cron'       => self::collect_cron(),
			'cache'      => self::collect_cache(),
			'filesystem' => self::collect_filesystem(),
			'dropins'    => self::collect_dropins(),
			'rewrite'    => self::collect_rewrite(),
			'gi_toolkit' => self::collect_gi_toolkit(),
			'integrations' => self::collect_integrations(),
			'mail'       => self::collect_mail(),
			'security'   => self::collect_security(),
			'rest'       => self::collect_rest(),
			'debug_log'  => self::collect_debug_log(),
		);

		$data['summary'] = self::build_summary( $data );

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_meta() {
		$user = wp_get_current_user();

		return array(
			'generated_at'      => gmdate( 'c' ),
			'generated_at_local' => wp_date( 'Y-m-d H:i:s T' ),
			'gi_toolkit_version' => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '',
			'site_url'          => site_url(),
			'home_url'          => home_url(),
			'admin_url'         => admin_url(),
			'viewer'            => array(
				'id'    => (int) $user->ID,
				'login' => (string) $user->user_login,
				'email' => (string) $user->user_email,
				'roles' => array_values( (array) $user->roles ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_wordpress() {
		global $wp_version, $wp_local_package;

		$permalink = get_option( 'permalink_structure' );

		return array(
			'version'              => $wp_version,
			'locale'               => get_locale(),
			'language_pack'        => is_string( $wp_local_package ) ? $wp_local_package : '',
			'multisite'            => is_multisite(),
			'blog_id'              => get_current_blog_id(),
			'environment_type'     => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '',
			'timezone_string'      => (string) get_option( 'timezone_string' ),
			'gmt_offset'           => (string) get_option( 'gmt_offset' ),
			'date_format'          => (string) get_option( 'date_format' ),
			'time_format'          => (string) get_option( 'time_format' ),
			'start_of_week'        => (int) get_option( 'start_of_week' ),
			'permalink_structure'  => is_string( $permalink ) ? $permalink : '',
			'users_can_register'   => (bool) get_option( 'users_can_register' ),
			'default_role'         => (string) get_option( 'default_role' ),
			'blog_public'          => (string) get_option( 'blogpublic' ),
			'admin_email'          => (string) get_option( 'admin_email' ),
			'core_update'          => self::get_core_update_info(),
			'block_editor'         => ! ( function_exists( 'use_block_editor_for_post_type' ) && ! use_block_editor_for_post_type( 'post' ) ),
			'doing_cron'           => wp_doing_cron(),
			'doing_ajax'           => wp_doing_ajax(),
			'is_ssl'               => is_ssl(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_php() {
		$limits = array(
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'max_input_time'     => ini_get( 'max_input_time' ),
			'max_input_vars'     => ini_get( 'max_input_vars' ),
			'post_max_size'      => ini_get( 'post_max_size' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'max_file_uploads'   => ini_get( 'max_file_uploads' ),
		);

		$extensions = get_loaded_extensions();
		sort( $extensions );

		return array(
			'version'          => phpversion(),
			'sapi'             => PHP_SAPI,
			'os'               => PHP_OS,
			'architecture'     => PHP_INT_SIZE * 8 . '-bit',
			'user'             => function_exists( 'get_current_user' ) ? get_current_user() : '',
			'ini_file'         => php_ini_loaded_file() ?: '',
			'scan_dir'         => php_ini_scanned_files() ?: '',
			'limits'           => $limits,
			'wp_memory_limit'  => WP_MEMORY_LIMIT,
			'wp_max_memory'    => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '',
			'extensions'       => $extensions,
			'opcache_enabled'  => function_exists( 'opcache_get_status' ) && ! empty( opcache_get_status( false ) ),
			'xdebug_enabled'   => extension_loaded( 'xdebug' ),
			'error_reporting'  => error_reporting(),
			'display_errors'   => ini_get( 'display_errors' ),
			'log_errors'       => ini_get( 'log_errors' ),
			'error_log'        => ini_get( 'error_log' ) ?: '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_server() {
		$keys = array(
			'SERVER_SOFTWARE',
			'SERVER_NAME',
			'SERVER_ADDR',
			'SERVER_PORT',
			'HTTP_HOST',
			'HTTPS',
			'REMOTE_ADDR',
			'DOCUMENT_ROOT',
			'REQUEST_SCHEME',
		);

		$server = array();
		foreach ( $keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$server[ $key ] = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
			}
		}

		return array(
			'values'        => $server,
			'https_detected' => is_ssl() || ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ),
			'host_ip'       => sanitize_text_field( wp_unslash( (string) ( $_SERVER['SERVER_ADDR'] ?? '' ) ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_constants() {
		$names = array(
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG',
			'SAVEQUERIES',
			'WP_CACHE',
			'COMPRESS_CSS',
			'COMPRESS_SCRIPTS',
			'CONCATENATE_SCRIPTS',
			'ENFORCE_GZIP',
			'DISALLOW_FILE_EDIT',
			'DISALLOW_FILE_MODS',
			'AUTOMATIC_UPDATER_DISABLED',
			'WP_AUTO_UPDATE_CORE',
			'FORCE_SSL_ADMIN',
			'FORCE_SSL_LOGIN',
			'COOKIE_DOMAIN',
			'COOKIEPATH',
			'SITECOOKIEPATH',
			'ADMIN_COOKIE_PATH',
			'PLUG_COOKIE_PATH',
			'WP_CRON_LOCK_TIMEOUT',
			'ALTERNATE_WP_CRON',
			'DISABLE_WP_CRON',
			'WP_POST_REVISIONS',
			'AUTOSAVE_INTERVAL',
			'EMPTY_TRASH_DAYS',
			'WP_ENVIRONMENT_TYPE',
			'GI_TOOLKIT_DEV_MOD',
			'GI_TOOLKIT_SAFE_MODE',
		);

		$out = array();
		foreach ( $names as $name ) {
			if ( defined( $name ) ) {
				$value = constant( $name );
				$out[ $name ] = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
			} else {
				$out[ $name ] = null;
			}
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_database() {
		global $wpdb;

		$charset = $wpdb->get_var( 'SELECT @@character_set_database' );
		$collate = $wpdb->get_var( 'SELECT @@collation_database' );
		$version = $wpdb->get_var( 'SELECT VERSION()' );

		$autoload_total = (int) $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
		);

		$autoload_top = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS size_bytes FROM {$wpdb->options} WHERE autoload = 'yes' ORDER BY size_bytes DESC LIMIT 20",
			ARRAY_A
		);

		$transient_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
		);

		$tables = self::get_table_sizes( 25 );

		return array(
			'version'           => (string) $version,
			'charset'           => (string) $charset,
			'collation'         => (string) $collate,
			'prefix'            => $wpdb->prefix,
			'base_prefix'       => $wpdb->base_prefix,
			'tables_count'      => count( $wpdb->tables( 'all' ) ),
			'autoload_bytes'    => $autoload_total,
			'autoload_human'    => size_format( $autoload_total ),
			'autoload_top'      => is_array( $autoload_top ) ? $autoload_top : array(),
			'transients_count'  => $transient_count,
			'tables_top_size'   => $tables,
			'last_error'        => (string) $wpdb->last_error,
			'queries_this_page' => defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) ? count( $wpdb->queries ) : null,
		);
	}

	/**
	 * @param int $limit Limite.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_table_sizes( $limit = 25 ) {
		global $wpdb;

		$limit = absint( $limit );
		$like  = $wpdb->esc_like( $wpdb->prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT table_name AS name,
					ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
					table_rows AS row_estimate
				FROM information_schema.TABLES
				WHERE table_schema = DATABASE() AND table_name LIKE %s
				ORDER BY (data_length + index_length) DESC
				LIMIT %d",
				$like,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_theme() {
		$theme     = wp_get_theme();
		$parent    = $theme->parent();
		$template  = get_template();
		$stylesheet = get_stylesheet();

		return array(
			'name'            => $theme->get( 'Name' ),
			'version'         => $theme->get( 'Version' ),
			'author'          => wp_strip_all_tags( $theme->get( 'Author' ) ),
			'template'        => $template,
			'stylesheet'      => $stylesheet,
			'is_child'        => (bool) $parent,
			'parent_name'     => $parent ? $parent->get( 'Name' ) : '',
			'parent_version'  => $parent ? $parent->get( 'Version' ) : '',
			'theme_root'      => get_theme_root(),
			'block_theme'     => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
			'template_files'  => array_slice( array_keys( (array) $theme->get_page_templates() ), 0, 30 ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all    = get_plugins();
		$active = get_option( 'active_plugins', array() );
		$mu     = get_mu_plugins();

		$active_list   = array();
		$inactive_list = array();

		foreach ( $all as $file => $data ) {
			$row = array(
				'file'    => $file,
				'name'    => $data['Name'] ?? $file,
				'version' => $data['Version'] ?? '',
				'author'  => wp_strip_all_tags( $data['Author'] ?? '' ),
			);
			if ( in_array( $file, (array) $active, true ) ) {
				$active_list[] = $row;
			} else {
				$inactive_list[] = $row;
			}
		}

		usort( $active_list, static function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );
		usort( $inactive_list, static function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return array(
			'active_count'   => count( $active_list ),
			'inactive_count' => count( $inactive_list ),
			'mu_count'       => count( $mu ),
			'active'         => $active_list,
			'inactive'       => $inactive_list,
			'must_use'       => array_map(
				static function ( $file, $data ) {
					return array(
						'file'    => $file,
						'name'    => $data['Name'] ?? $file,
						'version' => $data['Version'] ?? '',
					);
				},
				array_keys( $mu ),
				array_values( $mu )
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_users() {
		$counts = count_users();
		$roles  = $counts['avail_roles'] ?? array();

		return array(
			'total' => (int) ( $counts['total_users'] ?? 0 ),
			'roles' => is_array( $roles ) ? $roles : array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_content() {
		$counts = wp_count_posts( 'post' );
		$pages  = wp_count_posts( 'page' );
		$media  = wp_count_posts( 'attachment' );
		$comments = wp_count_comments();

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$pt_counts  = array();
		foreach ( $post_types as $pt ) {
			if ( in_array( $pt->name, array( 'attachment' ), true ) ) {
				continue;
			}
			$c = wp_count_posts( $pt->name );
			$pt_counts[ $pt->name ] = (int) ( $c->publish ?? 0 );
		}

		return array(
			'posts_published'    => (int) ( $counts->publish ?? 0 ),
			'posts_draft'        => (int) ( $counts->draft ?? 0 ),
			'pages_published'    => (int) ( $pages->publish ?? 0 ),
			'media'              => (int) ( $media->inherit ?? 0 ),
			'comments_approved'  => (int) ( $comments->approved ?? 0 ),
			'comments_moderated' => (int) ( $comments->moderated ?? 0 ),
			'comments_spam'      => (int) ( $comments->spam ?? 0 ),
			'post_types_public'  => $pt_counts,
			'revisions'          => (int) wp_count_posts( 'revision' )->inherit,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_cron() {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			$crons = array();
		}

		$events = array();
		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $instances ) {
				if ( ! is_array( $instances ) ) {
					continue;
				}
				foreach ( $instances as $sig => $data ) {
					$events[] = array(
						'hook'      => (string) $hook,
						'timestamp' => (int) $timestamp,
						'next_run'  => wp_date( 'Y-m-d H:i:s', (int) $timestamp ),
						'schedule'  => (string) ( $data['schedule'] ?? 'single' ),
						'args'      => isset( $data['args'] ) ? wp_json_encode( $data['args'] ) : '[]',
					);
					if ( count( $events ) >= 40 ) {
						break 3;
					}
				}
			}
		}

		usort(
			$events,
			static function ( $a, $b ) {
				return $a['timestamp'] <=> $b['timestamp'];
			}
		);

		return array(
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'alternate_cron' => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'total_scheduled' => count( $events ),
			'next_events'     => array_slice( $events, 0, 30 ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_cache() {
		global $wpdb;

		$object_cache = wp_using_ext_object_cache();
		$dropin       = file_exists( WP_CONTENT_DIR . '/object-cache.php' );

		return array(
			'external_object_cache' => $object_cache,
			'object_cache_dropin'   => $dropin,
			'transients_count'      => (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_filesystem() {
		$uploads = wp_upload_dir();

		$content_free = function_exists( 'disk_free_space' ) ? @disk_free_space( WP_CONTENT_DIR ) : false;
		$content_total = function_exists( 'disk_total_space' ) ? @disk_total_space( WP_CONTENT_DIR ) : false;

		return array(
			'abspath'              => ABSPATH,
			'wp_content_dir'       => WP_CONTENT_DIR,
			'uploads_path'         => $uploads['basedir'] ?? '',
			'uploads_url'          => $uploads['baseurl'] ?? '',
			'uploads_writable'     => wp_is_writable( $uploads['basedir'] ?? '' ),
			'wp_content_writable'  => wp_is_writable( WP_CONTENT_DIR ),
			'plugins_writable'     => wp_is_writable( WP_PLUGIN_DIR ),
			'themes_writable'      => wp_is_writable( get_theme_root() ),
			'disk_free'            => false !== $content_free ? size_format( $content_free ) : null,
			'disk_total'           => false !== $content_total ? size_format( $content_total ) : null,
			'fs_method'            => get_filesystem_method(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_dropins() {
		$dropins = array();
		$files   = array(
			'advanced-cache.php',
			'db.php',
			'db-error.php',
			'install.php',
			'maintenance.php',
			'object-cache.php',
			'php-error.php',
			'fatal-error-handler.php',
			'sunrise.php',
		);

		foreach ( $files as $file ) {
			$file = trim( $file );
			$path = WP_CONTENT_DIR . '/' . $file;
			if ( file_exists( $path ) ) {
				$dropins[ $file ] = array(
					'path'    => $path,
					'size'    => size_format( (int) filesize( $path ) ),
					'modified' => wp_date( 'Y-m-d H:i:s', (int) filemtime( $path ) ),
				);
			}
		}

		return $dropins;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_rewrite() {
		$rules = get_option( 'rewrite_rules' );

		return array(
			'rules_count' => is_array( $rules ) ? count( $rules ) : 0,
			'permalink_structure' => (string) get_option( 'permalink_structure' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_gi_toolkit() {
		$db_options   = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		$modules_data = function_exists( 'gi_toolkit_options' ) ? gi_toolkit_options( 'normal' ) : array();
		$active       = array();
		$inactive     = array();

		foreach ( $modules_data as $class => $meta ) {
			$name = $meta['original_name'] ?? $class;
			$on   = isset( $db_options[ $class ] ) && '1' === $db_options[ $class ];
			$row  = array(
				'class' => $class,
				'name'  => $name,
				'group' => $meta['group'] ?? '',
			);
			if ( $on ) {
				$active[] = $row;
			} else {
				$inactive[] = $row;
			}
		}

		return array(
			'version'        => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '',
			'dev_mode'       => defined( 'GI_TOOLKIT_DEV_MOD' ) && GI_TOOLKIT_DEV_MOD,
			'safe_mode'      => defined( 'GI_TOOLKIT_SAFE_MODE' ) && GI_TOOLKIT_SAFE_MODE,
			'active_count'   => count( $active ),
			'inactive_count' => count( $inactive ),
			'active_modules' => $active,
			'inactive_modules' => $inactive,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_integrations() {
		$out = array();

		if ( class_exists( 'Gi_Toolkit_Matomo' ) ) {
			$s = Gi_Toolkit_Matomo::get_settings_static();
			$out['matomo'] = array(
				'configured'        => Gi_Toolkit_Matomo::is_dashboard_ready( $s ),
				'site_id'           => absint( $s['site_id'] ?? 0 ),
				'tracking_enabled'  => '1' === (string) ( $s['tracking_enabled'] ?? '0' ),
				'auto_site'         => '1' === (string) ( $s['auto_site'] ?? '1' ),
				'has_url'           => '' !== ( $s['matomo_url'] ?? '' ),
			);
		}

		if ( class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			if ( method_exists( 'Gi_Toolkit_Uptime_Kuma', 'load_deploy_dependencies' ) ) {
				Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			}
			$s   = Gi_Toolkit_Uptime_Kuma::get_settings_static();
			$api = new Gi_Toolkit_Uptime_Kuma_API( $s );
			$out['uptime_kuma'] = array(
				'configured'   => $api->is_configured(),
				'monitor_id'   => absint( $s['monitor_id'] ?? 0 ),
				'auto_monitor' => '1' === (string) ( $s['auto_monitor'] ?? '1' ),
			);
		}

		if ( class_exists( 'Gi_Toolkit_SMTP_Mailer' ) ) {
			$opt = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_smtp_mailer', array() );
			$out['smtp'] = array(
				'active_provider' => (string) ( $opt['active_provider'] ?? '' ),
				'force_sender'    => '1' === (string) ( $opt['force_sender'] ?? '0' ),
				'sender_email'    => (string) ( $opt['sender_email'] ?? '' ),
			);
		}

		if ( defined( 'MAINWP_CHILD_FILE' ) ) {
			$out['mainwp_child'] = array(
				'active' => true,
			);
		}

		if ( class_exists( 'WooCommerce' ) ) {
			$out['woocommerce'] = array(
				'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			);
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_mail() {
		return array(
			'wp_mail_from'      => apply_filters( 'wp_mail_from', get_option( 'admin_email' ) ),
			'wp_mail_from_name' => apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) ),
			'admin_email'       => (string) get_option( 'admin_email' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_security() {
		return array(
			'ssl_admin'         => defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN,
			'disallow_file_edit' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'disallow_file_mods' => defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS,
			'file_mod_allowed'  => wp_is_file_mod_allowed( 'capability_update_core' ),
			'htaccess_exists'   => file_exists( ABSPATH . '.htaccess' ),
			'wp_config_writable' => wp_is_writable( ABSPATH . 'wp-config.php' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_rest() {
		$rest_url = rest_url();
		$response = wp_remote_get(
			$rest_url,
			array(
				'timeout'   => 8,
				'sslverify' => false,
			)
		);

		$ok = ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 400;

		return array(
			'rest_url'   => $rest_url,
			'rest_ok'    => $ok,
			'rest_error' => is_wp_error( $response ) ? $response->get_error_message() : '',
			'rest_code'  => is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_debug_log() {
		$paths = array(
			WP_CONTENT_DIR . '/debug.log',
		);

		$adv = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
		if ( is_array( $adv ) && ! empty( $adv['custom_log_folder'] ) && '1' === (string) $adv['custom_log_folder'] ) {
			$custom = WP_CONTENT_DIR . '/gi-toolkit/logs/debug.log';
			if ( file_exists( $custom ) ) {
				$paths[] = $custom;
			}
		}

		foreach ( $paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$size = (int) filesize( $path );
			$tail = self::read_file_tail( $path, 80 );

			return array(
				'path'     => $path,
				'size'     => size_format( $size ),
				'modified' => wp_date( 'Y-m-d H:i:s', (int) filemtime( $path ) ),
				'tail'     => $tail,
			);
		}

		return array(
			'path' => '',
			'tail' => array(),
		);
	}

	/**
	 * @param string $path  Fichier.
	 * @param int    $lines Lignes.
	 * @return array<int, string>
	 */
	private static function read_file_tail( $path, $lines = 50 ) {
		$lines = max( 1, absint( $lines ) );
		$content = @file( $path, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $content ) ) {
			return array();
		}
		return array_slice( $content, -$lines );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_core_update_info() {
		$core = get_core_updates();
		if ( ! is_array( $core ) || empty( $core ) ) {
			return array( 'status' => 'unknown' );
		}
		$first = $core[0];
		return array(
			'status'  => $first->response ?? '',
			'version' => $first->version ?? '',
		);
	}

	/**
	 * @param array<string, mixed> $data Données collectées.
	 * @return array<int, array{level:string, message:string}>
	 */
	private static function build_summary( array $data ) {
		$items = array();

		if ( ! empty( $data['wordpress']['is_ssl'] ) ) {
			$items[] = self::alert( 'ok', __( 'HTTPS actif', 'gi-toolkit' ) );
		} else {
			$items[] = self::alert( 'warn', __( 'HTTPS non détecté', 'gi-toolkit' ) );
		}

		$php_version = $data['php']['version'] ?? '';
		if ( version_compare( $php_version, '8.1', '>=' ) ) {
			$items[] = self::alert( 'ok', sprintf( __( 'PHP %s', 'gi-toolkit' ), $php_version ) );
		} elseif ( version_compare( $php_version, '7.4', '>=' ) ) {
			$items[] = self::alert( 'warn', sprintf( __( 'PHP %s — mise à jour recommandée', 'gi-toolkit' ), $php_version ) );
		} else {
			$items[] = self::alert( 'error', sprintf( __( 'PHP %s obsolète', 'gi-toolkit' ), $php_version ) );
		}

		if ( 'true' === ( $data['constants']['WP_DEBUG'] ?? null ) && 'true' === ( $data['constants']['WP_DEBUG_DISPLAY'] ?? null ) ) {
			$items[] = self::alert( 'error', __( 'WP_DEBUG_DISPLAY activé en production', 'gi-toolkit' ) );
		}

		if ( ( $data['database']['autoload_bytes'] ?? 0 ) > 1024 * 1024 ) {
			$items[] = self::alert( 'warn', sprintf(
				__( 'Options autoload volumineuses (%s)', 'gi-toolkit' ),
				$data['database']['autoload_human'] ?? ''
			) );
		}

		if ( ! empty( $data['cron']['wp_cron_disabled'] ) ) {
			$items[] = self::alert( 'warn', __( 'WP-Cron désactivé (DISABLE_WP_CRON)', 'gi-toolkit' ) );
		}

		if ( empty( $data['rest']['rest_ok'] ) ) {
			$items[] = self::alert( 'warn', __( 'API REST inaccessible', 'gi-toolkit' ) );
		} else {
			$items[] = self::alert( 'ok', __( 'API REST accessible', 'gi-toolkit' ) );
		}

		if ( ! empty( $data['debug_log']['path'] ) ) {
			$items[] = self::alert( 'warn', __( 'Fichier debug.log présent', 'gi-toolkit' ) );
		}

		return $items;
	}

	/**
	 * @param string $level   ok|warn|error.
	 * @param string $message Message.
	 * @return array{level:string, message:string}
	 */
	private static function alert( $level, $message ) {
		return array(
			'level'   => $level,
			'message' => $message,
		);
	}
}
