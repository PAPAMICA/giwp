<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestration des déploiements multi-sites.
 */
class MainWP_GIWeb_Deploy {

	/**
	 * @param array<string, mixed> $bundle        Bundle source.
	 * @param int[]                $site_ids      Sites cibles.
	 * @param string               $template_id   ID modèle.
	 * @param string               $template_name Nom modèle.
	 * @return array{deployment_id: int, results: array<int, array<string, mixed>>}
	 */
	public static function push_to_sites( $bundle, $site_ids, $template_id = '', $template_name = '' ) {
		$bundle = MainWP_GIWeb_Uptime_Kuma::merge_into_bundle(
			MainWP_GIWeb_Matomo::merge_into_bundle( is_array( $bundle ) ? $bundle : array() )
		);
		$hash          = md5( wp_json_encode( $bundle ) );
		$deployment_id = MainWP_GIWeb_History::create_deployment( $template_id, $template_name, $hash );
		$results       = array();

		foreach ( $site_ids as $site_id ) {
			$site_id = absint( $site_id );
			if ( ! $site_id ) {
				continue;
			}
			$args   = MainWP_GIWeb_Overrides::apply_to_bundle( $bundle, $site_id );
			$result = MainWP_GIWeb_API::import_site( $site_id, $bundle, $args );
			$status = ! empty( $result['success'] ) ? 'success' : 'error';
			$msg    = MainWP_GIWeb_API::format_deploy_result_message( $site_id, '', $result, 'success' === $status );
			if ( 'success' === $status ) {
				$refresh = MainWP_GIWeb_API::refresh_integrations_after_deploy( $site_id, $result );
				if ( '' !== (string) ( $refresh['message'] ?? '' ) ) {
					$msg .= ' · ' . (string) $refresh['message'];
				}
			}
			$ftp_note = MainWP_GIWeb_Ftp_Backup::maybe_ensure_on_deploy( $site_id, 'success' === $status );
			if ( '' !== $ftp_note ) {
				$msg .= ' · ' . $ftp_note;
			}
			MainWP_GIWeb_History::log_site_result( $deployment_id, $site_id, $status, $msg, $result );
			$results[ $site_id ] = $result;
		}

		return array(
			'deployment_id' => $deployment_id,
			'results'       => $results,
		);
	}

	/**
	 * Rafraîchit le statut de tous les sites connectés.
	 *
	 * @param array<int, object> $websites Sites MainWP.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sync_all_status( $websites ) {
		$out = array();
		if ( ! is_array( $websites ) ) {
			return $out;
		}
		foreach ( $websites as $site ) {
			$row = MainWP_GIWeb_Sites::normalize_one( $site );
			if ( $row['id'] <= 0 ) {
				continue;
			}
			$label        = $row['name'] ?: $row['url'] ?: ( '#' . $row['id'] );
			$out[ $row['id'] ] = self::sync_site_status( $row['id'], $label )['api'];
		}
		return $out;
	}

	/**
	 * Interroge le statut GI-Toolkit d’un site (avec message de log).
	 *
	 * @param int    $site_id ID MainWP.
	 * @param string $label   Libellé affiché dans les logs.
	 * @return array<string, mixed>
	 */
	public static function sync_site_status( $site_id, $label ) {
		$site_id = absint( $site_id );
		$label   = '' !== $label ? $label : '#' . $site_id;

		$api    = MainWP_GIWeb_API::get_status( $site_id );
		$ok     = ! empty( $api['success'] );
		$data   = is_array( $api['data'] ) ? $api['data'] : array();
		$detail = '';

		if ( $ok ) {
			$version = isset( $data['gi_toolkit_version'] ) ? (string) $data['gi_toolkit_version'] : '—';
			$modules = isset( $data['active_modules'] ) ? (string) $data['active_modules'] : '0';
			$detail  = sprintf(
				/* translators: 1: version, 2: active module count */
				__( 'GI-Toolkit %1$s — %2$s modules actifs', 'mainwp-giweb' ),
				$version,
				$modules
			);
			$mail = MainWP_GIWeb_Mail_Stats::extract_mail( $data );
			if ( is_array( $mail ) && ! empty( $mail['module_active'] ) && ! empty( $mail['table_ready'] ) ) {
				$detail .= sprintf(
					/* translators: 1: total mails, 2: failed mails */
					__( ' — mails : %1$d total, %2$d échec(s)', 'mainwp-giweb' ),
					(int) ( $mail['total'] ?? 0 ),
					(int) ( $mail['failed'] ?? 0 )
				);
			}
		} else {
			$detail = ! empty( $api['errors'][0] ) ? (string) $api['errors'][0] : __( 'Échec', 'mainwp-giweb' );
		}

		$log = sprintf(
			'[%s] %s — %s',
			$ok ? 'OK' : __( 'ERR', 'mainwp-giweb' ),
			$label,
			$detail
		);

		return array(
			'site_id' => $site_id,
			'success' => $ok,
			'log'     => $log,
			'message' => $detail,
			'data'    => $data,
			'api'     => $api,
		);
	}
}
