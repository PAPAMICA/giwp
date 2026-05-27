<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installation / activation de GI-Toolkit sur un site enfant via MainWP.
 */
class MainWP_GIWeb_Plugin_Installer {

	/** Identifiant MainWP Child (callableFunctions), pas le nom de méthode PHP. */
	const REMOTE_FUNCTION = 'installplugintheme';

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
				'ok'               => false,
				'url'              => '',
				'package_version'  => '',
				'message'          => __( 'URL ZIP GI-Toolkit indisponible. Définissez une URL dans Réglages ou vérifiez ZipArchive et wordpress_giwp.', 'mainwp-giweb' ),
			);
		}

		return array(
			'ok'              => true,
			'url'             => $url,
			'package_version' => MainWP_GIWeb_Zip::get_package_version(),
			'message'         => '',
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

		$settings        = MainWP_GIWeb_Settings::get();
		$target_version  = MainWP_GIWeb_Zip::get_package_version();
		$post            = array(
			'type'           => 'plugin',
			'url'            => wp_json_encode( $url ),
			'activatePlugin' => ( '1' === ( $settings['activate_after_install'] ?? '1' ) ) ? 'yes' : 'no',
		);
		if ( $is_deploy ) {
			// Remplace les fichiers du plugin sans toucher aux options WP (config conservée).
			$post['overwrite'] = 'true';
		}

		$raw = self::remote_request( $website_id, $post );
		$result = self::parse_install_response( $website_id, $raw, $is_deploy, $target_version );

		if ( $is_deploy && ! $result['success'] && is_array( $raw ) && 'folder_exists' === ( $raw['error_code'] ?? '' ) ) {
			$post['overwrite'] = 'true';
			$raw               = self::remote_request( $website_id, $post );
			$result            = self::parse_install_response( $website_id, $raw, $is_deploy, $target_version );
		}

		return $result;
	}

	/**
	 * Interprète la réponse MainWP Child après installplugintheme.
	 *
	 * @param int                  $website_id ID site.
	 * @param mixed                $raw        Réponse brute.
	 * @param bool                 $is_deploy  Libellé déploiement.
	 * @return array{success:bool, message:string, data:array<string,mixed>}
	 */
	private static function parse_install_response( $website_id, $raw, $is_deploy = false, $target_version = '' ) {
		$target_version = trim( (string) $target_version );
		if ( '' === $target_version ) {
			$target_version = MainWP_GIWeb_Zip::get_package_version();
		}

		if ( false === $raw ) {
			return self::result(
				false,
				__( 'Requête MainWP refusée (extension non activée ou site inaccessible).', 'mainwp-giweb' ),
				array(),
				$target_version,
				''
			);
		}

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			}
		}

		if ( ! is_array( $raw ) ) {
			return self::result(
				false,
				__( 'Réponse MainWP vide ou invalide lors de l’installation.', 'mainwp-giweb' ),
				array(),
				$target_version,
				''
			);
		}

		if ( isset( $raw['success'] ) && ! $raw['success'] ) {
			$msg = ! empty( $raw['errors'][0] ) ? (string) $raw['errors'][0] : __( 'Échec d’installation.', 'mainwp-giweb' );
			return self::result( false, $msg, $raw, $target_version, self::resolve_installed_version( $website_id, $raw ) );
		}

		if ( ! empty( $raw['error'] ) ) {
			$msg = (string) $raw['error'];
			if ( 'folder_exists' === ( $raw['error_code'] ?? '' ) ) {
				$msg = __( 'GI-Toolkit déjà présent — échec de la mise à jour forcée.', 'mainwp-giweb' );
			} else {
				$msg = MainWP_GIWeb_API::format_site_error( $website_id, '', $msg );
			}
			return self::result(
				false,
				$msg,
				$raw,
				$target_version,
				self::resolve_installed_version( $website_id, $raw )
			);
		}

		if ( isset( $raw['response'] ) && is_array( $raw['response'] ) ) {
			return self::parse_install_response( $website_id, $raw['response'], $is_deploy, $target_version );
		}

		$info = $raw;
		if ( self::is_install_success( $info ) ) {
			$installed = self::resolve_installed_version( $website_id, $info );
			$msg       = self::format_success_message( $is_deploy, $installed, $target_version );
			return self::result( true, $msg, $info, $target_version, $installed );
		}

		$hint = '';
		if ( ! empty( $info['error_message'] ) ) {
			$hint = (string) $info['error_message'];
		} elseif ( ! empty( $info['notices'] ) ) {
			$hint = is_string( $info['notices'] ) ? $info['notices'] : wp_json_encode( $info['notices'] );
		}

		$message = __( 'Réponse MainWP inattendue lors de l’installation.', 'mainwp-giweb' );
		if ( '' !== $hint ) {
			$message .= ' ' . wp_strip_all_tags( $hint );
		}

		return self::result(
			false,
			$message,
			$info,
			$target_version,
			self::resolve_installed_version( $website_id, $info )
		);
	}

	/**
	 * @param bool   $is_deploy         Déploiement massif.
	 * @param string $installed_version Version sur le site.
	 * @param string $target_version    Version cible ZIP.
	 * @return string
	 */
	private static function format_success_message( $is_deploy, $installed_version, $target_version ) {
		$installed_version = trim( (string) $installed_version );
		$target_version    = trim( (string) $target_version );

		if ( $is_deploy ) {
			if ( '' !== $installed_version && '' !== $target_version && version_compare( $installed_version, $target_version, '<' ) ) {
				return sprintf(
					/* translators: %s: target version */
					__( 'Mise à jour effectuée, cible %s non atteinte', 'mainwp-giweb' ),
					$target_version
				);
			}
			return __( 'GI-Toolkit mis à jour', 'mainwp-giweb' );
		}

		return __( 'GI-Toolkit installé', 'mainwp-giweb' );
	}

	/**
	 * @param int                  $website_id ID site.
	 * @param array<string, mixed> $info       Réponse install ou statut.
	 * @return string
	 */
	private static function resolve_installed_version( $website_id, array $info = array() ) {
		$from_install = self::install_version_from_info( $info );
		if ( '' !== $from_install ) {
			return $from_install;
		}

		$status = MainWP_GIWeb_API::get_status( $website_id );
		if ( ! empty( $status['success'] ) && ! empty( $status['data']['gi_toolkit_version'] ) ) {
			return (string) $status['data']['gi_toolkit_version'];
		}
		if ( ! empty( $status['data']['gi_toolkit_version'] ) ) {
			return (string) $status['data']['gi_toolkit_version'];
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $info Réponse enfant installplugintheme.
	 * @return string
	 */
	private static function install_version_from_info( array $info ) {
		if ( ! empty( $info['Version'] ) ) {
			return trim( (string) $info['Version'] );
		}
		$items = $info['other_data']['install_items'] ?? array();
		if ( is_array( $items ) && ! empty( $items[0]['version'] ) ) {
			return trim( (string) $items[0]['version'] );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $info Réponse enfant.
	 * @return bool
	 */
	private static function is_install_success( array $info ) {
		if ( isset( $info['installation'] ) && 'SUCCESS' === $info['installation'] ) {
			return true;
		}
		if ( ! empty( $info['slug'] ) || ! empty( $info['Name'] ) ) {
			return true;
		}
		$items = $info['other_data']['install_items'] ?? array();
		return is_array( $items ) && ! empty( $items );
	}

	/**
	 * @param array<string, mixed> $info Réponse enfant.
	 * @return string
	 */
	private static function install_label_from_info( array $info ) {
		if ( ! empty( $info['Name'] ) ) {
			return (string) $info['Name'];
		}
		$items = $info['other_data']['install_items'] ?? array();
		if ( is_array( $items ) && ! empty( $items[0]['name'] ) ) {
			return (string) $items[0]['name'];
		}
		return 'GI-Toolkit';
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
	/**
	 * @param bool                 $success           Succès.
	 * @param string               $message           Message.
	 * @param array<string, mixed> $data              Données brutes.
	 * @param string               $target_version    Version cible ZIP.
	 * @param string               $installed_version Version sur le site enfant.
	 * @return array{success:bool, message:string, data:array<string,mixed>, target_version:string, installed_version:string}
	 */
	private static function result( $success, $message, $data = array(), $target_version = '', $installed_version = '' ) {
		return array(
			'success'           => (bool) $success,
			'message'           => (string) $message,
			'data'              => is_array( $data ) ? $data : array(),
			'target_version'    => (string) $target_version,
			'installed_version' => (string) $installed_version,
		);
	}

	/**
	 * @param string $label             Nom du site.
	 * @param string $message           Message détaillé.
	 * @param bool   $ok                Succès.
	 * @param string $installed_version Version installée.
	 * @return string
	 */
	public static function format_deploy_log_line( $label, $message, $ok, $installed_version = '', $target_version = '' ) {
		$installed_version = trim( (string) $installed_version );
		$target_version    = trim( (string) $target_version );
		$version_part      = '' !== $installed_version ? sprintf( ' [v%s]', $installed_version ) : '';
		$target_part       = ( ! $ok && '' !== $target_version ) ? sprintf( ' (cible %s)', $target_version ) : '';

		return sprintf(
			'[%s] %s — %s%s%s',
			$ok ? 'OK' : __( 'ERR', 'mainwp-giweb' ),
			$label,
			$message,
			$version_part,
			$target_part
		);
	}
}
