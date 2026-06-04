<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alimente mails, backups et Uptime Kuma lors de la synchronisation globale MainWP.
 */
class MainWP_GIWeb_MainWP_Sync {

	const SYNC_BATCH_TRANSIENT = 'mainwp_giweb_sync_batch';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'mainwp_site_synced', array( __CLASS__, 'on_site_synced' ), 20, 2 );
		add_action( 'mainwp_synced_all_sites', array( __CLASS__, 'on_all_sites_synced' ), 20 );
	}

	/**
	 * @return bool
	 */
	private static function ensure_activator() {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator ) {
			return false;
		}

		if ( empty( $mainwp_giweb_activator->childKey ) && method_exists( $mainwp_giweb_activator, 'activate_extension' ) ) {
			$mainwp_giweb_activator->activate_extension();
		}

		return ! empty( $mainwp_giweb_activator->childKey ) || ! empty( $mainwp_giweb_activator->childEnabled );
	}

	/**
	 * Réinitialise les agrégats au premier site d’un batch de sync globale.
	 *
	 * @return void
	 */
	private static function maybe_reset_aggregates_for_batch() {
		if ( get_transient( self::SYNC_BATCH_TRANSIENT ) ) {
			return;
		}

		set_transient( self::SYNC_BATCH_TRANSIENT, '1', 5 * MINUTE_IN_SECONDS );
		MainWP_GIWeb_Status_Cache::mark_sync_started();
	}

	/**
	 * Construit une réponse API GI-Toolkit à partir des données déjà remontées par MainWP.
	 *
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	/**
	 * @param mixed $information Données sync MainWP (tableau ou objet).
	 * @return array<string, mixed>|null
	 */
	private static function normalize_information( $information ) {
		if ( is_array( $information ) ) {
			return $information;
		}

		if ( is_object( $information ) ) {
			return json_decode( wp_json_encode( $information ), true );
		}

		return null;
	}

	/**
	 * Score de richesse d’un sous-payload (mail, backup, etc.).
	 *
	 * @param array<string, mixed> $payload
	 * @return int
	 */
	private static function payload_richness_score( $payload ) {
		$score = 0;
		if ( ! empty( $payload['module_active'] ) || ! empty( $payload['plugin_active'] ) ) {
			$score += 50;
		}
		if ( ! empty( $payload['table_ready'] ) ) {
			$score += 30;
		}
		$score += min( 20, (int) ( $payload['total'] ?? 0 ) );
		if ( ! empty( $payload['last_backup_time'] ) ) {
			$score += 15;
		}
		if ( ! empty( $payload['last_backup_label'] ) ) {
			$score += 5;
		}

		return $score;
	}

	/**
	 * Garde le payload le plus complet entre deux sources.
	 *
	 * @param array<string, mixed>|null $a
	 * @param array<string, mixed>|null $b
	 * @return array<string, mixed>|null
	 */
	public static function pick_richer_payload( $a, $b ) {
		if ( ! is_array( $a ) && ! is_array( $b ) ) {
			return null;
		}
		if ( ! is_array( $a ) ) {
			return $b;
		}
		if ( ! is_array( $b ) ) {
			return $a;
		}

		$score_a = self::payload_richness_score( $a );
		$score_b = self::payload_richness_score( $b );
		if ( $score_b > $score_a ) {
			return $b;
		}
		if ( $score_a > $score_b ) {
			return $a;
		}

		return array_merge( $a, $b );
	}

	/**
	 * Fusionne la réponse API distante et les données déjà remontées par la sync MainWP.
	 *
	 * @param array<string, mixed> $remote
	 * @param array<string, mixed> $from_sync
	 * @return array<string, mixed>
	 */
	private static function merge_api_responses( $remote, $from_sync ) {
		$remote_data = is_array( $remote['data'] ?? null ) ? $remote['data'] : array();
		$sync_data   = is_array( $from_sync['data'] ?? null ) ? $from_sync['data'] : array();
		$merged      = $remote_data;

		foreach ( $sync_data as $key => $value ) {
			if ( in_array( $key, array( 'mail_catcher', 'updraftplus' ), true ) && is_array( $value ) ) {
				$existing       = isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ? $merged[ $key ] : null;
				$merged[ $key ] = self::pick_richer_payload( $existing, $value );
				continue;
			}

			if ( ! isset( $merged[ $key ] ) || '' === $merged[ $key ] || null === $merged[ $key ] ) {
				$merged[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = array_merge( $merged[ $key ], $value );
			}
		}

		$errors = array_merge(
			(array) ( $remote['errors'] ?? array() ),
			(array) ( $from_sync['errors'] ?? array() )
		);

		return array(
			'success' => ! empty( $remote['success'] ) || ! empty( $from_sync['success'] ),
			'data'    => $merged,
			'errors'  => array_values( array_unique( array_filter( array_map( 'strval', $errors ) ) ) ),
		);
	}

	private static function build_api_from_sync( $information ) {
		$information = self::normalize_information( $information );
		if ( ! is_array( $information ) ) {
			return null;
		}

		$data = array();
		if ( ! empty( $information['gi_toolkit_sync'] ) && is_array( $information['gi_toolkit_sync'] ) ) {
			$data = $information['gi_toolkit_sync'];
		}

		if ( ! empty( $information['gi_toolkit_mail_catcher'] ) && is_array( $information['gi_toolkit_mail_catcher'] ) ) {
			$data['mail_catcher'] = $information['gi_toolkit_mail_catcher'];
		}

		if ( ! empty( $information['gi_toolkit_updraftplus'] ) && is_array( $information['gi_toolkit_updraftplus'] ) ) {
			$data['updraftplus'] = $information['gi_toolkit_updraftplus'];
		}

		if ( empty( $data ) ) {
			return null;
		}

		return array(
			'success' => true,
			'data'    => $data,
			'errors'  => array(),
		);
	}

	/**
	 * @param object               $website     Objet site MainWP.
	 * @param array<string, mixed> $information Données remontées par MainWP.
	 * @return void
	 */
	public static function on_site_synced( $website, $information ) {
		if ( ! self::should_process_sync() ) {
			return;
		}

		if ( ! is_object( $website ) || empty( $website->id ) ) {
			return;
		}

		$site_id = absint( $website->id );
		if ( ! $site_id ) {
			return;
		}

		self::maybe_reset_aggregates_for_batch();

		$label = ! empty( $website->name ) ? (string) $website->name : '';
		if ( '' === $label && ! empty( $website->url ) ) {
			$label = (string) $website->url;
		}
		if ( '' === $label ) {
			$label = '#' . $site_id;
		}

		$url = ! empty( $website->url ) ? (string) $website->url : '';

		$information = self::normalize_information( $information );
		$api_sync    = self::build_api_from_sync( $information );
		$api_remote  = MainWP_GIWeb_API::get_status( $site_id );
		if ( ! is_array( $api_remote ) ) {
			$api_remote = array(
				'success' => false,
				'data'    => array(),
				'errors'  => array(),
			);
		}

		$api_sync_wrapped = is_array( $api_sync ) ? $api_sync : array(
			'success' => false,
			'data'    => array(),
			'errors'  => array(),
		);
		$api              = self::merge_api_responses( $api_remote, $api_sync_wrapped );

		if ( empty( $api['success'] ) && empty( $api['data'] ) ) {
			$result = MainWP_GIWeb_Deploy::sync_site_status( $site_id, $label );
			$api    = is_array( $result['api'] ?? null ) ? $result['api'] : array(
				'success' => false,
				'data'    => array(),
				'errors'  => array( __( 'Sync GI-Toolkit impossible.', 'mainwp-giweb' ) ),
			);
		}

		MainWP_GIWeb_Status_Cache::set_site( $site_id, $api );
		MainWP_GIWeb_Mail_Stats::record_site_sync( $site_id, $label, $url, $api, $information );
		MainWP_GIWeb_Backup_Stats::record_site_sync( $site_id, $label, $url, $api, $information );
		MainWP_GIWeb_Uptime_Kuma_Widget::schedule_refresh_on_sync();
	}

	/**
	 * Finalise la sync extension après le batch MainWP (cron ou sync globale manuelle).
	 *
	 * @return void
	 */
	public static function on_all_sites_synced() {
		delete_transient( self::SYNC_BATCH_TRANSIENT );
		MainWP_GIWeb_Status_Cache::mark_sync_completed();

		if ( MainWP_GIWeb_Uptime_Kuma::is_configured() ) {
			MainWP_GIWeb_Uptime_Kuma_Widget::refresh_cache( true );
		}
	}

	/**
	 * @return bool
	 */
	private static function should_process_sync() {
		if ( ! self::ensure_activator() ) {
			return false;
		}

		/**
		 * Autorise la collecte lors de la sync globale MainWP (y compris cron),
		 * sans exiger l’activation UI de l’extension.
		 *
		 * @param bool $allowed Traiter la sync.
		 */
		return (bool) apply_filters( 'mainwp_giweb_process_mainwp_sync', true );
	}
}

MainWP_GIWeb_MainWP_Sync::init();
