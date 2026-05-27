<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stockage et normalisation du bundle de configuration de travail.
 */
class MainWP_GIWeb_Bundle {

	const OPTION = 'mainwp_giweb_working_bundle';

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$bundle = get_option( self::OPTION, array() );
		return is_array( $bundle ) ? $bundle : array();
	}

	/**
	 * Extrait le bundle depuis une réponse API (gère les enveloppes imbriquées).
	 *
	 * @param array<string, mixed> $response Réponse MainWP / GI-Toolkit.
	 * @return array<string, mixed>|null
	 */
	public static function from_api_response( $response ) {
		if ( ! is_array( $response ) || empty( $response['success'] ) ) {
			return null;
		}

		$data = $response['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( isset( $data['modules'] ) && is_array( $data['modules'] ) ) {
			return $data;
		}

		if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['modules'] ) ) {
			return $data['data'];
		}

		return null;
	}

	/**
	 * Rend le bundle sérialisable (supprime types non JSON).
	 *
	 * @param array<string, mixed> $bundle Bundle brut.
	 * @return array<string, mixed>
	 */
	public static function normalize_for_storage( $bundle ) {
		if ( ! is_array( $bundle ) ) {
			return array();
		}

		$json = wp_json_encode( $bundle );
		if ( false === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<string, mixed> $bundle Bundle.
	 * @return true|WP_Error
	 */
	public static function save( $bundle ) {
		$bundle = self::normalize_for_storage( $bundle );
		if ( empty( $bundle['modules'] ) || ! is_array( $bundle['modules'] ) ) {
			return new WP_Error(
				'mainwp_giweb_invalid_bundle',
				__( 'Bundle invalide : aucun module reçu.', 'mainwp-giweb' )
			);
		}

		$json = wp_json_encode( $bundle );
		if ( false === $json ) {
			return new WP_Error(
				'mainwp_giweb_encode_failed',
				__( 'Impossible d’encoder la configuration (données trop volumineuses).', 'mainwp-giweb' )
			);
		}

		// ~1,5 Mo — limite pratique pour wp_options.
		if ( strlen( $json ) > 1500000 ) {
			return new WP_Error(
				'mainwp_giweb_bundle_too_large',
				__( 'La configuration importée est trop volumineuse pour être stockée sur le dashboard.', 'mainwp-giweb' )
			);
		}

		$updated  = update_option( self::OPTION, $bundle, false );
		$existing = self::get();
		if ( ! $updated && wp_json_encode( $existing ) !== wp_json_encode( $bundle ) ) {
			return new WP_Error(
				'mainwp_giweb_save_failed',
				__( 'Échec de l’enregistrement de la configuration sur le dashboard.', 'mainwp-giweb' )
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $bundle Bundle.
	 * @return array{total: int, active: int}
	 */
	public static function count_modules( $bundle ) {
		$modules = $bundle['modules'] ?? array();
		if ( ! is_array( $modules ) ) {
			return array(
				'total'  => 0,
				'active' => 0,
			);
		}

		$active = 0;
		foreach ( $modules as $mod ) {
			if ( is_array( $mod ) && ! empty( $mod['active'] ) && '1' === (string) $mod['active'] ) {
				++$active;
			}
		}

		return array(
			'total'  => count( $modules ),
			'active' => $active,
		);
	}

	/**
	 * Options d’un module dans le bundle de travail.
	 *
	 * @param string               $class  Classe module.
	 * @param array<string, mixed> $bundle Bundle (défaut : courant).
	 * @return array<string, mixed>|null
	 */
	public static function get_module_options( $class, $bundle = null ) {
		if ( ! is_array( $bundle ) ) {
			$bundle = self::get();
		}
		$modules = $bundle['modules'] ?? array();
		if ( ! is_array( $modules ) || ! isset( $modules[ $class ]['options'] ) ) {
			if ( MainWP_GIWeb_Module_Options::is_css_module( $class ) ) {
				return array( MainWP_GIWeb_Module_Options::FIELD_KEY => '' );
			}
			return null;
		}
		$options = $modules[ $class ]['options'];
		return is_array( $options ) ? $options : null;
	}

	/**
	 * Met à jour les options d’un module dans le bundle de travail.
	 *
	 * @param string               $class   Classe module.
	 * @param array<string, mixed> $options Options.
	 * @return true|WP_Error
	 */
	public static function set_module_options( $class, $options ) {
		if ( '' === $class || ! is_array( $options ) ) {
			return new WP_Error( 'mainwp_giweb_invalid_module', __( 'Module ou options invalides.', 'mainwp-giweb' ) );
		}

		$bundle = self::get();
		if ( empty( $bundle['modules'] ) || ! is_array( $bundle['modules'] ) ) {
			$bundle['modules'] = array();
		}

		if ( ! isset( $bundle['modules'][ $class ] ) || ! is_array( $bundle['modules'][ $class ] ) ) {
			$bundle['modules'][ $class ] = array(
				'active' => '0',
			);
		}

		$bundle['modules'][ $class ]['options'] = $options;
		return self::save( $bundle );
	}

	/**
	 * Masque les valeurs sensibles pour l’affichage (mots de passe, clés API).
	 *
	 * @param mixed $data Données.
	 * @return mixed
	 */
	public static function mask_sensitive_for_display( $data ) {
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $key => $value ) {
				$key_str = is_string( $key ) ? strtolower( $key ) : '';
				if ( is_array( $value ) ) {
					$out[ $key ] = self::mask_sensitive_for_display( $value );
				} elseif ( self::is_sensitive_key( $key_str ) && is_string( $value ) && '' !== $value ) {
					$out[ $key ] = '••••••••';
				} else {
					$out[ $key ] = $value;
				}
			}
			return $out;
		}
		return $data;
	}

	/**
	 * @param string $key Clé tableau.
	 * @return bool
	 */
	private static function is_sensitive_key( $key ) {
		$needles = array( 'password', 'passwd', 'secret', 'api_key', 'apikey', 'token', 'private_key' );
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}
		return false;
	}
}
