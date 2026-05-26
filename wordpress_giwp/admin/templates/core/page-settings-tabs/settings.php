<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onglet Paramètres (import/export, sécurité globale, maintenance).
 *
 * @since 1.0.0
 */

$gi_toolkit_security = Gi_Toolkit_Security::get_options();
$gi_toolkit_audit    = Gi_Toolkit_Security::get_audit_log_path();
$gi_toolkit_audit_sz = file_exists( $gi_toolkit_audit ) ? size_format( (int) filesize( $gi_toolkit_audit ) ) : '0 B';
?>

<div class="gi-toolkit__body__sections__item hide-in-all gi-toolkit-security-panel" data-key="settings">
	<div class="gi-toolkit__body__sections__item__top">
		<div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Sécurité globale', 'gi-toolkit' ); ?></div>
	</div>
	<div class="gi-toolkit__body__sections__item__top">
		<div class="gi-toolkit__body__sections__item__description">
			<?php esc_html_e( 'Ces options renforcent les contrôles d’accès et limitent l’activation accidentelle de modules sensibles.', 'gi-toolkit' ); ?>
		</div>
	</div>

	<?php
	$gi_toolkit_security_toggles = array(
		'ajax_strict_capabilities'       => __( 'Exiger une capability sur toutes les requêtes AJAX admin', 'gi-toolkit' ),
		'allow_high_risk_modules'        => __( 'Autoriser les modules à haut risque (Snippets, File Manager, Adminer, Search & Replace)', 'gi-toolkit' ),
		'audit_log_enabled'              => __( 'Journaliser les actions sensibles (fichier security-audit.log)', 'gi-toolkit' ),
		'search_replace_dry_run_default' => __( 'Search & Replace : mode simulation activé par défaut', 'gi-toolkit' ),
		'hide_sensitive_in_system_info'  => __( 'Masquer les clés API dans les infos système copiées', 'gi-toolkit' ),
		'confirm_module_activation'      => __( 'Demander une confirmation avant d’activer un module', 'gi-toolkit' ),
		'admin_dark_theme'               => __( 'Thème sombre sur la page des réglages GI-Toolkit', 'gi-toolkit' ),
	);
	foreach ( $gi_toolkit_security_toggles as $gi_toolkit_key => $gi_toolkit_label ) :
		$gi_toolkit_checked = ! empty( $gi_toolkit_security[ $gi_toolkit_key ] ) && '1' === $gi_toolkit_security[ $gi_toolkit_key ];
		?>
		<div class="gi-toolkit__body__sections__item__top toggle-settings">
			<div class="gi-toolkit__body__sections__item__bottom">
				<div class="gi-toolkit__body__sections__item__toggle">
					<label class="gi-toolkit__toggle">
						<input type="hidden" name="gi_toolkit_security_settings[<?php echo esc_attr( $gi_toolkit_key ); ?>]" value="0">
						<input type="checkbox" name="gi_toolkit_security_settings[<?php echo esc_attr( $gi_toolkit_key ); ?>]" value="1" <?php checked( $gi_toolkit_checked ); ?>>
						<span class="gi-toolkit__toggle__slider round"></span>
					</label>
				</div>
			</div>
			<div class="gi-toolkit__body__sections__item__title"><?php echo esc_html( $gi_toolkit_label ); ?></div>
		</div>
	<?php endforeach; ?>

	<div class="gi-toolkit__body__sections__item__space"></div>

	<div class="gi-toolkit__body__sections__item__top">
		<div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Journal d’audit', 'gi-toolkit' ); ?></div>
	</div>
	<div class="gi-toolkit__body__sections__item__description gi-toolkit-security-audit-meta">
		<code><?php echo esc_html( $gi_toolkit_audit ); ?></code>
		— <?php echo esc_html( $gi_toolkit_audit_sz ); ?>
	</div>

	<div class="gi-toolkit__body__sections__item__space"></div>

    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Import settings from JSON', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description"><?php echo esc_html_e( "You can import settings from a JSON file. This will overwrite all current settings. Please make sure to backup your current settings before importing a new file.", 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__bottom">
        <input type="file" name="gi_toolkit_settings_tab_input" accept="application/JSON">
        <button class="gi-toolkit__body__sections__item__btn" type="submit" name="gi_toolkit_settings_tab_upload_json_submit" >
			<?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/upload.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            <?php esc_html_e( 'Upload', 'gi-toolkit' ); ?>
        </button>
    </div>

    <div class="gi-toolkit__body__sections__item__space"></div>

    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Export settings to JSON', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description"><?php echo esc_html_e( 'You can export settings to a JSON file. This will create a JSON file with all current settings.', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__bottom">
        <button class="gi-toolkit__body__sections__item__btn" type="submit" name="gi_toolkit_settings_tab_download_json_submit" >
			<?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/download.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            <?php esc_html_e( 'Download', 'gi-toolkit' ); ?>
        </button>
    </div>

	<div class="gi-toolkit__body__sections__item__space"></div>

	<div class="gi-toolkit__body__sections__item__top toggle-settings">
        <div class="gi-toolkit__body__sections__item__bottom">
            <div class="gi-toolkit__body__sections__item__toggle">
                <label class="gi-toolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu'); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr(GI_TOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu'); ?>" id="JS-gi_toolkit_settings_use_wp_submenu_checkbox" value="1" <?php checked( $use_wp_submenu_status, '1' ); ?> >
                    <span class="gi-toolkit__toggle__slider round"></span>
                </label>
            </div>
        </div>
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Use WordPress Submenu for GI-Toolkit modules settings', 'gi-toolkit' ); ?></div>
    </div>
	<div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description"><?php echo esc_html_e( 'When enabled, each GI-Toolkit module will have its own submenu under the main GI-Toolkit menu in the WordPress admin dashboard.', 'gi-toolkit' ); ?></div>
    </div>
	
	<div class="gi-toolkit__body__sections__item__space"></div>

	<div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Regenerate module assets', 'gi-toolkit' ); ?></div>
    </div>
	<div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description"><?php echo esc_html_e( 'Force the regeneration of module assets by triggering the activation and deactivation hooks. This can help resolve issues with missing or outdated module files.', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__bottom">
        <button class="gi-toolkit__body__sections__item__btn" type="button" id="JS-gi_toolkit_regenerate_assets_btn" >
            <span class="button-loader">
                <?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/loader.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            </span>
            <span class="button-success" style="display: none;">
                <?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/check.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            </span>
            <span class="button-text"><?php esc_html_e( 'Regenerate', 'gi-toolkit' ); ?></span>
        </button>
    </div>

    <div class="gi-toolkit__body__sections__item__space"></div>

    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Copy system information', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description"><?php echo esc_html_e( 'Copy detailed system information to your clipboard for support purposes. This includes PHP version, WordPress configuration, active plugins, themes, and GI-Toolkit module settings.', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__bottom">
        <button class="gi-toolkit__body__sections__item__btn" type="button" id="JS-gi_toolkit_copy_system_info_btn" >
            <span class="button-copy">
                <?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            </span>
            <span class="button-loader" style="display: none;">
                <?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/loader.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            </span>
            <span class="button-success" style="display: none;">
                <?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/check.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            </span>
            <span class="button-text"><?php esc_html_e( 'Copy System Info', 'gi-toolkit' ); ?></span>
        </button>
    </div>
</div>
