<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Uptime Kuma (Socket.IO).
 */
class Gi_Toolkit_Uptime_Kuma_API {

	const STATUS_UP          = 1;
	const STATUS_DOWN        = 0;
	const STATUS_PENDING     = 2;
	const STATUS_MAINTENANCE = 3;

	/** @var int */
	private static $request_timeout = 30;

	/** @var array<string, mixed> */
	private $settings;

	/** @var string|null */
	private $last_error = null;

	/** @var Gi_Toolkit_Uptime_Kuma_Socket_Client|null */
	private $client = null;

	/**
	 * @param array<string, mixed> $settings Réglages.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param int $seconds Timeout.
	 * @return void
	 */
	public static function set_request_timeout( $seconds ) {
		self::$request_timeout = max( 5, min( 120, absint( $seconds ) ) );
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
	public static function normalize_kuma_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$url = untrailingslashit( esc_url_raw( $url ) );
		// L’API Socket.IO est à la racine de l’instance, pas sous /dashboard.
		$url = preg_replace( '#/dashboard/?$#i', '', $url );
		return untrailingslashit( $url );
	}

	/**
	 * @return bool
	 */
	public function is_configured() {
		if ( '' === self::normalize_kuma_url( $this->settings['kuma_url'] ?? '' ) ) {
			return false;
		}
		if ( '' !== trim( (string) ( $this->settings['api_token'] ?? '' ) ) ) {
			return true;
		}
		$username = trim( (string) ( $this->settings['kuma_username'] ?? '' ) );
		$password = (string) ( $this->settings['kuma_password'] ?? '' );
		return '' !== $username && '' !== $password;
	}

	/**
	 * @return bool
	 */
	public function test_connection() {
		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
				$login = $this->login_client( $client );
				if ( empty( $login['ok'] ) ) {
					$this->last_error = $login['message'] ?? __( 'Connexion Uptime Kuma refusée.', 'gi-toolkit' );
					return false;
				}
				return true;
			}
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_monitors() {
		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return null;
				}
				$client->emit( 'getMonitorList' );
				$list = $client->get_last_event( 'monitorList' );
				return is_array( $list ) ? $list : array();
			}
		);
	}

	/**
	 * @param int $monitor_id ID monitor.
	 * @param int $hours        Période en heures.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function get_monitor_beats( $monitor_id, $hours = 24 ) {
		$monitor_id = absint( $monitor_id );
		$hours      = max( 1, min( 168, absint( $hours ) ) );

		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) use ( $monitor_id, $hours ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return null;
				}
				$response = $client->emit( 'getMonitorBeats', $monitor_id, $hours );
				if ( ! is_array( $response ) || empty( $response['ok'] ) ) {
					$this->last_error = is_array( $response ) && ! empty( $response['msg'] )
						? (string) $response['msg']
						: __( 'Impossible de récupérer les heartbeats.', 'gi-toolkit' );
					return null;
				}
				return is_array( $response['data'] ?? null ) ? $response['data'] : array();
			}
		);
	}

	/**
	 * @param string $name Nom du monitor.
	 * @param string $url  URL surveillée.
	 * @return array{success:bool, monitor_id?:int, message?:string}
	 */
	/**
	 * Liste des monitors avec agrégation 24 h (une seule connexion Socket.IO).
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	public function get_monitors_dashboard_data() {
		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return null;
				}
				$client->emit( 'getMonitorList' );
				$list = $client->get_last_event( 'monitorList' );
				if ( ! is_array( $list ) ) {
					return array();
				}

				$rows = array();
				foreach ( $list as $key => $monitor ) {
					if ( ! is_array( $monitor ) ) {
						continue;
					}
					$monitor_id = isset( $monitor['id'] ) ? absint( $monitor['id'] ) : absint( $key );
					if ( $monitor_id < 1 ) {
						continue;
					}
					$response = $client->emit( 'getMonitorBeats', $monitor_id, 24 );
					$beats    = ( is_array( $response ) && ! empty( $response['ok'] ) && is_array( $response['data'] ?? null ) )
						? $response['data']
						: array();
					$agg      = Gi_Toolkit_Uptime_Kuma_Status_Data::aggregate_hourly_bars( $beats );
					$rows[]   = array(
						'id'             => $monitor_id,
						'name'           => (string) ( $monitor['name'] ?? '' ),
						'url'            => (string) ( $monitor['url'] ?? '' ),
						'active'         => ! empty( $monitor['active'] ),
						'bars'           => $agg['bars'] ?? array(),
						'avg_ping'       => (int) ( $agg['avg_ping'] ?? 0 ),
						'uptime_percent' => (float) ( $agg['uptime_percent'] ?? 0 ),
					);
				}
				return $rows;
			}
		);
	}

	/**
	 * @param string $name Nom du monitor.
	 * @param string $url  URL surveillée.
	 * @return array{success:bool, monitor_id?:int, message?:string}
	 */
	public function add_http_monitor( $name, $url ) {
		$name = sanitize_text_field( $name );
		$url  = esc_url_raw( $url );

		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) use ( $name, $url ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return array(
						'success' => false,
						'message' => $this->last_error ?: __( 'Connexion impossible.', 'gi-toolkit' ),
					);
				}

				$monitor = array(
					'type'                 => 'http',
					'name'                 => $name,
					'url'                  => $url,
					'method'               => 'GET',
					'interval'             => 60,
					'retryInterval'        => 60,
					'resendInterval'       => 0,
					'maxretries'           => 0,
					'upsidedown'           => false,
					'ignoreTls'            => false,
					'maxredirects'         => 10,
					'accepted_statuscodes' => array( '200-299' ),
					'active'               => true,
					'notificationIDList'   => array(),
				);

				$response = $client->emit( 'add', $monitor );
				if ( ! is_array( $response ) || empty( $response['ok'] ) ) {
					return array(
						'success' => false,
						'message' => is_array( $response ) && ! empty( $response['msg'] )
							? (string) $response['msg']
							: __( 'Création du monitor impossible.', 'gi-toolkit' ),
					);
				}

				$monitor_id = 0;
				if ( isset( $response['monitorID'] ) ) {
					$monitor_id = absint( $response['monitorID'] );
				} elseif ( isset( $response['monitorId'] ) ) {
					$monitor_id = absint( $response['monitorId'] );
				}

				return array(
					'success'    => $monitor_id > 0,
					'monitor_id' => $monitor_id,
					'message'    => $monitor_id > 0 ? __( 'Monitor créé.', 'gi-toolkit' ) : __( 'Monitor créé mais ID inconnu.', 'gi-toolkit' ),
				);
			}
		) ?: array( 'success' => false, 'message' => $this->last_error ?: __( 'Erreur API.', 'gi-toolkit' ) );
	}

	/**
	 * @param callable $callback Callback recevant le client.
	 * @return mixed
	 */
	private function with_client( $callback ) {
		$this->last_error = null;
		if ( ! $this->is_configured() ) {
			$this->last_error = __( 'URL Uptime Kuma ou token API manquant.', 'gi-toolkit' );
			return null;
		}

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-socket-client.php';

		$ssl = '1' !== (string) ( $this->settings['disable_ssl_verify'] ?? '0' );
		$url = self::normalize_kuma_url( $this->settings['kuma_url'] ?? '' );

		$client = new Gi_Toolkit_Uptime_Kuma_Socket_Client( $url, $ssl, self::$request_timeout );
		try {
			return $callback( $client );
		} finally {
			$client->disconnect();
		}
	}

	/**
	 * @param Gi_Toolkit_Uptime_Kuma_Socket_Client $client Client.
	 * @return array{ok:bool, message?:string}
	 */
	private function login_client( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
		$token = trim( (string) ( $this->settings['api_token'] ?? '' ) );
		if ( '' === $token ) {
			return array(
				'ok'      => false,
				'message' => __( 'Token API manquant.', 'gi-toolkit' ),
			);
		}

		if ( $this->looks_like_jwt( $token ) ) {
			$response = $client->emit( 'loginByToken', $token );
			if ( is_array( $response ) && ! empty( $response['ok'] ) ) {
				return array( 'ok' => true );
			}
			$msg = is_array( $response ) && ! empty( $response['msg'] ) ? (string) $response['msg'] : '';
			return array(
				'ok'      => false,
				'message' => $msg ?: __( 'Token JWT refusé par Uptime Kuma.', 'gi-toolkit' ),
			);
		}

		$username = trim( (string) ( $this->settings['kuma_username'] ?? '' ) );
		$password = (string) ( $this->settings['kuma_password'] ?? '' );
		if ( '' !== $username && '' !== $password ) {
			$response = $client->emit(
				'login',
				array(
					'username' => $username,
					'password' => $password,
					'token'    => '',
				)
			);
			if ( is_array( $response ) && ! empty( $response['token'] ) ) {
				return array( 'ok' => true );
			}
			return array(
				'ok'      => false,
				'message' => __( 'Identifiants Uptime Kuma invalides.', 'gi-toolkit' ),
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Fournissez un token JWT (format eyJ…) obtenu après connexion à Uptime Kuma, ou un couple utilisateur / mot de passe. L’URL doit être la racine du serveur (ex. https://status.example.com), pas la page /dashboard.', 'gi-toolkit' ),
		);
	}

	/**
	 * @param string $token Token.
	 * @return bool
	 */
	private function looks_like_jwt( $token ) {
		return (bool) preg_match( '/^eyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $token );
	}
}
