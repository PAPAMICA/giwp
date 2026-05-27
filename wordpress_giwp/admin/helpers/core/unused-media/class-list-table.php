<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Liste des médias non utilisés.
 */
class Gi_Toolkit_Unused_Media_List_Table extends WP_List_Table {

	/**
	 * @var Gi_Toolkit_Unused_Media
	 */
	private $module;

	/**
	 * @param Gi_Toolkit_Unused_Media $module Module parent.
	 */
	public function __construct( Gi_Toolkit_Unused_Media $module ) {
		$this->module = $module;
		parent::__construct(
			array(
				'singular' => 'gi-toolkit-unused-media',
				'plural'   => 'gi-toolkit-unused-media-items',
				'ajax'     => false,
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'preview'  => __( 'Aperçu', 'gi-toolkit' ),
			'name'     => __( 'Nom', 'gi-toolkit' ),
			'date'     => __( 'Date', 'gi-toolkit' ),
			'size'     => __( 'Taille', 'gi-toolkit' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Supprimer', 'gi-toolkit' ),
		);
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="media[]" value="%d" />',
			(int) $item->ID
		);
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_preview( $item ) {
		$thumb = wp_get_attachment_image_src( $item->ID, 'thumbnail' );
		$full  = wp_get_attachment_image_src( $item->ID, 'medium' );
		$src   = $thumb ? $thumb[0] : '';
		$large = $full ? $full[0] : $src;

		if ( ! $src && wp_attachment_is_image( $item->ID ) ) {
			$src = includes_url( 'images/media/document.png' );
		}

		$html  = '<div class="gi-unused-media-preview" data-preview="' . esc_url( $large ) . '" title="' . esc_attr__( 'Survoler pour agrandir', 'gi-toolkit' ) . '">';
		if ( $src ) {
			$html .= '<img src="' . esc_url( $src ) . '" alt="" width="56" height="56" loading="lazy" />';
		} else {
			$html .= '<span class="dashicons dashicons-media-default"></span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_name( $item ) {
		$title   = $item->post_title ? $item->post_title : basename( get_attached_file( $item->ID ) );
		$actions = array();

		$edit = get_edit_post_link( $item->ID, 'raw' );
		if ( $edit ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit ),
				esc_html__( 'Modifier', 'gi-toolkit' )
			);
		}

		$view = wp_get_attachment_url( $item->ID );
		if ( $view ) {
			$actions['view'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $view ),
				esc_html__( 'Voir le fichier', 'gi-toolkit' )
			);
		}

		return sprintf(
			'<strong class="gi-unused-media-title">%s</strong><br><span class="gi-unused-media-id">#%d</span>%s',
			esc_html( $title ),
			(int) $item->ID,
			$this->row_actions( $actions )
		);
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_date( $item ) {
		return esc_html( get_the_date( '', $item ) );
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_size( $item ) {
		$path = get_attached_file( $item->ID );
		if ( ! $path || ! file_exists( $path ) ) {
			return '—';
		}
		return esc_html( size_format( (int) filesize( $path ) ) );
	}

	/**
	 * @param object $item Ligne.
	 * @param string $column_name Colonne.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * @return void
	 */
	public function prepare_items() {
		$scan = Gi_Toolkit_Unused_Media_Scanner::get_scan_results();
		$ids  = ( $scan && ! empty( $scan['unused_ids'] ) ) ? array_map( 'intval', $scan['unused_ids'] ) : array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$per_page = 25;
		$page     = $this->get_pagenum();

		if ( empty( $ids ) ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per_page,
				)
			);
			return;
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post__in'       => $ids,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * @return void
	 */
	public function no_items() {
		$scan = Gi_Toolkit_Unused_Media_Scanner::get_scan_results();
		if ( ! $scan ) {
			esc_html_e( 'Lancez une analyse pour lister les médias non utilisés.', 'gi-toolkit' );
			return;
		}
		esc_html_e( 'Aucun média non utilisé détecté. Votre médiathèque est propre.', 'gi-toolkit' );
	}
}
