<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the surecart.
 *
 * @link       https://genevois-informatique.com
 * @since      1.15.0
 *
 * @package           Gi_Toolkit
 * @subpackage GI-Toolkit/admin
 */
class Gi_Toolkit_Surecart {

	/**
	 * The client instance.
	 */
	private $client;

	/**
	 * The settings instance.
	 */
	private $settings;

	/**
	 * The updater instance.
	 */
	private $updater;

	/**
	 * The transient id.
	 */
	private $transient_id = 'gi_toolkit_surecart_license';

	/**
	 * Init the surecart.
	 * 
	 * @since 1.15.0
	 */
	public function init_surecart() {
		global $gi_toolkit_surecart_client;

		if ( ! class_exists( 'SureCart\Licensing\Client' ) ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'licensing/src/Client.php';
		}

		$this->client   = new \SureCart\Licensing\Client( 'GI-Toolkit', 'pt_peLDYfw2gnkrUcoY5BzTNC89', GI_TOOLKIT_PLUGIN_FILE );
		$gi_toolkit_surecart_client = $this->client;

		$this->settings = $this->client->settings();
		$this->updater  = $this->client->updater();

		$this->client->settings()->add_page( array(
			'type'        => 'submenu',
			'parent_slug' => 'gi-toolkit-settings',
			'page_title'  => esc_html__( 'Manage License', 'gi-toolkit' ),
			'menu_title'  => esc_html__( 'Manage License', 'gi-toolkit' ),
			'capability'  => 'manage_options',
			'menu_slug'   => $this->client->slug . '-manage-license',
			'icon_url'    => '',
			'position'    => null,
		));

		if ( $this->license_activated() ) {
			$site_transient_prefix = 'site_transient_';//phpcs:ignore prefix to ignore the error
			add_filter( $site_transient_prefix . 'update_plugins', array( $this, 'force_surecart_updates' ) );
		} else {
			$site_transient_prefix = 'pre_set_site_transient_';//phpcs:ignore prefix to ignore the error
			remove_filter(  $site_transient_prefix . 'update_plugins', array( $this->updater, 'check_plugin_update' ) );
			remove_filter( 'plugins_api', array( $this->updater, 'plugins_api_filter' ), 10, 3 );
		}
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since 1.15.0
	 */
	public function enqueue_scripts_styles() {
		$surecart_license_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/surecart-license.asset.php' );
		wp_enqueue_style( 'Gi_Toolkit_surecart_license', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/surecart-license.css', array(), $surecart_license_assets['version'], 'all' );
		wp_enqueue_script( 'Gi_Toolkit_surecart_license', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/surecart-license.js', $surecart_license_assets['dependencies'], $surecart_license_assets['version'], true );
		$upgrade_label = __( 'License', 'gi-toolkit' );
		wp_localize_script( 'Gi_Toolkit_surecart_license', 'Gi_Toolkit_surecart_license', array(
			'activated' => $this->license_activated(),
			'i18n'      => array(
				'upgrade' => esc_js( '★ ' . esc_html( $upgrade_label ) ),
			),
		));
	}

	/**
	 * Show warning in llicence page if new version is available
	 * 
	 * @since 1.15.0
	 */
	public function show_warning_if_new_version( $action ) {
		if ( ! gi_toolkit_is_pro() && 'activate' === $action ) {
			$version_info = $this->updater->get_version_info();
			if ( is_object( $version_info ) ) {
				$new_version = $version_info->new_version ?? '';
				if ( version_compare( $new_version, GI_TOOLKIT_VERSION, '>' ) ) {
					?>
						<div class="gi_toolkit-update-notice orange">
							<b>
								<?php esc_html_e( 'Note:', 'gi-toolkit' ); ?>
							</b>
							<?php 
							echo wp_kses_post( sprintf(
								/* translators: %s: new version */
								__( 'A new version %s is available. If you activate your license, you will get the latest version automatically.', 'gi-toolkit' ),
								$new_version
							) );
							?>
						</div>
					<?php
				}
			}
		}
	}

	/**
	 * After license is activated
	 * 
	 * @since 1.15.0
	 */
	public function after_activated() {
		if ( ! gi_toolkit_is_pro() ) {
			$this->updater->delete_cached_version_info();
			$version_info = $this->updater->get_version_info();
			if ( is_object( $version_info ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				include_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-surecart-update.php';

				$upgrader = new Gi_Toolkit_Surecart_Update();
				$upgrader->update_from_surecart( $version_info->package );
			}
		}
		delete_transient( $this->transient_id );
		$site_transient_prefix = '_site_transient_';//phpcs:ignore prefix to ignore the error
		delete_option( $site_transient_prefix . 'update_plugins' );
	}

	/**
	 * After license is deactivated
	 * 
	 * @since 1.15.0
	 */
	public function after_deactivated() {
		delete_transient( $this->transient_id );
		$site_transient_prefix = '_site_transient_';//phpcs:ignore prefix to ignore the error
		delete_option( $site_transient_prefix . 'update_plugins' );
	}

	/**
	 * Force the surecart update if the license is active.
	 * 
	 * @since 1.15.0
	 */
	public function force_surecart_updates( $value ) {
		if ( is_object( $value ) ) {
			if ( isset( $value->response[ GI_TOOLKIT_BASENAME ] ) ) {
				$version_info = $this->updater->get_version_info();

				if ( is_object( $version_info ) ) {
					$value->response[ GI_TOOLKIT_BASENAME ]->new_version  = $version_info->new_version;
					$value->response[ GI_TOOLKIT_BASENAME ]->package      = $version_info->package;
					$value->response[ GI_TOOLKIT_BASENAME ]->requires     = $version_info->requires;
					$value->response[ GI_TOOLKIT_BASENAME ]->tested       = $version_info->tested;
					$value->response[ GI_TOOLKIT_BASENAME ]->requires_php = $version_info->requires_php;
				}
			}
		}

		return $value;
	}

	/**
	 * License activated
	 * 
	 * @since 1.15.0
	 */
	public function license_activated() {
		$activation = get_transient( $this->transient_id );

		if ( false === $activation ) {
			$activation = $this->settings->get_activation();
			if ( ! empty( $activation->id ) ) {
				set_transient( $this->transient_id, true, HOUR_IN_SECONDS * 2 );
			}
		}

		return $activation;
	}
}
