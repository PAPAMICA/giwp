<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : dossiers dans la médiathèque (taxonomie gi_media_folder).
 */
class Gi_Toolkit_Media_Folders {

	const OPTION_SETTINGS = 'gi_toolkit_media_folders_settings';

	const CRON_HOOK = 'gi_toolkit_media_folders_auto_sort';

	private $page_slug = 'gi-toolkit-settings-media-folders';

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Dossiers média', 'gi-toolkit' );

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/media-folders/class-taxonomy.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/media-folders/class-auto-sort.php';

		add_action( 'init', array( $this, 'register_taxonomy' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_bootstrap_terms' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );

		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_list_table' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_folder_filter' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );

		add_action( 'wp_ajax_gi_toolkit_media_folders_tree', array( $this, 'ajax_tree' ) );
		add_action( 'wp_ajax_gi_toolkit_media_folders_create', array( $this, 'ajax_create' ) );
		add_action( 'wp_ajax_gi_toolkit_media_folders_rename', array( $this, 'ajax_rename' ) );
		add_action( 'wp_ajax_gi_toolkit_media_folders_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_gi_toolkit_media_folders_move', array( $this, 'ajax_move' ) );
		add_action( 'wp_ajax_gi_toolkit_media_folders_auto_sort', array( $this, 'ajax_auto_sort' ) );

		add_action( 'add_attachment', array( $this, 'maybe_sort_on_upload' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_cron_sort' ) );
		add_action( 'wp_ajax_gi_toolkit_media_folders_list_attachments', array( $this, 'ajax_list_attachments' ) );
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
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public function register_taxonomy() {
		Gi_Toolkit_Media_Folders_Taxonomy::register();
	}

	/**
	 * @return void
	 */
	public function maybe_bootstrap_terms() {
		Gi_Toolkit_Media_Folders_Taxonomy::ensure_root_folders();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_settings() {
		$defaults = array(
			'auto_on_upload'    => '0',
			'cron_enabled'      => '0',
			'move_logo_header'  => '0',
		);
		$stored   = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return void
	 */
	private function save_settings( $settings ) {
		update_option( self::OPTION_SETTINGS, $settings, false );
		$this->sync_cron( $settings );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return void
	 */
	private function sync_cron( $settings ) {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( '1' === (string) ( $settings['cron_enabled'] ?? '0' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
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
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, 'gi_toolkit_media_folders_save' ) ) {
			return;
		}

		$settings = $this->get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['auto_on_upload']   = isset( $_POST['auto_on_upload'] ) ? '1' : '0';
		$settings['cron_enabled']     = isset( $_POST['cron_enabled'] ) ? '1' : '0';
		$settings['move_logo_header'] = isset( $_POST['move_logo_header'] ) ? '1' : '0';

		$this->save_settings( $settings );

		if ( '1' === $settings['move_logo_header'] ) {
			Gi_Toolkit_Media_Folders_Auto_Sort::move_custom_logo_to_header();
		}

		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
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
			'gi-toolkit-media-folders',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/media-folders.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-media-folders-admin',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/media-folders-admin.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-media-folders-admin',
			'giToolkitMediaFolders',
			$this->get_js_config()
		);

		$settings = $this->get_settings();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		?>
		<div class="gi-toolkit__body gi-toolkit-media-folders-page" style="padding:1rem 1.5rem 2rem;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<div class="gi-toolkit-media-folders-layout">
				<aside class="gi-toolkit-media-folders-sidebar">
					<h3><?php esc_html_e( 'Dossiers', 'gi-toolkit' ); ?></h3>
					<div id="gi-media-folders-tree" class="gi-media-folders-tree" aria-live="polite"></div>
					<p>
						<input type="text" id="gi-media-folder-new-name" class="regular-text" placeholder="<?php esc_attr_e( 'Nouveau dossier…', 'gi-toolkit' ); ?>" />
						<button type="button" class="button" id="gi-media-folder-create"><?php esc_html_e( 'Créer', 'gi-toolkit' ); ?></button>
					</p>
				</aside>

				<main class="gi-toolkit-media-folders-main">
					<h3><?php esc_html_e( 'Médias du dossier', 'gi-toolkit' ); ?> <span id="gi-media-folder-current-label"></span></h3>
					<p class="gi-media-folders-bulk">
						<select id="gi-media-folder-move-target">
							<option value=""><?php esc_html_e( 'Déplacer vers…', 'gi-toolkit' ); ?></option>
						</select>
						<button type="button" class="button" id="gi-media-folder-move-btn"><?php esc_html_e( 'Déplacer la sélection', 'gi-toolkit' ); ?></button>
						<button type="button" class="button" id="gi-media-folder-rename-btn"><?php esc_html_e( 'Renommer le dossier', 'gi-toolkit' ); ?></button>
						<button type="button" class="button button-link-delete" id="gi-media-folder-delete-btn"><?php esc_html_e( 'Supprimer le dossier', 'gi-toolkit' ); ?></button>
					</p>
					<div id="gi-media-folder-attachments" class="gi-media-folder-attachments"></div>
				</main>
			</div>

			<hr style="margin:2rem 0;" />

			<h3><?php esc_html_e( 'Auto-tri', 'gi-toolkit' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Classe automatiquement chaque média dans un dossier nommé d’après le slug de la première page où il apparaît (contenu ou image à la une). Les dossiers header et footer sont disponibles à la racine.', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( 'gi_toolkit_media_folders_save' ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1" />
				<p><label><input type="checkbox" name="auto_on_upload" value="1" <?php checked( $settings['auto_on_upload'], '1' ); ?> /> <?php esc_html_e( 'Trier automatiquement après chaque upload', 'gi-toolkit' ); ?></label></p>
				<p><label><input type="checkbox" name="cron_enabled" value="1" <?php checked( $settings['cron_enabled'], '1' ); ?> /> <?php esc_html_e( 'Exécuter le tri quotidien via cron', 'gi-toolkit' ); ?></label></p>
				<p><label><input type="checkbox" name="move_logo_header" value="1" <?php checked( $settings['move_logo_header'], '1' ); ?> /> <?php esc_html_e( 'Déplacer le logo du site vers le dossier header', 'gi-toolkit' ); ?></label></p>
				<?php submit_button( __( 'Enregistrer les réglages', 'gi-toolkit' ) ); ?>
			</form>
			<p>
				<button type="button" class="button button-primary" id="gi-media-folder-auto-sort-all"><?php esc_html_e( 'Trier tous les médias maintenant', 'gi-toolkit' ); ?></button>
			</p>
		</div>
		<?php
		echo '</div>';
	}

	/**
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public function enqueue_media_scripts( $hook ) {
		$screens = array( 'upload.php', 'post.php', 'post-new.php', 'media-upload.php' );
		$is_media = in_array( $hook, $screens, true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_modal = isset( $_GET['page'] ) && 'gi-toolkit-settings' === $_GET['page'];

		if ( ! $is_media && ! $is_modal ) {
			return;
		}

		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';
		wp_enqueue_style(
			'gi-toolkit-media-folders',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/media-folders.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-media-folders-media',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/media-folders-media.js',
			array( 'jquery', 'media-models', 'media-views', 'media-grid' ),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-media-folders-media',
			'giToolkitMediaFolders',
			$this->get_js_config()
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_js_config() {
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gi_toolkit_media_folders' ),
			'i18n'    => array(
				'allMedia'      => __( 'Tous les médias', 'gi-toolkit' ),
				'foldersTitle'  => __( 'Dossiers', 'gi-toolkit' ),
				'moveTo'        => __( 'Déplacer vers…', 'gi-toolkit' ),
				'moveSelection' => __( 'Déplacer la sélection', 'gi-toolkit' ),
				'confirmDelete' => __( 'Supprimer ce dossier ? Les médias ne seront pas supprimés.', 'gi-toolkit' ),
				'newName'       => __( 'Nouveau nom du dossier :', 'gi-toolkit' ),
				'selectMedia'   => __( 'Sélectionnez au moins un média.', 'gi-toolkit' ),
				'selectFolder'  => __( 'Choisissez un dossier de destination.', 'gi-toolkit' ),
				'sorting'       => __( 'Tri en cours…', 'gi-toolkit' ),
				'sortDone'      => __( 'Tri terminé.', 'gi-toolkit' ),
				'error'         => __( 'Erreur', 'gi-toolkit' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $query Args requête.
	 * @return array<string, mixed>
	 */
	public function filter_media_query( $query ) {
		$folder = self::get_active_folder_id( $query );
		if ( $folder > 0 ) {
			$query['tax_query'] = array(
				array(
					'taxonomy'         => Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $folder,
					'include_children' => true,
				),
			);
		}
		return $query;
	}

	/**
	 * ID dossier actif (médiathèque AJAX ou liste).
	 *
	 * @param array<string, mixed> $query Args query-attachments.
	 * @return int
	 */
	private static function get_active_folder_id( array $query = array() ) {
		if ( ! empty( $query['gi_media_folder'] ) ) {
			return absint( $query['gi_media_folder'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['gi_media_folder'] ) ) {
			return absint( $_REQUEST['gi_media_folder'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$req_query = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] )
			? wp_unslash( $_REQUEST['query'] )
			: array();
		if ( ! empty( $req_query['gi_media_folder'] ) ) {
			return absint( $req_query['gi_media_folder'] );
		}
		return 0;
	}

	/**
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function filter_list_table( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$folder = isset( $_GET['gi_media_folder'] ) ? absint( $_GET['gi_media_folder'] ) : 0;
		if ( $folder > 0 ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy'         => Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $folder,
						'include_children' => true,
					),
				)
			);
		}
	}

	/**
	 * @return void
	 */
	public function render_list_folder_filter() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['gi_media_folder'] ) ? absint( $_GET['gi_media_folder'] ) : 0;
		echo '<label class="screen-reader-text" for="gi-media-folder-filter">' . esc_html__( 'Dossier', 'gi-toolkit' ) . '</label>';
		echo '<select name="gi_media_folder" id="gi-media-folder-filter">';
		echo '<option value="">' . esc_html__( 'Tous les dossiers', 'gi-toolkit' ) . '</option>';
		foreach ( $terms as $term ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $term->term_id,
				selected( $current, (int) $term->term_id, false ),
				esc_html( $term->name )
			);
		}
		echo '</select>';
	}

	/**
	 * @return void
	 */
	private function verify_ajax() {
		check_ajax_referer( 'gi_toolkit_media_folders', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'gi-toolkit' ) ) );
		}
	}

	/**
	 * @return void
	 */
	public function ajax_tree() {
		$this->verify_ajax();
		wp_send_json_success(
			array(
				'tree'  => Gi_Toolkit_Media_Folders_Taxonomy::get_folder_tree(),
				'terms' => $this->flatten_terms_for_select(),
			)
		);
	}

	/**
	 * @return array<int, array{id: int, name: string}>
	 */
	private function flatten_terms_for_select() {
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $term ) {
			$out[] = array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
			);
		}
		return $out;
	}

	/**
	 * @return void
	 */
	public function ajax_create() {
		$this->verify_ajax();
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$result = Gi_Toolkit_Media_Folders_Taxonomy::create_folder( $name, $parent );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'term_id' => (int) ( $result['term_id'] ?? 0 ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_rename() {
		$this->verify_ajax();
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$result  = Gi_Toolkit_Media_Folders_Taxonomy::rename_folder( $term_id, $name );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	/**
	 * @return void
	 */
	public function ajax_delete() {
		$this->verify_ajax();
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$result  = Gi_Toolkit_Media_Folders_Taxonomy::delete_folder( $term_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	/**
	 * @return void
	 */
	public function ajax_move() {
		$this->verify_ajax();
		$ids     = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['attachment_ids'] ) ) : array();
		$folder  = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$manual  = ! isset( $_POST['manual'] ) || '1' === (string) wp_unslash( $_POST['manual'] );
		Gi_Toolkit_Media_Folders_Taxonomy::move_attachments( $ids, $folder, $manual );
		wp_send_json_success();
	}

	/**
	 * @return void
	 */
	public function ajax_list_attachments() {
		$this->verify_ajax();
		$folder = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$args   = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $folder > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $folder,
				),
			);
		}
		$posts = get_posts( $args );
		$items = array();
		foreach ( $posts as $post ) {
			$thumb = wp_get_attachment_image_src( $post->ID, 'thumbnail' );
			$items[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title ? $post->post_title : basename( get_attached_file( $post->ID ) ),
				'thumb' => $thumb ? $thumb[0] : '',
				'date'  => get_the_date( '', $post ),
			);
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * @return void
	 */
	public function ajax_auto_sort() {
		$this->verify_ajax();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'gi-toolkit' ) ) );
		}
		$overwrite = isset( $_POST['overwrite'] ) && '1' === (string) wp_unslash( $_POST['overwrite'] );
		$result    = Gi_Toolkit_Media_Folders_Auto_Sort::sort_all( $overwrite );
		$settings  = $this->get_settings();
		if ( '1' === $settings['move_logo_header'] ) {
			Gi_Toolkit_Media_Folders_Auto_Sort::move_custom_logo_to_header();
		}
		wp_send_json_success( $result );
	}

	/**
	 * @param int $attachment_id ID attachment.
	 * @return void
	 */
	public function maybe_sort_on_upload( $attachment_id ) {
		$settings = $this->get_settings();
		if ( '1' !== (string) $settings['auto_on_upload'] ) {
			return;
		}
		Gi_Toolkit_Media_Folders_Auto_Sort::sort_attachment( (int) $attachment_id, false );
	}

	/**
	 * @return void
	 */
	public function run_cron_sort() {
		Gi_Toolkit_Media_Folders_Auto_Sort::sort_all( false );
		$settings = $this->get_settings();
		if ( '1' === $settings['move_logo_header'] ) {
			Gi_Toolkit_Media_Folders_Auto_Sort::move_custom_logo_to_header();
		}
	}
}
