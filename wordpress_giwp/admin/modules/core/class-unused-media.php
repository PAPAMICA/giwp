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

		if ( isset( $_POST['reset'] ) && '1' === (string) wp_unslash( $_POST['reset'] ) ) {
			Gi_Toolkit_Unused_Media_Scanner::reset_scan();
		}

		$result = Gi_Toolkit_Unused_Media_Scanner::process_batch();
		wp_send_json_success( $result );
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
					'scanning'       => __( 'Analyse en cours…', 'gi-toolkit' ),
					'error'          => __( 'Erreur lors de l’analyse.', 'gi-toolkit' ),
					'confirmDelete'  => __( 'Supprimer définitivement les médias sélectionnés ?', 'gi-toolkit' ),
				),
			)
		);

		$scan = Gi_Toolkit_Unused_Media_Scanner::get_scan_results();

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;">
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET['gi_unused_deleted'] ) ) {
				printf(
					'<div class="notice notice-success inline"><p>%s</p></div>',
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
			<p><?php esc_html_e( 'Repérez les fichiers média qui ne sont référencés ni comme pièce jointe, ni comme image à la une, ni dans le contenu des publications.', 'gi-toolkit' ); ?></p>

			<div class="gi-unused-media-scan-bar">
				<button type="button" class="button button-primary" id="gi-unused-media-start-scan">
					<?php esc_html_e( 'Analyser la médiathèque', 'gi-toolkit' ); ?>
				</button>
				<progress id="gi-unused-media-scan-progress" max="100" value="0"></progress>
				<p id="gi-unused-media-scan-status" class="description"></p>
			</div>

			<?php if ( $scan ) : ?>
				<p class="gi-unused-media-stats">
					<?php
					printf(
						esc_html__(
							'Dernière analyse : %1$s — %2$d médias non utilisés sur %3$d.',
							'gi-toolkit'
						),
						esc_html(
							date_i18n(
								get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
								strtotime( $scan['scanned_at'] )
							)
						),
						count( $scan['unused_ids'] ?? array() ),
						(int) ( $scan['total_attachments'] ?? 0 )
					);
					?>
				</p>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'gi_toolkit_unused_media_bulk', 'gi_toolkit_unused_media_nonce' ); ?>
				<?php
				$table = new Gi_Toolkit_Unused_Media_List_Table( $this );
				$table->prepare_items();
				$table->search_box( __( 'Rechercher', 'gi-toolkit' ), 'gi-unused-media' );
				$table->display();
				?>
			</form>
		</div>
		<?php
		echo '</div>';
	}
}
