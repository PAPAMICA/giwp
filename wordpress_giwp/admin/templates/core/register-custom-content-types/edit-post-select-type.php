<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>
<hr><br>
<header class="gi-toolkit__header">
    
    <div class="gi-toolkit__header__center">
        <div class="gi-toolkit__header__left__title">
            <?php esc_html_e( 'Choice the content type to create', 'gi-toolkit' ); ?>
        </div>
    </div>

    <div class="gi-toolkit__header__right__save">
        <input type="hidden" name="post_status" value="draft">
        <?php submit_button( esc_html__('Continue', 'gi-toolkit') ); ?>
    </div>
    
</header>

<div class="gi-toolkit__sections-grid cols-3">
    <label class="gi-toolkit__section select-content-type">
        <input type="radio" name="content_type" id="" value="cpt" checked>
        <div>
            <h2>
                <?php esc_html_e( 'Custom Post Type', 'gi-toolkit' ); ?>
            </h2>
            <p>
                <?php esc_html_e( 'Create a custom post type based on the WordPress post type. Example: Portfolio, Testimonials, etc.', 'gi-toolkit' ); ?>
            </p>
        </div>
    </label>
    <label class="gi-toolkit__section select-content-type">
        <input type="radio" name="content_type" id="" value="taxonomy">
        <div>
            <h2>
                <?php esc_html_e( 'Custom Taxonomy', 'gi-toolkit' ); ?>
            </h2>
            <p>
                <?php esc_html_e( 'Create a custom taxonomy based on the WordPress taxonomy. Example: Category, Tags, etc.', 'gi-toolkit' ); ?>
            </p>
        </div>
    </label>
    <label class="gi-toolkit__section select-content-type" style="background-color: #f5f5f5; color: #999;">
        <input type="radio" name="content_type" id="" value="option_page" disabled>
        <div>
            <h2 style="color: #999;">
                <?php esc_html_e( 'Option Page', 'gi-toolkit' ); ?> (<?php esc_html_e( 'Coming soon', 'gi-toolkit' ); ?>)
            </h2>
            <p>
                <?php esc_html_e( 'Create a custom option page based on the WordPress option page. Example: Settings, etc.', 'gi-toolkit' ); ?>
            </p>
        </div>
    </label>
</div>
