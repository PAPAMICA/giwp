<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: bloque l’accès direct à certains fichiers à la racine WordPress.
 */
class Gi_Toolkit_Disallow_Access_WP_Sensible_Files {

	private $blocked = array(
		'readme.html',
		'license.txt',
		'wp-config-sample.php',
		'llms.txt',
	);

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Disallow Access WP Sensible Files', 'gi-toolkit' );
		add_action( 'template_redirect', array( $this, 'block' ), 0 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-disallow-sensible-files',
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
			<p><?php esc_html_e( 'Les requêtes dont l’URL se termine par l’un des noms de fichier suivants reçoivent une réponse 403 :', 'gi-toolkit' ); ?></p>
			<ul style="list-style:disc;padding-left:1.5rem;">
				<?php foreach ( $this->blocked as $f ) : ?>
					<li><code><?php echo esc_html( $f ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<p class="description"><?php esc_html_e( 'Liste intégrée au module ; pour en ajouter d’autres, utilisez un filtre ou un snippet personnalisé.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function block() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return;
		}
		$path = strtolower( $path );
		$file = basename( $path );
		if ( in_array( $file, $this->blocked, true ) ) {
			status_header( 403 );
			exit( esc_html__( 'Accès interdit.', 'gi-toolkit' ) );
		}
	}
}
