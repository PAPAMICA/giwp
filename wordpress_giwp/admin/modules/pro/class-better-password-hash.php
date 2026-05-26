<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: à la connexion, migre le hash du mot de passe vers bcrypt (si disponible en PHP).
 */
class Gi_Toolkit_Better_Password_Hash {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Better Password Hash', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'wp_login', array( $this, 'maybe_upgrade' ), 10, 2 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-better-password-hash',
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
			<p><?php esc_html_e( 'À chaque connexion réussie, si le mot de passe n’est pas déjà stocké en bcrypt (PHP 5.5+), il est re-hashé avec l’API WordPress.', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Aucun réglage : activez ou désactivez le module depuis la liste GI-Toolkit.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	/**
	 * @param string  $user_login Login.
	 * @param WP_User $user       Utilisateur.
	 */
	public function maybe_upgrade( $user_login, $user ) {
		unset( $user_login );
		if ( ! $user instanceof WP_User || empty( $_POST['pwd'] ) ) {
			return;
		}
		if ( ! defined( 'PASSWORD_BCRYPT' ) ) {
			return;
		}

		$hash = $user->user_pass;
		if ( is_string( $hash ) && ( 0 === strpos( $hash, '$2y$' ) || 0 === strpos( $hash, '$2a$' ) ) ) {
			return;
		}

		$password = (string) wp_unslash( $_POST['pwd'] );
		wp_set_password( $password, $user->ID );
	}
}
