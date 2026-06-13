<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrégation des statistiques Mail Catcher remontées via la synchro MainWP.
 */
class MainWP_GIWeb_Mail_Stats {

	const AGGREGATE_OPTION = 'mainwp_giweb_mail_aggregate';
	const ALERT_OPTION     = 'mainwp_giweb_mail_alert_pending';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
	}

	/**
	 * @param array<string, mixed> $data Données statut site.
	 * @return array<string, mixed>|null
	 */
	/**
	 * Nombre d’e-mails en échec pour un site (cohérent avec Mail Catcher).
	 *
	 * @param array<string, mixed>|null $mail Payload mail.
	 * @return int
	 */
	public static function get_failed_count( $mail ) {
		if ( ! is_array( $mail ) ) {
			return 0;
		}

		return max( 0, (int) ( $mail['failed'] ?? 0 ) );
	}

	/**
	 * @param array<string, mixed>|null $mail Payload mail.
	 * @return bool
	 */
	public static function has_mail_failures( $mail ) {
		return self::get_failed_count( $mail ) > 0;
	}

	/**
	 * @param array<string, mixed> $data Données statut site.
	 * @return array<string, mixed>|null
	 */
	public static function extract_mail( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! empty( $data['mail_catcher'] ) && is_array( $data['mail_catcher'] ) ) {
			return $data['mail_catcher'];
		}

		if ( array_key_exists( 'module_active', $data ) ) {
			return $data;
		}

		return null;
	}

	/**
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function extract_mail_from_sync( $information ) {
		if ( ! is_array( $information ) ) {
			return null;
		}

		if ( ! empty( $information['gi_toolkit_mail_catcher'] ) && is_array( $information['gi_toolkit_mail_catcher'] ) ) {
			return $information['gi_toolkit_mail_catcher'];
		}

		if ( ! empty( $information['gi_toolkit_sync']['mail_catcher'] ) && is_array( $information['gi_toolkit_sync']['mail_catcher'] ) ) {
			return $information['gi_toolkit_sync']['mail_catcher'];
		}

		return null;
	}

	/**
	 * @param array<string, mixed>|null $api_data    Données API GI-Toolkit.
	 * @param array<string, mixed>|null $information Données sync MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_mail_payload( $api_data, $information = null ) {
		$from_api  = is_array( $api_data ) ? self::extract_mail( $api_data ) : null;
		$from_sync = self::extract_mail_from_sync( $information );

		return MainWP_GIWeb_MainWP_Sync::pick_richer_payload( $from_api, $from_sync );
	}

	/**
	 * Met à jour l’agrégat réseau après synchro d’un site.
	 *
	 * @param int                       $site_id     ID MainWP.
	 * @param string                    $label       Nom site.
	 * @param string                    $url         URL site.
	 * @param array<string, mixed>      $api         Réponse API complète.
	 * @param array<string, mixed>|null $information Données sync MainWP (optionnel).
	 * @return array<string, mixed>
	 */
	public static function record_site_sync( $site_id, $label, $url, $api, $information = null ) {
		$site_id = absint( $site_id );
		$ok      = ! empty( $api['success'] );
		$data    = is_array( $api['data'] ?? null ) ? $api['data'] : array();
		$mail    = self::resolve_mail_payload( $data, $information );

		$aggregate = self::get_aggregate();
		if ( ! isset( $aggregate['sites'] ) || ! is_array( $aggregate['sites'] ) ) {
			$aggregate['sites'] = array();
		}

		$aggregate['sites'][ $site_id ] = array(
			'label'      => $label,
			'url'        => $url,
			'api_ok'     => $ok,
			'synced_at'  => time(),
			'mail'       => $mail,
		);

		$aggregate['updated_at'] = time();
		$aggregate['network']    = self::compute_network( $aggregate['sites'] );

		update_option( self::AGGREGATE_OPTION, $aggregate, false );

		self::maybe_set_alert( $aggregate );

		return $aggregate;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_aggregate() {
		$saved = get_option( self::AGGREGATE_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		if ( empty( $saved['sites'] ) || ! is_array( $saved['sites'] ) ) {
			$saved['sites'] = array();
		}
		if ( empty( $saved['network'] ) || ! is_array( $saved['network'] ) ) {
			$saved['network'] = self::compute_network( $saved['sites'] );
		}
		return $saved;
	}

	/**
	 * @param array<int, array<string, mixed>> $sites Sites indexés par ID.
	 * @return array<string, mixed>
	 */
	public static function compute_network( $sites ) {
		$network = array(
			'total'                 => 0,
			'success'               => 0,
			'failed'                => 0,
			'today'                 => 0,
			'resent_total'          => 0,
			'sites_tracked'         => 0,
			'sites_module_active'   => 0,
			'sites_with_failures'   => 0,
			'chart_labels'          => array(),
			'chart_sent'            => array(),
			'chart_failed'          => array(),
		);

		if ( ! is_array( $sites ) ) {
			return $network;
		}

		foreach ( $sites as $row ) {
			if ( empty( $row['mail'] ) || ! is_array( $row['mail'] ) || empty( $row['mail']['module_active'] ) ) {
				continue;
			}
			$m = $row['mail'];
			if ( empty( $m['table_ready'] ) ) {
				continue;
			}

			++$network['sites_module_active'];
			++$network['sites_tracked'];
			$network['total']        += (int) ( $m['total'] ?? 0 );
			$network['success']      += (int) ( $m['success'] ?? 0 );
			$network['failed']       += (int) ( $m['failed'] ?? 0 );
			$network['today']        += (int) ( $m['today'] ?? 0 );
			$network['resent_total'] += (int) ( $m['resent_total'] ?? 0 );

			if ( self::has_mail_failures( $m ) ) {
				++$network['sites_with_failures'];
			}

			$labels = isset( $m['chart_labels'] ) && is_array( $m['chart_labels'] ) ? $m['chart_labels'] : array();
			$sent   = isset( $m['chart_sent'] ) && is_array( $m['chart_sent'] ) ? $m['chart_sent'] : array();
			$failed = isset( $m['chart_failed'] ) && is_array( $m['chart_failed'] ) ? $m['chart_failed'] : array();

			if ( empty( $network['chart_labels'] ) && ! empty( $labels ) ) {
				$network['chart_labels'] = $labels;
				$network['chart_sent']   = array_fill( 0, count( $labels ), 0 );
				$network['chart_failed'] = array_fill( 0, count( $labels ), 0 );
			}

			foreach ( $labels as $i => $label ) {
				if ( ! isset( $network['chart_labels'][ $i ] ) ) {
					$network['chart_labels'][ $i ] = $label;
					$network['chart_sent'][ $i ]   = 0;
					$network['chart_failed'][ $i ] = 0;
				}
				$network['chart_sent'][ $i ]   += (int) ( $sent[ $i ] ?? 0 );
				$network['chart_failed'][ $i ] += (int) ( $failed[ $i ] ?? 0 );
			}
		}

		return $network;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_client_summary() {
		$agg     = self::get_aggregate();
		$network = $agg['network'] ?? array();

		return array(
			'total_failed'          => (int) ( $network['failed'] ?? 0 ),
			'sites_with_failures'   => (int) ( $network['sites_with_failures'] ?? 0 ),
			'sites_module_active'   => (int) ( $network['sites_module_active'] ?? 0 ),
			'has_alert'             => (bool) get_option( self::ALERT_OPTION, false ),
		);
	}

	/**
	 * @param array<string, mixed> $aggregate Agrégat complet.
	 * @return void
	 */
	private static function maybe_set_alert( $aggregate ) {
		$settings = MainWP_GIWeb_Settings::get();
		if ( empty( $settings['mail_alert_enabled'] ) || '1' !== (string) $settings['mail_alert_enabled'] ) {
			delete_option( self::ALERT_OPTION );
			return;
		}

		$min_failed = max( 1, (int) ( $settings['mail_alert_min_failed'] ?? 1 ) );
		$network    = $aggregate['network'] ?? array();
		$sites      = $aggregate['sites'] ?? array();
		$trigger    = false;
		$lines      = array();

		foreach ( $sites as $site_id => $row ) {
			$mail = $row['mail'] ?? null;
			if ( ! is_array( $mail ) || empty( $mail['module_active'] ) || empty( $mail['table_ready'] ) ) {
				continue;
			}
			$failed = (int) ( $mail['failed'] ?? 0 );
			if ( $failed < $min_failed ) {
				continue;
			}
			$trigger = true;
			$lines[] = sprintf(
				'%s : %d %s',
				$row['label'] ?? ( '#' . $site_id ),
				$failed,
				_n( 'échec', 'échecs', $failed, 'mainwp-giweb' )
			);
		}

		if ( ! $trigger ) {
			delete_option( self::ALERT_OPTION );
			return;
		}

		$payload = array(
			'time'    => time(),
			'message' => implode( '; ', $lines ),
			'network' => $network,
		);
		update_option( self::ALERT_OPTION, $payload, false );

		if ( ! empty( $settings['mail_alert_email'] ) && is_email( $settings['mail_alert_email'] ) ) {
			$key = 'mail_alert_sent_' . md5( wp_json_encode( $lines ) );
			if ( ! get_transient( $key ) ) {
				wp_mail(
					$settings['mail_alert_email'],
					sprintf(
						/* translators: %d: failed mail count on network */
						__( '[MainWP GI] %d échec(s) mail sur le réseau', 'mainwp-giweb' ),
						(int) ( $network['failed'] ?? 0 )
					),
					$payload['message'] . "\n\n" . MainWP_GIWeb_UI::admin_page_url()
				);
				set_transient( $key, 1, HOUR_IN_SECONDS );
			}
		}
	}

	/**
	 * @return void
	 */
	public static function clear_alert() {
		delete_option( self::ALERT_OPTION );
	}

	/**
	 * @return void
	 */
	public static function render_admin_notice() {
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			return;
		}

		$alert = get_option( self::ALERT_OPTION, false );
		if ( ! is_array( $alert ) || empty( $alert['message'] ) ) {
			return;
		}

		if ( ! self::is_mainwp_admin_screen() ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array(
					'mainwp_giweb_dismiss_mail' => '1',
				),
				MainWP_GIWeb_UI::is_extension_admin_page()
					? MainWP_GIWeb_UI::admin_page_url()
					: ( isset( $_GET['page'] ) ? add_query_arg( 'page', sanitize_text_field( wp_unslash( $_GET['page'] ) ), admin_url( 'admin.php' ) ) : admin_url( 'admin.php' ) )
			),
			'mainwp_giweb_dismiss_mail'
		);

		echo '<div class="notice notice-warning is-dismissible"><p><strong>';
		esc_html_e( 'GI-Toolkit — échecs mail détectés', 'mainwp-giweb' );
		echo '</strong> ';
		echo esc_html( (string) $alert['message'] );
		echo ' <a href="' . esc_url( MainWP_GIWeb_UI::admin_page_url() ) . '">';
		esc_html_e( 'Voir le manager', 'mainwp-giweb' );
		echo '</a> · <a href="' . esc_url( $dismiss_url ) . '">';
		esc_html_e( 'Masquer', 'mainwp-giweb' );
		echo '</a></p></div>';
	}

	/**
	 * @return bool
	 */
	public static function is_mainwp_admin_screen() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return 0 === strpos( $page, 'mainwp' ) || MainWP_GIWeb_UI::is_extension_admin_page( $page );
	}

	/**
	 * Stats mail d’un site (agrégat puis cache statut).
	 *
	 * @param int $site_id ID MainWP.
	 * @return array<string, mixed>|null
	 */
	public static function get_site_mail( $site_id ) {
		$site_id = absint( $site_id );
		if ( ! $site_id ) {
			return null;
		}

		$agg  = self::get_aggregate();
		$sites = $agg['sites'] ?? array();
		if ( is_array( $sites ) && isset( $sites[ $site_id ]['mail'] ) ) {
			$mail = $sites[ $site_id ]['mail'];
			return is_array( $mail ) ? $mail : null;
		}

		$cache = MainWP_GIWeb_Status_Cache::get_all();
		if ( ! isset( $cache[ $site_id ] ) || ! is_array( $cache[ $site_id ] ) ) {
			return null;
		}

		return self::extract_mail( $cache[ $site_id ]['data'] ?? array() );
	}

	/**
	 * @param array<string, mixed>|null $mail Stats mail site.
	 * @return string HTML badge / texte.
	 */
	public static function format_site_mail_cell( $mail ) {
		if ( ! is_array( $mail ) || empty( $mail['module_active'] ) ) {
			return '<span class="mainwp-giweb-mail-site mainwp-giweb-mail-site--inactive"><span class="mainwp-giweb-mail-site__hint">' . esc_html__( 'Mail Catcher inactif', 'mainwp-giweb' ) . '</span></span>';
		}
		if ( empty( $mail['table_ready'] ) ) {
			return '<span class="mainwp-giweb-mail-site mainwp-giweb-mail-site--pending"><span class="mainwp-giweb-mail-site__hint">' . esc_html__( 'Module actif — en attente de données', 'mainwp-giweb' ) . '</span></span>';
		}

		$failed  = self::get_failed_count( $mail );
		$success = (int) ( $mail['success'] ?? 0 );
		$total   = (int) ( $mail['total'] ?? 0 );
		$today   = (int) ( $mail['today'] ?? 0 );
		$resent  = (int) ( $mail['resent_total'] ?? 0 );
		$has_err = self::has_mail_failures( $mail );

		$html  = '<div class="mainwp-giweb-mail-site' . ( $has_err ? ' mainwp-giweb-mail-site--alert' : ' mainwp-giweb-mail-site--ok' ) . '">';
		$html .= '<div class="mainwp-giweb-mail-site__head">';
		if ( $has_err ) {
			$html .= '<span class="mainwp-giweb-badge err">' . esc_html(
				sprintf(
					/* translators: %d: failed mail count */
					_n( '%d échec', '%d échecs', $failed, 'mainwp-giweb' ),
					$failed
				)
			) . '</span>';
		} else {
			$html .= '<span class="mainwp-giweb-badge ok">' . esc_html__( 'Aucun échec', 'mainwp-giweb' ) . '</span>';
		}
		$html .= '<span class="mainwp-giweb-mail-site__total" title="' . esc_attr__( 'Total capturé', 'mainwp-giweb' ) . '">' . esc_html( (string) $total ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="mainwp-giweb-mail-site__metrics">';
		$html .= '<span class="mainwp-giweb-mail-metric mainwp-giweb-mail-metric--ok" title="' . esc_attr__( 'Envoyés avec succès', 'mainwp-giweb' ) . '">';
		$html .= esc_html( (string) $success ) . ' <small>' . esc_html__( 'OK', 'mainwp-giweb' ) . '</small></span>';
		$html .= '<span class="mainwp-giweb-mail-metric mainwp-giweb-mail-metric--fail" title="' . esc_attr__( 'En échec', 'mainwp-giweb' ) . '">';
		$html .= esc_html( (string) $failed ) . ' <small>' . esc_html__( 'KO', 'mainwp-giweb' ) . '</small></span>';
		$html .= '<span class="mainwp-giweb-mail-metric" title="' . esc_attr__( 'Aujourd’hui', 'mainwp-giweb' ) . '">';
		$html .= esc_html( (string) $today ) . ' <small>' . esc_html__( 'auj.', 'mainwp-giweb' ) . '</small></span>';
		if ( $resent > 0 ) {
			$html .= '<span class="mainwp-giweb-mail-metric" title="' . esc_attr__( 'Renvois', 'mainwp-giweb' ) . '">';
			$html .= esc_html( (string) $resent ) . ' <small>' . esc_html__( 'renv.', 'mainwp-giweb' ) . '</small></span>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Gère la dismissal de l’alerte.
	 *
	 * @return void
	 */
	public static function maybe_handle_dismiss() {
		if ( empty( $_GET['mainwp_giweb_dismiss_mail'] ) ) {
			return;
		}
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			return;
		}
		check_admin_referer( 'mainwp_giweb_dismiss_mail' );
		self::clear_alert();
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = MainWP_GIWeb_UI::admin_page_url();
		}
		$redirect = remove_query_arg( array( 'mainwp_giweb_dismiss_mail', '_wpnonce' ), $redirect );

		if ( ! headers_sent() ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		nocache_headers();
		echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
		echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $redirect ) . '">';
		echo '<script>window.location.replace(' . wp_json_encode( $redirect ) . ');</script>';
		echo '</head><body><p><a href="' . esc_url( $redirect ) . '">' . esc_html__( 'Continuer', 'mainwp-giweb' ) . '</a></p></body></html>';
		exit;
	}
}

MainWP_GIWeb_Mail_Stats::init();
add_action( 'admin_init', array( 'MainWP_GIWeb_Mail_Stats', 'maybe_handle_dismiss' ) );
