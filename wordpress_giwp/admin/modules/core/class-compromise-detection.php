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

	const ALERTS_MAX = 80;

	const RESOLVE_TTL = 2592000;

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
			add_action( 'admin_bar_menu', array( $this, 'register_admin_bar' ), 98 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
			add_action( 'admin_post_gi_compromise_toolbar', array( $this, 'handle_toolbar_action' ) );
			add_action( 'init', array( $this, 'handle_public_resolve' ), 1 );
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
			$extra = array();
			foreach ( array( 'diff', 'context', 'preview' ) as $key ) {
				if ( ! empty( $alert[ $key ] ) ) {
					$extra[ $key ] = $alert[ $key ];
				}
			}
			self::raise_alert( $alert['type'], $alert['summary'], $alert['details'], $extra );
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
				$user->user_login . ' (#' . (int) $user_id . ')',
				array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::user_fact_rows( (int) $user_id, $user->user_login ) )
			);
		} elseif ( array_intersect( $roles, array( 'editor', 'shop_manager' ) ) ) {
			self::raise_alert(
				'watch_role_elevation',
				__( 'Nouvel utilisateur privilégié', 'gi-toolkit' ),
				$user->user_login . ' — ' . implode( ', ', $roles ),
				array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::user_fact_rows( (int) $user_id, $user->user_login ) )
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
		$ctx   = Gi_Toolkit_Compromise_Detection_Monitor::user_fact_rows( (int) $user_id, $login );
		$ctx[] = Gi_Toolkit_Compromise_Detection_Monitor::fact_row( __( 'Anciens rôles', 'gi-toolkit' ), implode( ', ', $old ) );
		$ctx[] = Gi_Toolkit_Compromise_Detection_Monitor::fact_row( __( 'Nouveau rôle', 'gi-toolkit' ), (string) $role );
		if ( 'administrator' === $role ) {
			self::raise_alert(
				'watch_admin_user',
				__( 'Nouvel utilisateur administrateur détecté', 'gi-toolkit' ),
				$login . ' (rôle administrator)',
				array( 'context' => $ctx )
			);
			return;
		}
		if ( in_array( $role, array( 'editor', 'shop_manager' ), true ) ) {
			self::raise_alert(
				'watch_role_elevation',
				__( 'Élévation de privilèges', 'gi-toolkit' ),
				$login . ' → ' . $role,
				array( 'context' => $ctx )
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
				$user->user_login . ' (#' . (int) $user_id . ')',
				array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::user_fact_rows( (int) $user_id, $user->user_login ) )
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
			$user->user_login . ' (#' . (int) $user_id . ')',
			array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::user_fact_rows( (int) $user_id, $user->user_login ) )
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
			$user->user_login . ' (#' . (int) $user->ID . ')',
			array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::user_fact_rows( (int) $user->ID, $user->user_login ) )
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
			$post->post_title . ' (#' . (int) $post_id . ')',
			array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::page_fact_rows( $post ) )
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
				$post->post_title . ' (#' . (int) $post->ID . ')',
				array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::page_fact_rows( $post ) )
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
			$post->post_title . ' (#' . (int) $post_id . ')',
			array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::page_fact_rows( $post ) )
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
			$post->post_title . ' (#' . (int) $post_id . ')',
			array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::page_fact_rows( $post ) )
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
			$files = array();
			if ( ! empty( $options['plugin'] ) ) {
				$files[] = (string) $options['plugin'];
			} elseif ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
				$files = array_map( 'strval', $options['plugins'] );
			}
			$slug = implode( ', ', $files );
			self::raise_alert(
				'watch_plugins_themes',
				__( 'Extension(s) ajoutée(s)', 'gi-toolkit' ),
				$slug ? $slug : __( 'installation plugin', 'gi-toolkit' ),
				array( 'context' => Gi_Toolkit_Compromise_Detection_Monitor::plugin_fact_rows( $files ) )
			);
		}
		if ( 'theme' === $type ) {
			$slug = isset( $options['theme'] ) ? (string) $options['theme'] : '';
			self::raise_alert(
				'watch_plugins_themes',
				__( 'Thème(s) ajouté(s)', 'gi-toolkit' ),
				$slug ? $slug : __( 'installation thème', 'gi-toolkit' ),
				array( 'context' => $slug ? Gi_Toolkit_Compromise_Detection_Monitor::theme_fact_rows( array( $slug ) ) : array() )
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
			(string) $old . ' → ' . (string) $new,
			array(
				'context' => array(
					Gi_Toolkit_Compromise_Detection_Monitor::fact_row( __( 'Ancienne valeur', 'gi-toolkit' ), (string) $old ),
					Gi_Toolkit_Compromise_Detection_Monitor::fact_row( __( 'Nouvelle valeur', 'gi-toolkit' ), (string) $new ),
				),
			)
		);
	}

	/**
	 * @param string               $type    Clé de surveillance.
	 * @param string               $summary Titre.
	 * @param string               $details Détails.
	 * @param array<string, mixed> $extra   Contexte (diff, …).
	 * @return void
	 */
	public static function raise_alert( $type, $summary, $details = '', $extra = array() ) {
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

		$diff = '';
		if ( is_array( $extra ) && ! empty( $extra['diff'] ) ) {
			$diff = self::sanitize_diff( (string) $extra['diff'] );
		}

		$preview = '';
		if ( is_array( $extra ) && ! empty( $extra['preview'] ) ) {
			$preview = self::sanitize_diff( (string) $extra['preview'] );
		}

		$context = self::request_context();
		if ( is_array( $extra ) && ! empty( $extra['context'] ) && is_array( $extra['context'] ) ) {
			$context = array_merge( $context, $extra['context'] );
		}

		$host = '';
		if ( is_array( $extra ) && ! empty( $extra['host'] ) ) {
			$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( (string) $extra['host'] );
		}

		$entry = array(
			'id'          => self::new_alert_id(),
			'time'        => time(),
			'type'        => sanitize_key( $type ),
			'summary'     => sanitize_text_field( $summary ),
			'details'     => sanitize_text_field( $details ),
			'diff'        => $diff,
			'preview'     => $preview,
			'context'     => self::sanitize_context( $context ),
			'host'        => $host,
			'status'      => 'open',
			'resolved_at' => 0,
			'resolved_by' => 0,
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
			$details,
			1,
			'siren',
			$entry['id']
		);
	}

	/**
	 * @return string
	 */
	private static function new_alert_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return uniqid( 'cd_', true );
	}

	/**
	 * Secret HMAC pour les liens « marquer comme traitée » (sans connexion).
	 *
	 * @return string
	 */
	private static function resolve_secret() {
		return wp_salt( 'auth' ) . '|gi-toolkit-compromise-resolve';
	}

	/**
	 * @param string $alert_id ID alerte.
	 * @return string
	 */
	public static function make_resolve_url( $alert_id ) {
		$alert_id = (string) $alert_id;
		if ( '' === $alert_id ) {
			return '';
		}
		$exp = time() + self::RESOLVE_TTL;
		$sig = hash_hmac( 'sha256', $alert_id . '|' . (string) $exp, self::resolve_secret() );
		return add_query_arg(
			array(
				'gi_cd_resolve' => '1',
				'id'            => $alert_id,
				'exp'           => (string) $exp,
				'sig'           => $sig,
			),
			home_url( '/' )
		);
	}

	/**
	 * Lien signé depuis Pushover : marque l’alerte comme traitée sans connexion.
	 *
	 * @return void
	 */
	public function handle_public_resolve() {
		if ( empty( $_GET['gi_cd_resolve'] ) ) {
			return;
		}

		$id  = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$exp = isset( $_GET['exp'] ) ? absint( wp_unslash( $_GET['exp'] ) ) : 0;
		$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		nocache_headers();

		$status  = 'invalid';
		$summary = '';

		if ( '' === $id || ! $exp || ! preg_match( '/^[a-f0-9]{64}$/', $sig ) ) {
			self::render_public_resolve_page( 'invalid', '' );
			return;
		}

		$expected = hash_hmac( 'sha256', $id . '|' . (string) $exp, self::resolve_secret() );
		if ( ! hash_equals( $expected, $sig ) ) {
			self::render_public_resolve_page( 'invalid', '' );
			return;
		}

		if ( $exp < time() ) {
			self::render_public_resolve_page( 'expired', '' );
			return;
		}

		$found = null;
		foreach ( self::get_alerts() as $row ) {
			if ( is_array( $row ) && (string) ( $row['id'] ?? '' ) === $id ) {
				$found = $row;
				break;
			}
		}

		if ( ! $found ) {
			self::render_public_resolve_page( 'missing', '' );
			return;
		}

		$summary = isset( $found['summary'] ) ? (string) $found['summary'] : '';
		if ( 'resolved' === ( $found['status'] ?? 'open' ) ) {
			self::render_public_resolve_page( 'already', $summary );
			return;
		}

		self::set_alert_status( $id, 'resolved' );
		self::render_public_resolve_page( 'ok', $summary );
	}

	/**
	 * @param string $status  ok|already|expired|invalid|missing.
	 * @param string $summary Titre alerte.
	 * @return void
	 */
	private static function render_public_resolve_page( $status, $summary ) {
		$titles = array(
			'ok'      => __( 'Alerte marquée comme traitée', 'gi-toolkit' ),
			'already' => __( 'Alerte déjà traitée', 'gi-toolkit' ),
			'expired' => __( 'Lien expiré', 'gi-toolkit' ),
			'invalid' => __( 'Lien invalide', 'gi-toolkit' ),
			'missing' => __( 'Alerte introuvable', 'gi-toolkit' ),
		);
		$texts = array(
			'ok'      => __( 'Cette alerte a été marquée comme traitée. Vous pouvez fermer cette page.', 'gi-toolkit' ),
			'already' => __( 'Cette alerte était déjà marquée comme traitée.', 'gi-toolkit' ),
			'expired' => __( 'Ce lien n’est plus valable (30 jours). Connectez-vous au site pour traiter l’alerte.', 'gi-toolkit' ),
			'invalid' => __( 'Ce lien n’est pas valide.', 'gi-toolkit' ),
			'missing' => __( 'Cette alerte n’existe plus (journal vidé ou trop ancien).', 'gi-toolkit' ),
		);
		$title = isset( $titles[ $status ] ) ? $titles[ $status ] : $titles['invalid'];
		$text  = isset( $texts[ $status ] ) ? $texts[ $status ] : $texts['invalid'];
		$ok    = in_array( $status, array( 'ok', 'already' ), true );

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '<style>body{margin:0;font:16px/1.5 -apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:#f0f0f1;color:#1d2327}main{max-width:32rem;margin:12vh auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}h1{font-size:1.25rem;margin:0 0 8px}p{margin:0 0 12px;color:#50575e}.ok{color:#1e4620}.bad{color:#b32d2e}code{font-size:13px}</style>';
		echo '</head><body><main>';
		echo '<h1 class="' . ( $ok ? 'ok' : 'bad' ) . '">' . esc_html( $title ) . '</h1>';
		if ( '' !== $summary ) {
			echo '<p><strong>' . esc_html( $summary ) . '</strong></p>';
		}
		echo '<p>' . esc_html( $text ) . '</p>';
		echo '</main></body></html>';
		exit;
	}

	/**
	 * @param string $diff Diff.
	 * @return string
	 */
	private static function sanitize_diff( $diff ) {
		$diff = str_replace( "\0", '', (string) $diff );
		if ( strlen( $diff ) > 50000 ) {
			$diff = substr( $diff, 0, 50000 ) . "\n… (tronqué)";
		}
		return $diff;
	}

	/**
	 * @param array<int, mixed> $rows Faits.
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	private static function sanitize_context( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $rows, 0, 40 ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['label'] ) ) {
				continue;
			}
			$item = array(
				'label' => sanitize_text_field( (string) $row['label'] ),
				'value' => sanitize_textarea_field( isset( $row['value'] ) ? (string) $row['value'] : '' ),
			);
			if ( ! empty( $row['url'] ) ) {
				$url = esc_url_raw( (string) $row['url'] );
				if ( '' !== $url ) {
					$item['url'] = $url;
				}
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * IP et utilisateur au moment de l’alerte.
	 *
	 * @return array<int, array{label:string,value:string,url?:string}>
	 */
	private static function request_context() {
		self::load_helpers();
		$rows = array();
		$ip   = Gi_Toolkit_Compromise_Detection_Monitor::client_ip();
		if ( '' !== $ip ) {
			$rows[] = Gi_Toolkit_Compromise_Detection_Monitor::fact_row( __( 'Adresse IP', 'gi-toolkit' ), $ip );
		}
		$user = wp_get_current_user();
		if ( $user instanceof WP_User && $user->exists() ) {
			$rows[] = Gi_Toolkit_Compromise_Detection_Monitor::fact_row(
				__( 'Utilisateur connecté', 'gi-toolkit' ),
				$user->user_login . ' (#' . (int) $user->ID . ')',
				admin_url( 'user-edit.php?user_id=' . (int) $user->ID )
			);
		} else {
			$rows[] = Gi_Toolkit_Compromise_Detection_Monitor::fact_row(
				__( 'Utilisateur connecté', 'gi-toolkit' ),
				__( 'aucun (cron ou visiteur anonyme)', 'gi-toolkit' )
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_alerts() {
		$log = get_option( self::OPTION_ALERTS, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		$changed = false;
		foreach ( $log as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( empty( $row['id'] ) ) {
				$log[ $i ]['id'] = self::new_alert_id();
				$changed         = true;
			}
			if ( empty( $row['status'] ) ) {
				$log[ $i ]['status'] = 'open';
				$changed             = true;
			}
			if ( ! isset( $log[ $i ]['diff'] ) ) {
				$log[ $i ]['diff'] = '';
			}
		}
		if ( $changed ) {
			update_option( self::OPTION_ALERTS, $log, false );
		}
		return $log;
	}

	/**
	 * @param string $id     ID alerte.
	 * @param string $status open|resolved.
	 * @return bool
	 */
	public static function set_alert_status( $id, $status ) {
		$id     = (string) $id;
		$status = 'resolved' === $status ? 'resolved' : 'open';
		if ( '' === $id ) {
			return false;
		}
		$log     = self::get_alerts();
		$changed = false;
		$user_id = get_current_user_id();
		foreach ( $log as $i => $row ) {
			if ( ! is_array( $row ) || (string) ( $row['id'] ?? '' ) !== $id ) {
				continue;
			}
			$log[ $i ]['status'] = $status;
			if ( 'resolved' === $status ) {
				$log[ $i ]['resolved_at'] = time();
				$log[ $i ]['resolved_by'] = $user_id;
			} else {
				$log[ $i ]['resolved_at'] = 0;
				$log[ $i ]['resolved_by'] = 0;
			}
			$changed = true;
			break;
		}
		if ( $changed ) {
			update_option( self::OPTION_ALERTS, $log, false );
		}
		return $changed;
	}

	/**
	 * @return int
	 */
	public static function resolve_all_alerts() {
		$log   = self::get_alerts();
		$count = 0;
		$user  = get_current_user_id();
		$now   = time();
		foreach ( $log as $i => $row ) {
			if ( ! is_array( $row ) || 'open' !== ( $row['status'] ?? 'open' ) ) {
				continue;
			}
			$log[ $i ]['status']      = 'resolved';
			$log[ $i ]['resolved_at'] = $now;
			$log[ $i ]['resolved_by'] = $user;
			++$count;
		}
		if ( $count ) {
			update_option( self::OPTION_ALERTS, $log, false );
		}
		return $count;
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
			'outbound_whitelist'    => array(),
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

		$out['pushover_device']    = sanitize_text_field( $new_settings['pushover_device'] ?? $defaults['pushover_device'] );
		$stored                    = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$token = sanitize_text_field( $new_settings['pushover_app_token'] ?? '' );
		$out['pushover_app_token'] = '' !== $token
			? $token
			: sanitize_text_field( (string) ( $stored['pushover_app_token'] ?? $defaults['pushover_app_token'] ) );
		$user_key = sanitize_text_field( $new_settings['pushover_user_key'] ?? '' );
		$out['pushover_user_key'] = '' !== $user_key
			? $user_key
			: sanitize_text_field( (string) ( $stored['pushover_user_key'] ?? $defaults['pushover_user_key'] ) );
		$title                     = sanitize_text_field( $new_settings['pushover_title'] ?? $defaults['pushover_title'] );
		$out['pushover_title']     = '' !== $title ? $title : $defaults['pushover_title'];
		$message                   = sanitize_textarea_field( $new_settings['pushover_message'] ?? $defaults['pushover_message'] );
		$out['pushover_message']   = '' !== $message ? $message : $defaults['pushover_message'];

		foreach ( array_keys( self::watch_labels() ) as $key ) {
			$out[ $key ] = ( isset( $new_settings[ $key ] ) && '1' === (string) $new_settings[ $key ] ) ? '1' : '0';
		}

		if ( array_key_exists( 'outbound_whitelist', $new_settings ) ) {
			$raw = $new_settings['outbound_whitelist'];
			$out['outbound_whitelist'] = self::sanitize_whitelist_list( $raw );
		} else {
			$out['outbound_whitelist'] = self::sanitize_whitelist_list( $stored['outbound_whitelist'] ?? array() );
		}

		return $out;
	}

	/**
	 * @param mixed $raw Liste ou texte.
	 * @return string[]
	 */
	public static function sanitize_whitelist_list( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\r\n|\r|\n/', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		self::load_helpers();
		foreach ( $raw as $item ) {
			$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( (string) $item );
			if ( '' !== $host ) {
				$out[] = $host;
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out );
		return $out;
	}

	/**
	 * @param string $host Hôte.
	 * @return bool
	 */
	public static function add_outbound_whitelist( $host ) {
		self::load_helpers();
		$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( $host );
		if ( '' === $host ) {
			return false;
		}
		$settings = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$list = self::sanitize_whitelist_list( $settings['outbound_whitelist'] ?? array() );
		if ( ! in_array( $host, $list, true ) ) {
			$list[] = $host;
			sort( $list );
		}
		$settings['outbound_whitelist'] = $list;
		update_option( self::OPTION_SETTINGS, $settings );
		if ( self::$instance ) {
			self::$instance->settings = wp_parse_args( $settings, self::default_settings() );
		}
		return true;
	}

	/**
	 * @param string $host Hôte.
	 * @return void
	 */
	public static function remove_outbound_whitelist( $host ) {
		self::load_helpers();
		$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( $host );
		$settings = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			return;
		}
		$list = self::sanitize_whitelist_list( $settings['outbound_whitelist'] ?? array() );
		$list = array_values( array_diff( $list, array( $host ) ) );
		$settings['outbound_whitelist'] = $list;
		update_option( self::OPTION_SETTINGS, $settings );
		if ( self::$instance ) {
			self::$instance->settings = wp_parse_args( $settings, self::default_settings() );
		}
	}

	/**
	 * @param string $host Hôte.
	 * @return void
	 */
	public static function resolve_outbound_alerts_for_host( $host ) {
		self::load_helpers();
		$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( $host );
		if ( '' === $host ) {
			return;
		}
		$log     = self::get_alerts();
		$changed = false;
		$user_id = get_current_user_id();
		$now     = time();
		foreach ( $log as $i => $row ) {
			if ( ! is_array( $row ) || 'watch_outbound' !== ( $row['type'] ?? '' ) ) {
				continue;
			}
			if ( 'resolved' === ( $row['status'] ?? 'open' ) ) {
				continue;
			}
			$match = false;
			foreach ( self::outbound_hosts_from_alert( $row ) as $alert_host ) {
				if ( Gi_Toolkit_Compromise_Detection_Monitor::host_matches_allowed( $alert_host, $host ) ) {
					$match = true;
					break;
				}
			}
			if ( ! $match ) {
				continue;
			}
			$log[ $i ]['status']      = 'resolved';
			$log[ $i ]['resolved_at'] = $now;
			$log[ $i ]['resolved_by'] = $user_id;
			$changed                  = true;
		}
		if ( $changed ) {
			update_option( self::OPTION_ALERTS, $log, false );
		}
	}

	/**
	 * @param array<string, mixed> $row Alerte.
	 * @return string[]
	 */
	public static function outbound_hosts_from_alert( $row ) {
		self::load_helpers();
		$hosts = array();
		if ( ! empty( $row['host'] ) ) {
			$hosts[] = (string) $row['host'];
		}
		if ( ! empty( $row['context'] ) && is_array( $row['context'] ) ) {
			foreach ( $row['context'] as $fact ) {
				if ( ! is_array( $fact ) || empty( $fact['value'] ) ) {
					continue;
				}
				$label = isset( $fact['label'] ) ? (string) $fact['label'] : '';
				$value = (string) $fact['value'];
				if ( __( 'Hôte', 'gi-toolkit' ) === $label || 'Hôte' === $label ) {
					$hosts[] = $value;
				} elseif ( false !== strpos( $label, 'Hôtes' ) || false !== strpos( $label, 'hotes' ) ) {
					foreach ( explode( ',', $value ) as $part ) {
						$hosts[] = $part;
					}
				}
			}
		}
		if ( ! empty( $row['details'] ) && preg_match( '/^([a-z0-9.-]+)/i', (string) $row['details'], $m ) ) {
			$hosts[] = $m[1];
		}
		$out = array();
		foreach ( $hosts as $item ) {
			$clean = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( $item );
			if ( '' !== $clean ) {
				$out[] = $clean;
			}
		}
		return array_values( array_unique( $out ) );
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
	 * Icône barre d’admin s’il reste des alertes ouvertes.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Barre.
	 * @return void
	 */
	public function register_admin_bar( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$open = self::get_open_alerts();
		if ( empty( $open ) ) {
			return;
		}

		$count = count( $open );
		$page  = admin_url( 'admin.php?page=gi-toolkit-settings-compromise-detection' );
		$title = sprintf(
			'<span class="gi-cd-ab-wrap"><span class="dashicons dashicons-shield gi-cd-ab-icon" aria-hidden="true"></span><span class="gi-cd-ab-count">%s</span></span>',
			esc_html( number_format_i18n( $count ) )
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'gi-compromise-toolbar',
				'title' => $title,
				'href'  => $page,
				'meta'  => array(
					'class' => 'gi-cd-ab-menu',
					'title' => esc_attr(
						sprintf(
							/* translators: %d: open alerts */
							_n( '%d alerte de compromission à traiter', '%d alertes de compromission à traiter', $count, 'gi-toolkit' ),
							$count
						)
					),
				),
			)
		);

		$groups = array();
		foreach ( $open as $row ) {
			$label = isset( $row['summary'] ) ? (string) $row['summary'] : __( 'Alerte', 'gi-toolkit' );
			if ( ! isset( $groups[ $label ] ) ) {
				$groups[ $label ] = 0;
			}
			++$groups[ $label ];
		}
		$bits = array();
		foreach ( array_slice( $groups, 0, 8, true ) as $label => $n ) {
			$bits[] = esc_html( $label ) . ( $n > 1 ? ' ×' . (int) $n : '' );
		}
		$summary_html  = '<span class="gi-cd-ab-flyout__title">' . esc_html(
			sprintf(
				/* translators: %d: count */
				_n( '%d alerte à traiter', '%d alertes à traiter', $count, 'gi-toolkit' ),
				$count
			)
		) . '</span>';
		$summary_html .= '<ul class="gi-cd-ab-flyout__types"><li>' . implode( '</li><li>', $bits ) . '</li></ul>';

		$wp_admin_bar->add_node(
			array(
				'id'     => 'gi-cd-ab-summary',
				'parent' => 'gi-compromise-toolbar',
				'title'  => $summary_html,
				'href'   => false,
				'meta'   => array(
					'class' => 'gi-cd-ab-summary',
				),
			)
		);

		$maint_on = self::is_maintenance_enabled();
		$wp_admin_bar->add_node(
			array(
				'id'     => 'gi-cd-ab-maintenance',
				'parent' => 'gi-compromise-toolbar',
				'title'  => $maint_on
					? esc_html__( 'Désactiver le mode maintenance', 'gi-toolkit' )
					: esc_html__( 'Activer le mode maintenance', 'gi-toolkit' ),
				'href'   => self::toolbar_action_url( $maint_on ? 'maintenance_off' : 'maintenance_on' ),
			)
		);

		foreach ( array( 1 => __( 'Pause alertes 1 h', 'gi-toolkit' ), 2 => __( 'Pause alertes 2 h', 'gi-toolkit' ), 24 => __( 'Pause alertes 24 h', 'gi-toolkit' ) ) as $hours => $label ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'gi-cd-ab-pause-' . $hours,
					'parent' => 'gi-compromise-toolbar',
					'title'  => esc_html( $label ),
					'href'   => self::toolbar_action_url( 'pause', $hours ),
				)
			);
		}

		if ( self::is_paused() ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'gi-cd-ab-resume',
					'parent' => 'gi-compromise-toolbar',
					'title'  => esc_html__( 'Reprendre la surveillance', 'gi-toolkit' ),
					'href'   => self::toolbar_action_url( 'resume' ),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'gi-cd-ab-journal',
				'parent' => 'gi-compromise-toolbar',
				'title'  => esc_html__( 'Voir le journal', 'gi-toolkit' ),
				'href'   => $page,
			)
		);
	}

	/**
	 * @return void
	 */
	public function enqueue_admin_bar_assets() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( self::get_open_alerts() ) ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'gi-toolkit-compromise-detection-admin-bar',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/compromise-detection-admin-bar.css',
			array(),
			defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0'
		);
	}

	/**
	 * @return void
	 */
	public function handle_toolbar_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gi-toolkit' ) );
		}
		check_admin_referer( 'gi_compromise_toolbar' );

		$do    = isset( $_GET['gi_cd_do'] ) ? sanitize_key( wp_unslash( $_GET['gi_cd_do'] ) ) : '';
		$hours = isset( $_GET['hours'] ) ? absint( $_GET['hours'] ) : 0;

		if ( 'pause' === $do && in_array( $hours, array( 1, 2, 24 ), true ) ) {
			update_option( self::OPTION_PAUSE, time() + ( $hours * HOUR_IN_SECONDS ), false );
			self::load_helpers();
			Gi_Toolkit_Compromise_Detection_Monitor::save_snapshot(
				Gi_Toolkit_Compromise_Detection_Monitor::build_snapshot()
			);
		} elseif ( 'resume' === $do ) {
			delete_option( self::OPTION_PAUSE );
		} elseif ( 'maintenance_on' === $do ) {
			self::set_maintenance_enabled( true );
		} elseif ( 'maintenance_off' === $do ) {
			self::set_maintenance_enabled( false );
		}

		$back = wp_get_referer();
		if ( ! is_string( $back ) || '' === $back ) {
			$back = admin_url( 'admin.php?page=gi-toolkit-settings-compromise-detection' );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * @param string $action pause|resume|maintenance_on|maintenance_off.
	 * @param int    $hours  Durée si pause.
	 * @return string
	 */
	private static function toolbar_action_url( $action, $hours = 0 ) {
		$args = array(
			'action'   => 'gi_compromise_toolbar',
			'gi_cd_do' => $action,
		);
		if ( $hours ) {
			$args['hours'] = (int) $hours;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'gi_compromise_toolbar' );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_open_alerts() {
		$open = array();
		foreach ( self::get_alerts() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'resolved' !== ( $row['status'] ?? 'open' ) ) {
				$open[] = $row;
			}
		}
		return $open;
	}

	/**
	 * @return bool
	 */
	private static function is_maintenance_enabled() {
		$settings = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_maintenance_mode', array() );
		if ( ! is_array( $settings ) ) {
			return false;
		}
		return '1' === (string) ( $settings['enabled'] ?? '0' );
	}

	/**
	 * @param bool $enabled Activer.
	 * @return void
	 */
	private static function set_maintenance_enabled( $enabled ) {
		$mods = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		if ( ! is_array( $mods ) ) {
			$mods = array();
		}
		$mods['Gi_Toolkit_Maintenance_Mode'] = '1';
		update_option( GI_TOOLKIT_PLUGIN_SETTINGS, $mods );

		$option   = GI_TOOLKIT_PLUGIN_SETTINGS . '_maintenance_mode';
		$settings = get_option( $option, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['enabled'] = $enabled ? '1' : '0';
		update_option( $option, $settings );
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

		if ( isset( $_POST['gi_compromise_resolve'] ) ) {
			$id = sanitize_text_field( wp_unslash( $_POST['gi_compromise_resolve'] ) );
			self::set_alert_status( $id, 'resolved' );
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'resolved', $this->log_redirect( $redirect ) ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_reopen'] ) ) {
			$id = sanitize_text_field( wp_unslash( $_POST['gi_compromise_reopen'] ) );
			self::set_alert_status( $id, 'open' );
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'reopened', $this->log_redirect( $redirect ) ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_resolve_all'] ) ) {
			self::resolve_all_alerts();
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'resolved_all', $this->log_redirect( $redirect ) ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_whitelist'] ) ) {
			$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( wp_unslash( $_POST['gi_compromise_whitelist'] ) );
			if ( self::add_outbound_whitelist( $host ) ) {
				self::resolve_outbound_alerts_for_host( $host );
				wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'whitelisted', $this->log_redirect( $redirect ) ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'whitelist_fail', $this->log_redirect( $redirect ) ) );
			exit;
		}

		if ( isset( $_POST['gi_compromise_unwhitelist'] ) ) {
			$host = Gi_Toolkit_Compromise_Detection_Monitor::sanitize_host( wp_unslash( $_POST['gi_compromise_unwhitelist'] ) );
			self::remove_outbound_whitelist( $host );
			wp_safe_redirect( add_query_arg( 'gi_compromise_notice', 'unwhitelisted', $this->log_redirect( $redirect ) ) );
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
	 * Conserve le filtre du journal après une action.
	 *
	 * @param string $redirect URL.
	 * @return string
	 */
	private function log_redirect( $redirect ) {
		$filter = isset( $_POST['gi_compromise_log'] ) ? sanitize_key( wp_unslash( $_POST['gi_compromise_log'] ) ) : '';
		if ( ! $filter && isset( $_GET['gi_compromise_log'] ) ) {
			$filter = sanitize_key( wp_unslash( $_GET['gi_compromise_log'] ) );
		}
		if ( in_array( $filter, array( 'open', 'resolved', 'all' ), true ) ) {
			return add_query_arg( 'gi_compromise_log', $filter, $redirect );
		}
		return $redirect;
	}

	/**
	 * @param array<string, mixed> $row Alerte.
	 * @return string
	 */
	private function current_file_preview( $row ) {
		$type    = isset( $row['type'] ) ? (string) $row['type'] : '';
		$summary = isset( $row['summary'] ) ? (string) $row['summary'] : '';
		$details = isset( $row['details'] ) ? (string) $row['details'] : '';
		if ( 'watch_core_files' !== $type && false === strpos( $summary, 'Fichier sensible' ) ) {
			return '';
		}
		$hay = $details . ' ' . $summary;
		$snap      = Gi_Toolkit_Compromise_Detection_Monitor::get_snapshot();
		$contents  = isset( $snap['file_contents'] ) && is_array( $snap['file_contents'] ) ? $snap['file_contents'] : array();
		foreach ( Gi_Toolkit_Compromise_Detection_Monitor::sensitive_files() as $key => $meta ) {
			if ( false === strpos( $hay, $meta['label'] ) ) {
				continue;
			}
			$body = isset( $contents[ $key ] ) ? (string) $contents[ $key ] : '';
			if ( '' === $body ) {
				$body = Gi_Toolkit_Compromise_Detection_Monitor::read_file_for_snapshot( $meta['path'], ! empty( $meta['redact'] ) );
			}
			if ( '' === $body ) {
				return '';
			}
			$lines   = explode( "\n", $body );
			$out     = array( '--- a/' . $meta['label'] . ' (état actuel)' );
			$out[]   = '+++ b/' . $meta['label'];
			foreach ( $lines as $line ) {
				$out[] = '  ' . $line;
			}
			return implode( "\n", $out );
		}
		return '';
	}

	/**
	 * @param string $diff Diff unifié.
	 * @return void
	 */
	private function render_diff( $diff ) {
		$diff  = (string) $diff;
		$lines = preg_split( "/\r\n|\r|\n/", $diff );
		if ( ! is_array( $lines ) ) {
			$lines = array( $diff );
		}

		$looks_like_diff = false;
		foreach ( $lines as $line ) {
			if ( 0 === strpos( $line, '---' ) || 0 === strpos( $line, '+++' ) || 0 === strpos( $line, '+ ' ) || 0 === strpos( $line, '- ' ) ) {
				$looks_like_diff = true;
				break;
			}
		}
		if ( ! $looks_like_diff ) {
			echo '<p class="gi-cd-diff-note">' . esc_html( $diff ) . '</p>';
			return;
		}

		echo '<pre class="gi-cd-diff">';
		foreach ( $lines as $line ) {
			$class = 'gi-cd-diff__line';
			if ( 0 === strpos( $line, '+++' ) || 0 === strpos( $line, '---' ) ) {
				$class .= ' gi-cd-diff__line--meta';
			} elseif ( isset( $line[0] ) && '+' === $line[0] ) {
				$class .= ' gi-cd-diff__line--add';
			} elseif ( isset( $line[0] ) && '-' === $line[0] ) {
				$class .= ' gi-cd-diff__line--del';
			}
			echo '<span class="' . esc_attr( $class ) . '">' . esc_html( $line === '' ? ' ' : $line ) . '</span>';
		}
		echo '</pre>';
	}

	/**
	 * @param array<string, mixed> $row         Alerte.
	 * @param bool                 $is_resolved Traitée.
	 * @return void
	 */
	private function render_alert_details( $row, $is_resolved ) {
		$context = isset( $row['context'] ) && is_array( $row['context'] ) ? $row['context'] : array();
		$diff    = isset( $row['diff'] ) ? (string) $row['diff'] : '';
		$preview = isset( $row['preview'] ) ? (string) $row['preview'] : '';
		$details = isset( $row['details'] ) ? (string) $row['details'] : '';
		$legacy  = false;

		if ( '' === $diff && '' === $preview ) {
			$file_preview = $this->current_file_preview( $row );
			if ( '' !== $file_preview ) {
				$diff   = $file_preview;
				$legacy = true;
			}
		}

		if ( empty( $context ) && '' !== $details ) {
			$context[] = array(
				'label' => __( 'Détail', 'gi-toolkit' ),
				'value' => $details,
			);
		}

		if ( empty( $context ) && '' === $diff && '' === $preview ) {
			return;
		}

		$summary = ( '' !== $diff )
			? __( 'Voir les modifications', 'gi-toolkit' )
			: __( 'Voir les détails', 'gi-toolkit' );
		?>
		<details class="gi-cd-alert__details">
			<summary><?php echo esc_html( $summary ); ?></summary>
			<?php if ( ! empty( $context ) ) : ?>
				<dl class="gi-cd-facts">
					<?php foreach ( $context as $fact ) : ?>
						<?php
						if ( ! is_array( $fact ) || empty( $fact['label'] ) ) {
							continue;
						}
						$value = isset( $fact['value'] ) ? (string) $fact['value'] : '';
						$url   = isset( $fact['url'] ) ? (string) $fact['url'] : '';
						?>
						<div class="gi-cd-facts__row">
							<dt><?php echo esc_html( (string) $fact['label'] ); ?></dt>
							<dd>
								<?php if ( '' !== $url ) : ?>
									<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $value ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $value ); ?>
								<?php endif; ?>
							</dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<?php if ( $legacy ) : ?>
				<p class="description"><?php esc_html_e( 'Le contenu précédent n’a pas été conservé (alerte antérieure à cette fonctionnalité). Voici l’état actuel du fichier ; les prochaines modifications afficheront un diff ligne à ligne.', 'gi-toolkit' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $diff ) : ?>
				<?php $this->render_diff( $diff ); ?>
			<?php endif; ?>
			<?php if ( '' !== $preview ) : ?>
				<pre class="gi-cd-preview"><?php echo esc_html( $preview ); ?></pre>
			<?php endif; ?>
		</details>
		<?php
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
		$alerts      = self::get_alerts();
		$log_filter  = isset( $_GET['gi_compromise_log'] ) ? sanitize_key( wp_unslash( $_GET['gi_compromise_log'] ) ) : 'open';
		if ( ! in_array( $log_filter, array( 'open', 'resolved', 'all' ), true ) ) {
			$log_filter = 'open';
		}
		$open_count = 0;
		$done_count = 0;
		foreach ( $alerts as $row ) {
			if ( 'resolved' === ( $row['status'] ?? 'open' ) ) {
				++$done_count;
			} else {
				++$open_count;
			}
		}
		$visible = array();
		foreach ( $alerts as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : 'open';
			if ( 'all' === $log_filter || $status === $log_filter ) {
				$visible[] = $row;
			}
		}
		$notice = isset( $_GET['gi_compromise_notice'] ) ? sanitize_key( wp_unslash( $_GET['gi_compromise_notice'] ) ) : '';
		$labels = self::watch_labels();
		$pushover_ok = '' !== trim( (string) $settings['pushover_app_token'] ) && '' !== trim( (string) $settings['pushover_user_key'] );
		$has_app_token = '' !== trim( (string) $settings['pushover_app_token'] );
		$has_user_key  = '' !== trim( (string) $settings['pushover_user_key'] );
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
					echo ' · ';
					echo esc_html(
						sprintf(
							/* translators: %d: open alerts */
							_n( '%d alerte à traiter', '%d alertes à traiter', $open_count, 'gi-toolkit' ),
							$open_count
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
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Journal des alertes', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__content">
						<input type="hidden" name="gi_compromise_log" value="<?php echo esc_attr( $log_filter ); ?>" />
						<?php
						$base_log = admin_url( 'admin.php?page=gi-toolkit-settings-compromise-detection' );
						?>
						<ul class="gi-cd-log-filters">
							<li>
								<a href="<?php echo esc_url( add_query_arg( 'gi_compromise_log', 'open', $base_log ) ); ?>" class="<?php echo 'open' === $log_filter ? 'is-current' : ''; ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: count */
											__( 'À traiter (%d)', 'gi-toolkit' ),
											$open_count
										)
									);
									?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( add_query_arg( 'gi_compromise_log', 'resolved', $base_log ) ); ?>" class="<?php echo 'resolved' === $log_filter ? 'is-current' : ''; ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: count */
											__( 'Traitées (%d)', 'gi-toolkit' ),
											$done_count
										)
									);
									?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( add_query_arg( 'gi_compromise_log', 'all', $base_log ) ); ?>" class="<?php echo 'all' === $log_filter ? 'is-current' : ''; ?>">
									<?php esc_html_e( 'Toutes', 'gi-toolkit' ); ?>
								</a>
							</li>
						</ul>
						<?php if ( empty( $alerts ) ) : ?>
							<p><?php esc_html_e( 'Aucune alerte pour le moment.', 'gi-toolkit' ); ?></p>
						<?php elseif ( empty( $visible ) ) : ?>
							<p><?php esc_html_e( 'Aucune alerte dans ce filtre.', 'gi-toolkit' ); ?></p>
						<?php else : ?>
							<p class="gi-cd-actions">
								<?php if ( $open_count > 0 && 'resolved' !== $log_filter ) : ?>
									<button type="submit" class="button" name="gi_compromise_resolve_all" value="1"><?php esc_html_e( 'Tout marquer comme traité', 'gi-toolkit' ); ?></button>
								<?php endif; ?>
								<button type="submit" class="button" name="gi_compromise_clear_log" value="1"><?php esc_html_e( 'Vider le journal', 'gi-toolkit' ); ?></button>
							</p>
							<?php foreach ( $visible as $row ) : ?>
								<?php
								$is_resolved = 'resolved' === ( $row['status'] ?? 'open' );
								$alert_id    = isset( $row['id'] ) ? (string) $row['id'] : '';
								?>
								<article class="gi-cd-alert<?php echo $is_resolved ? ' gi-cd-alert--resolved' : ''; ?>">
									<header class="gi-cd-alert__head">
										<div>
											<span class="gi-cd-alert__badge gi-cd-alert__badge--<?php echo $is_resolved ? 'done' : 'open'; ?>">
												<?php echo $is_resolved ? esc_html__( 'Traitée', 'gi-toolkit' ) : esc_html__( 'À traiter', 'gi-toolkit' ); ?>
											</span>
											<strong class="gi-cd-alert__title"><?php echo esc_html( isset( $row['summary'] ) ? (string) $row['summary'] : '' ); ?></strong>
											<div class="gi-cd-alert__meta">
												<?php echo esc_html( ! empty( $row['time'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['time'] ) : '—' ); ?>
												<?php if ( ! empty( $row['details'] ) ) : ?>
													— <code><?php echo esc_html( (string) $row['details'] ); ?></code>
												<?php endif; ?>
												<?php if ( $is_resolved && ! empty( $row['resolved_at'] ) ) : ?>
													—
													<?php
													echo esc_html(
														sprintf(
															/* translators: %s: datetime */
															__( 'traitée le %s', 'gi-toolkit' ),
															wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['resolved_at'] )
														)
													);
													?>
												<?php endif; ?>
											</div>
										</div>
										<div class="gi-cd-alert__actions">
											<?php
											if ( 'watch_outbound' === ( $row['type'] ?? '' ) ) {
												foreach ( self::outbound_hosts_from_alert( $row ) as $out_host ) {
													if ( Gi_Toolkit_Compromise_Detection_Monitor::is_allowed_host( $out_host ) ) {
														echo '<span class="description">' . esc_html(
															sprintf(
																/* translators: %s: hostname */
																__( '%s autorisé', 'gi-toolkit' ),
																$out_host
															)
														) . '</span>';
														continue;
													}
													if ( $is_resolved ) {
														continue;
													}
													printf(
														'<button type="submit" class="button" name="gi_compromise_whitelist" value="%1$s">%2$s</button>',
														esc_attr( $out_host ),
														esc_html(
															sprintf(
																/* translators: %s: hostname */
																__( 'Autoriser %s', 'gi-toolkit' ),
																$out_host
															)
														)
													);
												}
											}
											?>
											<?php if ( $alert_id && ! $is_resolved ) : ?>
												<button type="submit" class="button button-primary" name="gi_compromise_resolve" value="<?php echo esc_attr( $alert_id ); ?>"><?php esc_html_e( 'Marquer comme traitée', 'gi-toolkit' ); ?></button>
											<?php elseif ( $alert_id ) : ?>
												<button type="submit" class="button" name="gi_compromise_reopen" value="<?php echo esc_attr( $alert_id ); ?>"><?php esc_html_e( 'Réouvrir', 'gi-toolkit' ); ?></button>
											<?php endif; ?>
										</div>
									</header>
									<?php $this->render_alert_details( $row, $is_resolved ); ?>
								</article>
							<?php endforeach; ?>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Le cron WordPress se déclenche surtout lors des visites. Pour un scan réellement chaque minute, planifiez wp-cron.php via crontab ou le panneau d’hébergement. Les événements (nouvel admin, page, plugin…) sont aussi détectés immédiatement via les hooks WordPress.', 'gi-toolkit' ); ?>
						</p>
					</div>
				</div>
				<details class="gi-cd-fold">
					<summary><?php esc_html_e( 'Compte Pushover', 'gi-toolkit' ); ?></summary>
					<div class="gi-cd-fold__body">
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
								<input type="password" class="regular-text code" id="gi_cd_pushover_app_token" name="<?php echo esc_attr( $this->option_id . '[pushover_app_token]' ); ?>" value="" autocomplete="new-password" placeholder="<?php echo $has_app_token ? esc_attr__( 'Jeton enregistré — laissez vide pour conserver', 'gi-toolkit' ) : ''; ?>" />
								<?php if ( $has_app_token ) : ?>
									<br /><span class="description"><?php esc_html_e( 'Le jeton n’est pas affiché. Laissez le champ vide pour le conserver.', 'gi-toolkit' ); ?></span>
								<?php endif; ?>
							</p>
							<p>
								<label for="gi_cd_pushover_user_key"><strong><?php esc_html_e( 'Clé utilisateur (User Key / user)', 'gi-toolkit' ); ?></strong></label><br />
								<input type="password" class="regular-text code" id="gi_cd_pushover_user_key" name="<?php echo esc_attr( $this->option_id . '[pushover_user_key]' ); ?>" value="" autocomplete="new-password" placeholder="<?php echo $has_user_key ? esc_attr__( 'Clé enregistrée — laissez vide pour conserver', 'gi-toolkit' ) : ''; ?>" />
								<?php if ( $has_user_key ) : ?>
									<br /><span class="description"><?php esc_html_e( 'La clé n’est pas affichée. Laissez le champ vide pour la conserver.', 'gi-toolkit' ); ?></span>
								<?php endif; ?>
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
								<?php esc_html_e( 'Un bouton « Marquer comme traitée » est ajouté à la notification Pushover (lien signé, sans connexion, valable 30 jours). Variable :', 'gi-toolkit' ); ?>
								<code title="<?php echo esc_attr( __( 'Lien signé pour traiter l’alerte', 'gi-toolkit' ) ); ?>">$resolve_url</code>
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
				</details>

				<details class="gi-cd-fold">
					<summary><?php esc_html_e( 'Éléments à surveiller', 'gi-toolkit' ); ?></summary>
					<div class="gi-cd-fold__body">
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
						<?php
						$custom_wl = self::sanitize_whitelist_list( $settings['outbound_whitelist'] ?? array() );
						?>
						<div class="gi-cd-whitelist">
							<p><strong><?php esc_html_e( 'Domaines autorisés (requêtes sortantes)', 'gi-toolkit' ); ?></strong></p>
							<p class="description"><?php esc_html_e( 'Un domaine par ligne. Les sous-domaines sont inclus (example.com autorise aussi api.example.com). Vous pouvez aussi autoriser un domaine depuis une alerte.', 'gi-toolkit' ); ?></p>
							<textarea class="large-text code" name="<?php echo esc_attr( $this->option_id . '[outbound_whitelist]' ); ?>" rows="4" placeholder="cdn.exemple.com"><?php echo esc_textarea( implode( "\n", $custom_wl ) ); ?></textarea>
							<?php if ( ! empty( $custom_wl ) ) : ?>
								<ul class="gi-cd-whitelist__list">
									<?php foreach ( $custom_wl as $wl_host ) : ?>
										<li>
											<code><?php echo esc_html( $wl_host ); ?></code>
											<button type="submit" class="button-link" name="gi_compromise_unwhitelist" value="<?php echo esc_attr( $wl_host ); ?>"><?php esc_html_e( 'Retirer', 'gi-toolkit' ); ?></button>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
				</details>

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
			'cleared'      => array( 'success', __( 'Journal vidé.', 'gi-toolkit' ) ),
			'resolved'     => array( 'success', __( 'Alerte marquée comme traitée.', 'gi-toolkit' ) ),
			'reopened'     => array( 'success', __( 'Alerte rouverte.', 'gi-toolkit' ) ),
			'resolved_all'  => array( 'success', __( 'Toutes les alertes ouvertes ont été marquées comme traitées.', 'gi-toolkit' ) ),
			'whitelisted'   => array( 'success', __( 'Domaine ajouté à la whitelist. Les prochaines requêtes vers ce domaine (et ses sous-domaines) ne déclencheront plus d’alerte.', 'gi-toolkit' ) ),
			'unwhitelisted' => array( 'success', __( 'Domaine retiré de la whitelist.', 'gi-toolkit' ) ),
			'whitelist_fail'=> array( 'error', __( 'Impossible d’ajouter ce domaine à la whitelist.', 'gi-toolkit' ) ),
			'test_ok'       => array( 'success', __( 'Notification de test Pushover envoyée.', 'gi-toolkit' ) ),
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
