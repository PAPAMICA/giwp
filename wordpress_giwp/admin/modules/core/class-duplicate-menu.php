<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Duplicate Menu
 * Description: 
 * @since 1.3.0
 */
class Gi_Toolkit_Duplicate_Menu {

    const ACTION = 'gi_toolkit-duplicate-menu-action';
    const NONCE  = 'gi_toolkit-duplicate-menu-nonce';

    private $header_title;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'duplicate_menu' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Duplicate Menu', 'gi-toolkit' );
    }

    /**
     * Duplicate the menu
     */
    public function duplicate_menu() {
        gi_toolkit_ajax_require_cap( 'edit_theme_options', self::NONCE );

        $source      = intval( sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ) );
        $destination = sanitize_text_field( wp_unslash( $_POST['destination'] ?? '' ) );
        if ( empty( $source ) || empty( $destination ) ) {
            wp_send_json_error( array( 'message' => __( 'Please select a menu and enter a name for the new menu.', 'gi-toolkit' ) ) );
        }

        $destination_id = wp_create_nav_menu( $destination );
        if ( is_wp_error( $destination_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Please select a menu and enter a name for the new menu.', 'gi-toolkit' ) ) );
        }

        $source_items = wp_get_nav_menu_items( $source );
        if ( ! is_array( $source_items ) ) {
            wp_send_json_error( array( 'message' => __( 'Please select a menu and enter a name for the new menu.', 'gi-toolkit' ) ) );
        }
        
        $ids_array = array();
        foreach ( $source_items as $item ) {
            
            $args = array(
                'menu-item-db-id'       => $item->db_id,
                'menu-item-object-id'   => $item->object_id,
                'menu-item-object'      => $item->object,
                'menu-item-type'        => $item->type,
                'menu-item-title'       => $item->title,
                'menu-item-url'         => $item->url,
                'menu-item-description' => $item->description,
                'menu-item-attr-title'  => $item->attr_title,
                'menu-item-target'      => $item->target,
                'menu-item-classes'     => implode( ' ', $item->classes ),
                'menu-item-xfn'         => $item->xfn,
                'menu-item-status'      => $item->post_status,
            );

            if ( $item->menu_item_parent ) {
                $args['menu-item-parent-id'] = $ids_array[$item->menu_item_parent] ?? 0;
            }

            $new_item_id = wp_update_nav_menu_item( $destination_id, 0, $args );

            if ( is_wp_error( $new_item_id ) ) {
                continue;
            }

            $ids_array[ $item->db_id ] = $new_item_id;
        }

        wp_send_json_success( array( 'message' => __( 'Menu duplicated successfully.', 'gi-toolkit' ) ) );
    }

    /**
     * Add a submenu
     *
     */
    public function add_submenu(){

        Gi_Toolkit_Settings::add_submenu_page(
            'gi-toolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'gi-toolkit-settings-duplicate-menu',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/duplicate-menu.asset.php' );
        wp_enqueue_style( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/duplicate-menu.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/duplicate-menu.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        wp_localize_script( 'Gi_Toolkit_submenu', 'gi_toolkitDuplicateMenu', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => self::ACTION,
            'nonce'   => wp_create_nonce( self::NONCE ),
        ));

        include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        
        $nav_menus = wp_get_nav_menus();

        ?>
            <div class="gi-toolkit__section">
                <div class="gi-toolkit__section__desc"><?php esc_html_e('Easily duplicate your WordPress Menus.', 'gi-toolkit'); ?></div>
                <div class="gi-toolkit__section__notice" id="gi_toolkit-duplicate-menu-notice">
                    <p class="gi-toolkit__section__notice__message"></p>
                </div>
                <div class="gi-toolkit__section__body">
                    <?php if ( empty( $nav_menus ) ): ?>
                        <div class="gi-toolkit__section__body__item">
                            <div class="gi-toolkit__section__body__item__content">
                                <p><?php esc_html_e( 'No menus found.', 'gi-toolkit' ); ?></p>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="gi-toolkit__section__body__item">
                            <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Select Menu', 'gi-toolkit' ); ?></div>
                            <div class="gi-toolkit__section__body__item__content">
                                <div class="gi-toolkit__select">
                                    <select id="gi-toolkit-duplicate-menu-source">
                                        <?php foreach ( $nav_menus as $nav_menu ) : ?>
                                            <option value="<?php echo esc_attr( $nav_menu->term_id ); ?>"><?php echo esc_html( $nav_menu->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="gi-toolkit__section__body__item">
                            <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Enter duplicate Menu name', 'gi-toolkit' ); ?></div>
                            <div class="gi-toolkit__section__body__item__content">
                                <div class="gi-toolkit__input-text">
                                    <input type="text" id="gi-toolkit-duplicate-menu-destination">
                                </div>
                            </div>
                        </div>
                        <div class="gi-toolkit__section__body__item">
                            <div class="gi-toolkit__section__body__item__content">
                                <input type="button" id="gi-toolkit-duplicate-menu-btn" class="button button-primary" value="Duplicate Menu">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php
    }
}
