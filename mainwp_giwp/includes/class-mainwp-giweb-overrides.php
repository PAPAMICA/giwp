<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclusions par site enfant.
 */
class MainWP_GIWeb_Overrides {

	const OPTION_KEY = 'mainwp_giweb_site_overrides';

	/**
	 * @param int $site_id ID site MainWP.
	 * @return array{excluded_modules: string[], excluded_option_modules: string[]}
	 */
	public static function get( $site_id ) {
		$all = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$key = (string) absint( $site_id );
		$data = $all[ $key ] ?? array();
		return array(
			'excluded_modules'        => isset( $data['excluded_modules'] ) && is_array( $data['excluded_modules'] ) ? $data['excluded_modules'] : array(),
			'excluded_option_modules' => isset( $data['excluded_option_modules'] ) && is_array( $data['excluded_option_modules'] ) ? $data['excluded_option_modules'] : array(),
		);
	}

	/**
	 * @param int                  $site_id ID site.
	 * @param array<string, mixed> $data    Overrides.
	 * @return void
	 */
	public static function save( $site_id, $data ) {
		$all = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$key = (string) absint( $site_id );
		$all[ $key ] = array(
			'excluded_modules'        => array_values( array_map( 'sanitize_text_field', $data['excluded_modules'] ?? array() ) ),
			'excluded_option_modules' => array_values( array_map( 'sanitize_text_field', $data['excluded_option_modules'] ?? array() ) ),
		);
		update_option( self::OPTION_KEY, $all, false );
	}

	/**
	 * Applique les exclusions à un bundle avant import.
	 *
	 * @param array<string, mixed> $bundle  Bundle.
	 * @param int                  $site_id Site ID.
	 * @return array<string, mixed>
	 */
	public static function apply_to_bundle( $bundle, $site_id ) {
		$ex = self::get( $site_id );
		return array(
			'excluded_modules'        => $ex['excluded_modules'],
			'excluded_option_modules' => $ex['excluded_option_modules'],
		);
	}
}
