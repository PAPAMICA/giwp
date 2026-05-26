<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: seuls les super-administrateurs peuvent activer / désinstaller des extensions.
 */
class Gi_Toolkit_No_Plugin_Management {

	private $caps = array(
		'activate_plugin',
		'deactivate_plugin',
		'delete_plugin',
		'install_plugins',
		'update_plugins',
		'upload_plugins',
		'edit_plugins',
	);

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_plugin_user_ids';

	private $page_slug = 'gi-toolkit-settings-no-plugin-mgmt';

	public function __construct() {
		$this->header_title = __( 'No Plugin Management', 'gi-toolkit' );
		add_filter( 'map_meta_cap', array( $this, 'map' ), 10, 4 );
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
		$raw = isset( $_POST['gi_toolkit_plugin_user_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_plugin_user_ids'] ) ) : '1';
		update_option( 'gi_toolkit_plugin_user_ids', $raw, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$allowed = get_option( 'gi_toolkit_plugin_user_ids', '1' );
		if ( ! is_string( $allowed ) ) {
			$allowed = '1';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Indiquez les ID utilisateurs autorisés à gérer les extensions (virgules). Par défaut : 1. En multisite, les super-administrateurs restent toujours autorisés.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_plugin_user_ids"><strong><?php esc_html_e( 'ID utilisateurs autorisés', 'gi-toolkit' ); ?></strong></label></p>
				<p><input type="text" class="regular-text" id="gi_toolkit_plugin_user_ids" name="gi_toolkit_plugin_user_ids" value="<?php echo esc_attr( $allowed ); ?>"/></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function map( $caps, $cap, $user_id, $args ) {
		unset( $args );
		if ( ! in_array( $cap, $this->caps, true ) ) {
			return $caps;
		}
		if ( is_multisite() && is_super_admin( $user_id ) ) {
			return $caps;
		}

		$allowed = get_option( 'gi_toolkit_plugin_user_ids', '1' );
		if ( ! is_string( $allowed ) ) {
			$allowed = '1';
		}
		$ids = array_filter( array_map( 'absint', explode( ',', $allowed ) ) );
		if ( empty( $ids ) ) {
			$ids = array( 1 );
		}
		if ( in_array( (int) $user_id, $ids, true ) ) {
			return $caps;
		}

		return array( 'do_not_allow' );
	}
}
