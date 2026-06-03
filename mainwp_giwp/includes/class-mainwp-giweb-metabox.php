<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistrement commun des metaboxes / widgets MainWP Overview.
 */
class MainWP_GIWeb_Metabox {

	/** @var int */
	private static $render_site_id = 0;

	/** @var string[] */
	private static $widget_ids = array(
		'mainwp-giweb-mail-widget-simple',
		'mainwp-giweb-mail-widget-detailed',
		'mainwp-giweb-backup-widget-simple',
		'mainwp-giweb-backup-widget-detailed',
		'mainwp-giweb-uptime-kuma-widget-simple',
		'mainwp-giweb-uptime-kuma-widget-detailed',
	);

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'mainwp_getmetaboxes', array( __CLASS__, 'normalize_metaboxes' ), 99, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_shell_assets' ) );
	}

	/**
	 * Padding cohérent sur les conteneurs MainWP de nos widgets.
	 *
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public static function enqueue_shell_assets( $hook ) {
		unset( $hook );
		if ( ! self::should_enqueue_on_mainwp_dashboard() ) {
			return;
		}

		wp_enqueue_style(
			'mainwp-giweb-widgets-shell',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/mainwp-widgets-shell.css',
			array(),
			MAINWP_GIWEB_VERSION
		);
	}

	/**
	 * @return bool
	 */
	public static function should_enqueue_on_mainwp_dashboard() {
		if ( isset( $_GET['page'] ) && 'managesites' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return true;
		}

		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return in_array( $page, array( 'mainwp_tab', 'mainwp-setup' ), true );
	}

	/**
	 * @return bool
	 */
	public static function can_register() {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator || empty( $mainwp_giweb_activator->childEnabled ) ) {
			return false;
		}

		return MainWP_GIWeb_Capabilities::can_access();
	}

	/**
	 * @return string
	 */
	public static function plugin_file() {
		global $mainwp_giweb_activator;

		if ( $mainwp_giweb_activator && method_exists( $mainwp_giweb_activator, 'getChildFile' ) ) {
			$file = (string) $mainwp_giweb_activator->getChildFile();
			if ( '' !== $file ) {
				return $file;
			}
		}

		return defined( 'MAINWP_GIWEB_PLUGIN_FILE' ) ? MAINWP_GIWEB_PLUGIN_FILE : '';
	}

	/**
	 * @return string
	 */
	public static function child_key() {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator ) {
			return '';
		}

		if ( is_array( $mainwp_giweb_activator->childEnabled ) && ! empty( $mainwp_giweb_activator->childEnabled['key'] ) ) {
			return (string) $mainwp_giweb_activator->childEnabled['key'];
		}

		return (string) ( $mainwp_giweb_activator->childKey ?? '' );
	}

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Liste existante.
	 * @param string                                   $id        ID widget.
	 * @param string                                   $title     Titre.
	 * @param callable|array<int, mixed>               $callback  Callback rendu.
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * @return int
	 */
	public static function get_render_site_id() {
		return self::$render_site_id;
	}

	/**
	 * @param callable|array<int, mixed> $callback  Callback rendu.
	 * @param int                          $site_id   ID site MainWP (0 = réseau).
	 * @return callable
	 */
	public static function scoped_callback( $callback, $site_id ) {
		$site_id = absint( $site_id );

		return static function () use ( $callback, $site_id ) {
			self::$render_site_id = $site_id;
			call_user_func( $callback );
			self::$render_site_id = 0;
		};
	}

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes       Liste existante.
	 * @param string                                   $id              ID widget.
	 * @param string                                   $title           Titre.
	 * @param callable|array<int, mixed>               $callback        Callback réseau.
	 * @param int|null                                 $dashboard_siteid ID site (Overview individuel).
	 * @return array<int, array<string, mixed>>
	 */
	public static function append( $metaboxes, $id, $title, $callback, $dashboard_siteid = null ) {
		if ( ! self::can_register() ) {
			return is_array( $metaboxes ) ? $metaboxes : array();
		}

		if ( ! is_array( $metaboxes ) ) {
			$metaboxes = array();
		}

		$plugin = self::plugin_file();
		if ( '' === $plugin ) {
			return $metaboxes;
		}

		$site_id  = null !== $dashboard_siteid ? absint( $dashboard_siteid ) : 0;
		$render   = $site_id > 0 ? self::scoped_callback( $callback, $site_id ) : $callback;
		$metaboxes[] = array(
			'id'            => $id,
			'plugin'        => $plugin,
			'key'           => self::child_key(),
			'metabox_title' => $title,
			'callback'      => $render,
			'layout'        => array( -1, -1, 6, 30 ),
		);

		return $metaboxes;
	}

	/**
	 * @param array<string, string>|mixed $options Options widgets.
	 * @param string                      $id      ID widget.
	 * @param string                      $title   Libellé.
	 * @return array<string, string>
	 */
	public static function append_screen_option( $options, $id, $title ) {
		if ( ! self::can_register() ) {
			return is_array( $options ) ? $options : array();
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options[ $id ] = $title;
		return $options;
	}

	/**
	 * Garantit la clé « plugin » sur nos metaboxes (évite WP_List_Util::pluck sur stdClass incomplets).
	 *
	 * @param mixed $metaboxes Metaboxes.
	 * @return mixed
	 */
	public static function normalize_metaboxes( $metaboxes ) {
		if ( ! is_array( $metaboxes ) ) {
			return $metaboxes;
		}

		$plugin = self::plugin_file();
		if ( '' === $plugin ) {
			return $metaboxes;
		}

		foreach ( $metaboxes as $idx => $box ) {
			if ( is_object( $box ) ) {
				$metaboxes[ $idx ] = (array) $box;
				$box               = $metaboxes[ $idx ];
			}

			if ( ! is_array( $box ) ) {
				continue;
			}

			$box_id = isset( $box['id'] ) ? (string) $box['id'] : '';
			if ( in_array( $box_id, self::$widget_ids, true ) && empty( $box['plugin'] ) ) {
				$metaboxes[ $idx ]['plugin'] = $plugin;
			}
		}

		return $metaboxes;
	}
}
