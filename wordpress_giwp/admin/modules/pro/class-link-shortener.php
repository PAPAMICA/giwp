<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: liens courts /go/{slug} — option gi_toolkit_shortlinks (tableau slug => url).
 */
class Gi_Toolkit_Link_Shortener {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_shortlinks';

	private $page_slug = 'gi-toolkit-settings-link-shortener';

	public function __construct() {
		$this->header_title = __( 'Link Shortener', 'gi-toolkit' );
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirect' ), 0 );
		if ( ! get_option( 'gi_toolkit_shortlinks_rules_flushed' ) ) {
			$this->rewrite();
			flush_rewrite_rules( false );
			update_option( 'gi_toolkit_shortlinks_rules_flushed', '1', true );
		}
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
		$raw = isset( $_POST['gi_toolkit_shortlinks_text'] ) ? wp_unslash( $_POST['gi_toolkit_shortlinks_text'] ) : '';
		$map = $this->parse_shortlinks_text( is_string( $raw ) ? $raw : '' );
		update_option( 'gi_toolkit_shortlinks', $map, false );
		flush_rewrite_rules( false );
		update_option( 'gi_toolkit_shortlinks_rules_flushed', '1', true );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	/**
	 * @param string $text Lignes « slug url ».
	 * @return array<string,string>
	 */
	private function parse_shortlinks_text( $text ) {
		$map  = array();
		$lines = preg_split( '/\R/', $text );
		if ( ! is_array( $lines ) ) {
			return $map;
		}
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			if ( ! preg_match( '/^([a-zA-Z0-9_-]+)\s+(\S+)/', $line, $m ) ) {
				continue;
			}
			$url = esc_url_raw( $m[2] );
			if ( $url ) {
				$map[ $m[1] ] = $url;
			}
		}
		return $map;
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$map = get_option( 'gi_toolkit_shortlinks', array() );
		if ( ! is_array( $map ) ) {
			$map = array();
		}
		$lines = array();
		foreach ( $map as $slug => $url ) {
			$lines[] = $slug . ' ' . $url;
		}
		$text = implode( "\n", $lines );
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré. Les règles de réécriture ont été actualisées.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Chaque ligne : un identifiant (lettres, chiffres, tiret, underscore) puis l’URL de destination. Les visites sur', 'gi-toolkit' ); ?> <code>/go/slug/</code> <?php esc_html_e( 'seront redirigées (302).', 'gi-toolkit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Exemple : la ligne « promo https://exemple.com/offre » crée l’adresse /go/promo/ sur ce site.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_shortlinks_text"><strong><?php esc_html_e( 'Raccourcis (une ligne : slug url)', 'gi-toolkit' ); ?></strong></label></p>
				<p><textarea class="large-text code" rows="14" id="gi_toolkit_shortlinks_text" name="gi_toolkit_shortlinks_text"><?php echo esc_textarea( $text ); ?></textarea></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function rewrite() {
		add_rewrite_rule( '^go/([a-zA-Z0-9_-]+)/?$', 'index.php?gi_short=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'gi_short';
		return $vars;
	}

	public function redirect() {
		$slug = get_query_var( 'gi_short' );
		if ( ! $slug ) {
			return;
		}

		$map = get_option( 'gi_toolkit_shortlinks', array() );
		if ( ! is_array( $map ) || empty( $map[ $slug ] ) ) {
			status_header( 404 );
			exit;
		}

		$url = esc_url_raw( $map[ $slug ] );
		if ( ! $url ) {
			status_header( 404 );
			exit;
		}

		wp_safe_redirect( $url, 302 );
		exit;
	}
}
