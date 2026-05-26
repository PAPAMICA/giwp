<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: shortcodes utilitaires.
 */
class Gi_Toolkit_Add_Essentials_Shortcodes {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Add Essentials Shortcodes', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_shortcode( 'gi_year', array( $this, 'year' ) );
		add_shortcode( 'gi_site_url', array( $this, 'site_url' ) );
		add_shortcode( 'gi_home_link', array( $this, 'home_link' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-essentials-shortcodes',
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
			<p><?php esc_html_e( 'Shortcodes disponibles dans le contenu :', 'gi-toolkit' ); ?></p>
			<ul class="ul-disc" style="margin-left:1.5em;">
				<li><code>[gi_year]</code> — <?php esc_html_e( 'année en cours (texte)', 'gi-toolkit' ); ?></li>
				<li><code>[gi_site_url]</code> — <?php esc_html_e( 'URL du site (échappée)', 'gi-toolkit' ); ?></li>
				<li><code>[gi_home_link label="…"]</code> — <?php esc_html_e( 'lien vers l’accueil (label par défaut : nom du site)', 'gi-toolkit' ); ?></li>
			</ul>
		</div>
		<?php
		echo '</div>';
	}

	public function year() {
		return esc_html( gmdate( 'Y' ) );
	}

	public function site_url() {
		return esc_url( home_url( '/' ) );
	}

	public function home_link( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => get_bloginfo( 'name' ),
			),
			$atts,
			'gi_home_link'
		);
		return '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $atts['label'] ) . '</a>';
	}
}
