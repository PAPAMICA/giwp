<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: CSS personnalisé sur wp-login.php (option gi_toolkit_login_custom_css).
 */
class Gi_Toolkit_Custom_Login_Design {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_login_css';

	private $page_slug = 'gi-toolkit-settings-login-css';

	public function __construct() {
		$this->header_title = __( 'Custom Login Design', 'gi-toolkit' );
		add_action( 'login_enqueue_scripts', array( $this, 'css' ) );
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
		$raw = isset( $_POST['gi_toolkit_login_custom_css'] ) ? wp_unslash( $_POST['gi_toolkit_login_custom_css'] ) : '';
		update_option( 'gi_toolkit_login_custom_css', is_string( $raw ) ? wp_strip_all_tags( $raw ) : '', false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	/**
	 * Scripts et styles de l’aperçu live.
	 *
	 * @return void
	 */
	private function enqueue_preview_assets() {
		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';

		wp_enqueue_style(
			'gi-toolkit-login-design-preview',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/custom-login-design-preview.css',
			array( 'gi-toolkit-pro-module-admin' ),
			$version
		);

		$logo_url = apply_filters( 'gi_toolkit_login_preview_logo_url', '' );
		if ( '' === $logo_url && function_exists( 'get_site_icon_url' ) ) {
			$logo_url = (string) get_site_icon_url( 512 );
		}
		if ( '' === $logo_url ) {
			$logo_url = includes_url( 'images/w-logo-blue.png' );
		}

		wp_enqueue_script(
			'gi-toolkit-login-design-preview',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/custom-login-design-preview.js',
			array(),
			$version,
			true
		);

		wp_localize_script(
			'gi-toolkit-login-design-preview',
			'giToolkitLoginPreview',
			array(
				'logoUrl' => $logo_url,
				'i18n'    => array(
					'message'      => __( 'Message d’information (aperçu)', 'gi-toolkit' ),
					'userLabel'    => __( 'Identifiant ou adresse e-mail', 'gi-toolkit' ),
					'passLabel'    => __( 'Mot de passe', 'gi-toolkit' ),
					'remember'     => __( 'Se souvenir de moi', 'gi-toolkit' ),
					'submit'       => __( 'Se connecter', 'gi-toolkit' ),
					'lostPassword' => __( 'Mot de passe oublié ?', 'gi-toolkit' ),
					'backToSite'   => __( '← Aller sur Mon site', 'gi-toolkit' ),
				),
			)
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		$this->enqueue_preview_assets();

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$css = get_option( 'gi_toolkit_login_custom_css', '' );
		if ( ! is_string( $css ) ) {
			$css = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'CSS brut injecté dans la page de connexion WordPress (balises HTML retirées pour la sécurité). Utilisez l’aperçu pour valider le rendu avant d’enregistrer.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<div class="gi-toolkit-login-design-layout">
					<div class="gi-toolkit-login-design-editor">
						<p><label for="gi_toolkit_login_custom_css"><strong><?php esc_html_e( 'CSS', 'gi-toolkit' ); ?></strong></label></p>
						<p><textarea class="large-text code" rows="20" id="gi_toolkit_login_custom_css" name="gi_toolkit_login_custom_css"><?php echo esc_textarea( $css ); ?></textarea></p>
						<?php submit_button(); ?>
					</div>
					<?php include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/pro/custom-login-design-preview.php'; ?>
				</div>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function css() {
		$css = get_option( 'gi_toolkit_login_custom_css', '' );
		if ( ! is_string( $css ) || '' === trim( $css ) ) {
			return;
		}
		echo '<style id="gi-toolkit-login-css">' . wp_strip_all_tags( $css ) . '</style>';
	}
}
