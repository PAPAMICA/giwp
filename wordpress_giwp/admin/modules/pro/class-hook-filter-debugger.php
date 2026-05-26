<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: trace des hooks (WP_DEBUG + paramètre d’URL).
 */
class Gi_Toolkit_Hook_Filter_Debugger {

	private $log = array();

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Hook And Filter Debugger', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['gi_hook_debug'] ) || ! WP_DEBUG ) {
			return;
		}

		add_action( 'all', array( $this, 'trace' ), 9999 );
		add_action( 'shutdown', array( $this, 'dump' ), 9999 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-hook-debugger',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$example = admin_url( 'index.php?gi_hook_debug=1' );
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<p><?php esc_html_e( 'Lorsque WP_DEBUG est activé dans wp-config.php, ajoutez le paramètre d’URL suivant sur une page d’administration pour journaliser dans error_log la liste des hooks déclenchés (extrait) :', 'gi-toolkit' ); ?></p>
			<p><code>?gi_hook_debug=1</code></p>
			<p><a class="button" href="<?php echo esc_url( $example ); ?>"><?php esc_html_e( 'Tester sur le tableau de bord', 'gi-toolkit' ); ?></a></p>
			<p class="description"><?php esc_html_e( 'Consultez le fichier debug.log ou les logs PHP de l’hébergeur.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function trace() {
		$this->log[] = current_filter();
	}

	public function dump() {
		if ( empty( $this->log ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'GI-Toolkit hook trace: ' . wp_json_encode( array_slice( array_unique( $this->log ), 0, 200 ) ) );
	}
}
