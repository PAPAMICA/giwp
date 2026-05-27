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

		Gi_Toolkit_Broken_Links_DB::maybe_install();
		if ( ! Gi_Toolkit_Broken_Links_DB::tables_ready() ) {
			wp_send_json_error( array( 'message' => __( 'Impossible de créer les tables du module.', 'gi-toolkit' ) ) );
		}

		try {
			$this->ajax_scan_batch_inner();
		} catch ( Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Erreur lors du scan : %s', 'gi-toolkit' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * @return void
	 */
	private function ajax_scan_batch_inner() {
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
	 * @param object|null $scan Ligne scan.
	 * @return array{broken: int, checked: int, status: string, date: string}
	 */
	private function get_scan_summary( $scan ) {
		if ( ! $scan ) {
			return array(
				'broken'  => 0,
				'checked' => 0,
				'status'  => '',
				'date'    => '',
			);
		}
		$date = $scan->finished_at ? $scan->finished_at : $scan->started_at;
		return array(
			'broken'  => (int) $scan->broken_count,
			'checked' => (int) $scan->urls_checked,
			'status'  => (string) $scan->status,
			'date'    => $date ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) : '',
		);
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

		Gi_Toolkit_Broken_Links_DB::maybe_install();

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
					'done'     => __( 'Scan terminé.', 'gi-toolkit' ),
					'error'    => __( 'Erreur lors du scan.', 'gi-toolkit' ),
				),
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'results';
		$settings = $this->get_settings();
		$scan_id  = Gi_Toolkit_Broken_Links_DB::get_latest_scan_id();
		$scan     = $scan_id ? Gi_Toolkit_Broken_Links_DB::get_scan( $scan_id ) : null;
		$summary  = $this->get_scan_summary( $scan );

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body gi-toolkit-broken-links-page">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! Gi_Toolkit_Broken_Links_DB::tables_ready() ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Les tables du module n’ont pas pu être créées. Vérifiez les droits MySQL.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<nav class="gi-broken-links-tabs" aria-label="<?php esc_attr_e( 'Navigation liens cassés', 'gi-toolkit' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) . '&tab=results' ) ); ?>" class="gi-broken-links-tab <?php echo 'results' === $tab ? 'is-active' : ''; ?>">
					<?php esc_html_e( 'Résultats', 'gi-toolkit' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) . '&tab=history' ) ); ?>" class="gi-broken-links-tab <?php echo 'history' === $tab ? 'is-active' : ''; ?>">
					<?php esc_html_e( 'Historique & planification', 'gi-toolkit' ); ?>
				</a>
			</nav>

			<div class="gi-broken-links-toolbar">
				<div class="gi-broken-links-scan-panel">
					<button type="button" class="button button-primary button-hero" id="gi-broken-links-start-scan">
						<?php esc_html_e( 'Lancer un scan', 'gi-toolkit' ); ?>
					</button>
					<div class="gi-broken-links-scan-progress-wrap" hidden>
						<progress id="gi-broken-links-scan-progress" max="100" value="0"></progress>
						<span id="gi-broken-links-scan-percent" class="gi-broken-links-scan-percent">0%</span>
					</div>
					<p id="gi-broken-links-scan-status" class="gi-broken-links-scan-status description"></p>
				</div>
				<p class="gi-broken-links-intro description">
					<?php esc_html_e( 'Analyse les liens dans le contenu des publications (posts, pages et types publics). Les vérifications externes sont limitées à environ une requête par seconde et par domaine.', 'gi-toolkit' ); ?>
				</p>
			</div>

			<?php if ( $scan_id && 'results' === $tab ) : ?>
				<div class="gi-broken-links-stats">
					<div class="gi-broken-links-stat gi-broken-links-stat--broken">
						<span class="gi-broken-links-stat__value"><?php echo esc_html( (string) $summary['broken'] ); ?></span>
						<span class="gi-broken-links-stat__label"><?php esc_html_e( 'Liens cassés', 'gi-toolkit' ); ?></span>
					</div>
					<div class="gi-broken-links-stat">
						<span class="gi-broken-links-stat__value"><?php echo esc_html( (string) $summary['checked'] ); ?></span>
						<span class="gi-broken-links-stat__label"><?php esc_html_e( 'URLs vérifiées', 'gi-toolkit' ); ?></span>
					</div>
					<div class="gi-broken-links-stat">
						<span class="gi-broken-links-stat__value">
							<?php
							if ( 'done' === $summary['status'] ) {
								esc_html_e( 'Terminé', 'gi-toolkit' );
							} elseif ( 'running' === $summary['status'] ) {
								esc_html_e( 'En cours', 'gi-toolkit' );
							} elseif ( 'failed' === $summary['status'] ) {
								esc_html_e( 'Échec', 'gi-toolkit' );
							} else {
								echo esc_html( $summary['status'] ?: '—' );
							}
							?>
						</span>
						<span class="gi-broken-links-stat__label"><?php esc_html_e( 'Statut', 'gi-toolkit' ); ?></span>
					</div>
					<?php if ( $summary['date'] ) : ?>
						<div class="gi-broken-links-stat">
							<span class="gi-broken-links-stat__value gi-broken-links-stat__value--sm"><?php echo esc_html( $summary['date'] ); ?></span>
							<span class="gi-broken-links-stat__label"><?php esc_html_e( 'Dernier scan', 'gi-toolkit' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="gi-broken-links-panel">
				<?php if ( 'history' === $tab ) : ?>
					<h2 class="gi-broken-links-panel__title"><?php esc_html_e( 'Historique des scans', 'gi-toolkit' ); ?></h2>
					<div class="gi-broken-links-table-wrap">
						<?php
						$history = new Gi_Toolkit_Broken_Links_Scans_List_Table();
						$history->prepare_items();
						$history->display();
						?>
					</div>

					<div class="gi-broken-links-settings">
						<h2 class="gi-broken-links-panel__title"><?php esc_html_e( 'Planification automatique', 'gi-toolkit' ); ?></h2>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) . '&tab=history' ) ); ?>" class="gi-broken-links-settings-form">
							<?php wp_nonce_field( 'gi_toolkit_broken_links_save' ); ?>
							<input type="hidden" name="gi_toolkit_pro_save" value="1" />
							<p>
								<label class="gi-broken-links-checkbox">
									<input type="checkbox" name="cron_enabled" value="1" <?php checked( $settings['cron_enabled'], '1' ); ?> />
									<?php esc_html_e( 'Activer le scan automatique', 'gi-toolkit' ); ?>
								</label>
							</p>
							<p>
								<label for="cron_schedule"><?php esc_html_e( 'Fréquence', 'gi-toolkit' ); ?></label>
								<select name="cron_schedule" id="cron_schedule" class="regular-text">
									<option value="daily" <?php selected( $settings['cron_schedule'], 'daily' ); ?>><?php esc_html_e( 'Quotidien', 'gi-toolkit' ); ?></option>
									<option value="weekly" <?php selected( $settings['cron_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Hebdomadaire', 'gi-toolkit' ); ?></option>
								</select>
							</p>
							<p>
								<label for="keep_scans"><?php esc_html_e( 'Conserver les scans (historique)', 'gi-toolkit' ); ?></label>
								<input type="number" min="3" max="50" class="small-text" name="keep_scans" id="keep_scans" value="<?php echo esc_attr( (string) $settings['keep_scans'] ); ?>" />
							</p>
							<?php submit_button( __( 'Enregistrer', 'gi-toolkit' ) ); ?>
						</form>
					</div>
				<?php else : ?>
					<h2 class="gi-broken-links-panel__title"><?php esc_html_e( 'Liens cassés', 'gi-toolkit' ); ?></h2>
					<div class="gi-broken-links-table-wrap">
						<?php
						$table = new Gi_Toolkit_Broken_Links_List_Table( (int) $scan_id );
						$table->prepare_items();
						$table->display();
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		echo '</div>';
	}
}
