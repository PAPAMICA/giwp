<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provisionnement automatique des hosts Zabbix pour les sites MainWP.
 */
class MainWP_GIWeb_Zabbix {

	const GROUP_WEBSITES           = '27';
	const GROUP_GENEVOIS           = '26';
	const TEMPLATE_MAINWP          = '10707';
	const TEMPLATE_PAGESPEED       = '10706';
	const TEMPLATE_WEBSITE_BROWSER = '10628';
	const MACRO_WEBSITE_DOMAIN     = '{$WEBSITE.DOMAIN}';
	const HOST_PREFIX              = 'WEB - ';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'mainwp_site_added', array( __CLASS__, 'on_site_added' ), 25, 2 );
		add_action( 'wp_ajax_mainwp_giweb_zabbix_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_mainwp_giweb_zabbix_provision_all', array( __CLASS__, 'ajax_provision_all' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		return '' !== $creds['url'] && '' !== $creds['api_token'];
	}

	/**
	 * @return bool
	 */
	public static function is_auto_create_enabled() {
		if ( ! self::is_configured() ) {
			return false;
		}
		$settings = MainWP_GIWeb_Settings::get();
		return '1' === (string) ( $settings['zabbix_auto_create'] ?? '0' );
	}

	/**
	 * @return array{url:string, api_token:string}
	 */
	public static function get_credentials() {
		$settings = MainWP_GIWeb_Settings::get();
		return array(
			'url'       => self::normalize_server_url( $settings['zabbix_url'] ?? '' ),
			'api_token' => trim( (string) ( $settings['zabbix_api_token'] ?? '' ) ),
		);
	}

	/**
	 * @param string $url URL Zabbix ou endpoint API.
	 * @return string
	 */
	private static function normalize_server_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return '';
		}
		$url = untrailingslashit( $url );
		$url = preg_replace( '#/api_jsonrpc\.php$#i', '', $url );
		return untrailingslashit( (string) $url );
	}

	/**
	 * @return string
	 */
	private static function api_endpoint() {
		$creds = self::get_credentials();
		return $creds['url'] . '/api_jsonrpc.php';
	}

	/**
	 * @param string               $method Méthode API.
	 * @param array<string, mixed> $params Paramètres.
	 * @return array{success:bool, data?:mixed, error?:string, code?:string}
	 */
	public static function api_request( $method, array $params = array() ) {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'error'   => __( 'Zabbix non configuré.', 'mainwp-giweb' ),
				'code'    => 'not_configured',
			);
		}

		$creds = self::get_credentials();
		$body  = wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'method'  => (string) $method,
				'params'  => $params,
				'id'      => 1,
			)
		);

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Requête Zabbix invalide.', 'mainwp-giweb' ),
				'code'    => 'encode_error',
			);
		}

		$response = wp_remote_post(
			self::api_endpoint(),
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'  => 'application/json-rpc',
					'Authorization' => 'Bearer ' . $creds['api_token'],
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
				'code'    => 'http_error',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Zabbix HTTP %d', 'mainwp-giweb' ),
					$code
				),
				'code'    => 'http_status',
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Réponse Zabbix illisible.', 'mainwp-giweb' ),
				'code'    => 'invalid_json',
			);
		}

		if ( ! empty( $decoded['error'] ) && is_array( $decoded['error'] ) ) {
			$message = (string) ( $decoded['error']['data'] ?? $decoded['error']['message'] ?? __( 'Erreur API Zabbix.', 'mainwp-giweb' ) );
			return array(
				'success' => false,
				'error'   => $message,
				'code'    => 'api_error',
			);
		}

		return array(
			'success' => true,
			'data'    => $decoded['result'] ?? null,
		);
	}

	/**
	 * @return array{success:bool, message:string, version?:string, error?:string}
	 */
	public static function test_connection() {
		$result = self::api_request( 'apiinfo.version', array() );
		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'message' => $result['error'] ?? __( 'Connexion impossible.', 'mainwp-giweb' ),
				'error'   => $result['error'] ?? '',
			);
		}

		$version = is_string( $result['data'] ) ? $result['data'] : '';
		return array(
			'success' => true,
			'message' => $version
				? sprintf(
					/* translators: %s: Zabbix version */
					__( 'Connexion OK — Zabbix %s', 'mainwp-giweb' ),
					$version
				)
				: __( 'Connexion Zabbix OK.', 'mainwp-giweb' ),
			'version' => $version,
		);
	}

	/**
	 * @param string $site_url URL WordPress du site.
	 * @return string
	 */
	public static function extract_domain( $site_url ) {
		$site_url = trim( (string) $site_url );
		if ( '' === $site_url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $site_url ) ) {
			$site_url = 'https://' . $site_url;
		}

		$parsed = wp_parse_url( $site_url );
		if ( empty( $parsed['host'] ) ) {
			return '';
		}

		$host = strtolower( (string) $parsed['host'] );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * @param string $domain Nom de domaine.
	 * @return string
	 */
	public static function host_name_for_domain( $domain ) {
		$domain = trim( (string) $domain );
		if ( '' === $domain ) {
			return '';
		}
		return self::HOST_PREFIX . $domain;
	}

	/**
	 * @param string $host_name Nom technique Zabbix.
	 * @param string $domain    Domaine sans www.
	 * @return array{hostid:string, host:string}|null
	 */
	public static function find_existing_host( $host_name, $domain ) {
		$by_name = self::api_request(
			'host.get',
			array(
				'output' => array( 'hostid', 'host', 'name' ),
				'filter' => array(
					'host' => array( $host_name ),
				),
				'limit'  => 1,
			)
		);

		if ( ! empty( $by_name['success'] ) && is_array( $by_name['data'] ) && ! empty( $by_name['data'][0] ) ) {
			return $by_name['data'][0];
		}

		if ( '' === $domain ) {
			return null;
		}

		$by_macro = self::api_request(
			'usermacro.get',
			array(
				'output'  => array( 'hostid', 'macro', 'value' ),
				'filter'  => array(
					'macro' => self::MACRO_WEBSITE_DOMAIN,
					'value' => $domain,
				),
				'limit'   => 1,
			)
		);

		if ( empty( $by_macro['success'] ) || ! is_array( $by_macro['data'] ) || empty( $by_macro['data'][0]['hostid'] ) ) {
			return null;
		}

		$hostid = (string) $by_macro['data'][0]['hostid'];
		$host   = self::api_request(
			'host.get',
			array(
				'output' => array( 'hostid', 'host', 'name' ),
				'hostids' => array( $hostid ),
				'limit'   => 1,
			)
		);

		if ( ! empty( $host['success'] ) && is_array( $host['data'] ) && ! empty( $host['data'][0] ) ) {
			return $host['data'][0];
		}

		return array(
			'hostid' => $hostid,
			'host'   => $host_name,
		);
	}

	/**
	 * Crée le host Zabbix s’il n’existe pas déjà.
	 *
	 * @param string $site_url URL du site MainWP.
	 * @return array{success:bool, code:string, message:string, hostid?:string}
	 */
	public static function ensure_host_for_site( $site_url ) {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'code'    => 'not_configured',
				'message' => __( 'Zabbix non configuré.', 'mainwp-giweb' ),
			);
		}

		$domain = self::extract_domain( $site_url );
		if ( '' === $domain ) {
			return array(
				'success' => false,
				'code'    => 'invalid_url',
				'message' => __( 'URL de site invalide pour Zabbix.', 'mainwp-giweb' ),
			);
		}

		$host_name = self::host_name_for_domain( $domain );
		$existing  = self::find_existing_host( $host_name, $domain );
		if ( is_array( $existing ) && ! empty( $existing['hostid'] ) ) {
			return array(
				'success' => true,
				'code'    => 'skipped',
				'hostid'  => (string) $existing['hostid'],
				'message' => sprintf(
					/* translators: 1: host name, 2: host id */
					__( 'Host Zabbix déjà présent : %1$s (#%2$s).', 'mainwp-giweb' ),
					(string) ( $existing['host'] ?? $host_name ),
					(string) $existing['hostid']
				),
			);
		}

		$create = self::api_request(
			'host.create',
			array(
				'host'      => $host_name,
				'name'      => $host_name,
				'groups'    => array(
					array( 'groupid' => self::GROUP_WEBSITES ),
					array( 'groupid' => self::GROUP_GENEVOIS ),
				),
				'templates' => array(
					array( 'templateid' => self::TEMPLATE_MAINWP ),
					array( 'templateid' => self::TEMPLATE_PAGESPEED ),
					array( 'templateid' => self::TEMPLATE_WEBSITE_BROWSER ),
				),
				'macros'    => array(
					array(
						'macro' => self::MACRO_WEBSITE_DOMAIN,
						'value' => $domain,
					),
				),
				'interfaces' => array(
					array(
						'type'  => 1,
						'main'  => 1,
						'useip' => 0,
						'dns'   => $domain,
						'port'  => '443',
					),
				),
			)
		);

		if ( empty( $create['success'] ) ) {
			return array(
				'success' => false,
				'code'    => 'create_failed',
				'message' => $create['error'] ?? __( 'Création du host Zabbix impossible.', 'mainwp-giweb' ),
			);
		}

		$hostids = is_array( $create['data'] ) && ! empty( $create['data']['hostids'] ) ? $create['data']['hostids'] : array();
		$hostid  = ! empty( $hostids[0] ) ? (string) $hostids[0] : '';

		return array(
			'success' => true,
			'code'    => 'created',
			'hostid'  => $hostid,
			'message' => sprintf(
				/* translators: 1: host name, 2: domain */
				__( 'Host Zabbix créé : %1$s ({$WEBSITE.DOMAIN} = %2$s).', 'mainwp-giweb' ),
				$host_name,
				$domain
			),
		);
	}

	/**
	 * @param object|null $activator Activator MainWP.
	 * @return array{success:bool, created:int, skipped:int, failed:int, messages:array<int, string>}
	 */
	public static function provision_all_sites( $activator = null ) {
		$summary = array(
			'success'  => true,
			'created'  => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'messages' => array(),
		);

		if ( ! self::is_configured() ) {
			$summary['success']  = false;
			$summary['messages'] = array( __( 'Zabbix non configuré.', 'mainwp-giweb' ) );
			return $summary;
		}

		foreach ( MainWP_GIWeb_Sites::fetch_all( $activator ) as $site ) {
			$row    = MainWP_GIWeb_Sites::normalize_one( $site );
			$url    = (string) ( $row['url'] ?? '' );
			$label  = (string) ( $row['name'] ?: $url ?: ( '#' . (int) ( $row['id'] ?? 0 ) ) );
			$result = self::ensure_host_for_site( $url );

			if ( empty( $result['success'] ) ) {
				++$summary['failed'];
				$summary['success'] = false;
				$summary['messages'][] = $label . ' — ' . (string) ( $result['message'] ?? __( 'Erreur', 'mainwp-giweb' ) );
				continue;
			}

			if ( 'created' === ( $result['code'] ?? '' ) ) {
				++$summary['created'];
			} else {
				++$summary['skipped'];
			}

			$summary['messages'][] = $label . ' — ' . (string) ( $result['message'] ?? '' );
		}

		return $summary;
	}

	/**
	 * @param object               $website     Site MainWP.
	 * @param array<string, mixed> $information Infos connexion.
	 * @return void
	 */
	public static function on_site_added( $website, $information ) {
		unset( $information );

		if ( ! self::is_auto_create_enabled() ) {
			return;
		}

		if ( ! is_object( $website ) || empty( $website->id ) ) {
			return;
		}

		$url    = ! empty( $website->url ) ? (string) $website->url : '';
		$result = self::ensure_host_for_site( $url );

		if ( empty( $result['message'] ) ) {
			return;
		}

		$logs = get_option( MainWP_GIWeb_Onboarding::LOG_OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		if ( empty( $logs[0]['site_id'] ) || (int) $logs[0]['site_id'] !== (int) $website->id ) {
			array_unshift(
				$logs,
				array(
					'site_id' => (int) $website->id,
					'time'    => gmdate( 'c' ),
					'success' => ! empty( $result['success'] ),
					'logs'    => array( '[Zabbix] ' . $result['message'] ),
				)
			);
			$logs = array_slice( $logs, 0, 50 );
			update_option( MainWP_GIWeb_Onboarding::LOG_OPTION, $logs, false );
			return;
		}

		if ( ! empty( $logs[0]['site_id'] ) && (int) $logs[0]['site_id'] === (int) $website->id ) {
			$logs[0]['logs'][] = '[Zabbix] ' . $result['message'];
			if ( empty( $result['success'] ) ) {
				$logs[0]['success'] = false;
			}
			update_option( MainWP_GIWeb_Onboarding::LOG_OPTION, $logs, false );
		}
	}

	/**
	 * @return void
	 */
	public static function ajax_test() {
		self::verify_ajax();

		$test = self::test_connection();
		if ( empty( $test['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $test['message'] ?? __( 'Connexion impossible.', 'mainwp-giweb' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => $test['message'],
				'version' => $test['version'] ?? '',
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_provision_all() {
		self::verify_ajax();

		global $mainwp_giweb_activator;
		$summary = self::provision_all_sites( $mainwp_giweb_activator ?? null );

		wp_send_json_success(
			array(
				'summary'  => $summary,
				'message'  => sprintf(
					/* translators: 1: created, 2: skipped, 3: failed */
					__( 'Zabbix : %1$d créé(s), %2$d déjà présent(s), %3$d échec(s).', 'mainwp-giweb' ),
					(int) $summary['created'],
					(int) $summary['skipped'],
					(int) $summary['failed']
				),
				'messages' => $summary['messages'],
			)
		);
	}

	/**
	 * @return void
	 */
	private static function verify_ajax() {
		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'mainwp-giweb' ) ), 403 );
		}
		check_ajax_referer( MainWP_GIWeb_Sync_Ajax::NONCE_ACTION, 'nonce' );
	}
}

MainWP_GIWeb_Zabbix::init();
