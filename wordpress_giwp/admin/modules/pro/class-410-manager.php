<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: réponses HTTP 410 pour chemins listés (option gi_toolkit_410_paths, une URL relative par ligne).
 */
class Gi_Toolkit_410_manager {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_410_paths';

	private $page_slug = 'gi-toolkit-settings-410';

	public function __construct() {
		$this->header_title = __( '410 Manager', 'gi-toolkit' );
		add_action( 'template_redirect', array( $this, 'maybe_410' ), 0 );
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
		$raw = isset( $_POST['gi_toolkit_410_paths'] ) ? wp_unslash( $_POST['gi_toolkit_410_paths'] ) : '';
		update_option( 'gi_toolkit_410_paths', is_string( $raw ) ? sanitize_textarea_field( $raw ) : '', false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$raw = get_option( 'gi_toolkit_410_paths', '' );
		if ( ! is_string( $raw ) ) {
			$raw = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Une ligne = un chemin d’URL relatif à la racine du site (sans domaine), par ex. /ancienne-page ou /blog/archive. Une requête correspondante recevra une réponse 410.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_410_paths"><strong><?php esc_html_e( 'Chemins (un par ligne)', 'gi-toolkit' ); ?></strong></label></p>
				<p><textarea class="large-text code" rows="12" id="gi_toolkit_410_paths" name="gi_toolkit_410_paths"><?php echo esc_textarea( $raw ); ?></textarea></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function maybe_410() {
		$raw = get_option( 'gi_toolkit_410_paths', '' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return;
		}

		$paths = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path  = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return;
		}
		$path = untrailingslashit( $path );

		foreach ( $paths as $p ) {
			$p = untrailingslashit( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( $path === $p || strpos( $path, $p . '/' ) === 0 ) {
				status_header( 410 );
				nocache_headers();
				exit( esc_html__( 'Cette ressource n’existe plus.', 'gi-toolkit' ) );
			}
		}
	}
}
