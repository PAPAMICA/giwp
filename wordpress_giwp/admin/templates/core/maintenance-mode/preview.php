<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template to preview the maintentance mode.
 * @since      2.9.0
 */

$gi_toolkit_class_maintenance_mode     = new Gi_Toolkit_Maintenance_Mode();
$gi_toolkit_preview_nonce              = sanitize_text_field( wp_unslash( $_GET[ $gi_toolkit_class_maintenance_mode->preview_param ] ?? '' ) );
if ( ! gi_toolkit_is_pro() || ! wp_verify_nonce( $gi_toolkit_preview_nonce, $gi_toolkit_class_maintenance_mode->nonce_action ) ) {
	exit;
}

$gi_toolkit_is_pro                     = gi_toolkit_is_pro();
$gi_toolkit_title_text                 = sanitize_text_field( wp_unslash( $_GET['title_text'] ?? '' ) );
$gi_toolkit_headline_text              = sanitize_text_field( wp_unslash( $_GET['headline_text'] ?? '' ) );
$gi_toolkit_body_text                  = wp_kses_post( wp_unslash( $_GET['body_text'] ?? '' ) );
$gi_toolkit_footer_text                = sanitize_text_field( wp_unslash( $_GET['footer_text'] ?? '' ) );
$gi_toolkit_background_color           = sanitize_text_field( wp_unslash( $_GET['background_color'] ?? '' ) );
$gi_toolkit_text_color                 = sanitize_text_field( wp_unslash( $_GET['text_color'] ?? '' ) );
$gi_toolkit_logo                       = sanitize_text_field( wp_unslash( $_GET['logo'] ?? '' ) );
$gi_toolkit_background_image           = sanitize_text_field( wp_unslash( $_GET['background_image'] ?? '' ) );
$gi_toolkit_logo_height                = sanitize_text_field( wp_unslash( $_GET['logo_height'] ?? '' ) );
$gi_toolkit_logo_width                 = sanitize_text_field( wp_unslash( $_GET['logo_width'] ?? '' ) );
$gi_toolkit_countdown_status           = $gi_toolkit_is_pro ? sanitize_text_field( wp_unslash( $_GET['countdown_status'] ?? '0' ) ) : '0';
$gi_toolkit_countdown_end_date         = sanitize_text_field( wp_unslash( $_GET['countdown_end_date'] ?? '' ) );
$gi_toolkit_countdown_end_date         = strtotime( $gi_toolkit_countdown_end_date );
$gi_toolkit_countdown_text_color       = sanitize_text_field( wp_unslash( $_GET['countdown_text_color'] ?? "#0000" ) );
$gi_toolkit_countdown_background_color = sanitize_text_field( wp_unslash( $_GET['countdown_background_color'] ?? "#FFFFFF" ) );

include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/maintenance-mode/html-template.php';
