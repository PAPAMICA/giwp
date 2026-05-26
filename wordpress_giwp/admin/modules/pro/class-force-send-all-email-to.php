<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: redirection de tous les wp_mail vers une adresse.
 */
class Gi_Toolkit_Force_Send_All_Email_To {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_force_mail_to';

	private $page_slug = 'gi-toolkit-settings-force-mail-to';

	public function __construct() {
		$this->header_title = __( 'Force Send All Email To', 'gi-toolkit' );
		add_filter( 'wp_mail', array( $this, 'redirect_all' ), 999 );
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
		$email = isset( $_POST['gi_toolkit_force_mail_to'] ) ? sanitize_email( wp_unslash( $_POST['gi_toolkit_force_mail_to'] ) ) : '';
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}
		update_option( 'gi_toolkit_force_mail_to', $email, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$val = get_option( 'gi_toolkit_force_mail_to', '' );
		if ( ! is_string( $val ) ) {
			$val = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p>
					<label for="gi_toolkit_force_mail_to"><strong><?php esc_html_e( 'Adresse e-mail unique', 'gi-toolkit' ); ?></strong></label>
				</p>
				<p><input type="email" class="large-text" id="gi_toolkit_force_mail_to" name="gi_toolkit_force_mail_to" value="<?php echo esc_attr( $val ); ?>" placeholder="dev@example.com"/></p>
				<p class="description"><?php esc_html_e( 'Laissez vide pour désactiver. Utile sur environnement de préproduction.', 'gi-toolkit' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function redirect_all( $args ) {
		$to = get_option( 'gi_toolkit_force_mail_to', '' );
		if ( ! is_string( $to ) || ! is_email( $to ) ) {
			return $args;
		}
		$args['to'] = $to;
		return $args;
	}
}
