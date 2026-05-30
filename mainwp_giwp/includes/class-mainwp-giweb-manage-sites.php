<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Colonnes Manage Sites MainWP + assets (liste et dashboard site).
 */
class MainWP_GIWeb_Manage_Sites {

	const COL_UPTIME = 'giweb_uptime_kuma';
	const COL_MAIL   = 'giweb_mail';

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! MainWP_GIWeb_Metabox::can_register() ) {
			return;
		}

		add_filter( 'mainwp_sitestable_getcolumns', array( __CLASS__, 'add_columns' ), 20, 2 );
		add_filter( 'mainwp_sitestable_item', array( __CLASS__, 'fill_column' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_manage_sites_screen() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return 'managesites' === $page;
	}

	/**
	 * @return int
	 */
	public static function get_dashboard_site_id() {
		if ( ! self::is_manage_sites_screen() || empty( $_GET['dashboard'] ) ) {
			return 0;
		}

		return absint( $_GET['dashboard'] );
	}

	/**
	 * @param array<string, string> $columns Colonnes existantes.
	 * @param array<string, string> $disp    Libellés affichés.
	 * @return array<string, string>
	 */
	public static function add_columns( $columns, $disp ) {
		unset( $disp );

		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$insert = array(
			self::COL_UPTIME => __( 'Uptime Kuma', 'mainwp-giweb' ),
			self::COL_MAIL   => __( 'Mails', 'mainwp-giweb' ),
		);

		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'site_actions' === $key ) {
				foreach ( $insert as $col_key => $col_label ) {
					$out[ $col_key ] = $col_label;
				}
			}
			$out[ $key ] = $label;
		}

		if ( ! isset( $out[ self::COL_UPTIME ] ) ) {
			$out = array_merge( $out, $insert );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $item        Données ligne site.
	 * @param string               $column_name Colonne en cours.
	 * @return array<string, mixed>
	 */
	public static function fill_column( $item, $column_name ) {
		if ( ! is_array( $item ) || empty( $item['id'] ) ) {
			return $item;
		}

		$site_id = absint( $item['id'] );
		if ( ! $site_id ) {
			return $item;
		}

		if ( self::COL_UPTIME === $column_name ) {
			$item[ self::COL_UPTIME ] = MainWP_GIWeb_Uptime_Kuma_Widget::format_site_cell( $site_id );
		}

		if ( self::COL_MAIL === $column_name ) {
			$item[ self::COL_MAIL ] = MainWP_GIWeb_Mail_Stats::format_site_mail_cell(
				MainWP_GIWeb_Mail_Stats::get_site_mail( $site_id )
			);
		}

		return $item;
	}

	/**
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );

		if ( ! self::is_manage_sites_screen() ) {
			return;
		}

		wp_enqueue_style(
			'mainwp-giweb-manage-sites',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/manage-sites.css',
			array(),
			MAINWP_GIWEB_VERSION
		);

		wp_enqueue_style(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array( 'mainwp-giweb-manage-sites' ),
			MAINWP_GIWEB_VERSION
		);

		wp_enqueue_style(
			'mainwp-giweb-uptime-kuma-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/uptime-kuma-widget.css',
			array( 'mainwp-giweb-manage-sites' ),
			MAINWP_GIWEB_VERSION
		);

		if ( self::get_dashboard_site_id() > 0 ) {
			wp_enqueue_script(
				'mainwp-giweb-uptime-kuma-widget',
				MAINWP_GIWEB_PLUGIN_URL . 'assets/js/uptime-kuma-widget.js',
				array(),
				MAINWP_GIWEB_VERSION,
				true
			);
			wp_enqueue_script(
				'mainwp-giweb-dashboard-widget',
				MAINWP_GIWEB_PLUGIN_URL . 'assets/js/dashboard-widget.js',
				array(),
				MAINWP_GIWEB_VERSION,
				true
			);
		}
	}
}
