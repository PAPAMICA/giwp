<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : détection des liens cassés dans le contenu.
 */
class Gi_Toolkit_Broken_Links {

	const OPTION_SETTINGS = 'gi_toolkit_broken_links_settings';

	const CRON_HOOK = 'gi_toolkit_broken_links_cron';

	private $page_slug = 'gi-toolkit-settings-broken-links';

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Liens cassés', 'gi-toolkit' );

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/broken-links/class-db.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/broken-links/class-scanner.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/broken-links/class-list-table.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/broken-links/class-scans-list-table.php';

		add_action( 'admin_init', array( $this, 'maybe_install_tables' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );

		add_action( 'wp_ajax_gi_toolkit_broken_links_scan_batch', array( $this, 'ajax_scan_batch' ) );

		add_action( self::CRON_HOOK, array( $this, 'run_cron_scan' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule_cron' ), 20 );
	}

	/**
	 * @return void
	 */
	public function maybe_schedule_cron() {
		$settings = $this->get_settings();
		if ( '1' !== (string) ( $settings['cron_enabled'] ?? '0' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$recurrence = in_array( $settings['cron_schedule'] ?? '', array( 'daily', 'weekly' ), true )
				? $settings['cron_schedule']
				: 'weekly';
			wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public function maybe_install_tables() {
		Gi_Toolkit_Broken_Links_DB::maybe_install();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_settings() {
		$defaults = array(
			'cron_enabled' => '0',
			'cron_schedule' => 'weekly',
			'keep_scans'   => 10,
		);
		$stored = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return void
	 */
	private function save_settings( $settings ) {
		update_option( self::OPTION_SETTINGS, $settings, false );
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( '1' === (string) ( $settings['cron_enabled'] ?? '0' ) ) {
			$recurrence = in_array( $settings['cron_schedule'] ?? '', array( 'daily', 'weekly' ), true )
				? $settings['cron_schedule']
				: 'weekly';
			wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::CRON_HOOK );
		}
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
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, 'gi_toolkit_broken_links_save' ) ) {
			return;
		}

		$settings = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['cron_enabled']  = isset( $_POST['cron_enabled'] ) ? '1' : '0';
		$settings['cron_schedule'] = isset( $_POST['cron_schedule'] )
			? sanitize_key( wp_unslash( $_POST['cron_schedule'] ) )
			: 'weekly';
		$settings['keep_scans']    = isset( $_POST['keep_scans'] )
			? max( 3, absint( wp_unslash( $_POST['keep_scans'] ) ) )
			: 10;

		$this->save_settings( $settings );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	/**
	 * @return void
	 */
	public function ajax_scan_batch() {
		check_ajax_referer( 'gi_toolkit_broken_links', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'gi-toolkit' ) ) );
		}

		if ( isset( $_POST['start'] ) && '1' === (string) wp_unslash( $_POST['start'] ) ) {
			$latest = Gi_Toolkit_Broken_Links_DB::get_latest_scan_id();
			if ( $latest ) {
				$scan = Gi_Toolkit_Broken_Links_DB::get_scan( $latest );
				if ( $scan && 'running' === $scan->status ) {
					Gi_Toolkit_Broken_Links_DB::update_scan(
						$latest,
						array(
							'status'      => 'failed',
							'finished_at' => current_time( 'mysql' ),
						)
					);
				}
			}
			Gi_Toolkit_Broken_Links_Scanner::start_scan( 'manual' );
			wp_send_json_success(
				array(
					'done'    => false,
					'percent' => 0,
					'message' => __( 'Préparation du scan…', 'gi-toolkit' ),
				)
			);
		}

		wp_send_json_success( Gi_Toolkit_Broken_Links_Scanner::process_batch() );
	}

	/**
	 * @return void
	 */
	public function run_cron_scan() {
		Gi_Toolkit_Broken_Links_Scanner::start_scan( 'cron' );
		$max = 500;
		while ( $max-- > 0 ) {
			$result = Gi_Toolkit_Broken_Links_Scanner::process_batch();
			if ( ! empty( $result['done'] ) ) {
				break;
			}
		}
	}

	/**
	 * @return void
	 */
	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		wp_enqueue_style(
			'gi-toolkit-broken-links',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/broken-links.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-broken-links',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/broken-links.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-broken-links',
			'giToolkitBrokenLinks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gi_toolkit_broken_links' ),
				'i18n'    => array(
					'scanning' => __( 'Scan en cours…', 'gi-toolkit' ),
					'error'    => __( 'Erreur lors du scan.', 'gi-toolkit' ),
				),
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'results';
		$settings = $this->get_settings();
		$scan_id  = Gi_Toolkit_Broken_Links_DB::get_latest_scan_id();

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<nav class="gi-broken-links-tabs">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) . '&tab=results' ) ); ?>" class="<?php echo 'results' === $tab ? 'is-active' : ''; ?>">
					<?php esc_html_e( 'Résultats', 'gi-toolkit' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) . '&tab=history' ) ); ?>" class="<?php echo 'history' === $tab ? 'is-active' : ''; ?>">
					<?php esc_html_e( 'Historique & planification', 'gi-toolkit' ); ?>
				</a>
			</nav>

			<div class="gi-broken-links-scan-bar">
				<button type="button" class="button button-primary" id="gi-broken-links-start-scan">
					<?php esc_html_e( 'Lancer un scan maintenant', 'gi-toolkit' ); ?>
				</button>
				<progress id="gi-broken-links-scan-progress" max="100" value="0"></progress>
				<p id="gi-broken-links-scan-status" class="description"></p>
			</div>

			<p class="description">
				<?php esc_html_e( 'Analyse les liens dans le contenu des publications (posts, pages et types publics). Les vérifications externes sont limitées à environ une requête par seconde et par domaine.', 'gi-toolkit' ); ?>
			</p>

			<?php if ( 'history' === $tab ) : ?>
				<h3><?php esc_html_e( 'Historique des scans', 'gi-toolkit' ); ?></h3>
				<?php
				$history = new Gi_Toolkit_Broken_Links_Scans_List_Table();
				$history->prepare_items();
				$history->display();
				?>

				<div class="gi-broken-links-settings">
					<h3><?php esc_html_e( 'Planification automatique', 'gi-toolkit' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) . '&tab=history' ) ); ?>">
						<?php wp_nonce_field( 'gi_toolkit_broken_links_save' ); ?>
						<input type="hidden" name="gi_toolkit_pro_save" value="1" />
						<p>
							<label>
								<input type="checkbox" name="cron_enabled" value="1" <?php checked( $settings['cron_enabled'], '1' ); ?> />
								<?php esc_html_e( 'Activer le scan automatique', 'gi-toolkit' ); ?>
							</label>
						</p>
						<p>
							<label for="cron_schedule"><?php esc_html_e( 'Fréquence', 'gi-toolkit' ); ?></label>
							<select name="cron_schedule" id="cron_schedule">
								<option value="daily" <?php selected( $settings['cron_schedule'], 'daily' ); ?>><?php esc_html_e( 'Quotidien', 'gi-toolkit' ); ?></option>
								<option value="weekly" <?php selected( $settings['cron_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Hebdomadaire', 'gi-toolkit' ); ?></option>
							</select>
						</p>
						<p>
							<label for="keep_scans"><?php esc_html_e( 'Conserver les scans (historique)', 'gi-toolkit' ); ?></label>
							<input type="number" min="3" max="50" name="keep_scans" id="keep_scans" value="<?php echo esc_attr( (string) $settings['keep_scans'] ); ?>" />
						</p>
						<?php submit_button( __( 'Enregistrer', 'gi-toolkit' ) ); ?>
					</form>
				</div>
			<?php else : ?>
				<h3><?php esc_html_e( 'Liens cassés (dernier scan)', 'gi-toolkit' ); ?></h3>
				<?php
				if ( $scan_id ) {
					$scan = Gi_Toolkit_Broken_Links_DB::get_scan( $scan_id );
					if ( $scan ) {
						printf(
							'<p class="description">%s</p>',
							esc_html(
								sprintf(
									/* translators: 1: date 2: broken 3: checked */
									__( 'Scan #%1$d — %2$d liens cassés sur %3$d URLs vérifiées (%4$s).', 'gi-toolkit' ),
									(int) $scan->id,
									(int) $scan->broken_count,
									(int) $scan->urls_checked,
									'done' === $scan->status ? __( 'terminé', 'gi-toolkit' ) : $scan->status
								)
							)
						);
					}
				}
				$table = new Gi_Toolkit_Broken_Links_List_Table( (int) $scan_id );
				$table->prepare_items();
				$table->display();
				?>
			<?php endif; ?>
		</div>
		<?php
		echo '</div>';
	}
}
