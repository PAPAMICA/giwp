<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Journal d'erreurs persistant de l'extension MainWP GI-Web.
 *
 * Indépendant de WP_DEBUG : toute exception attrapée par
 * MainWP_GIWeb_Sync_Ajax::send_exception() est enregistrée ici avec son
 * contexte complet (action AJAX, site concerné, message, fichier/ligne,
 * trace tronquée, durée de la requête si connue). Consultable depuis
 * l'onglet Historique sans avoir besoin d'un accès SSH/logs serveur.
 *
 * @since 1.7.0
 */
class MainWP_GIWeb_Error_Log {

	const OPTION_KEY  = 'mainwp_giweb_error_log';
	const MAX_ENTRIES = 200;
	const MAX_TRACE_CHARS = 2000;

	/**
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'mainwp_giweb_error_log';

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			ajax_action varchar(64) DEFAULT '',
			site_id bigint(20) unsigned DEFAULT 0,
			site_label varchar(255) DEFAULT '',
			error_type varchar(40) DEFAULT 'php_exception',
			message text,
			file varchar(255) DEFAULT '',
			line int unsigned DEFAULT 0,
			trace text,
			duration_ms int unsigned DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT 0,
			PRIMARY KEY  (id),
			KEY site_id (site_id),
			KEY ajax_action (ajax_action)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param Throwable|Exception          $e       Exception attrapée.
	 * @param array<string, mixed>         $context ajax_action, site_id, site_label, duration_ms, error_type.
	 * @return int ID de l'entrée créée (0 si l'insertion échoue).
	 */
	public static function record( $e, array $context = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_error_log';

		$trace = (string) $e->getTraceAsString();
		if ( strlen( $trace ) > self::MAX_TRACE_CHARS ) {
			$trace = substr( $trace, 0, self::MAX_TRACE_CHARS ) . "\n… (tronqué)";
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'MainWP GI-Web [%s] site=%s: %s (%s:%d)',
				$context['ajax_action'] ?? '?',
				$context['site_label'] ?? ( $context['site_id'] ?? '?' ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'ajax_action'  => sanitize_key( (string) ( $context['ajax_action'] ?? '' ) ),
				'site_id'      => absint( $context['site_id'] ?? 0 ),
				'site_label'   => sanitize_text_field( (string) ( $context['site_label'] ?? '' ) ),
				'error_type'   => sanitize_key( (string) ( $context['error_type'] ?? self::classify( $e ) ) ),
				'message'      => sanitize_textarea_field( (string) $e->getMessage() ),
				'file'         => sanitize_text_field( basename( (string) $e->getFile() ) ),
				'line'         => absint( $e->getLine() ),
				'trace'        => $trace,
				'duration_ms'  => absint( $context['duration_ms'] ?? 0 ),
				'user_id'      => get_current_user_id(),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d' )
		);

		self::prune();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Devine une catégorie d'erreur lisible à partir du message/type
	 * d'exception, pour aider au diagnostic sans lire la stack trace.
	 *
	 * @param Throwable|Exception $e Exception.
	 * @return string
	 */
	public static function classify( $e ) {
		$message = strtolower( (string) $e->getMessage() );

		if ( false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'timeout' ) ) {
			return 'timeout';
		}
		if ( false !== strpos( $message, 'could not resolve host' ) || false !== strpos( $message, 'connection refused' ) || false !== strpos( $message, 'couldn\'t connect' ) ) {
			return 'connection_failed';
		}
		if ( false !== strpos( $message, 'ssl' ) || false !== strpos( $message, 'certificate' ) ) {
			return 'ssl_error';
		}
		if ( false !== strpos( $message, 'memory' ) || false !== strpos( $message, 'allowed memory size' ) ) {
			return 'memory_exhausted';
		}
		if ( $e instanceof Error || $e instanceof TypeError || $e instanceof ArgumentCountError ) {
			return 'php_fatal';
		}
		return 'php_exception';
	}

	/**
	 * Message court destiné à l'utilisateur (préfixe explicite selon le type),
	 * sans exposer de chemin serveur.
	 *
	 * @param string $error_type Type retourné par classify().
	 * @param string $raw_message Message brut de l'exception.
	 * @return string
	 */
	public static function friendly_message( $error_type, $raw_message ) {
		$prefixes = array(
			'timeout'            => __( 'Délai d\'attente dépassé en contactant le site', 'mainwp-giweb' ),
			'connection_failed'  => __( 'Connexion impossible au site (DNS/réseau)', 'mainwp-giweb' ),
			'ssl_error'          => __( 'Erreur SSL/certificat en contactant le site', 'mainwp-giweb' ),
			'memory_exhausted'   => __( 'Mémoire PHP épuisée sur le Dashboard MainWP', 'mainwp-giweb' ),
			'php_fatal'          => __( 'Erreur PHP fatale côté Dashboard MainWP', 'mainwp-giweb' ),
			'php_exception'      => __( 'Erreur inattendue côté Dashboard MainWP', 'mainwp-giweb' ),
		);
		$prefix = $prefixes[ $error_type ] ?? $prefixes['php_exception'];
		$raw    = trim( wp_strip_all_tags( (string) $raw_message ) );

		return '' !== $raw ? ( $prefix . ' : ' . $raw ) : $prefix;
	}

	/**
	 * @param int $limit Limite.
	 * @return array<int, object>
	 */
	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_error_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				absint( $limit )
			)
		);
	}

	/**
	 * @param int $site_id Site.
	 * @param int $limit   Limite.
	 * @return array<int, object>
	 */
	public static function get_for_site( $site_id, $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_error_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE site_id = %d ORDER BY id DESC LIMIT %d",
				absint( $site_id ),
				absint( $limit )
			)
		);
	}

	/**
	 * Purge les entrées excédentaires (garde les MAX_ENTRIES plus récentes).
	 *
	 * @return void
	 */
	private static function prune() {
		global $wpdb;
		$table = $wpdb->prefix . 'mainwp_giweb_error_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count <= self::MAX_ENTRIES ) {
			return;
		}
		$overflow = $count - self::MAX_ENTRIES;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
				$overflow
			)
		);
	}
}
