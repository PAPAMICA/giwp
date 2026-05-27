<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installation / activation de GI-Toolkit sur un site enfant via MainWP.
 */
class MainWP_GIWeb_Plugin_Installer {

	const REMOTE_FUNCTION = 'install_plugin_theme';

	/**
	 * Vérifie que l’URL ZIP de déploiement est utilisable.
	 *
	 * @return array{ok: bool, url: string, message: string}
	 */
	public static function validate_deploy_url() {
		MainWP_GIWeb_Zip::build_if_needed();
		$url = MainWP_GIWeb_Zip::get_install_url();
		if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array(
				'ok'      => false,
				'url'     => '',
				'message' => __( 'URL ZIP GI-Toolkit indisponible. Définissez une URL dans Réglages ou vérifiez ZipArchive et wordpress_giwp.', 'mainwp-giweb' ),
			);
		}

		return array(
			'ok'      => true,
			'url'     => $url,
			'message' => '',
		);
	}

	/**
	 * Installe ou met à jour GI-Toolkit depuis l’URL ZIP configurée.
	 *
	 * @param int $website_id ID MainWP.
	 * @return array{success:bool, message:string, data:array<string,mixed>}
	 */
	public static function deploy_gi_toolkit( $website_id ) {
		return self::install_gi_toolkit( $website_id, true );
	}

	/**
	 * Installe et active GI-Toolkit depuis le ZIP du monorepo.
	 *
	 * @param int  $website_id ID MainWP.
	 * @param bool $is_deploy  Libellé « mise à jour » si true.
	 * @return array{success:bool, message:string, data:array<string,mixed>}
	 */
	public static function install_gi_toolkit( $website_id, $is_deploy = false ) {
		$website_id = absint( $website_id );
		if ( ! $website_id ) {
			return self::result( false, __( 'ID site invalide.', 'mainwp-giweb' ) );
		}

		$url = MainWP_GIWeb_Zip::get_install_url();
		if ( ! $url ) {
			return self::result(
				false,
				__( 'URL ZIP GI-Toolkit indisponible. Définissez une URL dans Réglages ou vérifiez ZipArchive et wordpress_giwp.', 'mainwp-giweb' )
			);
		}

		$settings = MainWP_GIWeb_Settings::get();
		$post     = array(
			'type'           => 'plugin',
			'url'            => wp_json_encode( $url ),
			'activatePlugin' => ( '1' === ( $settings['activate_after_install'] ?? '1' ) ) ? 'yes' : 'no',
		);

		$raw = self::remote_request( $website_id, $post );
		if ( is_array( $raw ) && ! empty( $raw['error'] ) ) {
			return self::result(
				false,
				MainWP_GIWeb_API::format_site_error( $website_id, '', (string) $raw['error'] )
			);
		}

		if ( is_array( $raw ) && isset( $raw['success'] ) && ! $raw['success'] ) {
			$msg = ! empty( $raw['errors'][0] ) ? (string) $raw['errors'][0] : __( 'Échec d’installation.', 'mainwp-giweb' );
			return self::result( false, $msg, $raw );
		}

		// Réponse MainWP classique (slug, Name, …).
		if ( is_array( $raw ) && ( ! empty( $raw['slug'] ) || ! empty( $raw['Name'] ) ) ) {
			$label = (string) ( $raw['Name'] ?? 'GI-Toolkit' );
			$msg   = $is_deploy
				? sprintf(
					/* translators: %s: plugin name */
					__( 'GI-Toolkit déployé / mis à jour (%s).', 'mainwp-giweb' ),
					$label
				)
				: sprintf(
					/* translators: %s: plugin name */
					__( 'GI-Toolkit installé (%s).', 'mainwp-giweb' ),
					$label
				);
			return self::result( true, $msg, $raw );
		}

		// Parfois la réponse est encapsulée.
		if ( is_array( $raw ) && isset( $raw['response'] ) && is_array( $raw['response'] ) ) {
			return self::install_gi_toolkit_from_response( $website_id, $raw['response'] );
		}

		return self::result( false, __( 'Réponse MainWP inattendue lors de l’installation.', 'mainwp-giweb' ), is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @param int                  $website_id ID site.
	 * @param array<string, mixed> $response   Réponse brute.
	 * @return array{success:bool, message:string, data:array<string,mixed>}
	 */
	private static function install_gi_toolkit_from_response( $website_id, $response ) {
		if ( ! empty( $response['error'] ) ) {
			return self::result(
				false,
				MainWP_GIWeb_API::format_site_error( $website_id, '', (string) $response['error'] )
			);
		}
		if ( ! empty( $response['slug'] ) || ! empty( $response['Name'] ) ) {
			return self::result( true, __( 'GI-Toolkit installé.', 'mainwp-giweb' ), $response );
		}
		return self::result( false, __( 'Installation non confirmée par le site enfant.', 'mainwp-giweb' ), $response );
	}

	/**
	 * Attend que GI-Toolkit réponde (après install).
	 *
	 * @param int $website_id ID site.
	 * @param int $attempts   Tentatives.
	 * @return array<string, mixed>
	 */
	public static function wait_for_gi_toolkit( $website_id, $attempts = 5 ) {
		$last = array( 'success' => false, 'errors' => array( __( 'GI-Toolkit non détecté.', 'mainwp-giweb' ) ) );
		for ( $i = 0; $i < $attempts; $i++ ) {
			$last = MainWP_GIWeb_API::get_status( $website_id );
			if ( ! empty( $last['success'] ) ) {
				return $last;
			}
			if ( $i < $attempts - 1 ) {
				sleep( 2 );
			}
		}
		return $last;
	}

	/**
	 * @param int                  $website_id ID site.
	 * @param array<string, mixed> $post_data  Données POST enfant.
	 * @return mixed
	 */
	private static function remote_request( $website_id, array $post_data ) {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator || ! method_exists( $mainwp_giweb_activator, 'getChildKey' ) ) {
			return array( 'error' => __( 'Extension MainWP GI-Toolkit non initialisée.', 'mainwp-giweb' ) );
		}

		if ( empty( $mainwp_giweb_activator->childKey ) && method_exists( $mainwp_giweb_activator, 'activate_extension' ) ) {
			$mainwp_giweb_activator->activate_extension();
		}

		$timeout_cb = static function () {
			return 300;
		};
		add_filter( 'http_request_timeout', $timeout_cb, 999 );
		add_filter( 'mainwp_http_request_timeout', $timeout_cb, 999 );

		$raw = apply_filters(
			'mainwp_fetchurlauthed',
			$mainwp_giweb_activator->getChildFile(),
			$mainwp_giweb_activator->getChildKey(),
			absint( $website_id ),
			self::REMOTE_FUNCTION,
			$post_data
		);

		remove_filter( 'http_request_timeout', $timeout_cb, 999 );
		remove_filter( 'mainwp_http_request_timeout', $timeout_cb, 999 );

		return $raw;
	}

	/**
	 * @param bool                 $success Succès.
	 * @param string               $message Message.
	 * @param array<string, mixed> $data    Données.
	 * @return array{success:bool, message:string, data:array<string,mixed>}
	 */
	private static function result( $success, $message, $data = array() ) {
		return array(
			'success' => (bool) $success,
			'message' => (string) $message,
			'data'    => is_array( $data ) ? $data : array(),
		);
	}
}
