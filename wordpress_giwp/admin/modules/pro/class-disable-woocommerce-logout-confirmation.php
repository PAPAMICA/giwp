<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: supprime la page de confirmation de déconnexion WooCommerce.
 */
class Gi_Toolkit_Disable_Woocommerce_Logout_Confirmation {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Disable WooCommerce Logout Confirmation', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'wp_loaded', array( $this, 'unhook' ), 20 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-wc-logout',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<p><?php esc_html_e( 'Si WooCommerce est actif, la redirection de déconnexion avec page de confirmation intermédiaire est désactivée.', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Aucun réglage supplémentaire.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function unhook() {
		if ( ! class_exists( 'WooCommerce', false ) || ! class_exists( 'WC_Form_Handler', false ) ) {
			return;
		}
		remove_action( 'template_redirect', array( 'WC_Form_Handler', 'logout_redirect' ) );
	}
}
