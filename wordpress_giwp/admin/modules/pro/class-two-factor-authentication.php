<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : 2FA par codes de secours (profil utilisateur + écran d’aide GI-Toolkit).
 */
class Gi_Toolkit_Two_Factor_Authentication {

	/**
	 * @var bool
	 */
	private $disable_form = true;

	/**
	 * @var string
	 */
	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Two Factor Authentication', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'show_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile' ) );
		add_action( 'login_form', array( $this, 'login_field' ) );
		add_filter( 'wp_authenticate_user', array( $this, 'verify_recovery_code' ), 10, 2 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-two-factor-auth',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$submenu_assets = include GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/global-admin.asset.php';
		wp_enqueue_style( 'gi-toolkit-2fa-help', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.css', array(), $submenu_assets['version'], 'all' );

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$profile_url = admin_url( 'profile.php' );
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<p><?php esc_html_e( 'Ce module ajoute une 2FA simple basée sur des codes de secours à usage unique, en complément du mot de passe.', 'gi-toolkit' ); ?></p>
			<ol style="line-height:1.6;">
				<li><?php esc_html_e( 'Ouvrez votre profil WordPress.', 'gi-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Dans la section « GI-Toolkit — 2FA », cochez la génération de nouveaux codes puis enregistrez le profil.', 'gi-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Notez immédiatement les 10 codes affichés (ils ne seront plus montrés ensuite).', 'gi-toolkit' ); ?></li>
				<li><?php esc_html_e( 'À la connexion, saisissez un code dans le champ prévu sur la page de login.', 'gi-toolkit' ); ?></li>
			</ol>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Ouvrir mon profil', 'gi-toolkit' ); ?></a>
			</p>
			<p class="description">
				<?php esc_html_e( 'Les administrateurs peuvent configurer la 2FA pour tout compte depuis Utilisateurs → Modifier.', 'gi-toolkit' ); ?>
			</p>
		</div>
		<?php
		echo '</div>';
	}

	public function login_field() {
		?>
		<p>
			<label for="gi_toolkit_recovery_code"><?php esc_html_e( 'Code de secours 2FA (si activé sur votre compte)', 'gi-toolkit' ); ?></label>
			<input type="text" name="gi_toolkit_recovery_code" id="gi_toolkit_recovery_code" class="input" value="" size="20" autocomplete="off"/>
		</p>
		<?php
	}

	/**
	 * @param WP_User|WP_Error $user     Utilisateur.
	 * @param string           $password Mot de passe (non utilisé).
	 * @return WP_User|WP_Error
	 */
	public function verify_recovery_code( $user, $password ) {
		unset( $password );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$hashes = get_user_meta( $user->ID, 'gi_toolkit_2fa_codes', true );
		if ( empty( $hashes ) || ! is_array( $hashes ) ) {
			return $user;
		}

		$rate_key = 'gi_toolkit_2fa_attempts_' . $user->ID;
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= 5 ) {
			return new WP_Error(
				'gi_2fa_rate_limit',
				__( 'Trop de tentatives. Réessayez dans quelques minutes.', 'gi-toolkit' )
			);
		}

		$code = isset( $_POST['gi_toolkit_recovery_code'] ) ? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_recovery_code'] ) ) : '';
		if ( '' === $code ) {
			return new WP_Error( 'gi_2fa_required', __( 'Un code de secours est requis pour ce compte.', 'gi-toolkit' ) );
		}

		foreach ( $hashes as $i => $hash ) {
			if ( ! is_string( $hash ) ) {
				continue;
			}
			if ( wp_check_password( $code, $hash ) ) {
				unset( $hashes[ $i ] );
				update_user_meta( $user->ID, 'gi_toolkit_2fa_codes', array_values( $hashes ) );
				delete_transient( $rate_key );
				return $user;
			}
		}

		set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );

		return new WP_Error( 'gi_2fa_invalid', __( 'Code de secours invalide.', 'gi-toolkit' ) );
	}

	public function profile_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$has_codes = (bool) get_user_meta( $user->ID, 'gi_toolkit_2fa_codes', true );
		wp_nonce_field( 'gi_toolkit_2fa', 'gi_toolkit_2fa_nonce' );
		?>
		<h2><?php esc_html_e( 'GI-Toolkit — 2FA (codes de secours)', 'gi-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="gi_toolkit_2fa_generate"><?php esc_html_e( 'Codes de secours', 'gi-toolkit' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="gi_toolkit_2fa_generate" id="gi_toolkit_2fa_generate" value="1"/>
						<?php esc_html_e( 'Générer 10 nouveaux codes (les anciens seront invalidés)', 'gi-toolkit' ); ?>
					</label>
					<?php if ( $has_codes ) : ?>
						<p class="description"><?php esc_html_e( 'La 2FA est active : une connexion nécessite un code à usage unique en plus du mot de passe.', 'gi-toolkit' ); ?></p>
					<?php endif; ?>
					<?php
					$pending = get_user_meta( $user->ID, 'gi_toolkit_2fa_pending_display', true );
					if ( $pending ) {
						delete_user_meta( $user->ID, 'gi_toolkit_2fa_pending_display' );
						echo '<p><strong>' . esc_html__( 'Enregistrez ces codes dans un endroit sûr (affichage unique) :', 'gi-toolkit' ) . '</strong></p><ul>';
						foreach ( (array) $pending as $c ) {
							echo '<li><code>' . esc_html( $c ) . '</code></li>';
						}
						echo '</ul>';
					}
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( empty( $_POST['gi_toolkit_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gi_toolkit_2fa_nonce'] ) ), 'gi_toolkit_2fa' ) ) {
			return;
		}
		if ( empty( $_POST['gi_toolkit_2fa_generate'] ) ) {
			return;
		}

		$codes   = array();
		$display = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$plain     = strtolower( wp_generate_password( 10, false, false ) );
			$codes[]   = wp_hash_password( $plain );
			$display[] = $plain;
		}
		update_user_meta( $user_id, 'gi_toolkit_2fa_codes', $codes );
		update_user_meta( $user_id, 'gi_toolkit_2fa_pending_display', $display );
	}
}
