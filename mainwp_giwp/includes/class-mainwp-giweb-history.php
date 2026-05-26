<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Historique des déploiements.
 */
class MainWP_GIWeb_History {

	/**
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$deploy  = $wpdb->prefix . 'mainwp_giweb_deployments';
		$sites   = $wpdb->prefix . 'mainwp_giweb_deployment_sites';

		$sql1 = "CREATE TABLE {$deploy} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			template_id varchar(64) DEFAULT '',
			template_name varchar(255) DEFAULT '',
			bundle_hash varchar(64) DEFAULT '',
			user_id bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset};";

		$sql2 = "CREATE TABLE {$sites} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			deployment_id bigint(20) unsigned NOT NULL,
			site_id bigint(20) unsigned NOT NULL,
			status varchar(20) DEFAULT 'pending',
			message text,
			response_json longtext,
			PRIMARY KEY  (id),
			KEY deployment_id (deployment_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	/**
	 * @param string               $template_id   ID modèle.
	 * @param string               $template_name Nom.
	 * @param string               $bundle_hash   Hash.
	 * @return int Deployment ID.
	 */
	public static function create_deployment( $template_id, $template_name, $bundle_hash ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_deployments';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'template_id'   => sanitize_text_field( $template_id ),
				'template_name' => sanitize_text_field( $template_name ),
				'bundle_hash'   => sanitize_text_field( $bundle_hash ),
				'user_id'       => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int    $deployment_id Deployment.
	 * @param int    $site_id       Site.
	 * @param string $status        Statut.
	 * @param string $message       Message.
	 * @param mixed  $response      Réponse.
	 * @return void
	 */
	public static function log_site_result( $deployment_id, $site_id, $status, $message, $response = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_deployment_sites';
		$json  = is_string( $response ) ? $response : wp_json_encode( $response );
		if ( strlen( $json ) > 50000 ) {
			$json = substr( $json, 0, 50000 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'deployment_id'  => absint( $deployment_id ),
				'site_id'        => absint( $site_id ),
				'status'         => sanitize_key( $status ),
				'message'        => sanitize_text_field( $message ),
				'response_json'  => $json,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param int $limit Limite.
	 * @return array<int, object>
	 */
	public static function get_recent_deployments( $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_deployments';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				absint( $limit )
			)
		);
	}

	/**
	 * @param int $deployment_id ID.
	 * @return array<int, object>
	 */
	public static function get_deployment_sites( $deployment_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_deployment_sites';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE deployment_id = %d ORDER BY id ASC",
				absint( $deployment_id )
			)
		);
	}
}
