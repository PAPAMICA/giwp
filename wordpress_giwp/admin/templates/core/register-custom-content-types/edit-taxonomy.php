<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $post;
$gi_toolkit_title        = get_the_title( $post->ID );
$gi_toolkit_settings     = $this->get_settings_taxonomy( $post->ID );
$gi_toolkit_settings     = is_array( $gi_toolkit_settings ) ? $gi_toolkit_settings : array();
$gi_toolkit_post_status  = get_post_status( $post->ID );
$gi_toolkit_post_status  = $gi_toolkit_post_status == 'draft' && empty( $gi_toolkit_settings['name'] ) ? 'publish' : $gi_toolkit_post_status;

?>
<hr><br>
<header class="gi-toolkit__header gi-toolkit-edit-post-header">
    
    <div class="gi-toolkit__header__center">
        <div class="gi-toolkit__header__left__title">
            <input type="text" name="post_title" value="<?php echo esc_attr( $gi_toolkit_title ); ?>" placeholder="<?php esc_html_e( 'Enter a title', 'gi-toolkit' ); ?>" required>
        </div>
    </div>

    <a class="gi-toolkit__header__submit-delete" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>" onclick="return confirm('<?php esc_html_e( 'Are you sure you want to delete this content type?', 'gi-toolkit' ); ?>');">
        <?php esc_html_e( 'Delete', 'gi-toolkit' ); ?>
    </a>

    <div class="gi-toolkit__header__right__save">
        <?php submit_button( esc_html__('Save', 'gi-toolkit') ); ?>
    </div>
    
</header>

<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">
        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'General', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="post_status" value="draft">
                        <input type="checkbox" name="post_status" value="publish" <?php checked( $gi_toolkit_post_status, 'publish' ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e("Status", 'gi-toolkit'); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If you want to activate this taxonomy, you need to check the status.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                <label class="gi-toolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[public]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[public]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['public'] ?? 1, 1 ); ?>>
                    <span class="gi-toolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Public', 'gi-toolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Visible to the public in the admin menu and on the front end.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                <label class="gi-toolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[hierarchical]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[hierarchical]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['hierarchical'] ?? 0, 1 ); ?>>
                    <span class="gi-toolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Hierarchical', 'gi-toolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be hierarchical like pages. If unchecked, it will be flat like posts.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select2">
                <div class="description"><strong><?php esc_html_e( 'Post Types', 'gi-toolkit' ); ?></strong></div>
                <select class="js-multiselect" name="<?php echo esc_attr( $this->content_type_settings . '[object_type]' ); ?>[]" multiple>
                    <?php
                    $gi_toolkit_cpts = get_post_types( array( 'public' => true ), 'objects' );
                    foreach ( $gi_toolkit_cpts as $gi_toolkit_cpt ) {
                        ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_cpt->name ); ?>" <?php selected( in_array( $gi_toolkit_cpt->name, $gi_toolkit_settings['object_type'] ?? array() ) ); ?>>
                            <?php echo esc_html( $gi_toolkit_cpt->label ); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'One or many post types that can be classified with this taxonomy.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                <label class="gi-toolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[sort]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[sort]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['sort'] ?? 0, 1 ); ?>>
                    <span class="gi-toolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Sort Terms', 'gi-toolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( "Whether terms in this taxonomy should be sorted in the order they are provided to `wp_set_object_terms()`.", 'gi-toolkit' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">
        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Labels', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>

        <?php foreach( $this->get_taxonomy_labels('required') as $gi_toolkit_key => $gi_toolkit_data ) : ?>
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $gi_toolkit_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $gi_toolkit_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[' . $gi_toolkit_key . ']' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings[$gi_toolkit_key] ?? '' ); ?>" placeholder="<?php echo esc_attr( $gi_toolkit_data['placeholder'] ?? '' ); ?>" <?php echo esc_attr( !empty( $gi_toolkit_data['required'] ) ? 'required' : '' ); ?>>
            </div>
            <?php if ( isset( $gi_toolkit_data['description'] ) ) : ?>
            <div class="description">
                <?php echo esc_html( $gi_toolkit_data['description'] ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Text Domain', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[text_domain]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['text_domain'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'your-text-domain', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( "Enter the text domain for translation. Example: 'your-text-domain'.", 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                <label class="gi-toolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['manage_optional_labels'] ?? 0, 1 ); ?>>
                    <span class="gi-toolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Manage Optional Labels', 'gi-toolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, you can manage the optional labels.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <?php foreach( $this->get_taxonomy_labels('optional') as $gi_toolkit_key => $gi_toolkit_data ) : ?>
        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $gi_toolkit_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $gi_toolkit_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[' . $gi_toolkit_key . ']' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings[$gi_toolkit_key] ?? '' ); ?>" placeholder="<?php echo esc_attr( $gi_toolkit_data['placeholder'] ?? '' ); ?>" <?php echo esc_attr( !empty( $gi_toolkit_data['required'] ) ? 'required' : '' ); ?>>
            </div>
            <?php if ( isset( $gi_toolkit_data['description'] ) ) : ?>
            <div class="description">
                <?php echo esc_html( $gi_toolkit_data['description'] ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>


<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">

        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Admin Menu', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_ui'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In UI", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Items can be edited and managed in the admin dashboard.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_in_menu'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In Menu", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be visible in the admin menu.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_nav_menus]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_nav_menus]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_in_nav_menus'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Appearance Menus Support", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( "Allow items to be added to menus in the 'Appearance' > 'Menus' screen. Must be turned on in 'Screen options'.", 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_tagcloud]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_tagcloud]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_tagcloud'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Tag Cloud", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'List the taxonomy in the Tag Cloud Widget controls.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_quick_edit]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_quick_edit]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_in_quick_edit'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Quick Edit", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Show the taxonomy in the quick/bulk edit panel.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_admin_column]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_admin_column]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_admin_column'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show Admin Column", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Display a column for the taxonomy on post type listing screens.', 'gi-toolkit' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">

        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Default Term', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                <label class="gi-toolkit__toggle">
                    <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>" value="0">
                    <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['default_term_enabled'] ?? 0, 1 ); ?>>
                    <span class="gi-toolkit__toggle__slider round"></span>
                </label>
                <div class="description"><strong><?php esc_html_e( 'Default Term', 'gi-toolkit' ); ?></strong></div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Create a term for the taxonomy that cannot be deleted. It will not be selected for posts by default.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Default Term Name', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_name]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['default_term_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Unassigned', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the default term name.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Default Term Slug', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_slug]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['default_term_slug'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'unassigned', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the default term slug.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[default_term_enabled]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Default Term Description', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[default_term_description]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['default_term_description'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Unassigned', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the default term description.', 'gi-toolkit' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">

        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Front end', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Permalink Rewrite', 'gi-toolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>">
                    <option value="taxonomy_key" <?php selected( $gi_toolkit_settings['permalink_rewrite'] ?? 'taxonomy_key', 'taxonomy_key' ); ?>><?php esc_html_e( 'Taxonomy Key (default)', 'gi-toolkit' ); ?></option>
                    <option value="custom_permalink" <?php selected( $gi_toolkit_settings['permalink_rewrite'] ?? 'taxonomy_key', 'custom_permalink' ); ?>><?php esc_html_e( 'Custom Permalink', 'gi-toolkit' ); ?></option>
                    <option value="no_permalink" <?php selected( $gi_toolkit_settings['permalink_rewrite'] ?? 'taxonomy_key', 'no_permalink' ); ?>><?php esc_html_e( 'No permalink (Prevent URL rewriting)', 'gi-toolkit' ); ?></option>
                </select>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=taxonomy_key">
                <?php
					echo esc_html( sprintf(
						/* translators: %s: Home URL */
						__( 'Rewrite the URL using the taxonomy key as the slug. Your permalink structure will be %s/{slug}.', 'gi-toolkit' ),
						home_url()
					) );
				?>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=custom_permalink">
                <?php
					echo esc_html( sprintf(
						/* translators: %s: Home URL */
						__( 'Rewrite the URL using a custom slug defined in the input below. Your permalink structure will be %s/{slug}.', 'gi-toolkit' ),
						home_url()
					) );
				?>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=no_permalink">
                <?php esc_html_e( 'Permalinks for this taxonomy are disabled.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=custom_permalink">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'URL Slug', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[slug]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['slug'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the URL slug. Example: "portfolio".', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>!=no_permalink">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[with_front]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[with_front]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['with_front'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Front URL Prefix", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Alters the permalink structure to add the `WP_Rewrite::$front` prefix to URLs.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>!=no_permalink">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[rewrite_hierarchical]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[rewrite_hierarchical]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['rewrite_hierarchical'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Rewrite Hierarchical", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Parent-child terms in URLs for hierarchical taxonomies.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[pages]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[pages]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['with_front'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Pagination", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Pagination support for the items URLs such as the archives.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['publicly_queryable'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Publicly Queryable", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'URLs for an item and items can be accessed with a query string.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>=1">
            <div class="gi-toolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Query Variable Support', 'gi-toolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>">
                    <option value="taxonomy_key" <?php selected( $gi_toolkit_settings['query_var'] ?? '', 'taxonomy_key' ); ?>><?php esc_html_e( 'Taxonomy Key (default)', 'gi-toolkit' ); ?></option>
                    <option value="custom_query_var" <?php selected( $gi_toolkit_settings['query_var'] ?? '', 'custom_query_var' ); ?>><?php esc_html_e( 'Custom Query Variable', 'gi-toolkit' ); ?></option>
                    <option value="none" <?php selected( $gi_toolkit_settings['query_var'] ?? '', 'none' ); ?>><?php esc_html_e( 'No Query Variable', 'gi-toolkit' ); ?></option>
                </select>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>=taxonomy_key">
                <?php esc_html_e( 'Terms can be accessed using the non-pretty permalink, e.g., {query_var}={term_slug}.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>=custom_query_var">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Query Variable Name', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[query_var_name]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['query_var_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Customize the query variable name.', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">

        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'Permissions', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Manage Terms Capability', 'gi-toolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[manage_terms]' ); ?>">
                   <?php
                    $gi_toolkit_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $gi_toolkit_all_capabilities as $gi_toolkit_capability => $gi_toolkit_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_capability ); ?>" <?php selected( $gi_toolkit_settings['manage_terms'] ?? 'manage_categories', $gi_toolkit_capability ); ?>>
                            <?php 
                            echo esc_html( $gi_toolkit_label ); 
                            if( $gi_toolkit_capability === 'manage_categories' ) {
                                echo ' (' . esc_html__( 'default', 'gi-toolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for managing terms of this taxonomy.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Edit Terms Capability', 'gi-toolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[edit_terms]' ); ?>">
                   <?php
                    $gi_toolkit_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $gi_toolkit_all_capabilities as $gi_toolkit_capability => $gi_toolkit_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_capability ); ?>" <?php selected( $gi_toolkit_settings['edit_terms'] ?? 'manage_categories', $gi_toolkit_capability ); ?>>
                            <?php 
                            echo esc_html( $gi_toolkit_label ); 
                            if( $gi_toolkit_capability === 'manage_categories' ) {
                                echo ' (' . esc_html__( 'default', 'gi-toolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for editing terms of this taxonomy.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Delete Terms Capability', 'gi-toolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[delete_terms]' ); ?>">
                   <?php
                    $gi_toolkit_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $gi_toolkit_all_capabilities as $gi_toolkit_capability => $gi_toolkit_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_capability ); ?>" <?php selected( $gi_toolkit_settings['delete_terms'] ?? 'manage_categories', $gi_toolkit_capability ); ?>>
                            <?php 
                            echo esc_html( $gi_toolkit_label ); 
                            if( $gi_toolkit_capability === 'manage_categories' ) {
                                echo ' (' . esc_html__( 'default', 'gi-toolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for deleting terms of this taxonomy.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select">
                <div class="description">
                    <strong><?php esc_html_e( 'Assign Terms Capability', 'gi-toolkit' ); ?></strong>
                </div>
                <select name="<?php echo esc_attr( $this->content_type_settings . '[assign_terms]' ); ?>">
                   <?php
                    $gi_toolkit_all_capabilities = $this->get_all_wp_capabilities();
                    foreach( $gi_toolkit_all_capabilities as $gi_toolkit_capability => $gi_toolkit_label ) {
                        ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_capability ); ?>" <?php selected( $gi_toolkit_settings['assign_terms'] ?? 'edit_posts', $gi_toolkit_capability ); ?>>
                            <?php 
                            echo esc_html( $gi_toolkit_label ); 
                            if( $gi_toolkit_capability === 'edit_posts' ) {
                                echo ' (' . esc_html__( 'default', 'gi-toolkit' ) . ')';
                            }
                            ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="description">
                <?php esc_html_e( 'The capability name for assigning terms of this taxonomy.', 'gi-toolkit' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="gi-toolkit__section">
    <div class="gi-toolkit__section__body">

        <div class="gi-toolkit__section__body__item">
            <h2>
                <?php esc_html_e( 'REST API', 'gi-toolkit' ); ?>
            </h2>
            <hr>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_in_rest'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In REST API", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be available in the REST API.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Base URL', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_base]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['rest_base'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'The base URL for the post type REST API URLs.', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'REST API Namespace', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_namespace]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['rest_namespace'] ?? 'wp/v2' ); ?>" placeholder="<?php esc_html_e( 'wp/v2', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'The namespace for the post type REST API URLs.', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'REST API Controller Class', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_controller_class]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['rest_controller_class'] ?? 'WP_REST_Terms_Controller' ); ?>" placeholder="<?php esc_html_e( 'WP_REST_Terms_Controller', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Optional custom controller to use instead of `WP_REST_Terms_Controller`.', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>