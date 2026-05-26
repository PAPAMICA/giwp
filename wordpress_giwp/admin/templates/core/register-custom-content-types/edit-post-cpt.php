<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $post;
$gi_toolkit_title        = get_the_title( $post->ID );
$gi_toolkit_settings     = $this->get_settings_cpt( $post->ID );
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
                <?php esc_html_e( 'If you want to activate the content type, you need to check the status.', 'gi-toolkit' ); ?>
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
                <div class="description"><strong><?php esc_html_e( 'Taxonomies', 'gi-toolkit' ); ?></strong></div>
                <select class="js-multiselect" name="<?php echo esc_attr( $this->content_type_settings . '[taxonomies]' ); ?>[]" multiple>
                    <?php
                    $gi_toolkit_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
                    foreach ( $gi_toolkit_taxonomies as $taxonomy ) {
                        ?>
                        <option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( in_array( $taxonomy->name, $gi_toolkit_settings['taxonomies'] ?? array() ) ); ?>>
                            <?php echo esc_html( $taxonomy->label . " (" . $taxonomy->name . ")" ); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'Select existing taxonomies to associate with this content type.', 'gi-toolkit' ); ?>
            </div>
        </div>
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__select2">
                <div class="description"><strong><?php esc_html_e( 'Supports', 'gi-toolkit' ); ?></strong></div>
                <select class="js-multiselect-tags" name="<?php echo esc_attr( $this->content_type_settings . '[supports]' ); ?>[]" multiple>
                    <?php
                    $gi_toolkit_supports = array(
                        'title'           => esc_html__( 'Title', 'gi-toolkit' ),
                        'editor'          => esc_html__( 'Editor', 'gi-toolkit' ),
                        'author'          => esc_html__( 'Author', 'gi-toolkit' ),
                        'thumbnail'       => esc_html__( 'Thumbnail', 'gi-toolkit' ),
                        'excerpt'         => esc_html__( 'Excerpt', 'gi-toolkit' ),
                        'comments'        => esc_html__( 'Comments', 'gi-toolkit' ),
                        'revisions'       => esc_html__( 'Revisions', 'gi-toolkit' ),
                        'page-attributes' => esc_html__( 'Page Attributes', 'gi-toolkit' ),
                        'custom-fields'   => esc_html__( 'Custom Fields', 'gi-toolkit' ),
                    );
                    $gi_toolkit_new_tag = $gi_toolkit_settings['supports'] ?? array();
                    if ( !empty( $gi_toolkit_new_tag ) && is_array( $gi_toolkit_new_tag ) ) {
                        foreach ( $gi_toolkit_new_tag as $tag ) {
                            if ( !array_key_exists( $tag, $gi_toolkit_supports ) ) {
                                $gi_toolkit_supports[$tag] = $tag;
                            }
                        }
                    }
                    foreach ( $gi_toolkit_supports as $gi_toolkit_key => $gi_toolkit_value ) {
                        ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_key ); ?>" <?php selected( in_array( $gi_toolkit_key, $gi_toolkit_settings['supports'] ?? array() ) ); ?>>
                            <?php echo esc_html( $gi_toolkit_value ); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'Select the features you want to enable for this content type. You can also create new features.', 'gi-toolkit' ); ?>
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

        <?php foreach( $this->get_cpt_labels('required') as $gi_toolkit_key => $gi_toolkit_data ) : ?>
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $gi_toolkit_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $gi_toolkit_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input 
                    type="text" 
                    name="<?php echo esc_attr( $this->content_type_settings . '[' . $gi_toolkit_key . ']' ); ?>" 
                    value="<?php echo esc_attr( $gi_toolkit_settings[$gi_toolkit_key] ?? '' ); ?>" 
                    placeholder="<?php echo esc_attr( $gi_toolkit_data['placeholder'] ?? '' ); ?>" 
                    <?php echo esc_attr( !empty( $gi_toolkit_data['required'] ) ? 'required' : '' ); ?>
                    <?php
                    if ( !empty( $gi_toolkit_data['custom-attributes'] ) && is_array( $gi_toolkit_data['custom-attributes'] ) ) {
                        foreach ( $gi_toolkit_data['custom-attributes'] as $gi_toolkit_attr => $gi_toolkit_value ) {
                            echo esc_attr( $gi_toolkit_attr ) . '="' . esc_attr( $gi_toolkit_value ) . '" ';
                        }
                    }
                    ?>
                
                >
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
        
        <?php foreach( $this->get_cpt_labels('optional') as $gi_toolkit_key => $gi_toolkit_data ) : ?>
        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[manage_optional_labels]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php echo esc_html( $gi_toolkit_data['label'] ?? '' ); ?> <?php echo wp_kses_post( !empty( $gi_toolkit_data['required'] ) ? '<span class="required">*</span>' : '' ); ?></strong>
                </div>
                <input 
                    type="text" 
                    name="<?php echo esc_attr( $this->content_type_settings . '[' . $gi_toolkit_key . ']' ); ?>" 
                    value="<?php echo esc_attr( $gi_toolkit_settings[$gi_toolkit_key] ?? '' ); ?>" 
                    placeholder="<?php echo esc_attr( $gi_toolkit_data['placeholder'] ?? '' ); ?>" 
                    <?php echo esc_attr( !empty( $gi_toolkit_data['required'] ) ? 'required' : '' ); ?>
                    <?php
                    if ( !empty( $gi_toolkit_data['custom-attributes'] ) && is_array( $gi_toolkit_data['custom-attributes'] ) ) {
                        foreach ( $gi_toolkit_data['custom-attributes'] as $gi_toolkit_attr => $gi_toolkit_value ) {
                            echo esc_attr( $gi_toolkit_attr ) . '="' . esc_attr( $gi_toolkit_value ) . '" ';
                        }
                    }
                    ?>
                >
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

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Admin Menu Parent', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[admin_menu_parent]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['admin_menu_parent'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'edit.php?post_type={parent_page}', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the menu parent slug. Example: "edit.php?post_type=page".', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Admin Menu Position', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="number" name="<?php echo esc_attr( $this->content_type_settings . '[menu_position]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['menu_position'] ?? 5 ); ?>" placeholder="<?php esc_html_e( '5', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the menu position. Example: "5".', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['use_dashicon'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Use Dashicon", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'Check this if you want to use a dashicon for the menu item. If unchecked, the menu item will use custom icon URL.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>=1">
            <div class="gi-toolkit__select-dashicon">
                <div class="description">
                    <strong><?php esc_html_e( 'Menu Icon', 'gi-toolkit' ); ?></strong>
                </div>
                <select class="js-select-dashicon" name="<?php echo esc_attr( $this->content_type_settings . '[menu_icon]' ); ?>">
                    <?php foreach( Gi_Toolkit_Select_Dashicon::get_dashicons() as $gi_toolkit_class => $gi_toolkit_label ) : ?>
                        <option value="<?php echo esc_attr( $gi_toolkit_class ); ?>" <?php selected( $gi_toolkit_settings['menu_icon'] ?? 'dashicons-admin-post', $gi_toolkit_class ); ?>>
                            <?php echo esc_html( $gi_toolkit_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="description">
                <?php esc_html_e( 'The icon for the menu item.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_ui]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[show_in_menu]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[use_dashicon]' ); ?>=0">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Custom Icon URL', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[custom_menu_icon]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['custom_menu_icon'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a URL', 'gi-toolkit' ); ?>">
            </div>
            <div class="description">
                <?php esc_html_e( 'Enter the URL of the custom icon. Example: "https://example.com/icon.png". Ideally, the icon should be 20x20 pixels.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_admin_bar]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[show_in_admin_bar]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['show_in_admin_bar'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Show In Admin Bar", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be visible in the admin bar.', 'gi-toolkit' ); ?>
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
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[exclude_from_search]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[exclude_from_search]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['exclude_from_search'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Exclude From Search", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will be excluded from search results.', 'gi-toolkit' ); ?>
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
                    <option value="post_type_key" <?php selected( $gi_toolkit_settings['permalink_rewrite'] ?? 'post_type_key', 'post_type_key' ); ?>><?php esc_html_e( 'Post Type Key (default)', 'gi-toolkit' ); ?></option>
                    <option value="custom_permalink" <?php selected( $gi_toolkit_settings['permalink_rewrite'] ?? 'post_type_key', 'custom_permalink' ); ?>><?php esc_html_e( 'Custom Permalink', 'gi-toolkit' ); ?></option>
                    <option value="no_permalink" <?php selected( $gi_toolkit_settings['permalink_rewrite'] ?? 'post_type_key', 'no_permalink' ); ?>><?php esc_html_e( 'No permalink (Prevent URL rewriting)', 'gi-toolkit' ); ?></option>
                </select>
            </div>
            <div class="description" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[permalink_rewrite]' ); ?>=post_type_key">
                <?php esc_html_e( 'Rewrite the URL using the post type key as the slug. Your permalink structure will be {slug}.', 'gi-toolkit' ); ?>
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

        <div class="gi-toolkit__section__body__item">
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
        
        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[feeds]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[feeds]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['feeds'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Feed URL", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'RSS feed URL for the post type items.', 'gi-toolkit' ); ?>
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
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[has_archive]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[has_archive]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['has_archive'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Archive", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, the content type will have an archive page.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[has_archive]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Archive Slug', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[archive_slug]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['archive_slug'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the archive slug. Example: "portfolio".', 'gi-toolkit' ); ?>
                </div>
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
                    <option value="post_type_key" <?php selected( $gi_toolkit_settings['query_var'] ?? '', 'post_type_key' ); ?>><?php esc_html_e( 'Post Type Key (default)', 'gi-toolkit' ); ?></option>
                    <option value="custom_query_var" <?php selected( $gi_toolkit_settings['query_var'] ?? '', 'custom_query_var' ); ?>><?php esc_html_e( 'Custom Query Variable', 'gi-toolkit' ); ?></option>
                    <option value="none" <?php selected( $gi_toolkit_settings['query_var'] ?? '', 'none' ); ?>><?php esc_html_e( 'No Query Variable', 'gi-toolkit' ); ?></option>
                </select>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[publicly_queryable]' ); ?>=1&<?php echo esc_attr( $this->content_type_settings . '[query_var]' ); ?>=custom_query_var">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Query Variable Name', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[query_var_name]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['query_var_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the query variable name. Example: "portfolio".', 'gi-toolkit' ); ?>
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
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['rename_capabilities'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Rename Capabilities", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( "By default the capabilities of the post type will inherit the 'Post' capability names, eg. edit_post, delete_posts. Enable to use post type specific capabilities, eg. edit_{singular}, delete_{plural}.", 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Singular Capability Name', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[singular_capability_name]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['singular_capability_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the singular capability name. Example: "portfolio".', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[rename_capabilities]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Plural Capability Name', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[plural_capability_name]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['plural_capability_name'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'Enter a slug', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Enter the plural capability name. Example: "portfolios".', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[can_export]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[can_export]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['can_export'] ?? 1, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Can Export", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( "Allow the post type to be exported from 'Tools' > 'Export'.", 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item">
            <div class="gi-toolkit__section__body__item__title activable">
                
                <div>
                    <label class="gi-toolkit__toggle">
                        <input type="hidden" name="<?php echo esc_attr( $this->content_type_settings . '[delete_with_user]' ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( $this->content_type_settings . '[delete_with_user]' ); ?>" value="1" <?php checked( $gi_toolkit_settings['delete_with_user'] ?? 0, 1 ); ?>>
                        <span class="gi-toolkit__toggle__slider round"></span>
                    </label>
                </div>
                <div>
                    <?php esc_html_e( "Delete with User", 'gi-toolkit' ); ?>
                </div>
            </div>
            <div class="description">
                <?php esc_html_e( 'If checked, items of this content type will be deleted when the user is deleted.', 'gi-toolkit' ); ?>
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
                <?php esc_html_e( 'If checked, the content type will be available in the REST API. Note if you want use Gutenberg editor, you need to enable this option.', 'gi-toolkit' ); ?>
            </div>
        </div>

        <div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->content_type_settings . '[show_in_rest]' ); ?>=1">
            <div class="gi-toolkit__input-text">
                <div class="description">
                    <strong><?php esc_html_e( 'Base URL', 'gi-toolkit' ); ?></strong>
                </div>
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_base]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['rest_base'] ?? '' ); ?>" placeholder="<?php esc_html_e( 'post', 'gi-toolkit' ); ?>">
                
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
                <input type="text" name="<?php echo esc_attr( $this->content_type_settings . '[rest_controller_class]' ); ?>" value="<?php echo esc_attr( $gi_toolkit_settings['rest_controller_class'] ?? 'WP_REST_Posts_Controller' ); ?>" placeholder="<?php esc_html_e( 'WP_REST_Posts_Controller', 'gi-toolkit' ); ?>">
                
                <div class="description">
                    <?php esc_html_e( 'Optional custom controller to use instead of `WP_REST_Posts_Controller`.', 'gi-toolkit' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>