<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: retire des entrées du menu Mon compte WooCommerce (option gi_toolkit_wc_menu_hide, clés séparées par des virgules).
 */
class Gi_Toolkit_My_Account_Menu_Customizer {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_wc_menu_hide';

	private $page_slug = 'gi-toolkit-settings-wc-account-menu';

	public function __construct() {
		$this->header_title = __( 'My Account Menu Customizer', 'gi-toolkit' );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'filter_items' ), 999 );
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
		$raw = isset( $_POST['gi_toolkit_wc_menu_hide'] ) ? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_wc_menu_hide'] ) ) : '';
		update_option( 'gi_toolkit_wc_menu_hide', $raw, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$raw = get_option( 'gi_toolkit_wc_menu_hide', '' );
		if ( ! is_string( $raw ) ) {
			$raw = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Nécessite WooCommerce. Indiquez les clés des entrées du menu « Mon compte » à masquer, séparées par des virgules (ex. downloads, edit-address).', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_wc_menu_hide"><strong><?php esc_html_e( 'Clés à masquer', 'gi-toolkit' ); ?></strong></label></p>
				<p><input type="text" class="large-text" id="gi_toolkit_wc_menu_hide" name="gi_toolkit_wc_menu_hide" value="<?php echo esc_attr( $raw ); ?>"/></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function filter_items( $items ) {
		$raw = get_option( 'gi_toolkit_wc_menu_hide', '' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $items;
		}
		$keys = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $raw ) ) );
		foreach ( $keys as $k ) {
			if ( $k && isset( $items[ $k ] ) ) {
				unset( $items[ $k ] );
			}
		}
		return $items;
	}
}
