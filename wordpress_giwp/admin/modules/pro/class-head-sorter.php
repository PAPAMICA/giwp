<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: retire quelques balises <head> souvent inutiles (shortlink, adjacency, generator WP si option).
 */
class Gi_Toolkit_Head_Sorter {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_head_sorter';

	private $page_slug = 'gi-toolkit-settings-head-sorter';

	public function __construct() {
		$this->header_title = __( 'Head Sorter', 'gi-toolkit' );
		add_action( 'init', array( $this, 'clean' ) );
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
		$on = ! empty( $_POST['gi_toolkit_remove_wp_generator'] );
		update_option( 'gi_toolkit_remove_wp_generator', $on ? '1' : '0', false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$on = get_option( 'gi_toolkit_remove_wp_generator', '0' ) === '1';
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Sur le front-office, les balises shortlink et de pagination adjacentes sont toujours retirées du head.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p>
					<label>
						<input type="checkbox" name="gi_toolkit_remove_wp_generator" value="1" <?php checked( $on ); ?>/>
						<?php esc_html_e( 'Retirer aussi la balise meta generator WordPress.', 'gi-toolkit' ); ?>
					</label>
				</p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function clean() {
		if ( is_admin() ) {
			return;
		}
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
		if ( get_option( 'gi_toolkit_remove_wp_generator', '0' ) === '1' ) {
			remove_action( 'wp_head', 'wp_generator' );
		}
	}
}
