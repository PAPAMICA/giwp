<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : nettoyage des médias non utilisés.
 */
class Gi_Toolkit_Unused_Media {

	private static $instance = null;

	private $page_slug = 'gi-toolkit-settings-unused-media';

	private $header_title = '';

	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		$this->header_title = __( 'Nettoyage médiathèque', 'gi-toolkit' );

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/unused-media/class-usage-scanner.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/unused-media/class-list-table.php';

		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );
		add_action( 'wp_ajax_gi_toolkit_unused_media_scan_batch', array( $this, 'ajax_scan_batch' ) );
	}

	/**
	 * @return self|null
	 */
	public static function instance() {
		return self::$instance;
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
	public function handle_bulk_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) || $this->page_slug !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'delete_posts' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['gi_toolkit_unused_media_nonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( wp_unslash( $_POST['gi_toolkit_unused_media_nonce'] ), 'gi_toolkit_unused_media_bulk' ) ) {
			return;
		}

		$action = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			$action = sanitize_key( wp_unslash( $_POST['action'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( ! empty( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_POST['action2'] ) );
		}

		if ( 'delete' !== $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = isset( $_POST['media'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['media'] ) ) : array();
		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( $id > 0 && wp_delete_attachment( $id, true ) ) {
				++$deleted;
				$this->remove_from_scan_cache( $id );
			}
		}

		$redirect = add_query_arg(
			array(
				'page'              => $this->page_slug,
				'gi_unused_deleted' => $deleted,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @param int $attachment_id ID supprimé.
	 * @return void
	 */
	private function remove_from_scan_cache( $attachment_id ) {
		$scan = Gi_Toolkit_Unused_Media_Scanner::get_scan_results();
		if ( ! $scan || empty( $scan['unused_ids'] ) ) {
			return;
		}
		$scan['unused_ids'] = array_values(
			array_diff( array_map( 'intval', $scan['unused_ids'] ), array( (int) $attachment_id ) )
		);
		update_option( Gi_Toolkit_Unused_Media_Scanner::OPTION_SCAN, $scan, false );
	}

	/**
	 * @return void
	 */
	public function ajax_scan_batch() {
		check_ajax_referer( 'gi_toolkit_unused_media', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'gi-toolkit' ) ) );
		}

		try {
			if ( isset( $_POST['reset'] ) && '1' === (string) wp_unslash( $_POST['reset'] ) ) {
				Gi_Toolkit_Unused_Media_Scanner::reset_scan();
			}

			wp_send_json_success( Gi_Toolkit_Unused_Media_Scanner::process_batch() );
		} catch ( Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Erreur lors de l’analyse : %s', 'gi-toolkit' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * @param array<string, mixed>|null $scan Résultats scan.
	 * @return array{unused: int, used: int, total: int, date: string, has_scan: bool}
	 */
	private function get_scan_summary( $scan ) {
		if ( ! is_array( $scan ) ) {
			return array(
				'unused'    => 0,
				'used'      => 0,
				'total'     => 0,
				'date'      => '',
				'has_scan'  => false,
			);
		}

		$unused = count( $scan['unused_ids'] ?? array() );
		$total  = (int) ( $scan['total_attachments'] ?? 0 );
		$used   = (int) ( $scan['used_count'] ?? max( 0, $total - $unused ) );
		$date   = '';

		if ( ! empty( $scan['scanned_at'] ) ) {
			$date = date_i18n(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $scan['scanned_at'] )
			);
		}

		return array(
			'unused'   => $unused,
			'used'     => $used,
			'total'    => $total,
			'date'     => $date,
			'has_scan' => true,
		);
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
			'gi-toolkit-unused-media',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/unused-media.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-unused-media',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/unused-media.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-unused-media',
			'giToolkitUnusedMedia',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gi_toolkit_unused_media' ),
				'i18n'    => array(
					'scanning'      => __( 'Analyse en cours…', 'gi-toolkit' ),
					'done'          => __( 'Analyse terminée.', 'gi-toolkit' ),
					'error'         => __( 'Erreur lors de l’analyse.', 'gi-toolkit' ),
					'confirmDelete' => __( 'Supprimer définitivement les médias sélectionnés ?', 'gi-toolkit' ),
				),
			)
		);

		$scan    = Gi_Toolkit_Unused_Media_Scanner::get_scan_results();
		$summary = $this->get_scan_summary( $scan );

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body gi-toolkit-unused-media-page">
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET['gi_unused_deleted'] ) ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: number deleted */
							__( '%d média(s) supprimé(s).', 'gi-toolkit' ),
							(int) $_GET['gi_unused_deleted']
						)
					)
				);
			}
			?>

			<p class="gi-unused-media-intro">
				<?php esc_html_e( 'Repérez les fichiers média qui ne sont référencés ni comme pièce jointe, ni comme image à la une, ni dans le contenu des publications (publiées et brouillons).', 'gi-toolkit' ); ?>
			</p>

			<div class="gi-unused-media-toolbar">
				<div class="gi-unused-media-scan-panel">
					<button type="button" class="button button-primary button-hero" id="gi-unused-media-start-scan">
						<?php esc_html_e( 'Analyser la médiathèque', 'gi-toolkit' ); ?>
					</button>
					<div class="gi-unused-media-scan-progress-wrap" hidden>
						<progress id="gi-unused-media-scan-progress" max="100" value="0"></progress>
						<span id="gi-unused-media-scan-percent" class="gi-unused-media-scan-percent">0%</span>
					</div>
					<p id="gi-unused-media-scan-status" class="gi-unused-media-scan-status description"></p>
				</div>
			</div>

			<?php if ( $summary['has_scan'] ) : ?>
				<div class="gi-unused-media-stats">
					<div class="gi-unused-media-stat gi-unused-media-stat--unused">
						<span class="gi-unused-media-stat__value"><?php echo esc_html( (string) $summary['unused'] ); ?></span>
						<span class="gi-unused-media-stat__label"><?php esc_html_e( 'Non utilisés', 'gi-toolkit' ); ?></span>
					</div>
					<div class="gi-unused-media-stat gi-unused-media-stat--ok">
						<span class="gi-unused-media-stat__value"><?php echo esc_html( (string) $summary['used'] ); ?></span>
						<span class="gi-unused-media-stat__label"><?php esc_html_e( 'Utilisés', 'gi-toolkit' ); ?></span>
					</div>
					<div class="gi-unused-media-stat">
						<span class="gi-unused-media-stat__value"><?php echo esc_html( (string) $summary['total'] ); ?></span>
						<span class="gi-unused-media-stat__label"><?php esc_html_e( 'Total médiathèque', 'gi-toolkit' ); ?></span>
					</div>
					<?php if ( $summary['date'] ) : ?>
						<div class="gi-unused-media-stat">
							<span class="gi-unused-media-stat__value gi-unused-media-stat__value--sm"><?php echo esc_html( $summary['date'] ); ?></span>
							<span class="gi-unused-media-stat__label"><?php esc_html_e( 'Dernière analyse', 'gi-toolkit' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="gi-unused-media-panel">
				<div class="gi-unused-media-panel__head">
					<h2 class="gi-unused-media-panel__title"><?php esc_html_e( 'Médias non utilisés', 'gi-toolkit' ); ?></h2>
					<?php if ( $summary['has_scan'] && $summary['unused'] > 0 ) : ?>
						<p class="gi-unused-media-panel__hint">
							<?php esc_html_e( 'Sélectionnez des lignes puis « Supprimer ». Survolez l’aperçu pour agrandir.', 'gi-toolkit' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<form method="post" class="gi-unused-media-form">
					<?php wp_nonce_field( 'gi_toolkit_unused_media_bulk', 'gi_toolkit_unused_media_nonce' ); ?>
					<div class="gi-unused-media-table-wrap">
						<?php
						$table = new Gi_Toolkit_Unused_Media_List_Table( $this );
						$table->prepare_items();
						$table->search_box( __( 'Rechercher', 'gi-toolkit' ), 'gi-unused-media' );
						$table->display();
						?>
					</div>
				</form>
			</div>
		</div>
		<?php
		echo '</div>';
	}
}
