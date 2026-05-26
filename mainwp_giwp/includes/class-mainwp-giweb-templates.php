<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modèles de configuration (snapshots) sur le dashboard.
 */
class MainWP_GIWeb_Templates {

	const OPTION_KEY = 'mainwp_giweb_templates';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all() {
		$t = get_option( self::OPTION_KEY, array() );
		return is_array( $t ) ? $t : array();
	}

	/**
	 * @param string $id ID template.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * @param string               $name   Nom.
	 * @param array<string, mixed> $bundle Bundle config.
	 * @return string ID template.
	 */
	public static function save( $name, $bundle ) {
		$all = self::all();
		$id  = 'tpl_' . wp_generate_password( 12, false, false );
		$all[ $id ] = array(
			'name'       => sanitize_text_field( $name ),
			'bundle'     => $bundle,
			'created_at' => gmdate( 'c' ),
			'hash'       => md5( wp_json_encode( $bundle ) ),
		);
		update_option( self::OPTION_KEY, $all, false );
		return $id;
	}

	/**
	 * @param string $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		update_option( self::OPTION_KEY, $all, false );
		return true;
	}
}
