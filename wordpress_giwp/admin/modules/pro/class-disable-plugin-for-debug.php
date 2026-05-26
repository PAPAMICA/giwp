<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: désactive temporairement des extensions listées (requête admin + nonce), effet au prochain chargement.
 */
class Gi_Toolkit_Disable_Plugin_For_Debug {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Disable Plugin For Debug', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'maybe_deactivate' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-disable-plugin-debug',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$example = wp_nonce_url(
			admin_url( 'index.php?gi_toolkit_debug_off=plugin-dir/plugin.php' ),
			'gi_toolkit_debug_off'
		);
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Réservé au dépannage : une requête GET avec nonce désactive immédiatement les extensions listées.', 'gi-toolkit' ); ?></p></div>
			<p><?php esc_html_e( 'Paramètre gi_toolkit_debug_off : chemins relatifs des extensions, séparés par des virgules (comme dans la liste des extensions).', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Exemple d’URL (à adapter, ne partagez pas) :', 'gi-toolkit' ); ?> <code style="word-break:break-all;"><?php echo esc_html( $example ); ?></code></p>
			<p class="description"><?php esc_html_e( 'Nécessite la capacité activate_plugins. Redirection vers la page des extensions après exécution.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function maybe_deactivate() {
		if ( empty( $_GET['gi_toolkit_debug_off'] ) || empty( $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gi_toolkit_debug_off' ) ) {
			return;
		}

		$list = sanitize_text_field( wp_unslash( $_GET['gi_toolkit_debug_off'] ) );
		$slugs = array_filter( array_map( 'sanitize_text_field', explode( ',', $list ) ) );
		if ( empty( $slugs ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( $slugs as $plugin_file ) {
			if ( strpos( $plugin_file, '..' ) !== false ) {
				continue;
			}
			if ( is_plugin_active( $plugin_file ) ) {
				deactivate_plugins( $plugin_file, true );
			}
		}

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}
}
