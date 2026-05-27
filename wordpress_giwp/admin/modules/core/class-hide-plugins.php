<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : masquer des extensions dans l’admin WordPress (liste Extensions).
 */
class Gi_Toolkit_Hide_Plugins {

	const OPTION_HIDDEN       = 'gi_toolkit_hidden_plugins';

	const OPTION_EXEMPT_USERS = 'gi_toolkit_hidden_plugins_exempt_users';

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_hidden_plugins';

	private $page_slug = 'gi-toolkit-settings-hide-plugins';

	public function __construct() {
		$this->header_title = __( 'Hide Plugins', 'gi-toolkit' );
		add_filter( 'all_plugins', array( $this, 'filter_all_plugins' ), 20 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
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

		$hidden = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['gi_toolkit_hidden_plugins'] ) && is_array( $_POST['gi_toolkit_hidden_plugins'] ) ) {
			$all    = array_keys( $this->get_installed_plugins() );
			$posted = array_map( 'sanitize_text_field', wp_unslash( $_POST['gi_toolkit_hidden_plugins'] ) );
			foreach ( $posted as $file ) {
				if ( in_array( $file, $all, true ) ) {
					$hidden[] = $file;
				}
			}
		}

		update_option( self::OPTION_HIDDEN, array_values( array_unique( $hidden ) ), false );

		$exempt = isset( $_POST['gi_toolkit_hidden_plugins_exempt_users'] )
			? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_hidden_plugins_exempt_users'] ) )
			: '1';
		update_option( self::OPTION_EXEMPT_USERS, $exempt, false );

		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	/**
	 * @return void
	 */
	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';

		$hidden      = $this->get_hidden_plugins();
		$exempt      = get_option( self::OPTION_EXEMPT_USERS, '1' );
		$plugins     = $this->get_installed_plugins();
		$gi_basename = plugin_basename( GI_TOOLKIT_PLUGIN_FILE );
		?>
		<div class="gi-toolkit__body gi-toolkit-hide-plugins-settings" style="padding:1rem 1.5rem 2rem;max-width:900px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Les extensions cochées n’apparaissent plus dans Extensions (liste installées). Elles restent actives sur le site.', 'gi-toolkit' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>

				<p>
					<label for="gi_toolkit_hidden_plugins_exempt_users"><strong><?php esc_html_e( 'Utilisateurs qui voient toutes les extensions', 'gi-toolkit' ); ?></strong></label><br />
					<input type="text" class="regular-text" id="gi_toolkit_hidden_plugins_exempt_users" name="gi_toolkit_hidden_plugins_exempt_users" value="<?php echo esc_attr( is_string( $exempt ) ? $exempt : '1' ); ?>" />
					<span class="description"><?php esc_html_e( 'ID utilisateurs séparés par des virgules (ex. 1). Les super-administrateurs multisite voient toujours tout.', 'gi-toolkit' ); ?></span>
				</p>

				<?php if ( empty( $plugins ) ) : ?>
					<p><?php esc_html_e( 'Aucune extension installée.', 'gi-toolkit' ); ?></p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Extensions à masquer', 'gi-toolkit' ); ?></strong></p>
					<div class="gi-toolkit-hide-plugins-list">
						<?php foreach ( $plugins as $file => $data ) : ?>
							<label class="gi-toolkit-hide-plugins-list__item">
								<input
									type="checkbox"
									name="gi_toolkit_hidden_plugins[]"
									value="<?php echo esc_attr( $file ); ?>"
									<?php checked( in_array( $file, $hidden, true ) ); ?>
									<?php disabled( $file === $gi_basename ); ?>
								/>
								<span class="gi-toolkit-hide-plugins-list__name"><?php echo esc_html( $data['Name'] ?? $file ); ?></span>
								<code class="gi-toolkit-hide-plugins-list__file"><?php echo esc_html( $file ); ?></code>
								<?php if ( $file === $gi_basename ) : ?>
									<span class="description"><?php esc_html_e( '(GI-Toolkit ne peut pas être masqué)', 'gi-toolkit' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<style>
			.gi-toolkit-hide-plugins-list {
				max-height: 420px;
				overflow: auto;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				background: #fff;
				padding: 8px 12px;
			}
			.gi-toolkit-hide-plugins-list__item {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 8px 12px;
				padding: 8px 4px;
				border-bottom: 1px solid #f0f0f1;
				margin: 0;
			}
			.gi-toolkit-hide-plugins-list__item:last-child {
				border-bottom: 0;
			}
			.gi-toolkit-hide-plugins-list__name {
				font-weight: 600;
				min-width: 160px;
			}
			.gi-toolkit-hide-plugins-list__file {
				font-size: 12px;
				color: #646970;
			}
		</style>
		<?php
		echo '</div>';
	}

	/**
	 * @param array<string, array<string, mixed>> $plugins Liste des extensions.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_all_plugins( $plugins ) {
		if ( ! is_array( $plugins ) || $this->user_can_see_hidden() ) {
			return $plugins;
		}

		$hidden = $this->get_hidden_plugins();
		if ( empty( $hidden ) ) {
			return $plugins;
		}

		foreach ( $hidden as $file ) {
			unset( $plugins[ $file ] );
		}

		return $plugins;
	}

	/**
	 * @return bool
	 */
	private function user_can_see_hidden() {
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		$exempt = get_option( self::OPTION_EXEMPT_USERS, '1' );
		if ( ! is_string( $exempt ) ) {
			$exempt = '1';
		}

		$ids = array_filter( array_map( 'absint', explode( ',', $exempt ) ) );
		if ( empty( $ids ) ) {
			$ids = array( 1 );
		}

		return in_array( get_current_user_id(), $ids, true );
	}

	/**
	 * @return string[]
	 */
	private function get_hidden_plugins() {
		$hidden = get_option( self::OPTION_HIDDEN, array() );
		if ( ! is_array( $hidden ) ) {
			return array();
		}

		$gi_basename = plugin_basename( GI_TOOLKIT_PLUGIN_FILE );

		return array_values(
			array_filter(
				array_map( 'sanitize_text_field', $hidden ),
				static function ( $file ) use ( $gi_basename ) {
					return is_string( $file ) && '' !== $file && $file !== $gi_basename;
				}
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		uasort(
			$plugins,
			static function ( $a, $b ) {
				return strcasecmp( (string) ( $a['Name'] ?? '' ), (string) ( $b['Name'] ?? '' ) );
			}
		);

		return $plugins;
	}
}
