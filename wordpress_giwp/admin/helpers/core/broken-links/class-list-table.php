<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Résultats du dernier scan (liens cassés).
 */
class Gi_Toolkit_Broken_Links_List_Table extends WP_List_Table {

	/**
	 * @var int
	 */
	private $scan_id;

	/**
	 * @param int $scan_id ID scan.
	 */
	public function __construct( $scan_id = 0 ) {
		$this->scan_id = $scan_id > 0 ? $scan_id : (int) Gi_Toolkit_Broken_Links_DB::get_latest_scan_id();
		parent::__construct(
			array(
				'singular' => 'gi-broken-link',
				'plural'   => 'gi-broken-links',
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'url'        => __( 'URL', 'gi-toolkit' ),
			'source'     => __( 'Page source', 'gi-toolkit' ),
			'link_text'  => __( 'Texte du lien', 'gi-toolkit' ),
			'status'     => __( 'Code HTTP', 'gi-toolkit' ),
			'checked_at' => __( 'Vérifié le', 'gi-toolkit' ),
		);
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_url( $item ) {
		return '<a href="' . esc_url( $item->url ) . '" target="_blank" rel="noopener">' . esc_html( $item->url ) . '</a>';
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_source( $item ) {
		$post_id = (int) $item->source_post_id;
		if ( $post_id < 1 ) {
			return '—';
		}
		$title = get_the_title( $post_id );
		$link  = get_edit_post_link( $post_id );
		if ( $link ) {
			return '<a href="' . esc_url( $link ) . '">' . esc_html( $title ? $title : '#' . $post_id ) . '</a>';
		}
		return esc_html( $title );
	}

	/**
	 * @param object $item Ligne.
	 * @return string
	 */
	protected function column_status( $item ) {
		$code = (int) $item->http_status;
		$msg  = $item->error_message ? ' — ' . $item->error_message : '';
		return '<code>' . esc_html( (string) $code ) . '</code>' . esc_html( $msg );
	}

	/**
	 * @param object $item Ligne.
	 * @param string $column_name Colonne.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'checked_at' === $column_name ) {
			return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->checked_at ) );
		}
		if ( 'link_text' === $column_name ) {
			return esc_html( $item->link_text ? $item->link_text : '—' );
		}
		return '';
	}

	/**
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		if ( $this->scan_id < 1 ) {
			$this->items = array();
			$this->set_pagination_args( array( 'total_items' => 0, 'per_page' => 25 ) );
			return;
		}

		$per_page = 25;
		$page     = $this->get_pagenum();
		$offset   = ( $page - 1 ) * $per_page;
		if ( ! Gi_Toolkit_Broken_Links_DB::tables_ready() ) {
			$this->items = array();
			$this->set_pagination_args( array( 'total_items' => 0, 'per_page' => 25 ) );
			return;
		}

		$table    = Gi_Toolkit_Broken_Links_DB::links_table();

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE scan_id = %d", $this->scan_id )
		);

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE scan_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				$this->scan_id,
				$per_page,
				$offset
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * @return void
	 */
	public function no_items() {
		if ( $this->scan_id < 1 ) {
			esc_html_e( 'Aucun scan pour le moment. Lancez un scan pour détecter les liens cassés.', 'gi-toolkit' );
			return;
		}
		esc_html_e( 'Aucun lien cassé détecté lors du dernier scan.', 'gi-toolkit' );
	}
}
