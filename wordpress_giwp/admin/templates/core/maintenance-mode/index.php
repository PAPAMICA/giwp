<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template for the maintentance mode.
 * @since      1.3.0
 */

$gi_toolkit_is_pro                     = gi_toolkit_is_pro();
$gi_toolkit_class_maintenance_mode     = new Gi_Toolkit_Maintenance_Mode();
$gi_toolkit_settings                   = $gi_toolkit_class_maintenance_mode->get_settings();
$gi_toolkit_title_text                 = $gi_toolkit_settings['title_text'] ?? '';
$gi_toolkit_headline_text              = $gi_toolkit_settings['headline_text'] ?? '';
$gi_toolkit_body_text                  = $gi_toolkit_settings['body_text'] ?? '';
$gi_toolkit_footer_text                = $gi_toolkit_settings['footer_text'] ?? '';
$gi_toolkit_background_color           = $gi_toolkit_settings['background_color'] ?? '';
$gi_toolkit_text_color                 = $gi_toolkit_settings['text_color'] ?? '';
$gi_toolkit_logo                       = $gi_toolkit_settings['logo'] ?? '';
$gi_toolkit_background_image           = $gi_toolkit_settings['background_image'] ?? '';
$gi_toolkit_logo_height                = $gi_toolkit_settings['logo_height'] ?? '';
$gi_toolkit_logo_width                 = $gi_toolkit_settings['logo_width'] ?? '';
$gi_toolkit_countdown_status           = $gi_toolkit_is_pro ? $gi_toolkit_settings['countdown_status'] ?? '0' : '0';
$gi_toolkit_countdown_end_date         = $gi_toolkit_settings['countdown_end_date'] ?? time();
$gi_toolkit_countdown_text_color       = $gi_toolkit_settings['countdown_text_color'] ?? "#0000";
$gi_toolkit_countdown_background_color = $gi_toolkit_settings['countdown_background_color'] ?? "#FFFFFF";

include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/maintenance-mode/html-template.php';