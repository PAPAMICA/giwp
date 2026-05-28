<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Socket.IO minimal (Engine.IO polling) pour Uptime Kuma.
 */
class Gi_Toolkit_Uptime_Kuma_Socket_Client {

	/** @var string */
	private $base_url;

	/** @var bool */
	private $ssl_verify;

	/** @var int */
	private $timeout;

	/** @var string */
	private $sid = '';

	/** @var int */
	private $ack_id = 0;

	/** @var array<int, true> */
	private $pending_acks = array();

	/** @var array<int, mixed> */
	private $ack_responses = array();

	/** @var array<string, mixed> */
	private $last_events = array();

	/** @var string|null */
	private $last_error = null;

	/**
	 * @param string $base_url    URL Kuma (sans slash final).
	 * @param bool   $ssl_verify  Vérifier SSL.
	 * @param int    $timeout     Timeout secondes.
	 */
	public function __construct( $base_url, $ssl_verify = true, $timeout = 30 ) {
		$this->base_url   = untrailingslashit( (string) $base_url );
		$this->ssl_verify = (bool) $ssl_verify;
		$this->timeout    = max( 5, absint( $timeout ) );
	}

	/**
	 * @return string|null
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * @param string $event Nom événement.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	public function emit( $event, ...$args ) {
		$this->last_error = null;
		if ( '' === $this->sid ) {
			if ( ! $this->connect() ) {
				return null;
			}
		}

		$this->ack_id++;
		$ack                             = $this->ack_id;
		$payload                         = wp_json_encode( array_merge( array( $event ), $args ) );
		$this->pending_acks[ $ack ]      = true;
		$packet                          = '42' . $ack . $payload;
		$responses                       = $this->request( 'POST', $packet );
		$this->process_packets( $responses );

		if ( ! array_key_exists( $ack, $this->ack_responses ) ) {
			unset( $this->pending_acks[ $ack ] );
			$this->last_error = __( 'Timeout Socket.IO (pas de réponse Kuma).', 'gi-toolkit' );
			return null;
		}

		$response = $this->ack_responses[ $ack ];
		unset( $this->ack_responses[ $ack ], $this->pending_acks[ $ack ] );
		return $response;
	}

	/**
	 * @param string $event Nom événement écouté.
	 * @return mixed|null
	 */
	public function get_last_event( $event ) {
		return $this->last_events[ $event ] ?? null;
	}

	/**
	 * @return bool
	 */
	public function connect() {
		$this->sid           = '';
		$this->pending_acks  = array();
		$this->ack_responses = array();
		$this->last_events   = array();

		$body = $this->request( 'GET', '' );
		if ( null === $body ) {
			return false;
		}

		$this->process_packets( $body );
		if ( '' === $this->sid ) {
			$this->last_error = $this->last_error ?: __( 'Handshake Socket.IO impossible.', 'gi-toolkit' );
			return false;
		}

		$this->process_packets( $this->request( 'POST', '40' ) );
		return true;
	}

	/**
	 * @return void
	 */
	public function disconnect() {
		if ( '' !== $this->sid ) {
			$this->request( 'POST', '41' );
		}
		$this->sid = '';
	}

	/**
	 * @param string $method GET|POST.
	 * @param string $payload Corps paquets Engine.IO.
	 * @return string|null
	 */
	private function request( $method, $payload ) {
		$url = add_query_arg(
			array(
				'EIO'       => '4',
				'transport' => 'polling',
				't'         => (string) microtime( true ),
			),
			$this->base_url . '/socket.io/'
		);

		if ( '' !== $this->sid ) {
			$url = add_query_arg( 'sid', rawurlencode( $this->sid ), $url );
		}

		$args = array(
			'timeout'   => $this->timeout,
			'sslverify' => $this->ssl_verify,
			'headers'   => array(
				'Accept' => 'text/plain, */*; q=0.01',
			),
		);

		if ( 'POST' === $method ) {
			$args['body']    = $payload;
			$args['headers']['Content-Type'] = 'text/plain;charset=UTF-8';
			$response        = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$this->last_error = sprintf(
				/* translators: %d: HTTP status */
				__( 'Uptime Kuma HTTP %d.', 'gi-toolkit' ),
				$code
			);
			return null;
		}

		return $body;
	}

	/**
	 * @param string $raw Paquets bruts.
	 * @return void
	 */
	private function process_packets( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		$offset = 0;
		$len    = strlen( $raw );

		while ( $offset < $len ) {
			if ( ! preg_match( '/^(\d+):/', substr( $raw, $offset ), $m ) ) {
				$packet = substr( $raw, $offset );
				$offset = $len;
			} else {
				$packet_len = (int) $m[1];
				$offset    += strlen( $m[0] );
				$packet     = substr( $raw, $offset, $packet_len );
				$offset    += $packet_len;
			}

			if ( '' === $packet ) {
				continue;
			}

			$this->process_packet( $packet );
		}
	}

	/**
	 * @param string $packet Un paquet Engine.IO.
	 * @return void
	 */
	private function process_packet( $packet ) {
		$type = (int) $packet[0];

		if ( 0 === $type ) {
			$data = json_decode( substr( $packet, 1 ), true );
			if ( is_array( $data ) && ! empty( $data['sid'] ) ) {
				$this->sid = (string) $data['sid'];
			}
			return;
		}

		if ( 2 === $type ) {
			$this->request( 'POST', '3' );
			return;
		}

		if ( 4 !== $type || strlen( $packet ) < 2 ) {
			return;
		}

		$socket_payload = substr( $packet, 1 );
		$socket_type    = (int) $socket_payload[0];

		if ( 0 === $socket_type ) {
			return;
		}

		if ( 2 === $socket_type ) {
			$this->parse_socket_event( $socket_payload );
			return;
		}

		if ( 3 === $socket_type ) {
			$this->parse_socket_ack( $socket_payload );
		}
	}

	/**
	 * @param string $payload Payload sans préfixe type Engine.
	 * @return void
	 */
	private function parse_socket_event( $payload ) {
		if ( ! preg_match( '/^2(\d*)(.*)$/s', $payload, $m ) ) {
			return;
		}

		$data = json_decode( $m[2], true );
		if ( ! is_array( $data ) || empty( $data[0] ) ) {
			return;
		}

		$event = (string) $data[0];
		$value = $data[1] ?? null;
		$this->last_events[ $event ] = $value;
	}

	/**
	 * @param string $payload Payload ACK.
	 * @return void
	 */
	private function parse_socket_ack( $payload ) {
		if ( ! preg_match( '/^3(\d+)(.*)$/s', $payload, $m ) ) {
			return;
		}

		$ack_id = (int) $m[1];
		$data   = json_decode( $m[2], true );
		unset( $this->pending_acks[ $ack_id ] );
		$this->ack_responses[ $ack_id ] = $data;
	}
}
