<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client API Matomo (REST JSON).
 */
class Gi_Toolkit_Matomo_API {

	/** @var int Timeout HTTP (secondes) pour wp_remote_get. */
	private static $request_timeout = 30;

	/**
	 * @var array<string, mixed>
	 */
	private $settings;

	/**
	 * @var string|null
	 */
	private $last_error = null;

	/**
	 * @param array<string, mixed> $settings Réglages module.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param int $seconds Timeout en secondes (3–120).
	 * @return void
	 */
	public static function set_request_timeout( $seconds ) {
		self::$request_timeout = max( 3, min( 120, absint( $seconds ) ) );
	}

	/**
	 * @return string|null
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * @param string $url URL brute.
	 * @return string
	 */
	public static function normalize_matomo_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		return untrailingslashit( $url );
	}

	/**
	 * @return bool
	 */
	public function is_configured() {
		return '' !== self::normalize_matomo_url( $this->settings['matomo_url'] ?? '' )
			&& '' !== trim( (string) ( $this->settings['api_token'] ?? '' ) );
	}

	/**
	 * @return string
	 */
	private function get_api_endpoint() {
		$base = self::normalize_matomo_url( $this->settings['matomo_url'] ?? '' );
		return $base . '/index.php';
	}

	/**
	 * Détecte un refus type rate-limit (Matomo / proxy).
	 *
	 * @param string $message Message d’erreur.
	 * @return bool
	 */
	public static function is_rate_limit_message( $message ) {
		$m = strtolower( (string) $message );
		if ( '' === $m ) {
			return false;
		}
		return false !== strpos( $m, 'too frequently' )
			|| false !== strpos( $m, 'rate limit' )
			|| false !== strpos( $m, 'try again later' );
	}

	/**
	 * @param string               $method   Méthode API Matomo.
	 * @param array<string, mixed> $params   Paramètres.
	 * @return mixed|null
	 */
	public function request( $method, array $params = array() ) {
		$this->last_error = null;

		if ( ! $this->is_configured() ) {
			$this->last_error = __( 'URL Matomo ou token API manquant.', 'gi-toolkit' );
			return null;
		}

		$query = array_merge(
			array(
				'module'     => 'API',
				'method'     => $method,
				'format'     => 'JSON',
				'token_auth' => $this->settings['api_token'],
			),
			$params
		);

		$url  = add_query_arg( $query, $this->get_api_endpoint() );
		$args = array(
			'timeout'   => self::$request_timeout,
			'sslverify' => '1' !== (string) ( $this->settings['disable_ssl_verify'] ?? '0' ),
			'headers'   => array(
				'User-Agent' => 'GI-Toolkit-Matomo/' . ( defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1' ),
			),
		);

		$max_attempts = 4;
		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$this->last_error = null;
			$response          = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				$this->last_error = $response->get_error_message();
				return null;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 429 === $code || ( $code >= 500 && $code < 600 && $attempt < $max_attempts ) ) {
				$this->last_error = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Matomo a répondu avec le code HTTP %d.', 'gi-toolkit' ),
					$code
				);
				if ( $attempt < $max_attempts ) {
					sleep( min( 8, $attempt * 2 ) );
					continue;
				}
				return null;
			}

			if ( $code < 200 || $code >= 300 ) {
				$this->last_error = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Matomo a répondu avec le code HTTP %d.', 'gi-toolkit' ),
					$code
				);
				return null;
			}

			if ( '' === $body ) {
				$this->last_error = __( 'Réponse Matomo vide.', 'gi-toolkit' );
				return null;
			}

			$data = json_decode( $body, true );
			if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
				$this->last_error = __( 'Réponse Matomo JSON invalide.', 'gi-toolkit' );
				return null;
			}

			if ( is_array( $data ) && isset( $data['result'] ) && 'error' === $data['result'] ) {
				$this->last_error = isset( $data['message'] ) ? (string) $data['message'] : __( 'Erreur API Matomo.', 'gi-toolkit' );
				if ( self::is_rate_limit_message( $this->last_error ) && $attempt < $max_attempts ) {
					sleep( min( 8, $attempt * 2 ) );
					continue;
				}
				return null;
			}

			return $data;
		}

		return null;
	}

	/**
	 * URL du tableau de bord Matomo pour un site donné.
	 *
	 * @param array<string, mixed> $settings   Réglages (matomo_url, site_id).
	 * @param string               $period_key Clé période UI (today, last7, …).
	 * @return string
	 */
	public static function get_site_dashboard_url( array $settings, $period_key = null ) {
		if ( null === $period_key || '' === (string) $period_key ) {
			$period_key = Gi_Toolkit_Matomo_Dashboard_Data::DEFAULT_PERIOD_KEY;
		}
		$base    = self::normalize_matomo_url( $settings['matomo_url'] ?? '' );
		$site_id = absint( $settings['site_id'] ?? 0 );

		if ( '' === $base || $site_id < 1 ) {
			return $base;
		}

		if ( 'live' === $period_key ) {
			return add_query_arg(
				array(
					'module' => 'Live',
					'action' => 'index',
					'idSite' => $site_id,
				),
				$base . '/index.php'
			);
		}

		$period = class_exists( 'Gi_Toolkit_Matomo_Dashboard_Data' )
			? Gi_Toolkit_Matomo_Dashboard_Data::resolve_period( $period_key )
			: array(
				'period' => 'range',
				'date'   => Gi_Toolkit_Matomo_Dashboard_Data::DEFAULT_PERIOD_KEY,
			);

		return add_query_arg(
			array(
				'module' => 'CoreHome',
				'action' => 'index',
				'idSite' => $site_id,
				'period' => $period['period'],
				'date'   => $period['date'],
			),
			$base . '/index.php'
		);
	}

	/**
	 * @return array{success:bool, version?:string, message?:string}
	 */
	public function test_connection() {
		$version = $this->request( 'API.getMatomoVersion' );
		if ( null === $version ) {
			return array(
				'success' => false,
				'message' => $this->last_error ?: __( 'Connexion impossible.', 'gi-toolkit' ),
			);
		}
		if ( is_array( $version ) && isset( $version['value'] ) ) {
			$version = $version['value'];
		}
		return array(
			'success' => true,
			'version' => is_string( $version ) ? $version : wp_json_encode( $version ),
		);
	}
}
