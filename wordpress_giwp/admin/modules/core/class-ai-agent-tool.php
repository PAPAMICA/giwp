<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : AI Agent Tool.
 *
 * Expose une API REST dédiée (clé API propre, indépendante de MainWP et des
 * comptes WordPress) permettant à un agent IA (Hermes Agent / PAPAMICA) d'agir
 * de façon fiable sur le site : purge de cache (WP Rocket, WP Super Cache,
 * W3TC, LiteSpeed…), lecture des logs d'erreurs PHP récents, informations
 * système, et exécution de quelques actions de maintenance sûres.
 *
 * Toutes les routes sont sous /wp-json/gi-toolkit/v1/ai-agent/... et
 * nécessitent l'en-tête `X-GI-AI-Agent-Key` (clé générée par site, stockée en
 * option WordPress, jamais transmise à MainWP ni journalisée en clair).
 *
 * @since 2.27.0
 */
class Gi_Toolkit_AI_Agent_Tool {

	const OPTION_SETTINGS  = 'gi_toolkit_ai_agent_tool_settings';
	const SETTINGS_PAGE_SLUG = 'gi-toolkit-settings-ai-agent-tool';
	const REST_NAMESPACE   = 'gi-toolkit/v1';
	const API_KEY_HEADER   = 'X-GI-AI-Agent-Key';
	const OPTION_LOG       = 'gi_toolkit_ai_agent_tool_log';
	const LOG_MAX_ENTRIES  = 100;

	/** @var self|null */
	private static $instance = null;

	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'admin_init', array( $this, 'maybe_regenerate_key' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_settings() {
		return array(
			'enabled'      => '1',
			'api_key'      => '',
			'allowed_ips'  => '',
			'rate_limit'   => 60, // requêtes / heure / route.
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
		if ( '' === $settings['api_key'] ) {
			$settings['api_key'] = self::generate_key();
			update_option( self::OPTION_SETTINGS, $settings, false );
		}
		return $settings;
	}

	/**
	 * @return string
	 */
	private static function generate_key() {
		return 'gi_ai_' . wp_generate_password( 40, false, false );
	}

	public function maybe_regenerate_key() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_GET['gi_ai_agent_regenerate_key'] ) || empty( $_GET['_wpnonce'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gi_ai_agent_regenerate_key' ) ) {
			return;
		}
		$settings = $this->get_settings();
		$settings['api_key'] = self::generate_key();
		update_option( self::OPTION_SETTINGS, $settings, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG . '&gi_ai_agent_regenerated=1' ) );
		exit;
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			__( 'AI Agent Tool', 'gi-toolkit' ),
			__( 'AI Agent Tool', 'gi-toolkit' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( $this, 'render_submenu' )
		);
	}

	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( self::SETTINGS_PAGE_SLUG, 'gi_toolkit_ai_agent_tool_save' ) ) {
			return;
		}

		$settings = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['enabled'] = isset( $_POST['enabled'] ) ? '1' : '0';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['allowed_ips'] = isset( $_POST['allowed_ips'] )
			? sanitize_textarea_field( wp_unslash( $_POST['allowed_ips'] ) )
			: $settings['allowed_ips'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['rate_limit'] = isset( $_POST['rate_limit'] ) ? max( 1, absint( wp_unslash( $_POST['rate_limit'] ) ) ) : $settings['rate_limit'];

		update_option( self::OPTION_SETTINGS, $settings, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => self::SETTINGS_PAGE_SLUG,
					'gi_toolkit_pro_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_submenu() {
		$settings = $this->get_settings();
		$site_url = untrailingslashit( get_site_url() );
		?>
		<div class="wrap gi-toolkit-ai-agent-tool-settings">
			<h1><?php esc_html_e( 'AI Agent Tool', 'gi-toolkit' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Expose une API REST dédiée permettant à un agent IA (ex. Hermes Agent) d\'agir sur ce site : purge de cache, lecture des erreurs PHP récentes, informations système. Indépendant de MainWP.', 'gi-toolkit' ); ?>
			</p>

			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Réglages enregistrés.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['gi_ai_agent_regenerated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Nouvelle clé API générée. Mets à jour l\'agent avec la nouvelle clé.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<div class="gi-toolkit-panel">
				<h2><?php esc_html_e( 'Connexion', 'gi-toolkit' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'URL de base', 'gi-toolkit' ); ?></th>
						<td><code><?php echo esc_html( $site_url . '/wp-json/' . self::REST_NAMESPACE . '/ai-agent/' ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clé API', 'gi-toolkit' ); ?></th>
						<td>
							<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $settings['api_key'] ); ?>" onclick="this.select();" />
							<p class="description">
								<?php esc_html_e( 'À passer dans l\'en-tête HTTP', 'gi-toolkit' ); ?> <code><?php echo esc_html( self::API_KEY_HEADER ); ?></code>.
							</p>
							<p>
								<a class="button"
									href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::SETTINGS_PAGE_SLUG, 'gi_ai_agent_regenerate_key' => '1' ), admin_url( 'admin.php' ) ), 'gi_ai_agent_regenerate_key' ) ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Régénérer la clé ? L\'ancienne clé sera immédiatement invalide.', 'gi-toolkit' ) ); ?>');">
									<?php esc_html_e( 'Régénérer la clé', 'gi-toolkit' ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG ) ); ?>">
					<?php wp_nonce_field( 'gi_toolkit_ai_agent_tool_save' ); ?>
					<input type="hidden" name="gi_toolkit_pro_save" value="1" />
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SETTINGS_PAGE_SLUG ); ?>" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Activer l\'API', 'gi-toolkit' ); ?></th>
							<td><label><input type="checkbox" name="enabled" value="1" <?php checked( '1', $settings['enabled'] ); ?> /> <?php esc_html_e( 'Activer les routes REST AI Agent Tool', 'gi-toolkit' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="gi_ai_agent_allowed_ips"><?php esc_html_e( 'IPs autorisées (optionnel)', 'gi-toolkit' ); ?></label></th>
							<td>
								<textarea id="gi_ai_agent_allowed_ips" name="allowed_ips" class="large-text code" rows="3" placeholder="1.2.3.4&#10;5.6.7.0/24"><?php echo esc_textarea( (string) $settings['allowed_ips'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Une IP ou plage CIDR par ligne. Laisser vide pour autoriser toutes les IPs (la clé API reste requise).', 'gi-toolkit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gi_ai_agent_rate_limit"><?php esc_html_e( 'Limite de requêtes / heure', 'gi-toolkit' ); ?></label></th>
							<td><input type="number" min="1" id="gi_ai_agent_rate_limit" name="rate_limit" class="small-text" value="<?php echo esc_attr( (string) (int) $settings['rate_limit'] ); ?>" /></td>
						</tr>
					</table>
					<?php submit_button( __( 'Enregistrer', 'gi-toolkit' ) ); ?>
				</form>
			</div>

			<div class="gi-toolkit-panel">
				<h2><?php esc_html_e( 'Routes disponibles', 'gi-toolkit' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Méthode', 'gi-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Route', 'gi-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Description', 'gi-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td>GET</td><td>/ai-agent/status</td><td><?php esc_html_e( 'Statut du site, versions, caches détectés.', 'gi-toolkit' ); ?></td></tr>
						<tr><td>POST</td><td>/ai-agent/cache/purge</td><td><?php esc_html_e( 'Purge le cache (WP Rocket, WP Super Cache, W3TC, LiteSpeed, object cache).', 'gi-toolkit' ); ?></td></tr>
						<tr><td>GET</td><td>/ai-agent/errors/recent</td><td><?php esc_html_e( 'Dernières lignes du debug.log PHP (si WP_DEBUG_LOG actif).', 'gi-toolkit' ); ?></td></tr>
						<tr><td>GET</td><td>/ai-agent/plugins</td><td><?php esc_html_e( 'Liste des plugins installés/actifs et mises à jour disponibles.', 'gi-toolkit' ); ?></td></tr>
						<tr><td>POST</td><td>/ai-agent/plugins/toggle</td><td><?php esc_html_e( 'Active ou désactive un plugin par son slug.', 'gi-toolkit' ); ?></td></tr>
						<tr><td>POST</td><td>/ai-agent/maintenance-mode</td><td><?php esc_html_e( 'Active/désactive le mode maintenance.', 'gi-toolkit' ); ?></td></tr>
						<tr><td>GET</td><td>/ai-agent/health</td><td><?php esc_html_e( 'Vérifications sommaires (disque, mémoire, erreurs récentes).', 'gi-toolkit' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------- */
	/* REST API                                                          */
	/* ---------------------------------------------------------------- */

	public function register_routes() {
		$settings = $this->get_settings();
		if ( '1' !== (string) ( $settings['enabled'] ?? '1' ) ) {
			return;
		}

		$permission = array( $this, 'check_permission' );

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_status' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_health' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/cache/purge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'route_cache_purge' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/errors/recent',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_errors_recent' ),
				'permission_callback' => $permission,
				'args'                => array(
					'lines' => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/plugins',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_plugins_list' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/plugins/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'route_plugins_toggle' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-agent/maintenance-mode',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'route_maintenance_mode' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Authentification par clé API dédiée + IP allowlist optionnelle + rate limit.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return bool|WP_Error
	 */
	public function check_permission( $request ) {
		$settings = $this->get_settings();

		$provided_key = $request->get_header( self::API_KEY_HEADER );
		if ( empty( $provided_key ) || ! hash_equals( (string) $settings['api_key'], (string) $provided_key ) ) {
			return new WP_Error( 'gi_ai_agent_unauthorized', __( 'Clé API invalide ou manquante.', 'gi-toolkit' ), array( 'status' => 401 ) );
		}

		if ( ! empty( $settings['allowed_ips'] ) ) {
			$remote_ip = self::get_client_ip();
			if ( ! self::ip_matches_allowlist( $remote_ip, (string) $settings['allowed_ips'] ) ) {
				return new WP_Error( 'gi_ai_agent_forbidden', __( 'Adresse IP non autorisée.', 'gi-toolkit' ), array( 'status' => 403 ) );
			}
		}

		if ( ! self::check_rate_limit( absint( $settings['rate_limit'] ?? 60 ) ) ) {
			return new WP_Error( 'gi_ai_agent_rate_limited', __( 'Limite de requêtes atteinte, réessaie plus tard.', 'gi-toolkit' ), array( 'status' => 429 ) );
		}

		return true;
	}

	/**
	 * @return string
	 */
	private static function get_client_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * @param string $ip        IP cliente.
	 * @param string $allowlist Liste (une entrée par ligne, IP ou CIDR).
	 * @return bool
	 */
	private static function ip_matches_allowlist( $ip, $allowlist ) {
		if ( '' === $ip ) {
			return false;
		}
		$lines = preg_split( '/[\r\n]+/', $allowlist );
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false === strpos( $line, '/' ) ) {
				if ( $line === $ip ) {
					return true;
				}
				continue;
			}
			list( $subnet, $mask ) = explode( '/', $line, 2 );
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ip_long     = ip2long( $ip );
				$subnet_long = ip2long( $subnet );
				$mask_long   = -1 << ( 32 - (int) $mask );
				if ( ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Rate limit simple basé sur transient (par minute glissante approx.).
	 *
	 * @param int $max_per_hour Nombre maximum de requêtes / heure.
	 * @return bool
	 */
	private static function check_rate_limit( $max_per_hour ) {
		if ( $max_per_hour < 1 ) {
			return true;
		}
		$key   = 'gi_ai_agent_rl_' . gmdate( 'YmdH' );
		$count = (int) get_transient( $key );
		if ( $count >= $max_per_hour ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * @param string               $action Nom de l'action.
	 * @param array<string, mixed> $data   Détails.
	 * @return void
	 */
	private static function audit_log( $action, array $data = array() ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'time'   => gmdate( 'c' ),
			'action' => sanitize_key( $action ),
			'ip'      => self::get_client_ip(),
			'data'   => $data,
		);
		if ( count( $log ) > self::LOG_MAX_ENTRIES ) {
			$log = array_slice( $log, -1 * self::LOG_MAX_ENTRIES );
		}
		update_option( self::OPTION_LOG, $log, false );

		if ( class_exists( 'Gi_Toolkit_Security' ) ) {
			Gi_Toolkit_Security::log( 'ai_agent_' . $action, $data );
		}
	}

	/**
	 * GET /ai-agent/status
	 */
	public function route_status( $request ) {
		unset( $request );

		global $wp_version;

		$caches = self::detect_cache_plugins();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'site_url'      => get_site_url(),
					'wp_version'    => $wp_version,
					'php_version'   => phpversion(),
					'gi_toolkit'    => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : null,
					'is_multisite'  => is_multisite(),
					'caches_detected' => $caches,
					'maintenance_mode' => (bool) get_option( 'gi_ai_agent_maintenance_mode', false ),
				),
			)
		);
	}

	/**
	 * GET /ai-agent/health
	 */
	public function route_health( $request ) {
		unset( $request );

		$disk_free  = function_exists( 'disk_free_space' ) ? @disk_free_space( ABSPATH ) : null;
		$disk_total = function_exists( 'disk_total_space' ) ? @disk_total_space( ABSPATH ) : null;

		$recent_errors = self::read_debug_log_tail( 20 );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'memory_limit'      => ini_get( 'memory_limit' ),
					'memory_usage'      => size_format( memory_get_usage( true ) ),
					'disk_free_bytes'   => $disk_free,
					'disk_total_bytes'  => $disk_total,
					'recent_error_count' => count( $recent_errors ),
					'recent_errors'     => $recent_errors,
				),
			)
		);
	}

	/**
	 * POST /ai-agent/cache/purge
	 */
	public function route_cache_purge( $request ) {
		$results = array();

		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$results['wp_rocket'] = true;
		}
		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix ?? '', true );
			$results['wp_super_cache'] = true;
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$results['w3_total_cache'] = true;
		}

		// LiteSpeed Cache.
		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
			$results['litespeed_cache'] = true;
		} elseif ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$results['litespeed_cache'] = true;
		}

		// WP Fastest Cache.
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
			$results['wp_fastest_cache'] = true;
		}

		// Object cache générique + transients de rendu.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results['object_cache'] = true;
		}

		do_action( 'gi_toolkit_ai_agent_cache_purge' );

		self::audit_log( 'cache_purge', array( 'results' => $results ) );

		if ( empty( $results ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'message' => __( 'Aucun plugin de cache connu détecté ; rien à purger.', 'gi-toolkit' ),
						'results' => $results,
					),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message' => __( 'Cache purgé.', 'gi-toolkit' ),
					'results' => $results,
				),
			)
		);
	}

	/**
	 * GET /ai-agent/errors/recent
	 */
	public function route_errors_recent( $request ) {
		$lines = $request->get_param( 'lines' );
		$lines = $lines ? min( 1000, absint( $lines ) ) : 100;

		$entries = self::read_debug_log_tail( $lines );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'debug_log_enabled' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
					'entries'           => $entries,
				),
			)
		);
	}

	/**
	 * @param int $max_lines Nombre de lignes max à retourner.
	 * @return array<int, string>
	 */
	private static function read_debug_log_tail( $max_lines = 100 ) {
		$log_path = WP_CONTENT_DIR . '/debug.log';
		if ( ! is_readable( $log_path ) ) {
			return array();
		}

		$max_bytes = 512 * 1024; // Ne lit jamais plus de 512 Ko depuis la fin.
		$size      = filesize( $log_path );
		$handle    = fopen( $log_path, 'r' );
		if ( ! $handle ) {
			return array();
		}

		$seek = max( 0, $size - $max_bytes );
		fseek( $handle, $seek );
		$content = stream_get_contents( $handle );
		fclose( $handle );

		if ( false === $content ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', trim( $content ) );
		$lines = array_filter( (array) $lines );

		return array_slice( array_values( $lines ), -1 * $max_lines );
	}

	/**
	 * GET /ai-agent/plugins
	 */
	public function route_plugins_list( $request ) {
		unset( $request );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$updates = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();

		$plugins = array();
		foreach ( $all_plugins as $slug => $data ) {
			$plugins[] = array(
				'slug'            => $slug,
				'name'            => $data['Name'] ?? $slug,
				'version'         => $data['Version'] ?? '',
				'active'          => in_array( $slug, $active_plugins, true ),
				'update_available' => isset( $updates[ $slug ] ),
				'new_version'     => isset( $updates[ $slug ]->update->new_version ) ? $updates[ $slug ]->update->new_version : null,
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'plugins' => $plugins ),
			)
		);
	}

	/**
	 * POST /ai-agent/plugins/toggle
	 */
	public function route_plugins_toggle( $request ) {
		if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$slug   = sanitize_text_field( (string) $request->get_param( 'slug' ) );
		$action = sanitize_key( (string) $request->get_param( 'action' ) );

		if ( '' === $slug || ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètres invalides : slug et action (activate|deactivate) requis.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		$installed = array_keys( get_plugins() );
		if ( ! in_array( $slug, $installed, true ) ) {
			return new WP_Error( 'gi_ai_agent_plugin_not_found', __( 'Plugin introuvable.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		if ( 'activate' === $action ) {
			$result = activate_plugin( $slug );
			if ( is_wp_error( $result ) ) {
				self::audit_log( 'plugin_toggle_failed', array( 'slug' => $slug, 'action' => $action, 'error' => $result->get_error_message() ) );
				return $result;
			}
		} else {
			deactivate_plugins( array( $slug ) );
		}

		self::audit_log( 'plugin_toggle', array( 'slug' => $slug, 'action' => $action ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'slug'   => $slug,
					'action' => $action,
				),
			)
		);
	}

	/**
	 * POST /ai-agent/maintenance-mode
	 */
	public function route_maintenance_mode( $request ) {
		$enabled = (bool) $request->get_param( 'enabled' );

		update_option( 'gi_ai_agent_maintenance_mode', $enabled );

		if ( $enabled ) {
			add_filter( 'gi_toolkit_ai_agent_maintenance_active', '__return_true' );
		}

		self::audit_log( 'maintenance_mode', array( 'enabled' => $enabled ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'maintenance_mode' => $enabled ),
			)
		);
	}

	/**
	 * @return array<string, bool>
	 */
	private static function detect_cache_plugins() {
		return array(
			'wp_rocket'        => function_exists( 'rocket_clean_domain' ),
			'wp_super_cache'   => function_exists( 'wp_cache_clean_cache' ),
			'w3_total_cache'   => function_exists( 'w3tc_flush_all' ),
			'litespeed_cache'  => class_exists( 'LiteSpeed\Purge' ) || has_action( 'litespeed_purge_all' ),
			'wp_fastest_cache' => function_exists( 'wpfc_clear_all_cache' ),
		);
	}
}
