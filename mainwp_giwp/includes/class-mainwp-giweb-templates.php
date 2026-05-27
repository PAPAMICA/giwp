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
	public static function save( $name, $bundle, $is_default = false ) {
		$all = self::all();
		$id  = 'tpl_' . wp_generate_password( 12, false, false );
		if ( $is_default ) {
			foreach ( $all as $key => $tpl ) {
				unset( $all[ $key ]['is_default'] );
			}
		}
		$all[ $id ] = array(
			'name'       => sanitize_text_field( $name ),
			'bundle'     => $bundle,
			'created_at' => gmdate( 'c' ),
			'hash'       => md5( wp_json_encode( $bundle ) ),
			'is_default' => $is_default ? 1 : 0,
		);
		update_option( self::OPTION_KEY, $all, false );
		if ( $is_default ) {
			$settings = MainWP_GIWeb_Settings::get();
			$settings['default_template_id'] = $id;
			MainWP_GIWeb_Settings::save( $settings );
		}
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

		$settings = MainWP_GIWeb_Settings::get();
		if ( ( $settings['default_template_id'] ?? '' ) === $id ) {
			$settings['default_template_id'] = '';
			MainWP_GIWeb_Settings::save( $settings );
		}
		return true;
	}

	/**
	 * @param string $name Nom exact (insensible à la casse).
	 * @return array{id:string, name:string, bundle:array}|null
	 */
	public static function find_by_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return null;
		}
		foreach ( self::all() as $id => $tpl ) {
			if ( strcasecmp( (string) ( $tpl['name'] ?? '' ), $name ) === 0 ) {
				return array_merge( $tpl, array( 'id' => $id ) );
			}
		}
		return null;
	}

	/**
	 * ID du modèle par défaut (réglage, flag is_default, ou nom « Default »).
	 *
	 * @return string
	 */
	public static function get_default_template_id() {
		$settings = MainWP_GIWeb_Settings::get();
		$pref     = (string) ( $settings['default_template_id'] ?? '' );
		if ( '' !== $pref && self::get( $pref ) ) {
			return $pref;
		}

		foreach ( self::all() as $id => $tpl ) {
			if ( ! empty( $tpl['is_default'] ) ) {
				return (string) $id;
			}
		}

		$default = self::find_by_name( 'Default' );
		return $default ? (string) $default['id'] : '';
	}

	/**
	 * @param string $template_id ID explicite ou vide pour défaut.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_bundle( $template_id = '' ) {
		$template_id = sanitize_text_field( (string) $template_id );
		if ( '' === $template_id ) {
			$template_id = self::get_default_template_id();
		}
		if ( '' === $template_id ) {
			return null;
		}
		$tpl = self::get( $template_id );
		if ( ! $tpl || empty( $tpl['bundle'] ) || ! is_array( $tpl['bundle'] ) ) {
			return null;
		}
		return $tpl['bundle'];
	}

	/**
	 * @param string $template_id ID modèle.
	 * @return bool
	 */
	public static function set_default( $template_id ) {
		$template_id = sanitize_text_field( $template_id );
		$all         = self::all();
		if ( ! isset( $all[ $template_id ] ) ) {
			return false;
		}
		foreach ( $all as $id => $tpl ) {
			$all[ $id ]['is_default'] = ( $id === $template_id ) ? 1 : 0;
		}
		update_option( self::OPTION_KEY, $all, false );

		$settings = MainWP_GIWeb_Settings::get();
		$settings['default_template_id'] = $template_id;
		return MainWP_GIWeb_Settings::save( $settings );
	}
}
