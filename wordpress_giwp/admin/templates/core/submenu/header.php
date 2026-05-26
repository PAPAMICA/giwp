<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The submenu header
 * 
 * @since      1.0.0
 */
?>

<div class="wrap gi-toolkit">

	<?php if ( ! isset($this->disable_form ) ) : ?>
    <form action="" method="post" enctype="multipart/form-data">
	<?php endif; ?>
        
        <header class="gi-toolkit__header">
            
            <div class="gi-toolkit__header__left">
                <div class="gi-toolkit__header__left__logo">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                    <img src="<?php echo esc_url( GI_TOOLKIT_PLUGIN_URL . 'admin/images/logo.png' ); ?>" alt="<?php esc_html_e('GI-Toolkit', 'gi-toolkit'); ?>" />
                </div>
            </div>
            
            <div class="gi-toolkit__header__center">
                <div class="gi-toolkit__header__left__title"><?php echo esc_html($this->header_title) ?></div>
            </div>

            <div class="gi-toolkit__header__right">

            <?php if ( isset($this->nonce_action) && ( ! isset($this->disable_save_form) || empty($this->disable_save_form) ) ) : ?>
                <div class="gi-toolkit__header__right__save">
                    <?php
                        wp_nonce_field( $this->nonce_action );
                        submit_button( esc_html__('Save', 'gi-toolkit') );
                    ?>
                </div>
            <?php endif; ?>

            </div>
            
        </header>