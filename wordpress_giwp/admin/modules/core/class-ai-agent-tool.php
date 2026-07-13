<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : AI Agent Tool.
 *
 * Expose une API REST dédiée (clé API propre, indépendante de MainWP et des
 * comptes WordPress) permettant à un agent IA (Hermes Agent / PAPAMICA) de
 * gérer et corriger les sites clients : cache (WP Rocket & autres), plugins,
 * thèmes, fichiers de thème/plugin (avec sauvegarde automatique), utilisateurs,
 * base de données, Wordfence, UpdraftPlus, Elementor, cron, logs d'erreurs.
 *
 * Toutes les routes sont sous /wp-json/gi-toolkit/v1/ai-agent/... et
 * nécessitent l'en-tête `X-GI-AI-Agent-Key` (clé générée par site, stockée en
 * option WordPress). Chaque route peut être activée/désactivée individuellement
 * depuis Réglages > AI Agent Tool ; toutes sont activées par défaut.
 *
 * @since 2.27.0
 */
class Gi_Toolkit_AI_Agent_Tool {

	const OPTION_SETTINGS    = 'gi_toolkit_ai_agent_tool_settings';
	const SETTINGS_PAGE_SLUG = 'gi-toolkit-settings-ai-agent-tool';
	const REST_NAMESPACE     = 'gi-toolkit/v1';
	const API_KEY_HEADER     = 'X-GI-AI-Agent-Key';
	const OPTION_LOG         = 'gi_toolkit_ai_agent_tool_log';
	const LOG_MAX_ENTRIES    = 100;
	const BACKUP_DIR_NAME    = 'gi-toolkit-ai-agent-backups';
	const ALLOWED_FILE_EXTENSIONS = array( 'php', 'css', 'js', 'json', 'txt' );
	const MAX_FILE_READ_BYTES     = 512000; // 500 Ko.

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

	/* ---------------------------------------------------------------- */
	/* Catalogue des routes                                              */
	/* ---------------------------------------------------------------- */

	/**
	 * Catalogue central : une entrée par route, groupée par catégorie.
	 * Toute nouvelle route doit être ajoutée ici pour être exposée et
	 * apparaître dans la page de réglages avec son interrupteur.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function route_catalog() {
		return array(
			// -- Site & diagnostic ------------------------------------------------
			'status' => array(
				'category' => __( 'Site & Diagnostic', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/status',
				'label'    => __( 'Statut du site (versions, caches détectés)', 'gi-toolkit' ),
				'callback' => 'route_status',
			),
			'health' => array(
				'category' => __( 'Site & Diagnostic', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/health',
				'label'    => __( 'Santé (mémoire, disque, erreurs récentes)', 'gi-toolkit' ),
				'callback' => 'route_health',
			),
			'errors_recent' => array(
				'category' => __( 'Site & Diagnostic', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/errors/recent',
				'label'    => __( 'Lecture du debug.log PHP', 'gi-toolkit' ),
				'callback' => 'route_errors_recent',
			),
			'cron_list' => array(
				'category' => __( 'Site & Diagnostic', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/cron',
				'label'    => __( 'Liste des tâches cron planifiées', 'gi-toolkit' ),
				'callback' => 'route_cron_list',
			),
			'cron_run' => array(
				'category' => __( 'Site & Diagnostic', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/cron/run',
				'label'    => __( 'Exécuter un hook cron immédiatement', 'gi-toolkit' ),
				'callback' => 'route_cron_run',
			),

			// -- Cache --------------------------------------------------------------
			'cache_purge' => array(
				'category' => __( 'Cache', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/cache/purge',
				'label'    => __( 'Purger le cache (WP Rocket, WP Super Cache, W3TC, LiteSpeed, Fastest Cache, object cache)', 'gi-toolkit' ),
				'callback' => 'route_cache_purge',
			),
			'cache_status' => array(
				'category' => __( 'Cache', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/cache/status',
				'label'    => __( 'Détecter les plugins de cache actifs', 'gi-toolkit' ),
				'callback' => 'route_cache_status',
			),

			// -- Plugins & thèmes -----------------------------------------------------
			'plugins_list' => array(
				'category' => __( 'Plugins & Thèmes', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/plugins',
				'label'    => __( 'Lister les plugins (versions, actif, updates)', 'gi-toolkit' ),
				'callback' => 'route_plugins_list',
			),
			'plugins_toggle' => array(
				'category' => __( 'Plugins & Thèmes', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/plugins/toggle',
				'label'    => __( 'Activer / désactiver un plugin', 'gi-toolkit' ),
				'callback' => 'route_plugins_toggle',
			),
			'plugins_update' => array(
				'category' => __( 'Plugins & Thèmes', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/plugins/update',
				'label'    => __( 'Mettre à jour un plugin vers la dernière version', 'gi-toolkit' ),
				'callback' => 'route_plugins_update',
			),
			'plugins_delete' => array(
				'category' => __( 'Plugins & Thèmes', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/plugins/delete',
				'label'    => __( 'Supprimer un plugin (doit être désactivé)', 'gi-toolkit' ),
				'callback' => 'route_plugins_delete',
			),
			'themes_list' => array(
				'category' => __( 'Plugins & Thèmes', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/themes',
				'label'    => __( 'Lister les thèmes installés', 'gi-toolkit' ),
				'callback' => 'route_themes_list',
			),
			'themes_activate' => array(
				'category' => __( 'Plugins & Thèmes', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/themes/activate',
				'label'    => __( 'Activer un thème', 'gi-toolkit' ),
				'callback' => 'route_themes_activate',
			),

			// -- Fichiers -------------------------------------------------------------
			'files_list' => array(
				'category' => __( 'Fichiers', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/files/list',
				'label'    => __( 'Lister un dossier (dans wp-content uniquement)', 'gi-toolkit' ),
				'callback' => 'route_files_list',
			),
			'files_read' => array(
				'category' => __( 'Fichiers', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/files/read',
				'label'    => __( 'Lire un fichier (dans wp-content uniquement)', 'gi-toolkit' ),
				'callback' => 'route_files_read',
			),
			'files_write' => array(
				'category' => __( 'Fichiers', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/files/write',
				'label'    => __( 'Écrire/corriger un fichier (sauvegarde automatique avant écriture)', 'gi-toolkit' ),
				'callback' => 'route_files_write',
			),

			// -- Utilisateurs ---------------------------------------------------------
			'users_list' => array(
				'category' => __( 'Utilisateurs', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/users',
				'label'    => __( 'Lister les utilisateurs', 'gi-toolkit' ),
				'callback' => 'route_users_list',
			),
			'users_create' => array(
				'category' => __( 'Utilisateurs', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/users/create',
				'label'    => __( 'Créer un utilisateur', 'gi-toolkit' ),
				'callback' => 'route_users_create',
			),
			'users_reset_password' => array(
				'category' => __( 'Utilisateurs', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/users/reset-password',
				'label'    => __( 'Réinitialiser le mot de passe d\'un utilisateur', 'gi-toolkit' ),
				'callback' => 'route_users_reset_password',
			),

			// -- Maintenance ------------------------------------------------------------
			'maintenance_mode' => array(
				'category' => __( 'Maintenance', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/maintenance-mode',
				'label'    => __( 'Activer / désactiver le mode maintenance', 'gi-toolkit' ),
				'callback' => 'route_maintenance_mode',
			),
			'db_optimize' => array(
				'category' => __( 'Maintenance', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/db/optimize',
				'label'    => __( 'Optimiser les tables de la base de données', 'gi-toolkit' ),
				'callback' => 'route_db_optimize',
			),

			// -- Wordfence ------------------------------------------------------------
			'wordfence_status' => array(
				'category' => __( 'Sécurité (Wordfence)', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/wordfence/status',
				'label'    => __( 'Statut Wordfence (dernier scan, problèmes détectés)', 'gi-toolkit' ),
				'callback' => 'route_wordfence_status',
			),
			'wordfence_scan' => array(
				'category' => __( 'Sécurité (Wordfence)', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/wordfence/scan',
				'label'    => __( 'Déclencher un scan Wordfence', 'gi-toolkit' ),
				'callback' => 'route_wordfence_scan',
			),
			'wordfence_unlock_ip' => array(
				'category' => __( 'Sécurité (Wordfence)', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/wordfence/unlock-ip',
				'label'    => __( 'Débloquer une IP bannie/verrouillée par Wordfence', 'gi-toolkit' ),
				'callback' => 'route_wordfence_unlock_ip',
			),

			// -- UpdraftPlus ----------------------------------------------------------
			'updraft_status' => array(
				'category' => __( 'Sauvegardes (UpdraftPlus)', 'gi-toolkit' ),
				'method'   => 'GET',
				'path'     => '/updraft/status',
				'label'    => __( 'Statut de la dernière sauvegarde UpdraftPlus', 'gi-toolkit' ),
				'callback' => 'route_updraft_status',
			),
			'updraft_backup' => array(
				'category' => __( 'Sauvegardes (UpdraftPlus)', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/updraft/backup',
				'label'    => __( 'Déclencher une sauvegarde UpdraftPlus manuelle', 'gi-toolkit' ),
				'callback' => 'route_updraft_backup',
			),

			// -- Elementor ------------------------------------------------------------
			'elementor_clear_cache' => array(
				'category' => __( 'Elementor', 'gi-toolkit' ),
				'method'   => 'POST',
				'path'     => '/elementor/clear-cache',
				'label'    => __( 'Vider le cache CSS/Files d\'Elementor', 'gi-toolkit' ),
				'callback' => 'route_elementor_clear_cache',
			),
		);
	}

	/**
	 * @return array<string, string> Clé de route => '1' (toutes activées par défaut).
	 */
	private static function default_enabled_routes() {
		$enabled = array();
		foreach ( array_keys( self::route_catalog() ) as $key ) {
			$enabled[ $key ] = '1';
		}
		return $enabled;
	}

	/**
	 * @param string $route_key Clé du catalogue.
	 * @param array<string, mixed> $settings Réglages courants (optionnel, relit si omis).
	 * @return bool
	 */
	public function is_route_enabled( $route_key, $settings = null ) {
		if ( null === $settings ) {
			$settings = $this->get_settings();
		}
		if ( '1' !== (string) ( $settings['enabled'] ?? '1' ) ) {
			return false;
		}
		$enabled_routes = $settings['enabled_routes'] ?? array();
		return '0' !== (string) ( $enabled_routes[ $route_key ] ?? '1' );
	}

	/* ---------------------------------------------------------------- */
	/* Réglages                                                          */
	/* ---------------------------------------------------------------- */

	/**
	 * @return array<string, mixed>
	 */
	private static function default_settings() {
		return array(
			'enabled'        => '1',
			'api_key'        => '',
			'allowed_ips'    => '',
			'rate_limit'     => 60, // requêtes / heure / route.
			'enabled_routes' => self::default_enabled_routes(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$stored   = get_option( self::OPTION_SETTINGS, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );

		// Toute route ajoutée après coup dans le catalogue est activée par défaut
		// même si le site a déjà des réglages enregistrés plus anciens.
		$stored_routes = is_array( $settings['enabled_routes'] ?? null ) ? $settings['enabled_routes'] : array();
		$settings['enabled_routes'] = wp_parse_args( $stored_routes, self::default_enabled_routes() );

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
		$settings             = $this->get_settings();
		$settings['api_key']  = self::generate_key();
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

		$enabled_routes = array();
		foreach ( array_keys( self::route_catalog() ) as $route_key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$enabled_routes[ $route_key ] = isset( $_POST[ 'route_' . $route_key ] ) ? '1' : '0';
		}
		$settings['enabled_routes'] = $enabled_routes;

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
		$catalog  = self::route_catalog();

		$by_category = array();
		foreach ( $catalog as $key => $route ) {
			$by_category[ $route['category'] ][ $key ] = $route;
		}
		?>
		<div class="wrap gi-toolkit-ai-agent-tool-settings">
			<h1><?php esc_html_e( 'AI Agent Tool', 'gi-toolkit' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Expose une API REST dédiée permettant à un agent IA (ex. Hermes Agent) de gérer et corriger ce site : cache, plugins, thèmes, fichiers, utilisateurs, Wordfence, UpdraftPlus, Elementor. Indépendant de MainWP.', 'gi-toolkit' ); ?>
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

					<h2><?php esc_html_e( 'Routes disponibles', 'gi-toolkit' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Toutes les routes sont activées par défaut. Décoche celles que tu ne veux pas exposer sur ce site.', 'gi-toolkit' ); ?></p>

					<?php foreach ( $by_category as $category_name => $routes ) : ?>
						<h3><?php echo esc_html( $category_name ); ?></h3>
						<table class="widefat striped gi-toolkit-ai-agent-routes-table">
							<thead>
								<tr>
									<th style="width:60px;"><?php esc_html_e( 'Actif', 'gi-toolkit' ); ?></th>
									<th style="width:70px;"><?php esc_html_e( 'Méthode', 'gi-toolkit' ); ?></th>
									<th style="width:220px;"><?php esc_html_e( 'Route', 'gi-toolkit' ); ?></th>
									<th><?php esc_html_e( 'Description', 'gi-toolkit' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $routes as $route_key => $route ) : ?>
									<tr>
										<td>
											<label>
												<input type="checkbox" name="route_<?php echo esc_attr( $route_key ); ?>" value="1"
													<?php checked( '1', (string) ( $settings['enabled_routes'][ $route_key ] ?? '1' ) ); ?> />
											</label>
										</td>
										<td><code><?php echo esc_html( $route['method'] ); ?></code></td>
										<td><code>/ai-agent<?php echo esc_html( $route['path'] ); ?></code></td>
										<td><?php echo esc_html( $route['label'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>

					<?php submit_button( __( 'Enregistrer', 'gi-toolkit' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------- */
	/* REST API : enregistrement                                         */
	/* ---------------------------------------------------------------- */

	public function register_routes() {
		$settings = $this->get_settings();
		if ( '1' !== (string) ( $settings['enabled'] ?? '1' ) ) {
			return;
		}

		foreach ( self::route_catalog() as $route_key => $route ) {
			if ( ! $this->is_route_enabled( $route_key, $settings ) ) {
				continue;
			}

			$wp_method = 'GET' === $route['method'] ? WP_REST_Server::READABLE : WP_REST_Server::CREATABLE;

			$args = array();
			if ( 'errors_recent' === $route_key ) {
				$args['lines'] = array(
					'default'           => 100,
					'sanitize_callback' => 'absint',
				);
			}

			register_rest_route(
				self::REST_NAMESPACE,
				'/ai-agent' . $route['path'],
				array(
					'methods'             => $wp_method,
					'callback'            => array( $this, $route['callback'] ),
					'permission_callback' => function ( $request ) use ( $route_key ) {
						return $this->check_permission( $request, $route_key );
					},
					'args'                => $args,
				)
			);
		}
	}

	/**
	 * Authentification par clé API dédiée + toggle de route + IP allowlist + rate limit.
	 *
	 * @param WP_REST_Request $request  Requête.
	 * @param string          $route_key Clé de route (pour double-vérification du toggle).
	 * @return bool|WP_Error
	 */
	public function check_permission( $request, $route_key = '' ) {
		$settings = $this->get_settings();

		if ( '' !== $route_key && ! $this->is_route_enabled( $route_key, $settings ) ) {
			return new WP_Error( 'gi_ai_agent_route_disabled', __( 'Cette route est désactivée sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

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
	 * Rate limit simple basé sur transient (fenêtre glissante horaire).
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
			'ip'     => self::get_client_ip(),
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

	/* ---------------------------------------------------------------- */
	/* Routes : Site & Diagnostic                                        */
	/* ---------------------------------------------------------------- */

	public function route_status( $request ) {
		unset( $request );

		global $wp_version;

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'site_url'         => get_site_url(),
					'wp_version'       => $wp_version,
					'php_version'      => phpversion(),
					'gi_toolkit'       => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : null,
					'is_multisite'     => is_multisite(),
					'caches_detected'  => self::detect_cache_plugins(),
					'stack_detected'   => self::detect_known_stack(),
					'maintenance_mode' => (bool) get_option( 'gi_ai_agent_maintenance_mode', false ),
				),
			)
		);
	}

	public function route_health( $request ) {
		unset( $request );

		$disk_free  = function_exists( 'disk_free_space' ) ? @disk_free_space( ABSPATH ) : null;
		$disk_total = function_exists( 'disk_total_space' ) ? @disk_total_space( ABSPATH ) : null;

		$recent_errors = self::read_debug_log_tail( 20 );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'memory_limit'       => ini_get( 'memory_limit' ),
					'memory_usage'       => size_format( memory_get_usage( true ) ),
					'disk_free_bytes'    => $disk_free,
					'disk_total_bytes'   => $disk_total,
					'recent_error_count' => count( $recent_errors ),
					'recent_errors'      => $recent_errors,
				),
			)
		);
	}

	public function route_errors_recent( $request ) {
		$lines = $request->get_param( 'lines' );
		$lines = $lines ? min( 1000, absint( $lines ) ) : 100;

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'debug_log_enabled' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
					'entries'           => self::read_debug_log_tail( $lines ),
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

		$max_bytes = 512 * 1024;
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

	public function route_cron_list( $request ) {
		unset( $request );

		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			$crons = array();
		}

		$events = array();
		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $instances ) {
				foreach ( (array) $instances as $instance ) {
					$events[] = array(
						'hook'      => $hook,
						'timestamp' => (int) $timestamp,
						'next_run'  => gmdate( 'c', (int) $timestamp ),
						'schedule'  => $instance['schedule'] ?? null,
						'interval'  => $instance['interval'] ?? null,
					);
				}
			}
		}

		usort( $events, function ( $a, $b ) {
			return $a['timestamp'] <=> $b['timestamp'];
		} );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'events' => array_slice( $events, 0, 200 ) ),
			)
		);
	}

	public function route_cron_run( $request ) {
		$hook = sanitize_text_field( (string) $request->get_param( 'hook' ) );
		if ( '' === $hook ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètre "hook" requis.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		if ( ! has_action( $hook ) ) {
			return new WP_Error( 'gi_ai_agent_hook_not_found', __( 'Aucun callback enregistré pour ce hook.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		do_action( $hook );
		self::audit_log( 'cron_run', array( 'hook' => $hook ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'hook' => $hook, 'message' => __( 'Hook exécuté.', 'gi-toolkit' ) ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Cache                                                     */
	/* ---------------------------------------------------------------- */

	public function route_cache_purge( $request ) {
		unset( $request );

		$results = array();

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$results['wp_rocket'] = true;
		}
		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}

		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix ?? '', true );
			$results['wp_super_cache'] = true;
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$results['w3_total_cache'] = true;
		}

		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
			$results['litespeed_cache'] = true;
		} elseif ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$results['litespeed_cache'] = true;
		}

		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
			$results['wp_fastest_cache'] = true;
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results['object_cache'] = true;
		}

		do_action( 'gi_toolkit_ai_agent_cache_purge' );

		self::audit_log( 'cache_purge', array( 'results' => $results ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'message' => empty( $results )
						? __( 'Aucun plugin de cache connu détecté ; rien à purger.', 'gi-toolkit' )
						: __( 'Cache purgé.', 'gi-toolkit' ),
					'results' => $results,
				),
			)
		);
	}

	public function route_cache_status( $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'caches_detected' => self::detect_cache_plugins() ),
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

	/**
	 * Détection du stack standard PAPAMICA (UpdraftPlus, Wordfence, Elementor,
	 * WP Rocket, MainWP Child) pour que l'agent sache immédiatement quels
	 * outils sont disponibles sans avoir à tester chaque route à l'aveugle.
	 *
	 * @return array<string, bool>
	 */
	private static function detect_known_stack() {
		return array(
			'updraftplus'    => class_exists( 'UpdraftPlus' ),
			'wordfence'      => class_exists( 'wfConfig' ) || defined( 'WORDFENCE_VERSION' ),
			'elementor'      => did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ),
			'wp_rocket'      => function_exists( 'rocket_clean_domain' ),
			'mainwp_child'   => defined( 'MAINWP_CHILD_FILE' ) || class_exists( 'MainWP_Child' ) || class_exists( 'MainWP\Child\MainWP_Child' ),
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Plugins & Thèmes                                          */
	/* ---------------------------------------------------------------- */

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
				'slug'              => $slug,
				'name'              => $data['Name'] ?? $slug,
				'version'           => $data['Version'] ?? '',
				'active'            => in_array( $slug, $active_plugins, true ),
				'update_available'  => isset( $updates[ $slug ] ),
				'new_version'       => isset( $updates[ $slug ]->update->new_version ) ? $updates[ $slug ]->update->new_version : null,
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'plugins' => $plugins ),
			)
		);
	}

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
				'data'    => array( 'slug' => $slug, 'action' => $action ),
			)
		);
	}

	/**
	 * Met à jour un plugin déjà installé vers sa dernière version disponible
	 * (WordPress.org ou un fournisseur de mise à jour tiers déjà configuré,
	 * ex. licence premium enregistrée sur le site).
	 */
	public function route_plugins_update( $request ) {
		$slug = sanitize_text_field( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètre "slug" requis.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! in_array( $slug, array_keys( get_plugins() ), true ) ) {
			return new WP_Error( 'gi_ai_agent_plugin_not_found', __( 'Plugin introuvable.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		wp_update_plugins(); // Force le rafraîchissement des infos de mise à jour avant tentative.

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		try {
			$was_active = is_plugin_active( $slug );
			$result     = $upgrader->upgrade( $slug );
		} catch ( Throwable $e ) {
			return new WP_Error( 'gi_ai_agent_update_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $result ) ) {
			self::audit_log( 'plugin_update_failed', array( 'slug' => $slug, 'error' => $result->get_error_message() ) );
			return $result;
		}
		if ( false === $result ) {
			self::audit_log( 'plugin_update_failed', array( 'slug' => $slug, 'error' => 'upgrade() returned false' ) );
			return new WP_Error( 'gi_ai_agent_update_failed', __( 'La mise à jour a échoué (aucun détail disponible).', 'gi-toolkit' ), array( 'status' => 500 ) );
		}

		if ( $was_active && ! is_plugin_active( $slug ) ) {
			activate_plugin( $slug );
		}

		self::audit_log( 'plugin_update', array( 'slug' => $slug ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'slug' => $slug, 'message' => __( 'Plugin mis à jour.', 'gi-toolkit' ) ),
			)
		);
	}

	public function route_plugins_delete( $request ) {
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$slug = sanitize_text_field( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètre "slug" requis.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		if ( is_plugin_active( $slug ) ) {
			return new WP_Error( 'gi_ai_agent_plugin_active', __( 'Désactive le plugin avant de le supprimer.', 'gi-toolkit' ), array( 'status' => 409 ) );
		}

		$result = delete_plugins( array( $slug ) );
		if ( is_wp_error( $result ) ) {
			self::audit_log( 'plugin_delete_failed', array( 'slug' => $slug, 'error' => $result->get_error_message() ) );
			return $result;
		}

		self::audit_log( 'plugin_delete', array( 'slug' => $slug ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'slug' => $slug, 'message' => __( 'Plugin supprimé.', 'gi-toolkit' ) ),
			)
		);
	}

	public function route_themes_list( $request ) {
		unset( $request );

		$themes = wp_get_themes();
		$active = get_stylesheet();

		$data = array();
		foreach ( $themes as $slug => $theme ) {
			$data[] = array(
				'slug'    => $slug,
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'active'  => $slug === $active,
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'themes' => $data ),
			)
		);
	}

	public function route_themes_activate( $request ) {
		$slug = sanitize_text_field( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètre "slug" requis.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'gi_ai_agent_theme_not_found', __( 'Thème introuvable.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		switch_theme( $slug );
		self::audit_log( 'theme_activate', array( 'slug' => $slug ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'slug' => $slug, 'message' => __( 'Thème activé.', 'gi-toolkit' ) ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Fichiers (restreint à wp-content, avec sauvegarde auto)   */
	/* ---------------------------------------------------------------- */

	/**
	 * Résout un chemin relatif (fourni par l'appelant) en chemin absolu
	 * sûr à l'intérieur de wp-content, ou retourne WP_Error.
	 *
	 * @param string $relative_path Chemin relatif fourni par l'appelant.
	 * @return string|WP_Error
	 */
	private static function resolve_safe_path( $relative_path ) {
		$relative_path = str_replace( '\\', '/', (string) $relative_path );
		$relative_path = ltrim( $relative_path, '/' );

		if ( '' === $relative_path || false !== strpos( $relative_path, '..' ) ) {
			return new WP_Error( 'gi_ai_agent_invalid_path', __( 'Chemin invalide.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		$base = realpath( WP_CONTENT_DIR );
		$full = $base . '/' . $relative_path;
		$real = realpath( dirname( $full ) );

		if ( false === $real || 0 !== strpos( $real, $base ) ) {
			return new WP_Error( 'gi_ai_agent_invalid_path', __( 'Chemin en dehors de wp-content.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		return $real . '/' . basename( $full );
	}

	public function route_files_list( $request ) {
		$rel_path = (string) $request->get_param( 'path' );
		$resolved = self::resolve_safe_path( '' === $rel_path ? '.' : $rel_path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		if ( ! is_dir( $resolved ) ) {
			return new WP_Error( 'gi_ai_agent_not_a_directory', __( 'Ce chemin n\'est pas un dossier.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		$entries = array();
		foreach ( scandir( $resolved ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$entry_path = $resolved . '/' . $entry;
			$entries[]  = array(
				'name'     => $entry,
				'type'     => is_dir( $entry_path ) ? 'dir' : 'file',
				'size'     => is_file( $entry_path ) ? filesize( $entry_path ) : null,
				'modified' => gmdate( 'c', filemtime( $entry_path ) ),
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'path' => $rel_path, 'entries' => $entries ),
			)
		);
	}

	public function route_files_read( $request ) {
		$rel_path = (string) $request->get_param( 'path' );
		$resolved = self::resolve_safe_path( $rel_path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		if ( ! is_file( $resolved ) || ! is_readable( $resolved ) ) {
			return new WP_Error( 'gi_ai_agent_file_not_found', __( 'Fichier introuvable ou illisible.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		$size = filesize( $resolved );
		$truncated = $size > self::MAX_FILE_READ_BYTES;
		$content = $truncated
			? file_get_contents( $resolved, false, null, 0, self::MAX_FILE_READ_BYTES )
			: file_get_contents( $resolved );

		self::audit_log( 'file_read', array( 'path' => $rel_path ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'path'      => $rel_path,
					'size'      => $size,
					'truncated' => $truncated,
					'content'   => $content,
				),
			)
		);
	}

	public function route_files_write( $request ) {
		$rel_path = (string) $request->get_param( 'path' );
		$content  = (string) $request->get_param( 'content' );

		$resolved = self::resolve_safe_path( $rel_path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$extension = strtolower( pathinfo( $resolved, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::ALLOWED_FILE_EXTENSIONS, true ) ) {
			return new WP_Error(
				'gi_ai_agent_extension_not_allowed',
				sprintf(
					/* translators: %s: comma-separated list of allowed extensions */
					__( 'Extension non autorisée. Extensions permises : %s', 'gi-toolkit' ),
					implode( ', ', self::ALLOWED_FILE_EXTENSIONS )
				),
				array( 'status' => 400 )
			);
		}

		$backup_info = null;
		if ( file_exists( $resolved ) ) {
			$backup_dir = WP_CONTENT_DIR . '/' . self::BACKUP_DIR_NAME;
			if ( ! is_dir( $backup_dir ) ) {
				wp_mkdir_p( $backup_dir );
			}
			$backup_name = gmdate( 'Ymd-His' ) . '__' . str_replace( '/', '_', ltrim( $rel_path, '/' ) ) . '.bak';
			$backup_path = $backup_dir . '/' . $backup_name;
			if ( ! copy( $resolved, $backup_path ) ) {
				return new WP_Error( 'gi_ai_agent_backup_failed', __( 'Impossible de créer la sauvegarde avant écriture ; annulation par sécurité.', 'gi-toolkit' ), array( 'status' => 500 ) );
			}
			$backup_info = str_replace( WP_CONTENT_DIR . '/', '', $backup_path );
		}

		$written = file_put_contents( $resolved, $content );
		if ( false === $written ) {
			return new WP_Error( 'gi_ai_agent_write_failed', __( 'Échec de l\'écriture du fichier.', 'gi-toolkit' ), array( 'status' => 500 ) );
		}

		self::audit_log( 'file_write', array( 'path' => $rel_path, 'backup' => $backup_info, 'bytes' => $written ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'path'          => $rel_path,
					'bytes_written' => $written,
					'backup'        => $backup_info,
				),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Utilisateurs                                             */
	/* ---------------------------------------------------------------- */

	public function route_users_list( $request ) {
		unset( $request );

		$users = get_users( array( 'number' => 200 ) );
		$data  = array();
		foreach ( $users as $user ) {
			$data[] = array(
				'id'    => $user->ID,
				'login' => $user->user_login,
				'email' => $user->user_email,
				'roles' => $user->roles,
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'users' => $data ),
			)
		);
	}

	public function route_users_create( $request ) {
		$username = sanitize_user( (string) $request->get_param( 'username' ) );
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$role     = sanitize_key( (string) ( $request->get_param( 'role' ) ?: 'administrator' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $username || ! is_email( $email ) ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'username et email valides requis.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}
		if ( username_exists( $username ) ) {
			return new WP_Error( 'gi_ai_agent_user_exists', __( 'Ce nom d\'utilisateur existe déjà.', 'gi-toolkit' ), array( 'status' => 409 ) );
		}
		if ( '' === $password ) {
			$password = wp_generate_password( 20, true );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'role'       => $role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			self::audit_log( 'user_create_failed', array( 'username' => $username, 'error' => $user_id->get_error_message() ) );
			return $user_id;
		}

		self::audit_log( 'user_create', array( 'username' => $username, 'role' => $role ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'user_id' => $user_id, 'username' => $username, 'password' => $password ),
			)
		);
	}

	public function route_users_reset_password( $request ) {
		$identifier = (string) $request->get_param( 'user' );
		if ( '' === $identifier ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètre "user" requis (ID, login ou email).', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		$user = is_numeric( $identifier ) ? get_user_by( 'id', (int) $identifier ) : get_user_by( 'login', $identifier );
		if ( ! $user ) {
			$user = get_user_by( 'email', $identifier );
		}
		if ( ! $user ) {
			return new WP_Error( 'gi_ai_agent_user_not_found', __( 'Utilisateur introuvable.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		$new_password = (string) $request->get_param( 'password' );
		if ( '' === $new_password ) {
			$new_password = wp_generate_password( 20, true );
		}

		reset_password( $user, $new_password );
		self::audit_log( 'user_reset_password', array( 'user_id' => $user->ID ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'user_id' => $user->ID, 'login' => $user->user_login, 'password' => $new_password ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Maintenance                                              */
	/* ---------------------------------------------------------------- */

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

	public function route_db_optimize( $request ) {
		unset( $request );

		global $wpdb;

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$optimized = array();
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $table ) . '`' );
			$optimized[ $table ] = false !== $result;
		}

		self::audit_log( 'db_optimize', array( 'table_count' => count( $optimized ) ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'optimized_tables' => $optimized ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Wordfence                                                 */
	/* ---------------------------------------------------------------- */

	/**
	 * @return bool
	 */
	private static function wordfence_available() {
		return class_exists( 'wfConfig' ) || defined( 'WORDFENCE_VERSION' );
	}

	public function route_wordfence_status( $request ) {
		unset( $request );

		if ( ! self::wordfence_available() ) {
			return new WP_Error( 'gi_ai_agent_wordfence_not_found', __( 'Wordfence n\'est pas actif sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		global $wpdb;

		$data = array( 'wordfence_active' => true );

		if ( class_exists( 'wfConfig' ) && method_exists( 'wfConfig', 'get' ) ) {
			try {
				$data['last_scan_completed'] = wfConfig::get( 'lastScanCompleted', null );
				$data['scan_running']        = (bool) wfConfig::get( 'wfScanEngineIsRunning', false );
			} catch ( Throwable $e ) {
				$data['config_read_error'] = $e->getMessage();
			}
		}

		$issues_table = $wpdb->prefix . 'wfIssues';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $issues_table ) ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data['open_issues_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$issues_table}` WHERE status = 'new'" );
		}

		$lockout_table = $wpdb->prefix . 'wfLockedOut';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lockout_table ) ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data['locked_out_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$lockout_table}`" );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	public function route_wordfence_scan( $request ) {
		unset( $request );

		if ( ! self::wordfence_available() ) {
			return new WP_Error( 'gi_ai_agent_wordfence_not_found', __( 'Wordfence n\'est pas actif sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		try {
			if ( class_exists( 'wordfence' ) && method_exists( 'wordfence', 'startScan' ) ) {
				wordfence::startScan( true );
			} elseif ( has_action( 'wordfence_doScan' ) ) {
				do_action( 'wordfence_doScan' );
			} else {
				return new WP_Error( 'gi_ai_agent_wordfence_scan_unavailable', __( 'Impossible de déclencher le scan avec cette version de Wordfence.', 'gi-toolkit' ), array( 'status' => 501 ) );
			}
		} catch ( Throwable $e ) {
			return new WP_Error( 'gi_ai_agent_wordfence_scan_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		self::audit_log( 'wordfence_scan_triggered' );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'message' => __( 'Scan Wordfence déclenché.', 'gi-toolkit' ) ),
			)
		);
	}

	public function route_wordfence_unlock_ip( $request ) {
		if ( ! self::wordfence_available() ) {
			return new WP_Error( 'gi_ai_agent_wordfence_not_found', __( 'Wordfence n\'est pas actif sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		$ip = sanitize_text_field( (string) $request->get_param( 'ip' ) );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'gi_ai_agent_invalid_params', __( 'Paramètre "ip" invalide.', 'gi-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$deleted_rows = 0;

		foreach ( array( 'wfLockedOut', 'wfBlockedIPLog', 'wfBlocks7' ) as $table_suffix ) {
			$table = $wpdb->prefix . $table_suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				continue;
			}
			foreach ( array( 'IP', 'ip' ) as $column ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = @$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `{$column}` = %s", $ip ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false !== $result ) {
					$deleted_rows += (int) $result;
				}
			}
		}

		self::audit_log( 'wordfence_unlock_ip', array( 'ip' => $ip, 'rows_deleted' => $deleted_rows ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'ip' => $ip, 'rows_deleted' => $deleted_rows ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : UpdraftPlus                                              */
	/* ---------------------------------------------------------------- */

	public function route_updraft_status( $request ) {
		unset( $request );

		if ( ! class_exists( 'UpdraftPlus' ) ) {
			return new WP_Error( 'gi_ai_agent_updraft_not_found', __( 'UpdraftPlus n\'est pas actif sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		$last_backup    = get_option( 'updraft_last_backup', array() );
		$backup_history = get_option( 'updraft_backup_history', array() );

		$sets = array();
		if ( is_array( $backup_history ) ) {
			foreach ( array_slice( array_keys( $backup_history ), -5 ) as $timestamp ) {
				$sets[] = array(
					'timestamp' => (int) $timestamp,
					'date'      => gmdate( 'c', (int) $timestamp ),
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'last_backup'    => $last_backup,
					'recent_backups' => $sets,
				),
			)
		);
	}

	public function route_updraft_backup( $request ) {
		unset( $request );

		if ( ! class_exists( 'UpdraftPlus' ) ) {
			return new WP_Error( 'gi_ai_agent_updraft_not_found', __( 'UpdraftPlus n\'est pas actif sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		global $updraftplus;

		try {
			if ( $updraftplus && method_exists( $updraftplus, 'boot_backup' ) ) {
				$updraftplus->boot_backup( true, true, false );
			} elseif ( function_exists( 'updraft_backupnow_backup_all' ) ) {
				updraft_backupnow_backup_all();
			} else {
				return new WP_Error( 'gi_ai_agent_updraft_backup_unavailable', __( 'Impossible de déclencher une sauvegarde avec cette version d\'UpdraftPlus.', 'gi-toolkit' ), array( 'status' => 501 ) );
			}
		} catch ( Throwable $e ) {
			return new WP_Error( 'gi_ai_agent_updraft_backup_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		self::audit_log( 'updraft_backup_triggered' );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'message' => __( 'Sauvegarde UpdraftPlus déclenchée (traitement en arrière-plan).', 'gi-toolkit' ) ),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Routes : Elementor                                                */
	/* ---------------------------------------------------------------- */

	public function route_elementor_clear_cache( $request ) {
		unset( $request );

		$elementor_active = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
		if ( ! $elementor_active ) {
			return new WP_Error( 'gi_ai_agent_elementor_not_found', __( 'Elementor n\'est pas actif sur ce site.', 'gi-toolkit' ), array( 'status' => 404 ) );
		}

		try {
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} else {
				do_action( 'elementor/core/files/clear_cache' );
			}
		} catch ( Throwable $e ) {
			return new WP_Error( 'gi_ai_agent_elementor_clear_cache_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		self::audit_log( 'elementor_clear_cache' );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'message' => __( 'Cache Elementor vidé.', 'gi-toolkit' ) ),
			)
		);
	}
}
