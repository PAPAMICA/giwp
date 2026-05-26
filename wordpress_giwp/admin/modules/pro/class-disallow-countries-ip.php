<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: blocage par pays (CF-IPCountry, GeoIP…).
 */
class Gi_Toolkit_Disallow_Countries_IP {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_disallow_countries';

	private $page_slug = 'gi-toolkit-settings-disallow-countries';

	public function __construct() {
		$this->header_title = __( 'Disallow Countries IP', 'gi-toolkit' );
		add_action( 'init', array( $this, 'maybe_block' ), 0 );
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
		$raw = isset( $_POST['gi_toolkit_disallow_countries'] ) ? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_disallow_countries'] ) ) : '';
		update_option( 'gi_toolkit_disallow_countries', $raw, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$val = get_option( 'gi_toolkit_disallow_countries', '' );
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
				<p><label for="gi_toolkit_disallow_countries"><strong><?php esc_html_e( 'Codes pays à bloquer (ISO2, séparés par des virgules)', 'gi-toolkit' ); ?></strong></label></p>
				<p><input type="text" class="large-text" id="gi_toolkit_disallow_countries" name="gi_toolkit_disallow_countries" value="<?php echo esc_attr( $val ); ?>" placeholder="CN, RU"/></p>
				<p class="description"><?php esc_html_e( 'Nécessite un en-tête pays (ex. Cloudflare CF-IPCountry ou GEOIP_COUNTRY_CODE). Laisser vide pour ne rien bloquer.', 'gi-toolkit' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function maybe_block() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$raw = get_option( 'gi_toolkit_disallow_countries', '' );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return;
		}

		$list = array_filter( array_map( 'strtoupper', array_map( 'trim', explode( ',', $raw ) ) ) );
		if ( empty( $list ) ) {
			return;
		}

		$country = '';
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
		} elseif ( ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) {
			$country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) );
		} elseif ( ! empty( $_SERVER['HTTP_GEOIP_COUNTRY_CODE'] ) ) {
			$country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_GEOIP_COUNTRY_CODE'] ) ) );
		}

		if ( '' === $country || 'XX' === $country || 'T1' === $country ) {
			return;
		}

		if ( in_array( $country, $list, true ) ) {
			status_header( 403 );
			exit( esc_html__( 'Accès refusé depuis votre région.', 'gi-toolkit' ) );
		}
	}
}
