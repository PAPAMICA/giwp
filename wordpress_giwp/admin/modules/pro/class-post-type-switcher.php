<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: métabox pour changer le type de publication (édition de contenu).
 */
class Gi_Toolkit_Post_Type_Switcher {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Post Type Switcher', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'add_meta_boxes', array( $this, 'box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-post-type-switcher',
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
			<p><?php esc_html_e( 'Une boîte latérale sur l’écran d’édition permet de changer le type de publication d’un contenu existant (types publics).', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Utilisez avec précaution : certaines métadonnées ou modèles peuvent ne pas correspondre au nouveau type.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function box() {
		$types = get_post_types(
			array(
				'show_ui' => true,
			),
			'names'
		);
		foreach ( $types as $pt ) {
			add_meta_box(
				'gi_toolkit_pts',
				__( 'Type de publication (GI-Toolkit)', 'gi-toolkit' ),
				array( $this, 'render' ),
				$pt,
				'side',
				'low'
			);
		}
	}

	public function render( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		wp_nonce_field( 'gi_toolkit_pts', 'gi_toolkit_pts_nonce' );
		$types = get_post_types( array( 'public' => true ), 'objects' );
		echo '<label class="screen-reader-text" for="gi_toolkit_post_type">' . esc_html__( 'Type', 'gi-toolkit' ) . '</label>';
		echo '<select name="gi_toolkit_post_type" id="gi_toolkit_post_type">';
		foreach ( $types as $obj ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $obj->name ),
				selected( $post->post_type, $obj->name, false ),
				esc_html( $obj->label )
			);
		}
		echo '</select>';
	}

	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['gi_toolkit_pts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gi_toolkit_pts_nonce'] ) ), 'gi_toolkit_pts' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST['gi_toolkit_post_type'] ) ) {
			return;
		}
		$new = sanitize_key( wp_unslash( $_POST['gi_toolkit_post_type'] ) );
		if ( ! post_type_exists( $new ) ) {
			return;
		}
		if ( $new === $post->post_type ) {
			return;
		}
		remove_action( 'save_post', array( $this, 'save' ), 10 );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => $new,
			)
		);
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}
}
