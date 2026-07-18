<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Uptime Kuma (Socket.IO) — authentification par utilisateur / mot de passe.
 *
 * Cible : Uptime Kuma 2.3.x (testé avec {@see Gi_Toolkit_Uptime_Kuma_Monitor_Payload::TARGET_VERSION}).
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
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		return $this->settings;
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
		$username = trim( (string) ( $this->settings['kuma_username'] ?? '' ) );
		$password = (string) ( $this->settings['kuma_password'] ?? '' );
		return '' !== $username && '' !== $password;
	}

	/**
	 * @return bool
	 */
	public function test_connection() {
		$result = $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
				$login = $this->login_client( $client );
				if ( empty( $login['ok'] ) ) {
					$this->last_error = $login['message'] ?? __( 'Connexion Uptime Kuma refusée.', 'gi-toolkit' );
					return false;
				}
				return true;
			}
		);
		return (bool) $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_monitors() {
		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
				$login = $this->login_client( $client );
				if ( empty( $login['ok'] ) ) {
					return null;
				}
				$client->emit( 'getMonitorList' );
				$client->poll_incoming( 12 );
				$list = $client->get_last_event( 'monitorList' );
				return is_array( $list ) ? $list : array();
			}
		);
	}

	/**
	 * Disponibilité 24 h / 30 j / 1 an (événements « uptime » après getMonitorList).
	 *
	 * @param int $monitor_id ID monitor.
	 * @return array{stats: array<string, float>|null, monitor: array<string, mixed>|null}|null
	 */
	public function get_monitor_uptime_stats( $monitor_id ) {
		$monitor_id = absint( $monitor_id );
		if ( $monitor_id < 1 ) {
			return null;
		}

		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) use ( $monitor_id ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return null;
				}
				$client->emit( 'getMonitorList' );
				$client->poll_incoming( 18 );
				$stats   = $client->get_uptime_for_monitor( $monitor_id );
				$list    = $client->get_last_event( 'monitorList' );
				$monitor = null;
				if ( is_array( $list ) && isset( $list[ $monitor_id ] ) && is_array( $list[ $monitor_id ] ) ) {
					$monitor = $list[ $monitor_id ];
				} elseif ( is_array( $list ) ) {
					foreach ( $list as $key => $row ) {
						$id = is_array( $row ) && isset( $row['id'] ) ? absint( $row['id'] ) : absint( $key );
						if ( $id === $monitor_id ) {
							$monitor = is_array( $row ) ? $row : null;
							break;
						}
					}
				}
				return array(
					'stats'   => is_array( $stats ) && ! empty( $stats ) ? $stats : null,
					'monitor' => $monitor,
				);
			}
		);
	}

	/**
	 * @param float|null $ratio Ratio 0–1.
	 * @return float|null Pourcentage affiché.
	 */
	public static function uptime_ratio_to_percent( $ratio ) {
		if ( null === $ratio || ! is_numeric( $ratio ) ) {
			return null;
		}
		return round( (float) $ratio * 100, 2 );
	}

	/**
	 * Heartbeats + stats uptime en une seule connexion Socket.IO (évite double login).
	 *
	 * @param int $monitor_id ID monitor.
	 * @param int $hours        Période heartbeats (heures).
	 * @return array{beats: array<int, array<string, mixed>>|null, uptime_bundle: array{stats: array<string, float>|null, monitor: array<string, mixed>|null}}|null
	 */
	public function get_monitor_dashboard_snapshot( $monitor_id, $hours = 24 ) {
		$monitor_id = absint( $monitor_id );
		$hours      = max( 1, min( 168, absint( $hours ) ) );

		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) use ( $monitor_id, $hours ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return null;
				}

				$beats_response = $client->emit( 'getMonitorBeats', $monitor_id, $hours );
				if ( ! is_array( $beats_response ) || empty( $beats_response['ok'] ) ) {
					$this->last_error = is_array( $beats_response ) && ! empty( $beats_response['msg'] )
						? (string) $beats_response['msg']
						: __( 'Impossible de récupérer les heartbeats.', 'gi-toolkit' );
					return array(
						'beats'         => null,
						'uptime_bundle' => array(
							'stats'   => null,
							'monitor' => null,
						),
					);
				}

				$beats = is_array( $beats_response['data'] ?? null ) ? $beats_response['data'] : array();

				$client->emit( 'getMonitorList' );
				$client->poll_incoming( 12 );
				$stats   = $client->get_uptime_for_monitor( $monitor_id );
				$list    = $client->get_last_event( 'monitorList' );
				$monitor = null;
				if ( is_array( $list ) && isset( $list[ $monitor_id ] ) && is_array( $list[ $monitor_id ] ) ) {
					$monitor = $list[ $monitor_id ];
				} elseif ( is_array( $list ) ) {
					foreach ( $list as $key => $row ) {
						$id = is_array( $row ) && isset( $row['id'] ) ? absint( $row['id'] ) : absint( $key );
						if ( $id === $monitor_id ) {
							$monitor = is_array( $row ) ? $row : null;
							break;
						}
					}
				}

				return array(
					'beats'         => $beats,
					'uptime_bundle' => array(
						'stats'   => is_array( $stats ) && ! empty( $stats ) ? $stats : null,
						'monitor' => $monitor,
					),
				);
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
	 * Liste légère des monitors (stats uptime / ping, sans heartbeats — adapté MainWP).
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	public function get_monitors_overview() {
		return $this->with_client(
			function ( Gi_Toolkit_Uptime_Kuma_Socket_Client $client ) {
				if ( empty( $this->login_client( $client )['ok'] ) ) {
					return null;
				}
				$client->emit( 'getMonitorList' );
				$client->poll_incoming( 24 );
				$list = $client->get_last_event( 'monitorList' );
				if ( ! is_array( $list ) ) {
					return array();
				}

				$poll_rounds = min( 80, max( 28, count( $list ) * 2 ) );
				$client->poll_incoming( $poll_rounds );

				$rows = array();
				foreach ( $list as $key => $monitor ) {
					if ( ! is_array( $monitor ) ) {
						continue;
					}
					$monitor_id = isset( $monitor['id'] ) ? absint( $monitor['id'] ) : absint( $key );
					if ( $monitor_id < 1 ) {
						continue;
					}
					$uptime_raw = $client->get_uptime_for_monitor( $monitor_id );
					$uptime_24  = null;
					$uptime_30  = null;
					if ( is_array( $uptime_raw ) ) {
						if ( isset( $uptime_raw['24'] ) ) {
							$uptime_24 = self::uptime_ratio_to_percent( $uptime_raw['24'] );
						}
						if ( isset( $uptime_raw['720'] ) ) {
							$uptime_30 = self::uptime_ratio_to_percent( $uptime_raw['720'] );
						}
					}
					$ping = $client->get_avg_ping_for_monitor( $monitor_id );

					$rows[] = array(
						'id'         => $monitor_id,
						'name'       => (string) ( $monitor['name'] ?? '' ),
						'url'        => isset( $monitor['url'] ) ? untrailingslashit( (string) $monitor['url'] ) : '',
						'active'     => ! empty( $monitor['active'] ),
						'uptime_24h' => $uptime_24,
						'uptime_30d' => $uptime_30,
						'avg_ping'   => null !== $ping ? (int) round( $ping ) : 0,
					);
				}
				return $rows;
			}
		);
	}

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
				$client->poll_incoming( 12 );
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
				$login = $this->login_client( $client );
				if ( empty( $login['ok'] ) ) {
					return array(
						'success' => false,
						'message' => $this->last_error ?: ( $login['message'] ?? __( 'Connexion impossible.', 'gi-toolkit' ) ),
					);
				}

				require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/uptime-kuma/class-monitor-payload.php';
				$monitor = Gi_Toolkit_Uptime_Kuma_Monitor_Payload::http_monitor( $name, $url, $this->settings );

				$response = $client->emit( 'add', $monitor );
				if ( ! is_array( $response ) || empty( $response['ok'] ) ) {
					$msg = is_array( $response ) && ! empty( $response['msg'] )
						? (string) $response['msg']
						: ( $client->get_last_error() ?: __( 'Création du monitor impossible.', 'gi-toolkit' ) );
					$this->last_error = $msg;
					return array(
						'success' => false,
						'message' => $msg,
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
			$this->last_error = __( 'URL, utilisateur et mot de passe Uptime Kuma requis.', 'gi-toolkit' );
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
		$username = trim( (string) ( $this->settings['kuma_username'] ?? '' ) );
		$password = (string) ( $this->settings['kuma_password'] ?? '' );

		if ( '' === $username || '' === $password ) {
			$msg = __( 'Renseignez l’utilisateur et le mot de passe admin Uptime Kuma.', 'gi-toolkit' );
			$this->last_error = $msg;
			return array(
				'ok'      => false,
				'message' => $msg,
			);
		}

		$response = $client->emit(
			'login',
			array(
				'username' => $username,
				'password' => $password,
				'token'    => '',
			)
		);

		if ( null === $response ) {
			$detail = $client->get_last_error();
			$msg    = $detail ?: __( 'Pas de réponse Socket.IO (vérifiez l’URL et l’accès réseau au serveur Kuma).', 'gi-toolkit' );
			$this->last_error = $msg;
			return array(
				'ok'      => false,
				'message' => $msg,
			);
		}

		if ( is_array( $response ) && ! empty( $response['ok'] ) ) {
			$this->last_error = null;
			return array( 'ok' => true );
		}

		if ( is_array( $response ) && ! empty( $response['tokenRequired'] ) ) {
			$msg = __( 'Ce compte exige la 2FA sur Uptime Kuma : désactivez-la temporairement ou utilisez un compte dédié sans 2FA.', 'gi-toolkit' );
			$this->last_error = $msg;
			return array(
				'ok'      => false,
				'message' => $msg,
			);
		}

		$server_msg = is_array( $response ) && ! empty( $response['msg'] ) ? (string) $response['msg'] : '';
		$msg        = $server_msg ?: __( 'Identifiants Uptime Kuma invalides.', 'gi-toolkit' );
		$this->last_error = $msg;

		return array(
			'ok'      => false,
			'message' => $msg,
		);
	}
}
