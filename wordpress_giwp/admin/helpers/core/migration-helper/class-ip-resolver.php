<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Résolution IP publique du site et métadonnées (PTR, ASN, hébergeur).
 */
class Gi_Toolkit_Migration_Helper_IP_Resolver {

	const TRANSIENT_PUBLIC_IP = 'gi_toolkit_migration_public_ip';
	const TRANSIENT_IP_INFO     = 'gi_toolkit_migration_ip_info_';

	const PUBLIC_IP_TTL = 600;
	const IP_INFO_TTL   = 3600;

	/**
	 * @return array<string, mixed>
	 */
	public static function get_toolbar_payload() {
		$public_ip = self::get_public_ip();
		$server_ip = self::get_server_ip();

		$payload = array(
			'site_url'   => home_url( '/' ),
			'host'       => wp_parse_url( home_url(), PHP_URL_HOST ) ?: '',
			'public_ip'  => $public_ip,
			'server_ip'  => $server_ip,
			'ip_info'    => array(),
			'has_public' => '' !== $public_ip,
		);

		$lookup_ip = $public_ip ?: ( self::is_public_ip( $server_ip ) ? $server_ip : '' );
		if ( '' !== $lookup_ip ) {
			$payload['ip_info'] = self::get_ip_info( $lookup_ip );
		}

		$info = is_array( $payload['ip_info'] ) ? $payload['ip_info'] : array();
		$payload['header_label'] = self::resolve_header_label( $info, (string) $payload['host'], $public_ip, $server_ip );
		$payload['header_logo']  = (string) ( $info['logo_url'] ?? '' );

		return $payload;
	}

	/**
	 * Libellé compact barre admin (1er segment du PTR, ex. h2web504).
	 *
	 * @param array<string, string> $info      Métadonnées IP.
	 * @param string                $site_host Hôte du site.
	 * @param string                $public_ip IP publique.
	 * @param string                $server_ip IP serveur.
	 * @return string
	 */
	public static function resolve_header_label( $info, $site_host, $public_ip, $server_ip ) {
		$ptr_label = (string) ( $info['ptr_subdomain'] ?? '' );
		if ( '' !== $ptr_label ) {
			return $ptr_label;
		}

		$ptr_label = self::extract_ptr_subdomain( (string) ( $info['reverse_dns'] ?? '' ) );
		if ( '' !== $ptr_label ) {
			return $ptr_label;
		}

		if ( '' !== $site_host ) {
			return $site_host;
		}

		return $public_ip ?: $server_ip;
	}

	/**
	 * Premier label du reverse DNS (h2web504.infomaniak.ch → h2web504).
	 *
	 * @param string $reverse_dns PTR.
	 * @return string
	 */
	public static function extract_ptr_subdomain( $reverse_dns ) {
		$reverse_dns = strtolower( rtrim( trim( (string) $reverse_dns ), '.' ) );
		if ( '' === $reverse_dns ) {
			return '';
		}

		$parts = explode( '.', $reverse_dns );
		if ( empty( $parts[0] ) ) {
			return '';
		}

		return sanitize_text_field( $parts[0] );
	}

	/**
	 * Domaine hébergeur déduit du PTR (h2web504.infomaniak.ch → infomaniak.ch).
	 *
	 * @param string $reverse_dns PTR.
	 * @return string
	 */
	public static function extract_isp_domain_from_ptr( $reverse_dns ) {
		$reverse_dns = strtolower( rtrim( trim( (string) $reverse_dns ), '.' ) );
		$parts       = array_values( array_filter( explode( '.', $reverse_dns ) ) );

		if ( count( $parts ) < 2 ) {
			return '';
		}

		if ( count( $parts ) >= 3 ) {
			return self::sanitize_isp_domain( implode( '.', array_slice( $parts, 1 ) ) );
		}

		return self::sanitize_isp_domain( implode( '.', $parts ) );
	}

	/**
	 * @param string $domain Domaine.
	 * @return string
	 */
	public static function sanitize_isp_domain( $domain ) {
		$domain = strtolower( trim( (string) $domain ) );
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = preg_replace( '#/.*$#', '', $domain );

		if ( '' === $domain || ! preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $domain ) ) {
			return '';
		}

		return $domain;
	}

	/**
	 * Favicon / logo hébergeur via domaine (Google favicon service).
	 *
	 * @param string $domain Domaine ISP.
	 * @return string
	 */
	public static function get_isp_logo_url( $domain ) {
		$domain = self::sanitize_isp_domain( $domain );
		if ( '' === $domain ) {
			return '';
		}

		return 'https://www.google.com/s2/favicons?domain=' . rawurlencode( $domain ) . '&sz=32';
	}

	/**
	 * @return string
	 */
	public static function get_public_ip() {
		$cached = get_transient( self::TRANSIENT_PUBLIC_IP );
		if ( is_string( $cached ) && self::is_public_ip( $cached ) ) {
			return $cached;
		}

		$endpoints = array(
			'https://api.ipify.org',
			'https://ipv4.icanhazip.com',
			'https://ifconfig.me/ip',
		);

		foreach ( $endpoints as $url ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'   => 6,
					'sslverify' => true,
					'headers'   => array(
						'Accept' => 'text/plain',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$ip = trim( (string) wp_remote_retrieve_body( $response ) );
			if ( self::is_public_ip( $ip ) ) {
				set_transient( self::TRANSIENT_PUBLIC_IP, $ip, self::PUBLIC_IP_TTL );
				return $ip;
			}
		}

		return '';
	}

	/**
	 * @return string
	 */
	public static function get_server_ip() {
		$ip = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '';
		return self::is_valid_ip( $ip ) ? $ip : '';
	}

	/**
	 * @param string $ip Adresse IPv4/IPv6.
	 * @return array<string, string>
	 */
	public static function get_ip_info( $ip ) {
		$ip = self::sanitize_ip( $ip );
		if ( '' === $ip ) {
			return array();
		}

		$cache_key = self::TRANSIENT_IP_INFO . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return self::enrich_display_fields( $cached );
		}

		$info = array(
			'reverse_dns'   => self::get_reverse_dns( $ip ),
			'ptr_subdomain' => '',
			'isp_domain'    => '',
			'logo_url'      => '',
			'asn'           => '',
			'org'           => '',
			'isp'           => '',
			'country'       => '',
			'lookup_error'  => '',
		);

		$remote = self::fetch_ipwhois( $ip );
		if ( ! empty( $remote ) ) {
			if ( '' === $info['reverse_dns'] && ! empty( $remote['reverse_dns'] ) ) {
				$info['reverse_dns'] = $remote['reverse_dns'];
			}
			$info['isp_domain'] = (string) ( $remote['isp_domain'] ?? '' );
			$info['asn']        = $remote['asn'] ?? '';
			$info['org']        = $remote['org'] ?? '';
			$info['isp']        = $remote['isp'] ?? '';
			$info['country']    = $remote['country'] ?? '';
		} elseif ( '' === $info['reverse_dns'] ) {
			$info['lookup_error'] = __( 'Informations réseau indisponibles (API ou DNS).', 'gi-toolkit' );
		}

		$info = self::enrich_display_fields( $info );

		set_transient( $cache_key, $info, self::IP_INFO_TTL );

		return $info;
	}

	/**
	 * @param string $ip IP.
	 * @return string
	 */
	private static function get_reverse_dns( $ip ) {
		if ( '' === $ip || ! function_exists( 'gethostbyaddr' ) ) {
			return '';
		}

		$ptr = gethostbyaddr( $ip );
		if ( ! is_string( $ptr ) || $ptr === $ip || '' === trim( $ptr ) ) {
			return '';
		}

		return $ptr;
	}

	/**
	 * @param string $ip IP.
	 * @return array<string, string>
	 */
	private static function fetch_ipwhois( $ip ) {
		$url      = 'https://ipwho.is/' . rawurlencode( $ip );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 8,
				'sslverify' => true,
				'headers'   => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::fetch_ipapi_co_fallback( $ip );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::fetch_ipapi_co_fallback( $ip );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			return self::fetch_ipapi_co_fallback( $ip );
		}

		$connection = is_array( $body['connection'] ?? null ) ? $body['connection'] : array();
		$asn_raw    = $connection['asn'] ?? '';
		$org        = sanitize_text_field( (string) ( $connection['org'] ?? '' ) );
		$isp        = sanitize_text_field( (string) ( $connection['isp'] ?? $org ) );

		return array(
			'reverse_dns' => sanitize_text_field( (string) ( $body['reverse'] ?? '' ) ),
			'isp_domain'  => self::sanitize_isp_domain( (string) ( $connection['domain'] ?? '' ) ),
			'asn'         => self::format_asn_label( $asn_raw, $org ),
			'org'         => $org,
			'isp'         => $isp,
			'country'     => sanitize_text_field( (string) ( $body['country'] ?? '' ) ),
		);
	}

	/**
	 * @param string $ip IP.
	 * @return array<string, string>
	 */
	private static function fetch_ipapi_co_fallback( $ip ) {
		$url      = 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 8,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! empty( $body['error'] ) ) {
			return array();
		}

		$org = sanitize_text_field( (string) ( $body['org'] ?? '' ) );

		$ptr = sanitize_text_field( (string) ( $body['hostname'] ?? '' ) );

		return array(
			'reverse_dns' => $ptr,
			'isp_domain'  => self::extract_isp_domain_from_ptr( $ptr ),
			'asn'         => self::format_asn_label( $body['asn'] ?? '', $org ),
			'org'         => $org,
			'isp'         => sanitize_text_field( (string) ( $body['org'] ?? '' ) ),
			'country'     => sanitize_text_field( (string) ( $body['country_name'] ?? '' ) ),
		);
	}

	/**
	 * Complète sous-domaine PTR, domaine ISP et URL logo.
	 *
	 * @param array<string, string> $info Métadonnées.
	 * @return array<string, string>
	 */
	private static function enrich_display_fields( $info ) {
		if ( ! is_array( $info ) ) {
			return array();
		}

		$ptr = (string) ( $info['reverse_dns'] ?? '' );
		if ( '' === (string) ( $info['ptr_subdomain'] ?? '' ) ) {
			$info['ptr_subdomain'] = self::extract_ptr_subdomain( $ptr );
		}

		if ( '' === (string) ( $info['isp_domain'] ?? '' ) ) {
			$info['isp_domain'] = self::extract_isp_domain_from_ptr( $ptr );
		}

		if ( '' === (string) ( $info['logo_url'] ?? '' ) ) {
			$info['logo_url'] = self::get_isp_logo_url( (string) $info['isp_domain'] );
		}

		return $info;
	}

	/**
	 * @param mixed  $asn Numéro ou libellé ASN.
	 * @param string $org Organisation.
	 * @return string
	 */
	private static function format_asn_label( $asn, $org ) {
		$asn = trim( (string) $asn );
		$org = trim( $org );

		if ( '' === $asn && '' === $org ) {
			return '';
		}

		if ( '' !== $asn && 0 !== stripos( $asn, 'AS' ) ) {
			$asn = 'AS' . $asn;
		}

		if ( '' !== $asn && '' !== $org ) {
			return $asn . ' — ' . $org;
		}

		return '' !== $asn ? $asn : $org;
	}

	/**
	 * @param bool $public_only Effacer uniquement l’IP publique.
	 * @return void
	 */
	public static function bust_cache( $public_only = false ) {
		delete_transient( self::TRANSIENT_PUBLIC_IP );

		if ( $public_only ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::TRANSIENT_IP_INFO ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_IP_INFO ) . '%'
			)
		);
	}

	/**
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_public_ip( $ip ) {
		$ip = self::sanitize_ip( $ip );
		if ( '' === $ip ) {
			return false;
		}

		return (bool) filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_valid_ip( $ip ) {
		return '' !== self::sanitize_ip( $ip );
	}

	/**
	 * @param string $ip IP.
	 * @return string
	 */
	public static function sanitize_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $ip;
	}
}
