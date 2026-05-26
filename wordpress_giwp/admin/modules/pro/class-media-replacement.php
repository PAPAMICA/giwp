<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: formulaire simple sur la fiche de média pour remplacer le fichier (même type MIME).
 */
class Gi_Toolkit_Media_Replacement {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Media Replacement', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'post-upload-ui', array( $this, 'note' ) );
		add_action( 'post_edit_form_tag', array( $this, 'multipart' ) );
		add_action( 'edit_form_after_title', array( $this, 'form' ) );
		add_action( 'edit_attachment', array( $this, 'handle' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-media-replacement',
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
			<p><?php esc_html_e( 'Sur la fiche d’une pièce jointe (médiathèque), un formulaire permet de remplacer le fichier par un autre du même type MIME.', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'L’ID de la pièce jointe et les URLs restent identiques après remplacement.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function multipart() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'attachment' === $screen->id ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public function note() {
		echo '<p class="description">' . esc_html__( 'Pour remplacer un fichier existant, ouvrez la pièce jointe depuis la médiathèque et utilisez le formulaire « Remplacer le fichier ».', 'gi-toolkit' ) . '</p>';
	}

	public function form( $post ) {
		if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		wp_nonce_field( 'gi_toolkit_replace_media', 'gi_toolkit_replace_media_nonce' );
		echo '<div class="postbox" style="margin-top:12px"><h2 class="hndle">' . esc_html__( 'Remplacer le fichier', 'gi-toolkit' ) . '</h2><div class="inside">';
		echo '<p><input type="file" name="gi_toolkit_replace_file" accept="' . esc_attr( $this->mime_for_post( $post ) ) . '"/></p>';
		echo '<p><button type="submit" class="button button-primary" name="gi_toolkit_replace_submit" value="1">' . esc_html__( 'Téléverser et remplacer', 'gi-toolkit' ) . '</button></p>';
		echo '</div></div>';
	}

	private function mime_for_post( $post ) {
		$mime = get_post_mime_type( $post );
		return $mime ? $mime : '*/*';
	}

	public function handle( $post_id ) {
		if ( empty( $_POST['gi_toolkit_replace_submit'] ) ) {
			return;
		}
		if ( empty( $_POST['gi_toolkit_replace_media_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gi_toolkit_replace_media_nonce'] ) ), 'gi_toolkit_replace_media' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || empty( $_FILES['gi_toolkit_replace_file']['tmp_name'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file = wp_handle_upload(
			$_FILES['gi_toolkit_replace_file'],
			array( 'test_form' => false )
		);
		if ( isset( $file['error'] ) ) {
			return;
		}

		$path = get_attached_file( $post_id );
		if ( $path && is_string( $path ) && file_exists( $path ) ) {
			@unlink( $path );
		}

		update_attached_file( $post_id, $file['file'] );
		wp_update_post(
			array(
				'ID'             => $post_id,
				'post_mime_type' => $file['type'],
			)
		);

		$meta = wp_generate_attachment_metadata( $post_id, $file['file'] );
		if ( ! is_wp_error( $meta ) && ! empty( $meta ) ) {
			wp_update_attachment_metadata( $post_id, $meta );
		}
	}
}
