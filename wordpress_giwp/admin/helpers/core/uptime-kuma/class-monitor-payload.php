<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payload monitor HTTP aligné sur Uptime Kuma 2.3.x (réf. 2.3.2 — server/server.js « add »).
 */
class Gi_Toolkit_Uptime_Kuma_Monitor_Payload {

	const TARGET_VERSION = '2.3.2';

	/**
	 * @param string               $name     Nom affiché.
	 * @param string               $url      URL surveillée.
	 * @param array<string, mixed> $settings Réglages GI-Toolkit (ignoreTls, etc.).
	 * @return array<string, mixed>
	 */
	public static function http_monitor( $name, $url, array $settings = array() ) {
		$ignore_tls = '1' === (string) ( $settings['disable_ssl_verify'] ?? '0' );

		return array(
			'type'                         => 'http',
			'name'                         => $name,
			'url'                          => $url,
			'method'                       => 'GET',
			'interval'                     => 60,
			'retryInterval'                => 60,
			'resendInterval'               => 0,
			'maxretries'                   => 0,
			'retryOnlyOnStatusCodeFailure' => false,
			'ignoreTls'                    => $ignore_tls,
			'upsideDown'                   => false,
			'expiryNotification'           => false,
			'domainExpiryNotification'     => true,
			'maxredirects'                 => 10,
			'accepted_statuscodes'         => array( '200-299' ),
			'saveResponse'                 => false,
			'saveErrorResponse'            => true,
			'active'                       => true,
			'notificationIDList'           => (object) array(),
			'kafkaProducerBrokers'         => array(),
			'kafkaProducerSaslOptions'     => array(
				'mechanism' => 'None',
			),
			'conditions'                   => array(),
			'rabbitmqNodes'                => array(),
		);
	}
}
