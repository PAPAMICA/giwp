<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: WordPress Debug Switch
 * Description: Force WP_DEBUG, WP_DEBUG_DISPLAY and WP_DEBUG_LOG on or off (overrides wp-config.php).
 *
 * @since 2.23.7
 */
class Gi_Toolkit_WordPress_Debug_Switch {

	const OPTION_ID       = 'wordpress_debug_switch';
	const SUBMENU_PAGE_ID = 'gi-toolkit-settings-wordpress-debug-switch';

	/** @var string */
	private $option_key;

	/** @var string */
	private $nonce_action;

	/** @var string */
	private $header_title = '';

	/**
	 * @return void
	 */
	public function __construct() {
		$this->option_key   = GI_TOOLKIT_PLUGIN_SETTINGS . '_' . self::OPTION_ID;
		$this->nonce_action = $this->option_key . '_action';

		add_action( 'plugins_loaded', array( __CLASS__, 'enforce' ), 0 );
		add_action( 'init', array( __CLASS__, 'enforce' ), 0 );
		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
	}

	/**
	 * @return void
	 */
	public function class_init() {
		$this->header_title = esc_html__( 'Mode debug WordPress', 'gi-toolkit' );
	}

	/**
	 * @return void
	 */
	public static function activate() {
		update_option(
			GI_TOOLKIT_PLUGIN_SETTINGS . '_' . self::OPTION_ID,
			array( 'enabled' => '0' ),
			false
		);
		self::sync_wp_config( false );
	}

	/**
	 * @return void
	 */
	public static function deactivate() {
		self::sync_wp_config( false );
		delete_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_' . self::OPTION_ID );
	}

	/**
	 * @return bool
	 */
	public static function is_debug_enabled() {
		$settings = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_' . self::OPTION_ID, array() );
		if ( ! is_array( $settings ) ) {
			return false;
		}
		return '1' === (string) ( $settings['enabled'] ?? '0' );
	}

	/**
	 * Applique les réglages PHP (écrase l’effet d’un wp-config incohérent sur la requête courante).
	 *
	 * @return void
	 */
	public static function enforce() {
		if ( self::is_debug_enabled() ) {
			self::apply_debug_on();
			return;
		}

		self::apply_debug_off();
	}

	/**
	 * @return void
	 */
	private static function apply_debug_on() {
		if ( function_exists( 'error_reporting' ) ) {
			error_reporting( E_ALL );
		}

		@ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.display_errors_Disallowed
		@ini_set( 'log_errors', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * @return void
	 */
	private static function apply_debug_off() {
		if ( function_exists( 'error_reporting' ) ) {
			error_reporting( 0 );
		}

		@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.display_errors_Disallowed
		@ini_set( 'log_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * @param bool $enabled État debug.
	 * @return void
	 */
	private static function sync_wp_config( $enabled ) {
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

		Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', (bool) $enabled );
		Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', (bool) $enabled );
		Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', (bool) $enabled );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_settings() {
		$defaults = array( 'enabled' => '0' );
		$settings = get_option( $this->option_key, $defaults );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * @param array<string, mixed> $new_settings Réglages bruts.
	 * @return array<string, string>
	 */
	private function sanitize_settings( $new_settings ) {
		return array(
			'enabled' => ( ! empty( $new_settings['enabled'] ) && '1' === (string) $new_settings['enabled'] ) ? '1' : '0',
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
			self::SUBMENU_PAGE_ID,
			array( $this, 'render_submenu' ),
			null
		);
	}

	/**
	 * @return void
	 */
	public function save_submenu() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ $this->option_key ] ) || ! is_array( $_POST[ $this->option_key ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_key ] ) );
		update_option( $this->option_key, $new_settings, false );
		self::sync_wp_config( '1' === $new_settings['enabled'] );
		self::enforce();

		wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? admin_url( 'admin.php' ) ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function render_submenu() {
		$settings = $this->get_settings();
		$enabled  = '1' === $settings['enabled'];

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->render_submenu_content( $settings, $enabled );
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * @param array<string, string> $settings Réglages.
	 * @param bool                  $enabled  Debug activé.
	 * @return void
	 */
	private function render_submenu_content( array $settings, $enabled ) {
		$effective_debug    = self::is_debug_enabled();
		$error_reporting    = error_reporting();
		$display_errors_ini = ini_get( 'display_errors' );
		?>
		<div class="gi-toolkit__section">
			<div class="gi-toolkit__section__desc">
				<?php esc_html_e( 'Force WP_DEBUG, WP_DEBUG_DISPLAY et WP_DEBUG_LOG dans wp-config.php et à l’exécution. Les valeurs existantes dans wp-config sont écrasées.', 'gi-toolkit' ); ?>
			</div>
			<div class="gi-toolkit__section__body">
				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title activable">
						<div>
							<label class="gi-toolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[enabled]" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>>
								<span class="gi-toolkit__toggle__slider round"></span>
							</label>
						</div>
						<div><?php esc_html_e( 'Activer le mode debug WordPress', 'gi-toolkit' ); ?></div>
					</div>
					<div class="gi-toolkit__section__body__item__content">
						<p class="description">
							<?php
							if ( $enabled ) {
								esc_html_e( 'Debug forcé à true (constants + display_errors + error_reporting E_ALL).', 'gi-toolkit' );
							} else {
								esc_html_e( 'Debug forcé à false (constants + display_errors 0 + error_reporting 0).', 'gi-toolkit' );
							}
							?>
						</p>
					</div>
				</div>

				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'État effectif', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__content">
						<ul class="gi-toolkit-debug-status__list">
							<li>
								<strong>WP_DEBUG:</strong>
								<?php echo $effective_debug ? esc_html__( 'true', 'gi-toolkit' ) : esc_html__( 'false', 'gi-toolkit' ); ?>
							</li>
							<li>
								<strong>WP_DEBUG_DISPLAY:</strong>
								<?php echo $effective_debug ? esc_html__( 'true', 'gi-toolkit' ) : esc_html__( 'false', 'gi-toolkit' ); ?>
							</li>
							<li>
								<strong>WP_DEBUG_LOG:</strong>
								<?php echo $effective_debug ? esc_html__( 'true', 'gi-toolkit' ) : esc_html__( 'false', 'gi-toolkit' ); ?>
							</li>
							<li>
								<strong>display_errors (ini):</strong>
								<code><?php echo esc_html( (string) $display_errors_ini ); ?></code>
							</li>
							<li>
								<strong>error_reporting:</strong>
								<code><?php echo esc_html( (string) $error_reporting ); ?></code>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
