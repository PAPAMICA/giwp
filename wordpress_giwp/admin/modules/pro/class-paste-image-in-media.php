<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: collage d’images depuis le presse-papiers sur l’écran Médiathèque (upload.php).
 */
class Gi_Toolkit_Paste_Image_In_Media {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Paste Image In Media', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-paste-media',
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
			<p><?php esc_html_e( 'Sur Médiathèque (upload.php), collez une image depuis le presse-papiers : un téléversement vers la médiathèque est lancé automatiquement.', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Réservé aux comptes pouvant téléverser des fichiers.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function enqueue( $hook ) {
		if ( 'upload.php' !== $hook || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$js = <<<'JS'
(function(){document.addEventListener("paste",function(e){var items=e.clipboardData&&e.clipboardData.items;if(!items||!window.wp||!wp.media)return;for(var i=0;i<items.length;i++){if(items[i].type.indexOf("image")!==0)continue;var file=items[i].getAsFile();if(!file)continue;e.preventDefault();var frame=wp.media({library:{type:"image"},multiple:false});frame.on("open",function(){try{frame.uploader.uploader.uploader.addFile(file);}catch(err){}});frame.open();break;}});})();
JS;
		wp_register_script( 'gi-toolkit-paste-media', false, array( 'jquery', 'media-upload' ), '1.0', true );
		wp_enqueue_script( 'gi-toolkit-paste-media' );
		wp_add_inline_script( 'gi-toolkit-paste-media', $js );
	}
}
