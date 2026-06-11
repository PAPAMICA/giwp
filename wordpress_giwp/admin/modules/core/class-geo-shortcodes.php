<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : shortcodes géo-localisés compatibles cache (WP Rocket).
 */
class Gi_Toolkit_Geo_Shortcodes {

	const AJAX_RESOLVE  = 'gi_toolkit_geo_resolve';
	const AJAX_PREVIEW  = 'gi_toolkit_geo_preview';
	const AJAX_DETECT   = 'gi_toolkit_geo_detect';

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_geo_shortcodes';

	private $page_slug = 'gi-toolkit-settings-geo-shortcodes';

	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private static $shortcodes_registered = false;

	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/geo-shortcodes/class-country-resolver.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/geo-shortcodes/class-store.php';

		$this->header_title = __( 'Geo Shortcodes', 'gi-toolkit' );

		add_action( 'init', array( $this, 'register_shortcodes' ), 25 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_RESOLVE, array( $this, 'ajax_resolve' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_RESOLVE, array( $this, 'ajax_resolve' ) );
		add_action( 'wp_ajax_' . self::AJAX_PREVIEW, array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_' . self::AJAX_DETECT, array( $this, 'ajax_detect' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_preview_notice' ), 999 );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'rocket_delay_js_exclusions' ) );
		add_filter( 'rocket_exclude_defer_js', array( $this, 'rocket_exclude_defer_js' ) );
	}

	/**
	 * @return void
	 */
	public function register_shortcodes() {
		if ( self::$shortcodes_registered ) {
			return;
		}
		self::$shortcodes_registered = true;

		add_shortcode( 'geo', array( $this, 'shortcode_geo' ) );

		foreach ( array_keys( Gi_Toolkit_Geo_Shortcodes_Store::get_variables() ) as $slug ) {
			add_shortcode( 'geo_' . $slug, array( $this, 'dynamic_shortcode' ) );
		}
	}

	/**
	 * @param array<string, mixed>|string $atts    Attributs.
	 * @param string|null                 $content Contenu.
	 * @param string                      $tag     Nom shortcode.
	 * @return string
	 */
	public function dynamic_shortcode( $atts, $content, $tag ) {
		unset( $atts, $content );
		if ( 0 !== strpos( (string) $tag, 'geo_' ) ) {
			return '';
		}
		return $this->render_placeholder( substr( (string) $tag, 4 ) );
	}

	/**
	 * @param array<string, mixed>|string $atts Attributs.
	 * @return string
	 */
	public function shortcode_geo( $atts ) {
		$atts = shortcode_atts(
			array(
				'var'   => '',
				'key'   => '',
				'slug'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			'geo'
		);

		$slug = Gi_Toolkit_Geo_Shortcodes_Store::sanitize_slug(
			$atts['var'] ?: ( $atts['key'] ?: $atts['slug'] )
		);

		if ( '' === $slug ) {
			return '';
		}

		return $this->render_placeholder( $slug );
	}

	/**
	 * @param string $slug Variable.
	 * @return string
	 */
	private function render_placeholder( $slug ) {
		$slug = Gi_Toolkit_Geo_Shortcodes_Store::sanitize_slug( $slug );
		if ( '' === $slug || ! Gi_Toolkit_Geo_Shortcodes_Store::get_variable( $slug ) ) {
			return '';
		}

		$default = Gi_Toolkit_Geo_Shortcodes_Store::resolve_value(
			$slug,
			Gi_Toolkit_Geo_Shortcodes_Store::get_default_country()
		);

		self::mark_front_script_needed();
		$GLOBALS['gi_toolkit_geo_shortcodes_used'] = true;

		return sprintf(
			'<span class="gi-geo-var" data-gi-geo-var="%1$s" data-gi-geo-default="%2$s"></span>',
			esc_attr( $slug ),
			esc_attr( $default )
		);
	}

	/**
	 * @return void
	 */
	private static function mark_front_script_needed() {
		if ( ! has_action( 'wp_footer', array( __CLASS__, 'ensure_front_script' ) ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'ensure_front_script' ), 5 );
		}
	}

	/**
	 * @return void
	 */
	public static function ensure_front_script() {
		if ( wp_script_is( 'gi-toolkit-geo-shortcodes-front', 'enqueued' ) ) {
			return;
		}
		self::enqueue_front_assets_static();
	}

	/**
	 * @return void
	 */
	public function enqueue_front_assets() {
		if ( is_admin() ) {
			return;
		}
		if ( ! self::page_has_geo_placeholders() && ! self::content_has_geo_shortcodes() ) {
			return;
		}
		self::enqueue_front_assets_static();
	}

	/**
	 * @return void
	 */
	private static function enqueue_front_assets_static() {
		wp_enqueue_script(
			'gi-toolkit-geo-shortcodes-front',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/geo-shortcodes-front.js',
			array(),
			GI_TOOLKIT_VERSION,
			true
		);

		wp_localize_script(
			'gi-toolkit-geo-shortcodes-front',
			'giToolkitGeoShortcodes',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_RESOLVE,
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function page_has_geo_placeholders() {
		return ! empty( $GLOBALS['gi_toolkit_geo_shortcodes_used'] );
	}

	/**
	 * @return bool
	 */
	private static function content_has_geo_shortcodes() {
		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof WP_Post && is_string( $post->post_content ) ) {
				if ( false !== strpos( $post->post_content, '[geo_' ) || false !== strpos( $post->post_content, '[geo ' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, $this->page_slug ) ) {
			return;
		}

		wp_enqueue_style(
			'gi-toolkit-geo-shortcodes-admin',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/geo-shortcodes-admin.css',
			array(),
			GI_TOOLKIT_VERSION
		);

		wp_enqueue_script(
			'gi-toolkit-geo-shortcodes-admin',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/geo-shortcodes-admin.js',
			array( 'jquery' ),
			GI_TOOLKIT_VERSION,
			true
		);

		wp_localize_script(
			'gi-toolkit-geo-shortcodes-admin',
			'giToolkitGeoShortcodesAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'gi_toolkit_geo_shortcodes_admin' ),
				'previewAction'    => self::AJAX_PREVIEW,
				'detectAction'     => self::AJAX_DETECT,
				'resolveAction'    => self::AJAX_RESOLVE,
				'settings'         => Gi_Toolkit_Geo_Shortcodes_Store::get(),
				'countries'        => Gi_Toolkit_Geo_Shortcodes_Store::common_country_codes(),
				'detectedCountry'  => Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_country_code(),
				'previewCountry'   => Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_preview_country(),
				'i18n'             => array(
					'saved'            => __( 'Enregistré.', 'gi-toolkit' ),
					'invalidSlug'      => __( 'Identifiant invalide (lettres minuscules, chiffres, underscore).', 'gi-toolkit' ),
					'duplicateSlug'    => __( 'Cet identifiant existe déjà.', 'gi-toolkit' ),
					'confirmDelete'    => __( 'Supprimer cette variable ?', 'gi-toolkit' ),
					'previewSet'       => __( 'Simulation activée. Ouvrez une page contenant vos shortcodes (même en navigation privée).', 'gi-toolkit' ),
					'previewCleared'   => __( 'Simulation désactivée.', 'gi-toolkit' ),
					'copyDone'         => __( 'Copié.', 'gi-toolkit' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
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

	/**
	 * @return void
	 */
	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, $this->nonce_action ) ) {
			return;
		}

		$raw = isset( $_POST['gi_toolkit_geo_shortcodes_json'] )
			? wp_unslash( $_POST['gi_toolkit_geo_shortcodes_json'] )
			: '{}';

		$payload = json_decode( is_string( $raw ) ? $raw : '{}', true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		Gi_Toolkit_Geo_Shortcodes_Store::save( Gi_Toolkit_Geo_Shortcodes_Store::from_editor_payload( $payload ) );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	/**
	 * @return void
	 */
	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';

		$settings = Gi_Toolkit_Geo_Shortcodes_Store::get();
		?>
		<div class="gi-toolkit__body gi-geo-shortcodes-admin">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<div class="gi-geo-shortcodes-admin__intro">
				<p><?php esc_html_e( 'Créez des shortcodes affichant une valeur différente selon le pays du visiteur. Compatible avec le cache de page (WP Rocket) : le HTML est identique pour tous, la personnalisation se fait via JavaScript et une requête AJAX non mise en cache.', 'gi-toolkit' ); ?></p>
				<p><?php esc_html_e( 'Exemples :', 'gi-toolkit' ); ?> <code>[geo_phone]</code>, <code>[geo_currency]</code>, <code>[geo var="phone"]</code></p>
			</div>

			<form method="post" id="gi-geo-shortcodes-form" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1" />
				<input type="hidden" name="gi_toolkit_geo_shortcodes_json" id="gi-geo-shortcodes-json" value="" />

				<div class="gi-geo-shortcodes-panel">
					<h2><?php esc_html_e( 'Réglages généraux', 'gi-toolkit' ); ?></h2>
					<div class="gi-geo-shortcodes-grid">
						<p>
							<label for="gi-geo-default-country"><strong><?php esc_html_e( 'Pays par défaut', 'gi-toolkit' ); ?></strong></label><br />
							<input type="text" class="regular-text" id="gi-geo-default-country" maxlength="2" pattern="[A-Za-z]{2}" value="<?php echo esc_attr( (string) ( $settings['default_country'] ?? 'FR' ) ); ?>" />
							<span class="description"><?php esc_html_e( 'Utilisé si le pays du visiteur est inconnu.', 'gi-toolkit' ); ?></span>
						</p>
						<p>
							<label for="gi-geo-geoip-path"><strong><?php esc_html_e( 'Base GeoIP2 (.mmdb) — optionnel', 'gi-toolkit' ); ?></strong></label><br />
							<input type="text" class="large-text code" id="gi-geo-geoip-path" value="<?php echo esc_attr( (string) ( $settings['geoip_db_path'] ?? '' ) ); ?>" placeholder="/chemin/vers/GeoLite2-Country.mmdb" />
							<span class="description"><?php esc_html_e( 'Repli si aucun en-tête pays (Cloudflare, nginx GeoIP…).', 'gi-toolkit' ); ?></span>
						</p>
					</div>
				</div>

				<div class="gi-geo-shortcodes-panel">
					<div class="gi-geo-shortcodes-panel__head">
						<h2><?php esc_html_e( 'Variables', 'gi-toolkit' ); ?></h2>
						<button type="button" class="button button-secondary" id="gi-geo-add-variable"><?php esc_html_e( 'Ajouter une variable', 'gi-toolkit' ); ?></button>
					</div>
					<div id="gi-geo-variables-root" class="gi-geo-variables-root"></div>
				</div>

				<div class="gi-geo-shortcodes-panel gi-geo-shortcodes-panel--preview">
					<h2><?php esc_html_e( 'Prévisualisation & test', 'gi-toolkit' ); ?></h2>
					<div class="gi-geo-preview-toolbar">
						<label for="gi-geo-preview-country"><?php esc_html_e( 'Simuler le pays', 'gi-toolkit' ); ?></label>
						<input type="text" id="gi-geo-preview-country" maxlength="2" class="small-text" placeholder="FR" />
						<button type="button" class="button" id="gi-geo-preview-apply"><?php esc_html_e( 'Appliquer sur le site', 'gi-toolkit' ); ?></button>
						<button type="button" class="button button-link-delete" id="gi-geo-preview-clear"><?php esc_html_e( 'Arrêter la simulation', 'gi-toolkit' ); ?></button>
						<span class="gi-geo-preview-detected">
							<?php esc_html_e( 'Pays détecté (serveur) :', 'gi-toolkit' ); ?>
							<strong id="gi-geo-detected-country"><?php echo esc_html( Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_country_code() ?: '—' ); ?></strong>
						</span>
					</div>
					<p class="description"><?php esc_html_e( 'La simulation fonctionne aussi sur les pages en cache (cookie signé 1 h). Ajoutez ?gi_geo_preview=CH à une URL pour tester rapidement en étant connecté.', 'gi-toolkit' ); ?></p>
					<div id="gi-geo-preview-output" class="gi-geo-preview-output"></div>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	/**
	 * @return void
	 */
	public function ajax_resolve() {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );

		$keys_raw = isset( $_REQUEST['keys'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['keys'] ) ) : '';
		$keys     = array_filter(
			array_map(
				array( Gi_Toolkit_Geo_Shortcodes_Store::class, 'sanitize_slug' ),
				array_map( 'trim', explode( ',', $keys_raw ) )
			)
		);

		if ( empty( $keys ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucune variable demandée.', 'gi-toolkit' ) ) );
		}

		$country = Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_country_code();
		$values  = Gi_Toolkit_Geo_Shortcodes_Store::resolve_many( $country, $keys );

		wp_send_json_success(
			array(
				'country' => $country,
				'values'  => $values,
				'preview' => '' !== Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_preview_country(),
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'gi_toolkit_geo_shortcodes_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		$country = isset( $_POST['country'] ) ? Gi_Toolkit_Geo_Shortcodes_Country_Resolver::sanitize_country( wp_unslash( $_POST['country'] ) ) : '';
		$clear   = ! empty( $_POST['clear'] );

		if ( $clear || '' === $country ) {
			Gi_Toolkit_Geo_Shortcodes_Country_Resolver::clear_preview_cookie();
			wp_send_json_success(
				array(
					'message' => __( 'Simulation désactivée.', 'gi-toolkit' ),
					'country' => Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_country_code(),
				)
			);
		}

		Gi_Toolkit_Geo_Shortcodes_Country_Resolver::set_preview_cookie( $country );
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: country code */
					__( 'Simulation activée pour %s.', 'gi-toolkit' ),
					$country
				),
				'country' => $country,
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_detect() {
		check_ajax_referer( 'gi_toolkit_geo_shortcodes_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		wp_send_json_success(
			array(
				'country' => Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_country_code(),
				'preview' => Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_preview_country(),
			)
		);
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Barre admin.
	 * @return void
	 */
	public function admin_bar_preview_notice( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$preview = Gi_Toolkit_Geo_Shortcodes_Country_Resolver::get_preview_country();
		if ( '' === $preview ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'gi-geo-preview',
				'title' => sprintf(
					/* translators: %s: country code */
					__( 'Geo : simulation %s', 'gi-toolkit' ),
					$preview
				),
				'href'  => admin_url( 'admin.php?page=' . $this->page_slug ),
				'meta'  => array(
					'class' => 'gi-geo-preview-ab',
				),
			)
		);
	}

	/**
	 * @param string[] $excluded Exclusions Rocket delay JS.
	 * @return string[]
	 */
	public function rocket_delay_js_exclusions( $excluded ) {
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}
		$excluded[] = 'geo-shortcodes-front';
		return $excluded;
	}

	/**
	 * @param string[] $excluded Exclusions Rocket defer JS.
	 * @return string[]
	 */
	public function rocket_exclude_defer_js( $excluded ) {
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}
		$excluded[] = 'gi-toolkit-geo-shortcodes-front';
		return $excluded;
	}
}
