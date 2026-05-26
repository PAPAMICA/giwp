<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Log In/Out Menu
 * Description: Enable log in, log out and dynamic log in/out menu item for addition to any menu.
 * @since 1.4.0
 */
class Gi_Toolkit_Login_Logout_Menu {

	/**
	 * Invoke the hooks.
	 * 
	 * @since   1.4.0
	 */
	public function __construct() {
		add_action( 'admin_head-nav-menus.php', array( $this, 'add_menu_metabox' ) );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'add_url_to_item' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'maybe_remove_item' ) );
	}

	/**
	 * Add metabox to Appearance >> Menus page.
	 *
	 * @since 1.4.0
	 */
	public function add_menu_metabox() {
		add_meta_box(
			'gi_toolkit-login-logout',
			'Log In / Log Out',
			array( $this, 'render_login_logout_metabox' ),
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Render login logout metabox.
	 * 
	 * @since 1.4.0
	 */
	public function render_login_logout_metabox() {
        global $nav_menu_selected_id;

		$menu_items = array(
            'gi_toolkit-login' => array(
                'title'   => __( 'Log In', 'gi-toolkit' ),
                'url'     => '#gi_toolkit-login',
                'classes' => array( 'gi_toolkit-login-menu-item' ),
            ),
            'gi_toolkit-logout' => array(
                'title'   => __( 'Log Out', 'gi-toolkit' ),
                'url'     => '#gi_toolkit-logout',
                'classes' => array( 'gi_toolkit-logout-menu-item' ),
            ),
            'gi_toolkit-login-logout' => array(
                'title'     => __( 'Log In / Log Out', 'gi-toolkit' ),
                'url'       => '#gi_toolkit-login-logout',
                'classes'   => array( 'gi_toolkit-login-logout-menu-item' ),
            ),
        );

		$item_details = array(
            'db_id'            => 0,
            'object'           => 'gi-toolkit',
            'object_id'        => '',
            'menu_item_parent' => 0,
            'type'             => 'custom',
            'title'            => '',
            'url'              => '',
            'target'           => '',
            'attr_title'       => '',
            'classes'          => array(),
            'xfn'              => '',
        );

        $menu_items_object = array();

		foreach ( $menu_items as $item_id => $details ) {
            $menu_items_object[ $details['title'] ]            = (object) $item_details;
            $menu_items_object[ $details['title'] ]->object_id = $item_id;
            $menu_items_object[ $details['title'] ]->title     = $details['title'];
            $menu_items_object[ $details['title'] ]->classes   = $details['classes'];
            $menu_items_object[ $details['title'] ]->url       = $details['url'];
        }

        $walker = new Walker_Nav_Menu_Checklist( array() );

		?>
        <div id="gi_toolkit-login-logout" class="posttypediv">
            <div id="tabs-panel-gi_toolkit-login-logout" class="tabs-panel tabs-panel-active">
				<ul id="gi_toolkit-login-logout-checklist" class="categorychecklist form-no-clear">
					<?php
						echo walk_nav_menu_tree( 
							array_map( 'wp_setup_nav_menu_item', $menu_items_object ),
							0,
							(object) array( 'walker' => $walker)
						);
					?>
				</ul>
            </div>
			<p class="button-controls wp-clearfix" data-items-type="gi_toolkit-login-logout">
				<span class="list-controls hide-if-no-js">
					<input type="checkbox" id="page-tab" class="select-all">
					<label for="page-tab"><?php echo esc_html_e( 'Select All', 'gi-toolkit' ) ?></label>
				</span>
				<span class="add-to-menu">
					<input type="submit" class="button submit-add-to-menu right" value="<?php echo esc_attr_e( 'Add to Menu', 'gi-toolkit' ) ?>" name="gi_toolkit-login-logout" id="submit-gi_toolkit-login-logout" <?php disabled( $nav_menu_selected_id, 0 ) ?>>
					<span class="spinner"></span>
				</span>
			</p>
        </div>
        <?php
	}

	/**
	 * Add URL to item.
	 * 
	 * @since 1.4.0
	 */
	public function add_url_to_item( $menu_item ) {
		global $pagenow;

		if ( 'nav-menus.php' != $pagenow && ! defined( 'DOING_AJAX' ) && isset( $menu_item->url ) && false !== strpos( $menu_item->url, 'gi-toolkit' ) ) {
			$db_options     = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
			$move_login_url = isset( $db_options['Gi_Toolkit_Move_Login_URL'] ) ? $db_options['Gi_Toolkit_Move_Login_URL'] : '0';

			if ( '1' == $move_login_url && class_exists( 'Gi_Toolkit_Move_Login_URL' ) ) {
				$class_move_login_url = new Gi_Toolkit_Move_Login_URL();
				$settings             = $class_move_login_url->get_settings();
				$login_url            = get_site_url() . '/' . $settings['login_slug'];
			} else {
				$login_url = wp_login_url();
			}

			switch( $menu_item->url ) {
				case '#gi_toolkit-login';
					$menu_item->url = $login_url;
					break;
				case '#gi_toolkit-logout';
					$menu_item->url = wp_logout_url();
					break;
				case '#gi_toolkit-login-logout';
					$menu_item->url   = ( is_user_logged_in() ) ? wp_logout_url() : $login_url;
					$menu_item->title = ( is_user_logged_in() ) ? __( 'Log Out', 'gi-toolkit' ) : __( 'Log In', 'gi-toolkit' );
					break;
			}
		}

		return $menu_item;
	}

	/**
	 * Remove login or logout menu item based on is_user_logged_in()
	 * 
	 * @since 1.4.0
	 */
	public function maybe_remove_item( $sorted_menu_items ) {
		foreach( $sorted_menu_items as $menu => $item ) {
			$item_classes = $item->classes;
			if ( in_array( 'gi_toolkit-login-menu-item', $item_classes ) ) {
                if ( is_user_logged_in() ) {
                    unset( $sorted_menu_items[$menu] );
                }
            }
            if ( in_array( 'gi_toolkit-logout-menu-item', $item_classes ) ) {
                if ( ! is_user_logged_in() ) {
                    unset( $sorted_menu_items[$menu] );
                }
            }
		}

		return $sorted_menu_items;
	}

	/**
     * Run on option deactivation.
	 * 
	 * @since 1.4.0
     */
    public static function deactivate(){

		$post_args = array(
			'numberposts'  => -1,
			'post_type'    => 'nav_menu_item',
			'fields'       => 'ids',
			'meta_key'     => '_menu_item_url',//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => 'gi-toolkit',//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => 'REGEXP',
		);
		$menu_items = get_posts( $post_args );

		foreach ( $menu_items as $menu_item_id ) {
			wp_delete_post( $menu_item_id, true );
		}
    }
}
