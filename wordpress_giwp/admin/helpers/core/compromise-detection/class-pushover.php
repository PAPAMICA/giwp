<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Envoi d’alertes Pushover pour la détection de compromission.
 *
 * @since 2.29.9
 */
class Gi_Toolkit_Compromise_Detection_Pushover {

	const API_URL = 'https://api.pushover.net/1/messages.json';

	/**
	 * Empêche l’inspection HTTP de flagger l’appel Pushover.
	 *
	 * @var bool
	 */
	public static $sending = false;

	/**
	 * @param array<string, mixed> $settings Réglages du module.
	 * @param string               $title    Titre (max 250).
	 * @param string               $message  Corps (max 1024).
	 * @param int                  $priority -2 à 2.
	 * @param string               $sound    Son Pushover.
	 * @return array{success:bool, message:string}
	 */
	public static function send( $settings, $title, $message, $priority = 1, $sound = 'siren' ) {
		$token = isset( $settings['pushover_app_token'] ) ? trim( (string) $settings['pushover_app_token'] ) : '';
		$user  = isset( $settings['pushover_user_key'] ) ? trim( (string) $settings['pushover_user_key'] ) : '';

		if ( '' === $token || '' === $user ) {
			return array(
				'success' => false,
				'message' => __( 'Pushover n’est pas configuré (jeton application et clé utilisateur requis).', 'gi-toolkit' ),
			);
		}

		$url = home_url( '/' );

		$title = self::truncate( $title, 250 );
		$body  = self::truncate( $message, 1024 );

		$payload = array(
			'token'     => $token,
			'user'      => $user,
			'title'     => $title,
			'message'   => $body,
			'priority'  => (int) $priority,
			'sound'     => sanitize_key( $sound ),
			'url'       => $url,
			'url_title' => __( 'Ouvrir le site', 'gi-toolkit' ),
		);

		$device = isset( $settings['pushover_device'] ) ? trim( (string) $settings['pushover_device'] ) : '';
		if ( '' !== $device ) {
			$payload['device'] = $device;
		}

		if ( 2 === (int) $priority ) {
			$payload['retry']  = 60;
			$payload['expire'] = 3600;
		}

		self::$sending = true;
		$response      = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 8,
				'body'    => $payload,
			)
		);
		self::$sending = false;

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 === $code && is_array( $data ) && ! empty( $data['status'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Notification Pushover envoyée.', 'gi-toolkit' ),
			);
		}

		$errors = '';
		if ( is_array( $data ) && ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
			$errors = implode( ' ', array_map( 'sanitize_text_field', $data['errors'] ) );
		}

		return array(
			'success' => false,
			'message' => $errors ? $errors : sprintf(
				/* translators: %d: HTTP status */
				__( 'Échec Pushover (HTTP %d).', 'gi-toolkit' ),
				$code
			),
		);
	}

	/**
	 * Envoie une alerte en appliquant les modèles titre / message.
	 *
	 * @param array<string, mixed> $settings Réglages.
	 * @param string               $type     Clé de surveillance.
	 * @param string               $summary  Résumé.
	 * @param string               $details  Détails.
	 * @param int                  $priority Priorité Pushover.
	 * @param string               $sound    Son.
	 * @return array{success:bool, message:string}
	 */
	public static function send_alert( $settings, $type, $summary, $details = '', $priority = 1, $sound = 'siren' ) {
		$vars    = self::build_variables( $type, $summary, $details );
		$title   = self::apply_template( self::title_template( $settings ), $vars );
		$message = self::apply_template( self::message_template( $settings ), $vars );
		return self::send( $settings, $title, $message, $priority, $sound );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return array{success:bool, message:string}
	 */
	public static function send_test( $settings ) {
		return self::send_alert(
			$settings,
			'test',
			__( 'Test Pushover', 'gi-toolkit' ),
			__( 'Ceci est un test du module Détection de compromission. Si vous lisez ceci, la configuration Pushover fonctionne.', 'gi-toolkit' ),
			0,
			'pushover'
		);
	}

	/**
	 * @return string
	 */
	public static function default_title_template() {
		return '[GI] $alert_summary — $website_name';
	}

	/**
	 * @return string
	 */
	public static function default_message_template() {
		return '$alert_summary' . "\n\n" . '$alert_details' . "\n\n" . 'Site : $website_name' . "\n" . 'URL : $website_url' . "\n" . 'Date : $datetime';
	}

	/**
	 * @return array<string, string>
	 */
	public static function available_variables() {
		return array(
			'$website_name'     => __( 'Nom du site WordPress', 'gi-toolkit' ),
			'$website_url'      => __( 'URL du site', 'gi-toolkit' ),
			'$website_host'     => __( 'Domaine (ex. example.com)', 'gi-toolkit' ),
			'$admin_url'        => __( 'Lien vers le module dans wp-admin', 'gi-toolkit' ),
			'$alert_summary'    => __( 'Résumé de l’alerte', 'gi-toolkit' ),
			'$alert_details'    => __( 'Détails de l’alerte', 'gi-toolkit' ),
			'$alert_type'       => __( 'Identifiant technique (watch_admin_user, …)', 'gi-toolkit' ),
			'$alert_type_label' => __( 'Libellé lisible du type d’alerte', 'gi-toolkit' ),
			'$datetime'         => __( 'Date et heure de l’alerte', 'gi-toolkit' ),
			'$ip'               => __( 'Adresse IP de la requête', 'gi-toolkit' ),
			'$user'             => __( 'Utilisateur WordPress connecté (si présent)', 'gi-toolkit' ),
		);
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return string
	 */
	public static function title_template( $settings ) {
		$title = isset( $settings['pushover_title'] ) ? trim( (string) $settings['pushover_title'] ) : '';
		return '' !== $title ? $title : self::default_title_template();
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return string
	 */
	public static function message_template( $settings ) {
		$message = isset( $settings['pushover_message'] ) ? trim( (string) $settings['pushover_message'] ) : '';
		return '' !== $message ? $message : self::default_message_template();
	}

	/**
	 * @param string $type    Type.
	 * @param string $summary Résumé.
	 * @param string $details Détails.
	 * @return array<string, string>
	 */
	public static function build_variables( $type, $summary, $details = '' ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? $host : '';
		if ( 'www.' === substr( $host, 0, 4 ) ) {
			$host = substr( $host, 4 );
		}

		$labels = class_exists( 'Gi_Toolkit_Compromise_Detection' )
			? Gi_Toolkit_Compromise_Detection::watch_labels()
			: array();
		$label  = isset( $labels[ $type ] ) ? (string) $labels[ $type ] : (string) $type;
		if ( 'test' === $type ) {
			$label = __( 'Test', 'gi-toolkit' );
		}

		$current = wp_get_current_user();
		$user    = ( $current && $current->ID ) ? (string) $current->user_login : '';
		$ip      = function_exists( 'gi_toolkit_get_current_ip' ) ? gi_toolkit_get_current_ip() : '';

		return array(
			'$website_name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'$website_url'      => home_url( '/' ),
			'$website_host'     => $host,
			'$admin_url'        => admin_url( 'admin.php?page=gi-toolkit-settings-compromise-detection' ),
			'$alert_summary'    => (string) $summary,
			'$alert_details'    => (string) $details,
			'$alert_type'       => (string) $type,
			'$alert_type_label' => $label,
			'$datetime'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'$ip'               => is_string( $ip ) ? $ip : '',
			'$user'             => $user,
		);
	}

	/**
	 * @param string                $template Modèle.
	 * @param array<string, string> $vars     Variables.
	 * @return string
	 */
	public static function apply_template( $template, $vars ) {
		$template = (string) $template;
		if ( '' === $template ) {
			return '';
		}
		uksort(
			$vars,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		return str_replace( array_keys( $vars ), array_values( $vars ), $template );
	}

	/**
	 * @param string $text Texte.
	 * @param int    $max  Longueur max.
	 * @return string
	 */
	private static function truncate( $text, $max ) {
		$text = wp_strip_all_tags( (string) $text );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
			return mb_substr( $text, 0, $max - 1 ) . '…';
		}
		if ( strlen( $text ) > $max ) {
			return substr( $text, 0, $max - 1 ) . '…';
		}
		return $text;
	}
}
