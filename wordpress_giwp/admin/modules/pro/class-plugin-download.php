<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: lien de téléchargement ZIP sur la liste des extensions.
 */
class Gi_Toolkit_Plugin_Download {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Plugin Download', 'gi-toolkit' );
		add_filter( 'plugin_action_links', array( $this, 'add_link' ), 20, 2 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-plugin-download',
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
			<p><?php esc_html_e( 'Un lien « Télécharger ZIP » est ajouté sur la page Extensions pour les paquets hébergés sur WordPress.org (fichier .latest-stable.zip).', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Aucun réglage : le module fonctionne dès qu’il est activé.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function add_link( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return $links;
		}

		$slug = dirname( $file );
		if ( '.' === $slug ) {
			$slug = basename( $file, '.php' );
		}

		$url                  = 'https://downloads.wordpress.org/plugin/' . rawurlencode( $slug ) . '.latest-stable.zip';
		$links['gi_download'] = '<a href="' . esc_url( $url ) . '" rel="noopener noreferrer">' . esc_html__( 'Télécharger ZIP', 'gi-toolkit' ) . '</a>';

		return $links;
	}
}
