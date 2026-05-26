<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin settings of the plugin.
 * @since      1.0.0
 */

global $gi_toolkit_module_settings_submenu_pages;

$gi_toolkit_modules           = gi_toolkit_options();
$gi_toolkit_order             = array_column( $gi_toolkit_modules, 'name' );
array_multisort( $gi_toolkit_order, SORT_ASC, $gi_toolkit_modules );
?>

<div class="wrap gi-toolkit">

    <form action="" method="post" enctype="multipart/form-data" >

        <header class="gi-toolkit__header">
    
            <div class="gi-toolkit__header__left">

                <div class="gi-toolkit__header__left__logo">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                    <img height="51" src="<?php echo esc_url( GI_TOOLKIT_PLUGIN_URL . 'admin/images/icon-128x128.gif' ); ?>" alt="<?php esc_html_e('GI-Toolkit', 'gi-toolkit'); ?>" />    
                </div>

                <div class="gi-toolkit__header__left__title">
                    <?php esc_html_e( 'GI-Toolkit', 'gi-toolkit' ); ?>
                    <div class="gi-toolkit__header__left__title__version">
                        <?php esc_html_e( 'Version', 'gi-toolkit' ); ?> <?php echo esc_html( GI_TOOLKIT_VERSION ); ?> - <a href="#" class="gi-toolkit__header__left__title__version__open-modal-button"><?php esc_html_e("What's new?", 'gi-toolkit'); ?></a>
                    </div>
                </div>

            </div>

            <div class="gi-toolkit__header__right">

                <div class="gi-toolkit__header__right__search">
                    <input type="text" placeholder="<?php esc_html_e('Search', 'gi-toolkit'); ?>" >
                    <span class="loop">
						<?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/loop.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
                    </span>
                </div>

                <div class="gi-toolkit__header__right__save">
                    <?php
                        wp_nonce_field( GI_TOOLKIT_PLUGIN_SETTINGS . '_action' );
                        submit_button( esc_html__('Save', 'gi-toolkit') );
                    ?>
                </div>

            </div>
            
        </header>

        <div class="gi-toolkit__body">

            <div class="gi-toolkit__body__groups">

                <?php foreach ( gi_toolkit_settings_groups() as $gi_toolkit_group_key => $gi_toolkit_group_data ) : ?>

                    <?php

                        $gi_toolkit_has_items           = false;
                        $gi_toolkit_is_exception        = $gi_toolkit_group_data['exception'] ?? false;
						$gi_toolkit_show_counter        = ! $gi_toolkit_is_exception;
						$gi_toolkit_counter             = 0;
						$gi_toolkit_counter_activated   = 0;
                        if ( $gi_toolkit_is_exception ) {
                            $gi_toolkit_has_items = true;

							if ( $gi_toolkit_group_key === 'all' ) {
								$gi_toolkit_show_counter = true;
								$gi_toolkit_counter      = count( $gi_toolkit_modules );
							}

                        } else {
                            foreach ( $gi_toolkit_modules as $gi_toolkit_option_key => $gi_toolkit_option_data ) {
                                if ( $gi_toolkit_option_data['group'] === $gi_toolkit_group_key ) {
									$gi_toolkit_counter++;
                                    $gi_toolkit_has_items = true;
                                }
								$gi_toolkit_checked = isset( $db_options[$gi_toolkit_option_key] ) && $db_options[$gi_toolkit_option_key] === '1';
								if ( $gi_toolkit_checked ) {
									$gi_toolkit_counter_activated++;
								}

                            }

							if ( $gi_toolkit_group_key === 'activated' && $gi_toolkit_counter_activated > 0 ) {
								$gi_toolkit_has_items = true;
								$gi_toolkit_counter   = $gi_toolkit_counter_activated;
							}
                        }

                        if ( ! $gi_toolkit_has_items ) {
                            continue;
                        }
                    ?>

                    <div class="gi-toolkit__body__groups__item" data-key="<?php echo esc_attr($gi_toolkit_group_key); ?>" >
                        <span class="logo">
                            <?php
                                if ( isset($gi_toolkit_group_data['logo']) && file_exists(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/' . $gi_toolkit_group_data['logo'] ) ) {
									echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/' . $gi_toolkit_group_data['logo'] ), gi_toolkit_allowed_tags_for_svg_files() );
                                }
                            ?>
                        </span>
                        <?php echo esc_html($gi_toolkit_group_data['name'] ?? ''); ?>
						<?php if ( $gi_toolkit_show_counter ): ?>
							<span class="counter"><?php echo esc_html($gi_toolkit_counter); ?></span>
						<?php endif; ?>
                    </div>

                <?php endforeach; ?>

            </div>

            <div class="gi-toolkit__body__sections">

                <?php
                $gi_toolkit_high_risk = Gi_Toolkit_Security::high_risk_modules();
                foreach ( $gi_toolkit_modules as $gi_toolkit_option_key => $gi_toolkit_option_data ) :
                        $gi_toolkit_option_path     = $gi_toolkit_option_data['path'] ?? '';
                        $gi_toolkit_coming_soon     = $gi_toolkit_option_data['coming_soon'] ?? false;
                        $gi_toolkit_is_addon_module = false;

                        /**
                         * Check if is relative path in plugin folder.
                         */
                        if ( strpos( $gi_toolkit_option_path, 'pro/' ) === 0 || strpos( $gi_toolkit_option_path, 'core/' ) === 0 ) {
                            $gi_toolkit_file_exist = (bool) gi_toolkit_resolve_module_path( $gi_toolkit_option_path );
                        } else {
                            $gi_toolkit_is_addon_module = true;
                            $gi_toolkit_file_exist     = is_file( $gi_toolkit_option_path );
                        }
                        $gi_toolkit_disabled   = $gi_toolkit_file_exist ? false : true;
                        $gi_toolkit_checked    = isset($db_options[$gi_toolkit_option_key]) && $db_options[$gi_toolkit_option_key] === '1' && !$gi_toolkit_disabled;
                ?>
                    <div class="gi-toolkit__body__sections__item module-item <?php echo esc_attr( $gi_toolkit_checked ? 'activated' : '' ); ?> <?php echo esc_attr( $gi_toolkit_disabled ? 'disabled' : '' ); ?>" data-key="<?php echo esc_attr($gi_toolkit_option_data['group'] ?? ''); ?><?php echo $gi_toolkit_checked ? ' activated' : ''; ?>" data-title="<?php echo esc_attr($gi_toolkit_option_data['name'] ?? ''); ?>" data-originaltitle="<?php echo esc_attr($gi_toolkit_option_data['original_name'] ?? ''); ?>"<?php echo in_array( $gi_toolkit_option_key, $gi_toolkit_high_risk, true ) ? ' data-high-risk="1"' : ''; ?>>
                        <div class="gi-toolkit__body__sections__item__top module-header">
                            <div class="gi-toolkit__body__sections__item__title">
                                <span class="gi-toolkit__body__sections__item__title__text">
                                    <?php echo esc_html($gi_toolkit_option_data['name'] ?? ''); ?>
                                </span>
                                <span class="gi-toolkit__body__sections__item__title__tags">
                                    <?php if ( $gi_toolkit_coming_soon ): ?>
                                        <span class="comming-soon"><?php esc_html_e('coming soon', 'gi-toolkit'); ?></span>
                                    <?php endif; ?>
									<?php if ( !$gi_toolkit_is_addon_module && !$gi_toolkit_coming_soon ): ?>
										<a class="documentation" href="https://genevois-informatique.com/?module_documentation=<?php echo esc_attr( $gi_toolkit_option_key ); ?>" target="_blank"><?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/images/documentation-icon.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?><?php esc_html_e('DOC', 'gi-toolkit'); ?></a>
									<?php endif; ?>
									<?php if ( is_array($gi_toolkit_module_settings_submenu_pages) && isset($gi_toolkit_module_settings_submenu_pages[$gi_toolkit_option_key]) ): ?>
										<a class="module-settings" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $gi_toolkit_module_settings_submenu_pages[$gi_toolkit_option_key] ) ); ?>"><?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/gear.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?><?php esc_html_e('SETTINGS', 'gi-toolkit'); ?></a>
									<?php endif; ?>
                                </span>
                            </div>
                            <div class="gi-toolkit__body__sections__item__toggle">
                                <label class="gi-toolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( GI_TOOLKIT_PLUGIN_SETTINGS . '[' .  $gi_toolkit_option_key . ']'); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr(GI_TOOLKIT_PLUGIN_SETTINGS . '[' . $gi_toolkit_option_key . ']'); ?>" value="1" <?php echo esc_attr( checked($gi_toolkit_checked, true, false) ); ?> <?php echo esc_attr( $gi_toolkit_disabled ? 'disabled' : '' ); ?>>
                                    <span class="gi-toolkit__toggle__slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="gi-toolkit__body__sections__item__bottom">
                            <div class="gi-toolkit__body__sections__item__description"><?php echo esc_html($gi_toolkit_option_data['desc'] ?? ''); ?></div>
                        </div>
                    </div>

                <?php endforeach; ?>

                <?php include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/settings.php'; ?>

                <?php include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/credentials.php'; ?>

            </div>

        </div>

        <div class="gi-toolkit__save-button">
            <button type="submit">
				<?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/save.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
            </button>
        </div>

    </form>

</div>

<div class="gi-toolkit__changelog-modal">
    <div class="gi-toolkit__changelog-modal__content">
        <div class="gi-toolkit__changelog-modal__content__close"><?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?></div>
        <div>
            <h2><?php echo esc_html(
				sprintf(
					/* translators: %s: GI-Toolkit version */
					__("What's new in v%s", 'gi-toolkit'),
					GI_TOOLKIT_VERSION,
				)); ?>
			</h2>
			<div class="gi-toolkit__changelog-modal__content__body">
				<?php echo do_shortcode( '[gi_toolkit_changelog limit="3"]' ); ?>

				<div class="gi-toolkit__changelog-modal__content__body__link">
					<a href="<?php echo esc_url( __( 'https://genevois-informatique.com/en/changelog/', 'gi-toolkit' ) ); ?>" target="_blank"><?php esc_html_e( 'View all changelogs', 'gi-toolkit' ); ?></a>
				</div>
			</div>
        </div>
    </div>
</div>
