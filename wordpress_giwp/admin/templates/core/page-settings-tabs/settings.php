<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The settings tap of the settings page.
 * 
 * @since      1.0.0
 */
?>

<div class="gi-toolkit__body__sections__item hide-in-all" data-key="settings">
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
