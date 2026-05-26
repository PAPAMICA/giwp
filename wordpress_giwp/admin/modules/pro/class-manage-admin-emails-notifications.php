<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: réduit certains e-mails automatiques WordPress (mises à jour).
 */
class Gi_Toolkit_Manage_Admin_Emails_Notifications {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_manage_admin_emails';

	private $page_slug = 'gi-toolkit-settings-manage-admin-emails';

	public function __construct() {
		$this->header_title = __( 'Manage Admin Emails Notifications', 'gi-toolkit' );
		add_filter( 'auto_core_update_send_email', array( $this, 'maybe_block_core' ), 10, 4 );
		add_filter( 'auto_plugin_update_send_email', array( $this, 'maybe_block_plugin' ), 10, 4 );
		add_filter( 'auto_theme_update_send_email', array( $this, 'maybe_block_theme' ), 10, 4 );
		add_filter( 'send_core_update_notification_email', array( $this, 'maybe_block_notification' ), 10, 3 );
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
		$on = ! empty( $_POST['gi_toolkit_block_update_emails'] );
		update_option( 'gi_toolkit_disable_core_update_emails', $on ? '1' : '0', false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$on = get_option( 'gi_toolkit_disable_core_update_emails', '0' ) === '1';
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p>
					<label>
						<input type="checkbox" name="gi_toolkit_block_update_emails" value="1" <?php checked( $on ); ?>/>
						<?php esc_html_e( 'Ne pas envoyer les e-mails automatiques de mise à jour (cœur, extensions, thèmes) et la notification de mise à jour du cœur.', 'gi-toolkit' ); ?>
					</label>
				</p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function maybe_block_core( $send, $type, $core_update, $result ) {
		unset( $type, $core_update, $result );
		return $this->is_blocked() ? false : $send;
	}

	public function maybe_block_plugin( $send, $type, $plugin_update, $result ) {
		unset( $type, $plugin_update, $result );
		return $this->is_blocked() ? false : $send;
	}

	public function maybe_block_theme( $send, $type, $theme_update, $result ) {
		unset( $type, $theme_update, $result );
		return $this->is_blocked() ? false : $send;
	}

	public function maybe_block_notification( $send, $email_params, $version ) {
		unset( $email_params, $version );
		return $this->is_blocked() ? false : $send;
	}

	private function is_blocked() {
		return get_option( 'gi_toolkit_disable_core_update_emails', '0' ) === '1';
	}
}
