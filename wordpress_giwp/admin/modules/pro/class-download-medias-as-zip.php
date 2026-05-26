<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: action groupée « Télécharger en ZIP » sur la médiathèque.
 */
class Gi_Toolkit_Download_Medias_As_Zip {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Download Medias As Zip', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_filter( 'bulk_actions-upload', array( $this, 'bulk' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle' ), 10, 3 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-download-medias-zip',
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
			<p><?php esc_html_e( 'Dans la liste de la médiathèque, sélectionnez des fichiers puis l’action groupée « Télécharger en ZIP (GI-Toolkit) ».', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'L’extension PHP ZipArchive doit être disponible sur le serveur.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function bulk( $actions ) {
		$actions['gi_toolkit_zip'] = __( 'Télécharger en ZIP (GI-Toolkit)', 'gi-toolkit' );
		return $actions;
	}

	public function handle( $redirect, $action, $post_ids ) {
		if ( 'gi_toolkit_zip' !== $action || empty( $post_ids ) ) {
			return $redirect;
		}
		if ( ! class_exists( 'ZipArchive', false ) ) {
			return add_query_arg( 'gi_zip_err', '1', $redirect );
		}

		$tmp = wp_tempnam( 'gi-media' );
		if ( ! $tmp ) {
			return $redirect;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			@unlink( $tmp );
			return $redirect;
		}

		foreach ( $post_ids as $id ) {
			$id = absint( $id );
			if ( ! $id || ! current_user_can( 'read_post', $id ) ) {
				continue;
			}
			$path = get_attached_file( $id );
			if ( $path && is_readable( $path ) ) {
				$zip->addFile( $path, $id . '-' . basename( $path ) );
			}
		}
		$zip->close();

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="medias-gi-toolkit.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}
}
