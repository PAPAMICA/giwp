<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: désactivation globale des commentaires.
 */
class Gi_Toolkit_Disable_Comments {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Disable Comments', 'gi-toolkit' );
		add_action( 'admin_init', array( $this, 'disable_support' ) );
		add_filter( 'comments_open', '__return_false', 20 );
		add_filter( 'pings_open', '__return_false', 20 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );
		add_action( 'admin_menu', array( $this, 'remove_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-disable-comments',
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
			<p><?php esc_html_e( 'Les commentaires et rétroliens sont fermés sur tout le site, le support « commentaires » est retiré des types de contenu et le menu « Commentaires » est masqué dans l’administration.', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Aucun réglage supplémentaire : activez ou désactivez le module depuis la liste principale GI-Toolkit.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function disable_support() {
		$post_types = get_post_types( array(), 'names' );
		foreach ( $post_types as $pt ) {
			if ( post_type_supports( $pt, 'comments' ) ) {
				remove_post_type_support( $pt, 'comments' );
				remove_post_type_support( $pt, 'trackbacks' );
			}
		}
	}

	public function remove_menu() {
		remove_menu_page( 'edit-comments.php' );
	}
}
