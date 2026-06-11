<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Détection du pays visiteur (en-têtes CDN, GeoIP2, défaut).
 */
class Gi_Toolkit_Geo_Shortcodes_Country_Resolver {

	const PREVIEW_COOKIE       = 'gi_toolkit_geo_preview';
	const PREVIEW_SIG_COOKIE   = 'gi_toolkit_geo_preview_sig';
	const PREVIEW_COOKIE_TTL   = 3600;

	/**
	 * @return string Code ISO2 ou chaîne vide.
	 */
	public static function get_country_code() {
		$preview = self::get_preview_country();
		if ( '' !== $preview ) {
			return $preview;
		}

		$headers = array(
			'HTTP_CF_IPCOUNTRY',
			'GEOIP_COUNTRY_CODE',
			'HTTP_GEOIP_COUNTRY_CODE',
			'HTTP_X_COUNTRY_CODE',
		);

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$code = self::sanitize_country( wp_unslash( $_SERVER[ $header ] ) );
			if ( '' !== $code ) {
				return $code;
			}
		}

		$db_country = self::lookup_geoip_database( self::get_client_ip() );
		if ( '' !== $db_country ) {
			return $db_country;
		}

		return Gi_Toolkit_Geo_Shortcodes_Store::get_default_country();
	}

	/**
	 * Pays simulé (admin / cookie signé).
	 *
	 * @return string
	 */
	public static function get_preview_country() {
		if ( ! empty( $_GET['gi_geo_preview'] ) ) {
			$code = self::sanitize_country( wp_unslash( $_GET['gi_geo_preview'] ) );
			if ( '' !== $code && self::can_use_preview() ) {
				return $code;
			}
		}

		if ( empty( $_COOKIE[ self::PREVIEW_COOKIE ] ) ) {
			return '';
		}

		$code = self::sanitize_country( wp_unslash( $_COOKIE[ self::PREVIEW_COOKIE ] ) );
		if ( '' === $code ) {
			return '';
		}

		if ( ! self::is_preview_cookie_valid( $code ) ) {
			return '';
		}

		return $code;
	}

	/**
	 * @return bool
	 */
	private static function can_use_preview() {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( empty( $_COOKIE[ self::PREVIEW_COOKIE ] ) || empty( $_COOKIE[ self::PREVIEW_SIG_COOKIE ] ) ) {
			return false;
		}

		$code = self::sanitize_country( wp_unslash( $_COOKIE[ self::PREVIEW_COOKIE ] ) );
		return '' !== $code && self::is_preview_cookie_valid( $code );
	}

	/**
	 * @param string $country Code ISO2.
	 * @return bool
	 */
	private static function is_preview_cookie_valid( $country ) {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		$sig = isset( $_COOKIE[ self::PREVIEW_SIG_COOKIE ] )
			? (string) wp_unslash( $_COOKIE[ self::PREVIEW_SIG_COOKIE ] )
			: '';

		if ( '' === $sig ) {
			return false;
		}

		return hash_equals( self::preview_signature( $country ), $sig );
	}

	/**
	 * @param string $country Code ISO2.
	 * @return string
	 */
	public static function preview_signature( $country ) {
		$country = self::sanitize_country( $country );
		return hash_hmac( 'sha256', $country, wp_salt( 'auth' ) );
	}

	/**
	 * @param mixed $raw Code brut.
	 * @return string
	 */
	public static function sanitize_country( $raw ) {
		$code = strtoupper( sanitize_text_field( (string) $raw ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return '';
		}
		if ( in_array( $code, array( 'XX', 'T1', 'A1', 'A2', 'O1' ), true ) ) {
			return '';
		}
		return $code;
	}

	/**
	 * @return string
	 */
	public static function get_client_ip() {
		$candidates = array();

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$candidates[] = trim( (string) ( $parts[0] ?? '' ) );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}

		foreach ( $candidates as $ip ) {
			$ip = trim( (string) $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $ip;
			}
		}

		foreach ( $candidates as $ip ) {
			$ip = trim( (string) $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * @param string $ip IP publique.
	 * @return string
	 */
	private static function lookup_geoip_database( $ip ) {
		$ip = trim( (string) $ip );
		if ( '' === $ip ) {
			return '';
		}

		$path = Gi_Toolkit_Geo_Shortcodes_Store::get_geoip_db_path();
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}

		if ( ! class_exists( '\WPMTK\GeoIp2\Database\Reader' ) ) {
			self::ensure_geoip2_autoload();
		}

		if ( ! class_exists( '\WPMTK\GeoIp2\Database\Reader' ) ) {
			return '';
		}

		try {
			$reader  = new \WPMTK\GeoIp2\Database\Reader( $path );
			$record  = $reader->country( $ip );
			$iso     = (string) ( $record->country->isoCode ?? '' );
			return self::sanitize_country( $iso );
		} catch ( Exception $e ) {
			unset( $e );
			return '';
		}
	}

	/**
	 * @return void
	 */
	private static function ensure_geoip2_autoload() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		$vendor = GI_TOOLKIT_PLUGIN_PATH . 'includes/vendor/autoload.php';
		if ( is_readable( $vendor ) ) {
			require_once $vendor;
		}

		spl_autoload_register(
			static function ( $class ) {
				if ( 0 !== strpos( $class, 'WPMTK\\GeoIp2\\' ) ) {
					return;
				}
				$relative = str_replace( '\\', '/', substr( $class, 13 ) );
				$file     = GI_TOOLKIT_PLUGIN_PATH . 'includes/packages/geoip2/geoip2/src/' . $relative . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}

	/**
	 * @param string $country Code ISO2.
	 * @return void
	 */
	public static function set_preview_cookie( $country ) {
		$country = self::sanitize_country( $country );
		if ( '' === $country ) {
			self::clear_preview_cookie();
			return;
		}

		$secure   = is_ssl();
		$expires  = time() + self::PREVIEW_COOKIE_TTL;
		$path     = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$domain   = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		setcookie( self::PREVIEW_COOKIE, $country, $expires, $path, $domain, $secure, false );
		setcookie( self::PREVIEW_SIG_COOKIE, self::preview_signature( $country ), $expires, $path, $domain, $secure, true );

		$_COOKIE[ self::PREVIEW_COOKIE ]     = $country;
		$_COOKIE[ self::PREVIEW_SIG_COOKIE ] = self::preview_signature( $country );
	}

	/**
	 * @return void
	 */
	public static function clear_preview_cookie() {
		$path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();

		setcookie( self::PREVIEW_COOKIE, '', time() - HOUR_IN_SECONDS, $path, $domain, $secure, false );
		setcookie( self::PREVIEW_SIG_COOKIE, '', time() - HOUR_IN_SECONDS, $path, $domain, $secure, true );

		unset( $_COOKIE[ self::PREVIEW_COOKIE ], $_COOKIE[ self::PREVIEW_SIG_COOKIE ] );
	}
}
