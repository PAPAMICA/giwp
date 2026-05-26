<?php
/**
 * Plugin Name: MainWP GI-Web Child Bridge
 * Description: Pont MainWP pour piloter GI-Toolkit à distance. Requis sur chaque site enfant avec MainWP Child et GI-Toolkit.
 * Version: 1.0.0
 * Author: Genevois Informatique
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package MainWP_GIWeb_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler MainWP : nom de fonction = identifiant appelé depuis le dashboard.
 *
 * @param array<string, mixed> $data Données POST.
 * @return array<string, mixed>
 */
function gi_toolkit( $data = array() ) {
	if ( ! class_exists( 'Gi_Toolkit_MainWP_API' ) ) {
		return array(
			'success' => false,
			'data'    => array(),
			'errors'  => array( 'GI-Toolkit is not active on this child site.' ),
		);
	}
	return Gi_Toolkit_MainWP_API::handle_request( is_array( $data ) ? $data : array() );
}

/**
 * Compatibilité anciennes versions MainWP Child.
 *
 * @param array<string, mixed> $data Données.
 * @return array<string, mixed>
 */
function mainwp_giweb_child_handler( $data = array() ) {
	return gi_toolkit( $data );
}

add_filter(
	'mainwp_child_actions',
	static function ( $actions ) {
		if ( ! is_array( $actions ) ) {
			$actions = array();
		}
		$actions['gi_toolkit'] = 'gi_toolkit';
		return $actions;
	}
);
