<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: bascule de compte (administrateurs avec droit edit_users).
 */
class Gi_Toolkit_User_Switching {

	const COOKIE = 'gi_toolkit_switch_old';

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'User Switching', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'bar' ), 999 );
		add_action( 'admin_init', array( $this, 'handle' ) );
		add_action( 'init', array( $this, 'handle' ), 1 );
		add_filter( 'user_row_actions', array( $this, 'row_action' ), 10, 2 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-user-switching',
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
			<p><?php esc_html_e( 'Les comptes disposant du droit « Modifier les utilisateurs » voient l’action « Se connecter comme » dans la liste des utilisateurs et un raccourci dans la barre d’administration.', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Un cookie sécurisé mémorise votre compte d’origine pour permettre le retour via « Revenir à mon compte ».', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}

	public function row_action( $actions, $user ) {
		if ( ! current_user_can( 'edit_users' ) || get_current_user_id() === (int) $user->ID ) {
			return $actions;
		}
		$url                    = wp_nonce_url(
			add_query_arg( 'gi_toolkit_us', (int) $user->ID, admin_url( 'index.php' ) ),
			'gi_toolkit_us_' . (int) $user->ID
		);
		$actions['gi_switch'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Se connecter comme', 'gi-toolkit' ) . '</a>';
		return $actions;
	}

	public function handle() {
		if ( empty( $_GET['gi_toolkit_us'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['gi_toolkit_us'] ) );

		if ( 'back' === $action ) {
			if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gi_toolkit_us_back' ) ) {
				return;
			}
			$old = isset( $_COOKIE[ self::COOKIE ] ) ? absint( $_COOKIE[ self::COOKIE ] ) : 0;
			if ( $old && get_userdata( $old ) ) {
				wp_clear_auth_cookie();
				wp_set_auth_cookie( $old, true );
				wp_set_current_user( $old );
				if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
					setcookie( self::COOKIE, '0', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
				}
				wp_safe_redirect( admin_url() );
				exit;
			}
			return;
		}

		$target = absint( $action );
		if ( ! $target || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gi_toolkit_us_' . $target ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$current = get_current_user_id();
		if ( ! get_userdata( $target ) ) {
			return;
		}

		setcookie( self::COOKIE, (string) $current, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $target, true );
		wp_set_current_user( $target );
		wp_safe_redirect( admin_url() );
		exit;
	}

	public function bar( $bar ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! empty( $_COOKIE[ self::COOKIE ] ) && absint( $_COOKIE[ self::COOKIE ] ) ) {
			$url = wp_nonce_url( add_query_arg( 'gi_toolkit_us', 'back', home_url( '/' ) ), 'gi_toolkit_us_back' );
			$bar->add_node(
				array(
					'id'    => 'gi-toolkit-us-back',
					'title' => __( 'Revenir à mon compte', 'gi-toolkit' ),
					'href'  => $url,
				)
			);
			return;
		}

		$bar->add_node(
			array(
				'id'     => 'gi-toolkit-us',
				'title'  => __( 'Changer d’utilisateur…', 'gi-toolkit' ),
				'href'   => admin_url( 'users.php' ),
				'parent' => 'top-secondary',
			)
		);
	}
}
