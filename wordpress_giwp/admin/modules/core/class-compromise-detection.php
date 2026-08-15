<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Compromise Detection
 * Description: Surveille les signes de compromission et envoie des alertes Pushover.
 *
 * @since 2.29.9
 */
class Gi_Toolkit_Compromise_Detection {

	const OPTION_SETTINGS = 'gi_toolkit_settings_compromise_detection';

	const OPTION_ALERTS = 'gi_toolkit_compromise_alerts';

	const OPTION_PAUSE = 'gi_toolkit_compromise_pause_until';

	const CRON_HOOK = 'gi_toolkit_compromise_detection_cron';

	const CRON_SCHEDULE = 'gi_toolkit_every_minute';

	const DEDUP_TTL = 1800;

	const ALERTS_MAX = 40;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $option_id;

	/**
	 * @var string
	 */
	private $header_title = '';

	/**
	 * @var string
	 */
	private $nonce_action;

	/**
	 * @var array<string, mixed>|null
	 */
	private $settings = null;

	public function __construct() {
		$this->option_id    = self::OPTION_SETTINGS;
		$this->nonce_action = self::OPTION_SETTINGS . '_action';

		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		self::load_helpers();

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );

		if ( self::is_module_enabled() ) {
			add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
			add_action( self::CRON_HOOK, array( $this, 'run_scan' ) );
			$this->register_realtime_hooks();
		}
	}

	/**
	 * @return void
	 */
	public static function load_helpers() {
		$dir = GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/compromise-detection/';
		require_once $dir . 'class-pushover.php';
		require_once $dir . 'class-monitor.php';
	}

	/**
	 * @return bool
	 */
	public static function is_module_enabled() {
		$opts = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		return is_array( $opts ) && isset( $opts[ __CLASS__ ] ) && '1' === (string) $opts[ __CLASS__ ];
	}

	/**
	 * @return void
	 */
	public function class_init() {
		$this->header_title = esc_html__( 'Détection de compromission', 'gi-toolkit' );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules Schedules WP.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_cron_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Toutes les minutes (GI-Toolkit)', 'gi-toolkit' ),
			);
		}
		return $schedules;
	}

	/**
	 * @return void
	 */
	public static function activate() {
		self::load_helpers();
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );
		Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot(
			Gi_Toolkit_Compromise_Detection_Monitor::build_snapshot()
		);
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * @return void
	 */
	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	private function register_realtime_hooks() {
		add_action( 'user_register', array( $this, 'on_user_register' ), 20 );
		add_action( 'set_user_role', array( $this, 'on_set_user_role' ), 20, 3 );
		add_action( 'add_user_role', array( $this, 'on_add_user_role' ), 20, 2 );
		add_action( 'delete_user', array( $this, 'on_delete_user' ), 10, 1 );
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 20, 2 );
		add_action( 'after_password_reset', array( $this, 'on_password_reset' ), 20, 1 );
		add_action( 'wp_insert_post', array( $this, 'on_insert_post' ), 20, 3 );
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 20, 3 );
		add_action( 'trashed_post', array( $this, 'on_trashed_post' ), 20 );
		add_action( 'before_delete_post', array( $this, 'on_deleted_post' ), 20 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 20, 2 );
		add_action( 'activated_plugin', array( $this, 'on_plugin_change' ), 20 );
		add_action( 'deleted_plugin', array( $this, 'on_plugin_change' ), 20 );
		add_action( 'switch_theme', array( $this, 'on_theme_change' ), 20 );
		add_action( 'deleted_theme', array( $this, 'on_theme_change' ), 20 );
		add_filter( 'pre_http_request', array( 'Gi_Toolkit_Compromise_Detection_Monitor', 'inspect_http_request' ), 5, 3 );
		add_action( 'wp_login_failed', array( 'Gi_Toolkit_Compromise_Detection_Monitor', 'record_failed_login' ), 20 );
		add_action( 'update_option_siteurl', array( $this, 'on_site_option_change' ), 20, 2 );
		add_action( 'update_option_home', array( $this, 'on_site_option_change' ), 20, 2 );
		add_action( 'update_option_admin_email', array( $this, 'on_site_option_change' ), 20, 2 );
		add_action( 'update_option_users_can_register', array( $this, 'on_site_option_change' ), 20, 2 );
	}

	/**
	 * Scan cron (toutes les minutes) : compare l’état actuel au snapshot.
	 *
	 * @return void
	 */
	public function run_scan() {
		self::load_helpers();

		$old = Gi_Toolkit_Compromise_Detection_Monitor::get_snapshot();
		$new = Gi_Toolkit_Compromise_Detection_Monitor::build_snapshot();

		if ( empty( $old ) ) {
			Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot( $new );
			return;
		}

		if ( self::is_paused() ) {
			Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot( $new );
			return;
		}

		$alerts = Gi_Toolkit_Compromise_Detection_Monitor::diff_snapshots( $old, $new );
		foreach ( $alerts as $alert ) {
			self::raise_alert( $alert['type'], $alert['summary'], $alert['details'] );
		}

		Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot( $new );
	}

	/**
	 * @param int $user_id ID.
	 * @return void
	 */
	public function on_user_register( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		if ( in_array( 'administrator', $roles, true ) || user_can( $user, 'manage_options' ) ) {
			self::raise_alert(
				'watch_admin_user',
				__( 'Nouvel utilisateur administrateur détecté', 'gi-toolkit' ),
				$user->user_login . ' (#' . (int) $user_id . ')'
			);
		} elseif ( array_intersect( $roles, array( 'editor', 'shop_manager' ) ) ) {
			self::raise_alert(
				'watch_role_elevation',
				__( 'Nouvel utilisateur privilégié', 'gi-toolkit' ),
				$user->user_login . ' — ' . implode( ', ', $roles )
			);
		}
	}

	/**
	 * @param int      $user_id ID.
	 * @param string   $role    Nouveau rôle.
	 * @param string[] $old     Anciens rôles.
	 * @return void
	 */
	public function on_set_user_role( $user_id, $role, $old = array() ) {
		$old = is_array( $old ) ? $old : array();
		if ( in_array( $role, $old, true ) ) {
			return;
		}
		$user  = get_userdata( (int) $user_id );
		$login = $user ? $user->user_login : '#' . (int) $user_id;
		if ( 'administrator' === $role ) {
			self::raise_alert(
				'watch_admin_user',
				__( 'Nouvel utilisateur administrateur détecté', 'gi-toolkit' ),
				$login . ' (rôle administrator)'
			);
			return;
		}
		if ( in_array( $role, array( 'editor', 'shop_manager' ), true ) ) {
			self::raise_alert(
				'watch_role_elevation',
				__( 'Élévation de privilèges', 'gi-toolkit' ),
				$login . ' → ' . $role
			);
		}
	}

	/**
	 * @param int    $user_id ID.
	 * @param string $role    Rôle ajouté.
	 * @return void
	 */
	public function on_add_user_role( $user_id, $role ) {
		$this->on_set_user_role( $user_id, $role, array() );
	}

	/**
	 * @param int $user_id ID.
	 * @return void
	 */
	public function on_delete_user( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		if ( in_array( 'administrator', $roles, true ) || user_can( $user, 'manage_options' ) ) {
			self::raise_alert(
				'watch_user_deleted',
				__( 'Administrateur supprimé ou rétrogradé', 'gi-toolkit' ),
				$user->user_login . ' (#' . (int) $user_id . ')'
			);
		}
	}

	/**
	 * @param int     $user_id ID.
	 * @param WP_User $old     Ancien objet user.
	 * @return void
	 */
	public function on_profile_update( $user_id, $old ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! $old instanceof WP_User ) {
			return;
		}
		if ( $user->user_pass === $old->user_pass ) {
			return;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		$priv  = in_array( 'administrator', $roles, true )
			|| in_array( 'editor', $roles, true )
			|| user_can( $user, 'manage_options' )
			|| user_can( $user, 'install_plugins' );
		if ( ! $priv ) {
			return;
		}
		self::raise_alert(
			'watch_password',
			__( 'Mot de passe modifié (compte privilégié)', 'gi-toolkit' ),
			$user->user_login . ' (#' . (int) $user_id . ')'
		);
	}

	/**
	 * @param WP_User $user Utilisateur.
	 * @return void
	 */
	public function on_password_reset( $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		$priv  = in_array( 'administrator', $roles, true )
			|| in_array( 'editor', $roles, true )
			|| user_can( $user, 'manage_options' )
			|| user_can( $user, 'install_plugins' );
		if ( ! $priv ) {
			return;
		}
		self::raise_alert(
			'watch_password',
			__( 'Mot de passe modifié (compte privilégié)', 'gi-toolkit' ),
			$user->user_login . ' (#' . (int) $user->ID . ')'
		);
	}

	/**
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Mise à jour ?
	 * @return void
	 */
	public function on_insert_post( $post_id, $post, $update ) {
		if ( $update || ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}
		self::raise_alert(
			'watch_pages',
			__( 'Page(s) ajoutée(s)', 'gi-toolkit' ),
			$post->post_title . ' (#' . (int) $post_id . ')'
		);
	}

	/**
	 * @param string  $new  Nouveau statut.
	 * @param string  $old  Ancien statut.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function on_transition_post_status( $new, $old, $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}
		if ( in_array( $new, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}
		if ( in_array( $old, array( 'new', 'auto-draft' ), true ) && 'trash' !== $new ) {
			self::raise_alert(
				'watch_pages',
				__( 'Page(s) ajoutée(s)', 'gi-toolkit' ),
				$post->post_title . ' (#' . (int) $post->ID . ')'
			);
		}
	}

	/**
	 * @param int $post_id ID.
	 * @return void
	 */
	public function on_trashed_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}
		self::raise_alert(
			'watch_pages',
			__( 'Page(s) supprimée(s)', 'gi-toolkit' ),
			$post->post_title . ' (#' . (int) $post_id . ')'
		);
	}

	/**
	 * @param int $post_id ID.
	 * @return void
	 */
	public function on_deleted_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
			return;
		}
		self::raise_alert(
			'watch_pages',
			__( 'Page(s) supprimée(s)', 'gi-toolkit' ),
			$post->post_title . ' (#' . (int) $post_id . ')'
		);
	}

	/**
	 * @param WP_Upgrader          $upgrader Upgrader.
	 * @param array<string, mixed> $options  Options.
	 * @return void
	 */
	public function on_upgrader_complete( $upgrader, $options ) {
		unset( $upgrader );
		$type   = isset( $options['type'] ) ? (string) $options['type'] : '';
		$action = isset( $options['action'] ) ? (string) $options['action'] : '';
		if ( 'install' !== $action ) {
			return;
		}
		if ( 'plugin' === $type ) {
			$slug = '';
			if ( ! empty( $options['plugin'] ) ) {
				$slug = (string) $options['plugin'];
			} elseif ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
				$slug = implode( ', ', $options['plugins'] );
			}
			self::raise_alert(
				'watch_plugins_themes',
				__( 'Extension(s) ajoutée(s)', 'gi-toolkit' ),
				$slug ? $slug : __( 'installation plugin', 'gi-toolkit' )
			);
		}
		if ( 'theme' === $type ) {
			$slug = isset( $options['theme'] ) ? (string) $options['theme'] : __( 'installation thème', 'gi-toolkit' );
			self::raise_alert(
				'watch_plugins_themes',
				__( 'Thème(s) ajouté(s)', 'gi-toolkit' ),
				$slug
			);
		}
	}

	/**
	 * @return void
	 */
	public function on_plugin_change() {
		// Le cron comparera la liste ; on déclenche un scan léger après coup.
		wp_schedule_single_event( time() + 5, self::CRON_HOOK );
	}

	/**
	 * @return void
	 */
	public function on_theme_change() {
		wp_schedule_single_event( time() + 5, self::CRON_HOOK );
	}

	/**
	 * @param mixed $old Ancienne valeur.
	 * @param mixed $new Nouvelle valeur.
	 * @return void
	 */
	public function on_site_option_change( $old, $new ) {
		if ( (string) $old === (string) $new ) {
			return;
		}
		$labels = array(
			'update_option_siteurl'            => __( 'URL du site (siteurl) modifiée', 'gi-toolkit' ),
			'update_option_home'               => __( 'URL d’accueil (home) modifiée', 'gi-toolkit' ),
			'update_option_admin_email'        => __( 'E-mail administrateur modifié', 'gi-toolkit' ),
			'update_option_users_can_register' => __( 'Option « inscription ouverte » modifiée', 'gi-toolkit' ),
		);
		$filter = current_filter();
		$summary = isset( $labels[ $filter ] ) ? $labels[ $filter ] : __( 'Option sensible du site modifiée', 'gi-toolkit' );
		self::raise_alert(
			'watch_site_options',
			$summary,
			(string) $old . ' → ' . (string) $new
		);
	}

	/**
	 * @param string $type    Clé de surveillance.
	 * @param string $summary Titre.
	 * @param string $details Détails.
	 * @return void
	 */
	public static function raise_alert( $type, $summary, $details = '' ) {
		self::load_helpers();

		$instance = self::$instance;
		$settings = $instance ? $instance->get_settings() : self::read_settings();

		if ( empty( $settings[ $type ] ) || '1' !== (string) $settings[ $type ] ) {
			return;
		}

		if ( self::is_paused() ) {
			return;
		}

		$fingerprint = md5( $type . '|' . $summary . '|' . $details );
		if ( get_transient( 'gi_toolkit_compromise_dedup_' . $fingerprint ) ) {
			return;
		}
		set_transient( 'gi_toolkit_compromise_dedup_' . $fingerprint, 1, self::DEDUP_TTL );

		$entry = array(
			'time'    => time(),
			'type'    => sanitize_key( $type ),
			'summary' => sanitize_text_field( $summary ),
			'details' => sanitize_text_field( $details ),
		);
		self::store_alert( $entry );

		if ( class_exists( 'Gi_Toolkit_Security' ) ) {
			Gi_Toolkit_Security::log(
				'compromise_alert',
				array(
					'type'    => $type,
					'summary' => $summary,
					'details' => $details,
				)
			);
		}

		Gi_Toolkit_Compromise_Detection_Pushover::send_alert(
			$settings,
			$type,
			$summary,
			$details
		);
	}

	/**
	 * @param array<string, mixed> $entry Alerte.
	 * @return void
	 */
	private static function store_alert( $entry ) {
		$log = get_option( self::OPTION_ALERTS, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::ALERTS_MAX );
		update_option( self::OPTION_ALERTS, $log, false );
	}

	/**
	 * @return bool
	 */
	public static function is_paused() {
		$until = (int) get_option( self::OPTION_PAUSE, 0 );
		return $until > time();
	}

	/**
	 * @return int Timestamp de fin, 0 si inactif.
	 */
	public static function pause_until() {
		$until = (int) get_option( self::OPTION_PAUSE, 0 );
		return $until > time() ? $until : 0;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings() {
		self::load_helpers();
		return array(
			'pushover_app_token'    => '',
			'pushover_user_key'     => '',
			'pushover_device'       => '',
			'pushover_title'        => Gi_Toolkit_Compromise_Detection_Pushover::default_title_template(),
			'pushover_message'      => Gi_Toolkit_Compromise_Detection_Pushover::default_message_template(),
			'watch_admin_user'      => '1',
			'watch_pages'           => '1',
			'watch_password'        => '1',
			'watch_plugins_themes'  => '1',
			'watch_outbound'        => '1',
			'watch_role_elevation'  => '1',
			'watch_user_deleted'    => '1',
			'watch_php_uploads'     => '1',
			'watch_mu_dropins'      => '1',
			'watch_site_options'    => '1',
			'watch_core_files'      => '1',
			'watch_login_spike'     => '1',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function watch_labels() {
		return array(
			'watch_admin_user'     => __( 'Ajout d’un utilisateur administrateur', 'gi-toolkit' ),
			'watch_pages'          => __( 'Ajout ou suppression de page', 'gi-toolkit' ),
			'watch_password'       => __( 'Changement de mot de passe (comptes privilégiés)', 'gi-toolkit' ),
			'watch_plugins_themes' => __( 'Ajout ou suppression d’extension / thème', 'gi-toolkit' ),
			'watch_outbound'       => __( 'Requêtes sortantes suspectes (scan d’autres sites)', 'gi-toolkit' ),
			'watch_role_elevation' => __( 'Élévation de privilèges (éditeur, shop manager…)', 'gi-toolkit' ),
			'watch_user_deleted'   => __( 'Suppression ou rétrogradation d’administrateur', 'gi-toolkit' ),
			'watch_php_uploads'    => __( 'Fichiers PHP dans le dossier uploads', 'gi-toolkit' ),
			'watch_mu_dropins'     => __( 'Must-use plugins et drop-ins (object-cache.php…)', 'gi-toolkit' ),
			'watch_site_options'   => __( 'URL du site, e-mail admin, inscription ouverte', 'gi-toolkit' ),
			'watch_core_files'     => __( 'Modification de wp-config.php, .htaccess ou index.php', 'gi-toolkit' ),
			'watch_login_spike'    => __( 'Pic de connexions échouées (≥ 25 / minute)', 'gi-toolkit' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}
		$this->settings = self::read_settings();
		return $this->settings;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function read_settings() {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
	}

	/**
	 * @param array<string, mixed> $new_settings Réglages.
	 * @return void
	 */
	public function save_settings( $new_settings ) {
		$sanitized      = $this->sanitize_settings( $new_settings );
		$this->settings = $sanitized;
		update_option( self::OPTION_SETTINGS, $sanitized );
	}

	/**
	 * @param array<string, mixed> $new_settings Brut.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $new_settings ) {
		if ( ! is_array( $new_settings ) ) {
			$new_settings = array();
		}
		$defaults = self::default_settings();
		$out      = array();

		$out['pushover_app_token'] = sanitize_text_field( $new_settings['pushover_app_token'] ?? $defaults['pushover_app_token'] );
		$out['pushover_user_key']  = sanitize_text_field( $new_settings['pushover_user_key'] ?? $defaults['pushover_user_key'] );
		$out['pushover_device']    = sanitize_text_field( $new_settings['pushover_device'] ?? $defaults['pushover_device'] );
		$title                     = sanitize_text_field( $new_settings['pushover_title'] ?? $defaults['pushover_title'] );
		$out['pushover_title']     = '' !== $title ? $title : $defaults['pushover_title'];
		$message                   = sanitize_textarea_field( $new_settings['pushover_message'] ?? $defaults['pushover_message'] );
		$out['pushover_message']   = '' !== $message ? $message : $defaults['pushover_message'];

		foreach ( array_keys( self::watch_labels() ) as $key ) {
			$out[ $key ] = ( isset( $new_settings[ $key ] ) && '1' === (string) $new_settings[ $key ] ) ? '1' : '0';
		}

		return $out;
	}

	/**
	 * @return void
	 */
	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-compromise-detection',
			array( $this, 'render_submenu' ),
			null
		);
	}

	/**
	 * @return void
	 */
	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$assets = include GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/hide-admin-bar.asset.php';
		wp_enqueue_style(
			'Gi_Toolkit_submenu',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/hide-admin-bar.css',
			array(),
			$assets['version'],
			'all'
		);
		wp_enqueue_style(
			'gi-toolkit-compromise-detection',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/compromise-detection.css',
			array( 'Gi_Toolkit_submenu' ),
			defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0'
		);

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * @return void
	 */
	public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$redirect = admin_url( 'admin.php?page=gi-toolkit-settings-compromise-detection' );

		if ( isset( $_POST[ $this->option_id ] ) && is_array( $_POST[ $this->option_id ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->save_settings( wp_unslash( $_POST[ $this->option_id ] ) );
		}

		if ( isset( $_POST['gi_compromise_pause'] ) ) {
			$hours = absint( wp_unslash( $_POST['gi_compromise_pause'] ) );
			if ( in_array( $hours, array( 1, 2, 24 ), true ) ) {
				update_option( self::OPTION_PAUSE, time() + ( $hours * HOUR_IN_SECONDS ), false );
				self::load_helpers();
				Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot(
					Gi_Toolkit_Compromise_Detection_Monitor::build_snapshot()
				);
			}
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'paused', $redirect ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_resume'] ) ) {
			delete_option( self::OPTION_PAUSE );
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'resumed', $redirect ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_baseline'] ) ) {
			self::load_helpers();
			Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot(
				Gi_Toolkit_Compromise_Detection_Monitor::build_snapshot()
			);
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'baseline', $redirect ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_scan_now'] ) ) {
			$this->run_scan();
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'scanned', $redirect ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_clear_log'] ) ) {
			update_option( self::OPTION_ALERTS, array(), false );
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'cleared', $redirect ) );
			exit;
		}

		$notice = 'saved';
		if ( isset( $_POST['gi_compromise_test_pushover'] ) ) {
			self::load_helpers();
			$result = Gi_Toolkit_Compromise_Detection_Pushover::send_test( $this->get_settings() );
			$notice = ! empty( $result['success'] ) ? 'test_ok' : 'test_fail';
			if ( 'test_fail' === $notice ) {
				set_transient(
					'gi_toolkit_compromise_test_error',
					isset( $result['message'] ) ? (string) $result['message'] : '',
					MINUTE_IN_SECONDS
				);
			}
		}

		wp_safe_redirect( add_query_arg( 'gi_compromise_notice', $notice, $redirect ) );
		exit;
	}

	/**
	 * @return void
	 */
	private function submenu_content() {
		$settings    = $this->get_settings();
		$pause_until = self::pause_until();
		$paused      = $pause_until > 0;
		$snapshot    = Gi_Toolkit_Compromise_Detection_Monitor::get_snapshot();
		$last_scan   = isset( $snapshot['taken_at'] ) ? (int) $snapshot['taken_at'] : 0;
		$next_cron   = wp_next_scheduled( self::CRON_HOOK );
		$alerts      = get_option( self::OPTION_ALERTS, array() );
		if ( ! is_array( $alerts ) ) {
			$alerts = array();
		}
		$notice = isset( $_GET['gi_compromise_notice'] ) ? sanitize_key( wp_unslash( $_GET['gi_compromise_notice'] ) ) : '';
		$labels = self::watch_labels();
		$pushover_ok = '' !== trim( (string) $settings['pushover_app_token'] ) && '' !== trim( (string) $settings['pushover_user_key'] );
		?>
		<div class="gi-toolkit__section gi-cd">
			<div class="gi-toolkit__section__desc">
				<?php esc_html_e( 'Surveille le site toutes les minutes (et en temps réel via les hooks WordPress). En cas de suspicion de compromission, une alerte Pushover est envoyée. À l’activation, l’état actuel est mémorisé comme normal : seuls les changements ultérieurs déclenchent une alerte.', 'gi-toolkit' ); ?>
			</div>

			<?php $this->render_notices( $notice ); ?>

			<div class="gi-cd-status <?php echo $paused ? 'gi-cd-status--paused' : ( $pushover_ok ? 'gi-cd-status--ok' : 'gi-cd-status--warn' ); ?>">
				<?php if ( $paused ) : ?>
					<strong><?php esc_html_e( 'Module en pause', 'gi-toolkit' ); ?></strong>
					—
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: datetime */
							__( 'aucune alerte jusqu’au %s. Le snapshot est mis à jour pour éviter un flot de notifications à la reprise.', 'gi-toolkit' ),
							wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $pause_until )
						)
					);
					?>
				<?php elseif ( ! $pushover_ok ) : ?>
					<strong><?php esc_html_e( 'Surveillance active, Pushover incomplet', 'gi-toolkit' ); ?></strong>
					—
					<?php esc_html_e( 'les événements sont journalisés mais aucune notification ne part tant que le jeton et la clé utilisateur ne sont pas renseignés.', 'gi-toolkit' ); ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Surveillance active', 'gi-toolkit' ); ?></strong>
					—
					<?php esc_html_e( 'alertes Pushover activées.', 'gi-toolkit' ); ?>
				<?php endif; ?>
				<div class="gi-cd-status__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: datetime or em dash */
							__( 'Dernier scan : %s', 'gi-toolkit' ),
							$last_scan ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_scan ) : '—'
						)
					);
					echo ' · ';
					echo esc_html(
						sprintf(
							/* translators: %s: datetime or em dash */
							__( 'Prochain cron : %s', 'gi-toolkit' ),
							$next_cron ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cron ) : '—'
						)
					);
					?>
				</div>
			</div>

			<div class="gi-toolkit__section__body">
				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Pause (maintenance)', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__content">
						<p><?php esc_html_e( 'Mettez le module en pause pour créer un admin, installer une extension ou modifier des pages sans déclencher d’alerte. L’état du site est mémorisé pendant la pause.', 'gi-toolkit' ); ?></p>
						<div class="gi-cd-actions">
							<button type="submit" class="button" name="gi_compromise_pause" value="1"><?php esc_html_e( 'Pause 1 h', 'gi-toolkit' ); ?></button>
							<button type="submit" class="button" name="gi_compromise_pause" value="2"><?php esc_html_e( 'Pause 2 h', 'gi-toolkit' ); ?></button>
							<button type="submit" class="button" name="gi_compromise_pause" value="24"><?php esc_html_e( 'Pause 24 h', 'gi-toolkit' ); ?></button>
							<?php if ( $paused ) : ?>
								<button type="submit" class="button button-primary" name="gi_compromise_resume" value="1"><?php esc_html_e( 'Reprendre maintenant', 'gi-toolkit' ); ?></button>
							<?php endif; ?>
							<button type="submit" class="button" name="gi_compromise_baseline" value="1"><?php esc_html_e( 'Marquer l’état actuel comme normal', 'gi-toolkit' ); ?></button>
							<button type="submit" class="button" name="gi_compromise_scan_now" value="1"><?php esc_html_e( 'Scanner maintenant', 'gi-toolkit' ); ?></button>
						</div>
					</div>
				</div>

				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Compte Pushover', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__content">
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: pushover.net URL */
									__( 'Créez une application sur %s, puis collez le jeton (API Token) et votre clé utilisateur (User Key).', 'gi-toolkit' ),
									'<a href="https://pushover.net/" target="_blank" rel="noopener noreferrer">pushover.net</a>'
								),
								array(
									'a' => array(
										'href'   => array(),
										'target' => array(),
										'rel'    => array(),
									),
								)
							);
							?>
						</p>
						<div class="gi-cd-fields">
							<p>
								<label for="gi_cd_pushover_app_token"><strong><?php esc_html_e( 'Jeton application (API Token / token)', 'gi-toolkit' ); ?></strong></label><br />
								<input type="text" class="regular-text code" id="gi_cd_pushover_app_token" name="<?php echo esc_attr( $this->option_id . '[pushover_app_token]' ); ?>" value="<?php echo esc_attr( (string) $settings['pushover_app_token'] ); ?>" autocomplete="off" />
							</p>
							<p>
								<label for="gi_cd_pushover_user_key"><strong><?php esc_html_e( 'Clé utilisateur (User Key / user)', 'gi-toolkit' ); ?></strong></label><br />
								<input type="text" class="regular-text code" id="gi_cd_pushover_user_key" name="<?php echo esc_attr( $this->option_id . '[pushover_user_key]' ); ?>" value="<?php echo esc_attr( (string) $settings['pushover_user_key'] ); ?>" autocomplete="off" />
							</p>
							<p>
								<label for="gi_cd_pushover_device"><strong><?php esc_html_e( 'Appareil (optionnel)', 'gi-toolkit' ); ?></strong></label><br />
								<input type="text" class="regular-text" id="gi_cd_pushover_device" name="<?php echo esc_attr( $this->option_id . '[pushover_device]' ); ?>" value="<?php echo esc_attr( (string) $settings['pushover_device'] ); ?>" placeholder="<?php esc_attr_e( 'Tous les appareils si vide', 'gi-toolkit' ); ?>" />
							</p>
							<p>
								<label for="gi_cd_pushover_title"><strong><?php esc_html_e( 'Titre de la notification', 'gi-toolkit' ); ?></strong></label><br />
								<input type="text" class="large-text" id="gi_cd_pushover_title" name="<?php echo esc_attr( $this->option_id . '[pushover_title]' ); ?>" value="<?php echo esc_attr( (string) $settings['pushover_title'] ); ?>" />
							</p>
							<p>
								<label for="gi_cd_pushover_message"><strong><?php esc_html_e( 'Message de la notification', 'gi-toolkit' ); ?></strong></label><br />
								<textarea class="large-text code" id="gi_cd_pushover_message" name="<?php echo esc_attr( $this->option_id . '[pushover_message]' ); ?>" rows="7"><?php echo esc_textarea( (string) $settings['pushover_message'] ); ?></textarea>
							</p>
							<p class="description gi-cd-vars">
								<?php esc_html_e( 'Variables disponibles :', 'gi-toolkit' ); ?>
								<?php foreach ( Gi_Toolkit_Compromise_Detection_Pushover::available_variables() as $var => $var_label ) : ?>
									<code title="<?php echo esc_attr( $var_label ); ?>"><?php echo esc_html( $var ); ?></code>
								<?php endforeach; ?>
							</p>
						</div>
						<p>
							<button type="submit" class="button" name="gi_compromise_test_pushover" value="1"><?php esc_html_e( 'Envoyer une notification de test', 'gi-toolkit' ); ?></button>
							<span class="description"><?php esc_html_e( 'Enregistre d’abord les réglages, puis envoie le test.', 'gi-toolkit' ); ?></span>
						</p>
					</div>
				</div>

				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Éléments à surveiller', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__content">
						<p><?php esc_html_e( 'Tout est actif par défaut. Décochez ce que vous ne souhaitez pas surveiller.', 'gi-toolkit' ); ?></p>
						<div class="gi-cd-watches">
							<?php foreach ( $labels as $key => $label ) : ?>
								<div class="gi-toolkit__checkbox">
									<label class="gi-toolkit__checkbox__label">
										<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[' . $key . ']' ); ?>" value="0" />
										<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[' . $key . ']' ); ?>" value="1" <?php checked( (string) $settings[ $key ], '1' ); ?> />
										<span class="mark"></span>
										<span class="gi-toolkit__checkbox__label__text"><?php echo esc_html( $label ); ?></span>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Journal des alertes', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__content">
						<?php if ( empty( $alerts ) ) : ?>
							<p><?php esc_html_e( 'Aucune alerte pour le moment.', 'gi-toolkit' ); ?></p>
						<?php else : ?>
							<p>
								<button type="submit" class="button" name="gi_compromise_clear_log" value="1"><?php esc_html_e( 'Vider le journal', 'gi-toolkit' ); ?></button>
							</p>
							<table class="widefat striped gi-cd-log">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Date', 'gi-toolkit' ); ?></th>
										<th><?php esc_html_e( 'Alerte', 'gi-toolkit' ); ?></th>
										<th><?php esc_html_e( 'Détails', 'gi-toolkit' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $alerts as $row ) : ?>
										<tr>
											<td><?php echo esc_html( ! empty( $row['time'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['time'] ) : '—' ); ?></td>
											<td><?php echo esc_html( isset( $row['summary'] ) ? (string) $row['summary'] : '' ); ?></td>
											<td><code><?php echo esc_html( isset( $row['details'] ) ? (string) $row['details'] : '' ); ?></code></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Le cron WordPress se déclenche surtout lors des visites. Pour un scan réellement chaque minute, planifiez wp-cron.php via crontab ou le panneau d’hébergement. Les événements (nouvel admin, page, plugin…) sont aussi détectés immédiatement via les hooks WordPress.', 'gi-toolkit' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $notice Clé.
	 * @return void
	 */
	private function render_notices( $notice ) {
		$messages = array(
			'saved'    => array( 'success', __( 'Réglages enregistrés.', 'gi-toolkit' ) ),
			'paused'   => array( 'warning', __( 'Module mis en pause. Vous pouvez modifier le site sans alerte.', 'gi-toolkit' ) ),
			'resumed'  => array( 'success', __( 'Surveillance reprise.', 'gi-toolkit' ) ),
			'baseline' => array( 'success', __( 'État actuel mémorisé comme normal. Les prochains écarts déclencheront une alerte.', 'gi-toolkit' ) ),
			'scanned'  => array( 'success', __( 'Scan manuel terminé.', 'gi-toolkit' ) ),
			'cleared'  => array( 'success', __( 'Journal vidé.', 'gi-toolkit' ) ),
			'test_ok'  => array( 'success', __( 'Notification de test Pushover envoyée.', 'gi-toolkit' ) ),
			'test_fail'=> array( 'error', __( 'Échec de la notification de test Pushover.', 'gi-toolkit' ) ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		$class = $messages[ $notice ][0];
		$text  = $messages[ $notice ][1];
		if ( 'test_fail' === $notice ) {
			$err = get_transient( 'gi_toolkit_compromise_test_error' );
			delete_transient( 'gi_toolkit_compromise_test_error' );
			if ( is_string( $err ) && '' !== $err ) {
				$text .= ' ' . $err;
			}
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $text )
		);
	}
}
