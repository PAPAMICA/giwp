<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tables SQL pour le module liens cassés.
 */
class Gi_Toolkit_Broken_Links_DB {

	const SCANS_TABLE  = 'gi_toolkit_broken_link_scans';

	const LINKS_TABLE  = 'gi_toolkit_broken_links';

	const DB_VERSION   = '1.0.0';

	const OPTION_VER   = 'gi_toolkit_broken_links_db_version';

	/**
	 * @return string
	 */
	public static function scans_table() {
		global $wpdb;
		return $wpdb->prefix . self::SCANS_TABLE;
	}

	/**
	 * @return string
	 */
	public static function links_table() {
		global $wpdb;
		return $wpdb->prefix . self::LINKS_TABLE;
	}

	/**
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$scans   = self::scans_table();
		$links   = self::links_table();

		$sql = "CREATE TABLE {$scans} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			started_at datetime NOT NULL,
			finished_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'running',
			urls_checked int unsigned NOT NULL DEFAULT 0,
			broken_count int unsigned NOT NULL DEFAULT 0,
			trigger_type varchar(20) NOT NULL DEFAULT 'manual',
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset};

		CREATE TABLE {$links} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			url text NOT NULL,
			source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_type varchar(50) NOT NULL DEFAULT '',
			link_text varchar(255) NOT NULL DEFAULT '',
			http_status smallint NOT NULL DEFAULT 0,
			error_message varchar(255) NOT NULL DEFAULT '',
			checked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY url (url(191))
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::OPTION_VER, self::DB_VERSION, false );
	}

	/**
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::OPTION_VER, '' ) ) {
			self::install();
		}
	}

	/**
	 * @param string $trigger manual|cron.
	 * @return int Scan ID.
	 */
	public static function create_scan( $trigger = 'manual' ) {
		global $wpdb;
		$wpdb->insert(
			self::scans_table(),
			array(
				'started_at'   => current_time( 'mysql' ),
				'status'       => 'running',
				'trigger_type' => sanitize_key( $trigger ),
			),
			array( '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $scan_id ID scan.
	 * @param array<string, mixed> $data Données.
	 * @return void
	 */
	public static function update_scan( $scan_id, $data ) {
		global $wpdb;
		$wpdb->update( self::scans_table(), $data, array( 'id' => (int) $scan_id ) );
	}

	/**
	 * @param int $scan_id ID scan.
	 * @return void
	 */
	public static function delete_scan_links( $scan_id ) {
		global $wpdb;
		$wpdb->delete( self::links_table(), array( 'scan_id' => (int) $scan_id ), array( '%d' ) );
	}

	/**
	 * @param int                  $scan_id ID scan.
	 * @param array<string, mixed> $row Ligne.
	 * @return void
	 */
	public static function insert_broken_link( $scan_id, $row ) {
		global $wpdb;
		$wpdb->insert(
			self::links_table(),
			array(
				'scan_id'         => (int) $scan_id,
				'url'             => $row['url'] ?? '',
				'source_post_id'  => (int) ( $row['source_post_id'] ?? 0 ),
				'source_type'     => $row['source_type'] ?? '',
				'link_text'       => substr( (string) ( $row['link_text'] ?? '' ), 0, 255 ),
				'http_status'     => (int) ( $row['http_status'] ?? 0 ),
				'error_message'   => substr( (string) ( $row['error_message'] ?? '' ), 0, 255 ),
				'checked_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * @param int $limit Limite.
	 * @return array<int, object>
	 */
	public static function get_recent_scans( $limit = 10 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::scans_table() . ' ORDER BY id DESC LIMIT %d',
				(int) $limit
			)
		);
	}

	/**
	 * @param int $scan_id ID scan.
	 * @return object|null
	 */
	public static function get_scan( $scan_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::scans_table() . ' WHERE id = %d',
				(int) $scan_id
			)
		);
	}

	/**
	 * @return int|null
	 */
	public static function get_latest_scan_id() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT id FROM ' . self::scans_table() . ' ORDER BY id DESC LIMIT 1' );
	}

	/**
	 * @param int $keep Nombre de scans à conserver.
	 * @return void
	 */
	public static function prune_old_scans( $keep = 10 ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . self::scans_table() . ' ORDER BY id DESC LIMIT %d, 9999',
				(int) $keep
			)
		);
		if ( empty( $ids ) ) {
			return;
		}
		$in = implode( ',', array_map( 'intval', $ids ) );
		$wpdb->query( 'DELETE FROM ' . self::links_table() . ' WHERE scan_id IN (' . $in . ')' );
		$wpdb->query( 'DELETE FROM ' . self::scans_table() . ' WHERE id IN (' . $in . ')' );
	}
}
