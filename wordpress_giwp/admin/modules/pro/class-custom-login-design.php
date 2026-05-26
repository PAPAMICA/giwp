<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: CSS personnalisé sur wp-login.php (option gi_toolkit_login_custom_css).
 */
class Gi_Toolkit_Custom_Login_Design {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_login_css';

	private $page_slug = 'gi-toolkit-settings-login-css';

	public function __construct() {
		$this->header_title = __( 'Custom Login Design', 'gi-toolkit' );
		add_action( 'login_enqueue_scripts', array( $this, 'css' ) );
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
		$raw = isset( $_POST['gi_toolkit_login_custom_css'] ) ? wp_unslash( $_POST['gi_toolkit_login_custom_css'] ) : '';
		update_option( 'gi_toolkit_login_custom_css', is_string( $raw ) ? wp_strip_all_tags( $raw ) : '', false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$css = get_option( 'gi_toolkit_login_custom_css', '' );
		if ( ! is_string( $css ) ) {
			$css = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'CSS brut injecté dans la page de connexion WordPress (balises HTML retirées pour la sécurité).', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_login_custom_css"><strong><?php esc_html_e( 'CSS', 'gi-toolkit' ); ?></strong></label></p>
				<p><textarea class="large-text code" rows="16" id="gi_toolkit_login_custom_css" name="gi_toolkit_login_custom_css"><?php echo esc_textarea( $css ); ?></textarea></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function css() {
		$css = get_option( 'gi_toolkit_login_custom_css', '' );
		if ( ! is_string( $css ) || '' === trim( $css ) ) {
			return;
		}
		echo '<style id="gi-toolkit-login-css">' . wp_strip_all_tags( $css ) . '</style>';
	}
}
