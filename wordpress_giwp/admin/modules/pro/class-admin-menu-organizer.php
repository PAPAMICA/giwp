<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: ordre de menu admin personnalisé (option gi_toolkit_admin_menu_order : slugs séparés par des virgules).
 */
class Gi_Toolkit_Admin_Menu_Organizer {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_admin_menu_order';

	private $page_slug = 'gi-toolkit-settings-admin-menu-order';

	public function __construct() {
		$this->header_title = __( 'Admin Menu Organizer', 'gi-toolkit' );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'menu_order' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'render_submenu' )
		);
	}

	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, $this->nonce_action ) ) {
			return;
		}
		$raw = isset( $_POST['gi_toolkit_admin_menu_order'] ) ? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_admin_menu_order'] ) ) : '';
		update_option( 'gi_toolkit_admin_menu_order', $raw, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$raw = get_option( 'gi_toolkit_admin_menu_order', '' );
		if ( ! is_string( $raw ) ) {
			$raw = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Liste des slugs de menu WordPress dans l’ordre souhaité, séparés par des virgules (ex. index.php, edit.php, upload.php). Les entrées non listées suivent dans l’ordre par défaut.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_admin_menu_order"><strong><?php esc_html_e( 'Ordre des slugs', 'gi-toolkit' ); ?></strong></label></p>
				<p><textarea class="large-text code" rows="4" id="gi_toolkit_admin_menu_order" name="gi_toolkit_admin_menu_order"><?php echo esc_textarea( $raw ); ?></textarea></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function menu_order( $menu_order ) {
		$raw = get_option( 'gi_toolkit_admin_menu_order', '' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $menu_order;
		}
		$custom = array_map( 'trim', explode( ',', $raw ) );
		if ( empty( $custom ) ) {
			return $menu_order;
		}
		if ( ! is_array( $menu_order ) ) {
			return $custom;
		}
		$merged = array_merge( array_intersect( $custom, $menu_order ), array_diff( $menu_order, $custom ) );
		return $merged;
	}
}
