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

		update_option(
			MainWP_GIWeb_Mail_Stats::AGGREGATE_OPTION,
			array(
				'sites'      => array(),
				'network'    => MainWP_GIWeb_Mail_Stats::compute_network( array() ),
				'updated_at' => 0,
			),
			false
		);
		update_option(
			MainWP_GIWeb_Backup_Stats::AGGREGATE_OPTION,
			array(
				'sites'      => array(),
				'network'    => MainWP_GIWeb_Backup_Stats::compute_network( array() ),
				'updated_at' => 0,
			),
			false
		);
		MainWP_GIWeb_Mail_Stats::clear_alert();
	}

	/**
	 * Construit une réponse API GI-Toolkit à partir des données déjà remontées par MainWP.
	 *
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	private static function build_api_from_sync( $information ) {
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

		$api = self::build_api_from_sync( $information );
		if ( ! is_array( $api ) || empty( $api['success'] ) ) {
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

		if ( ! (bool) apply_filters( 'mainwp_activated_check', false ) ) {
			return false;
		}

		/**
		 * Autorise la collecte lors de la sync globale MainWP (y compris cron),
		 * sans exiger un accès UI à l’extension.
		 *
		 * @param bool $allowed Traiter la sync.
		 */
		return (bool) apply_filters( 'mainwp_giweb_process_mainwp_sync', true );
	}
}

MainWP_GIWeb_MainWP_Sync::init();
