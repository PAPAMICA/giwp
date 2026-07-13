<?php
/**
 * Nettoyage à la désinstallation du plugin.
 *
 * @package Gi_Toolkit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'GI_TOOLKIT_PLUGIN_SETTINGS' ) ) {
	define( 'GI_TOOLKIT_PLUGIN_SETTINGS', 'gi_toolkit_settings' );
}

$gi_toolkit_options_to_delete = array(
	GI_TOOLKIT_PLUGIN_SETTINGS,
	GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu',
	GI_TOOLKIT_PLUGIN_SETTINGS . '_promot',
	'gi_toolkit_credentials_tab',
	'gi_toolkit_security_settings',
	'gi_toolkit_openai_key',
);

foreach ( $gi_toolkit_options_to_delete as $gi_toolkit_option ) {
	delete_option( $gi_toolkit_option );
}

global $wpdb;

// Options préfixées des modules (gi_toolkit_settings_*).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( GI_TOOLKIT_PLUGIN_SETTINGS ) . '\_%',
		$wpdb->esc_like( 'gi_toolkit_' ) . '%'
	)
);

// Tables custom connues.
$gi_toolkit_tables = array(
	$wpdb->prefix . 'gi_toolkit_mail_catcher',
);
foreach ( $gi_toolkit_tables as $gi_toolkit_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DROP TABLE IF EXISTS `{$gi_toolkit_table}`" );
}

// Dossiers de données du plugin (nom canonique + ancien chemin).
foreach ( array( 'gi-toolkit', 'gi_toolkit' ) as $gi_toolkit_dir_name ) {
	$gi_toolkit_data_dir = WP_CONTENT_DIR . '/' . $gi_toolkit_dir_name;
	if ( is_dir( $gi_toolkit_data_dir ) ) {
		gi_toolkit_uninstall_remove_directory( $gi_toolkit_data_dir );
	}
}

/**
 * Suppression récursive d’un répertoire.
 *
 * @param string $dir Chemin absolu.
 * @return void
 */
function gi_toolkit_uninstall_remove_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			gi_toolkit_uninstall_remove_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	rmdir( $dir );
}
