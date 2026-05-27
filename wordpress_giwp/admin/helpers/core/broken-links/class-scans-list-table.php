<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Historique des scans de liens.
 */
class Gi_Toolkit_Broken_Links_Scans_List_Table extends WP_List_Table {

	/**
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'id'           => __( 'ID', 'gi-toolkit' ),
			'started_at'   => __( 'Début', 'gi-toolkit' ),
			'finished_at'  => __( 'Fin', 'gi-toolkit' ),
			'trigger_type' => __( 'Déclencheur', 'gi-toolkit' ),
			'urls_checked' => __( 'URLs vérifiées', 'gi-toolkit' ),
			'broken_count' => __( 'Liens cassés', 'gi-toolkit' ),
			'status'       => __( 'Statut', 'gi-toolkit' ),
		);
	}

	/**
	 * @param object $item Ligne.
	 * @param string $column_name Colonne.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'started_at':
			case 'finished_at':
				return $item->$column_name
					? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->$column_name ) )
					: '—';
			case 'trigger_type':
				return 'cron' === $item->trigger_type
					? esc_html__( 'Automatique', 'gi-toolkit' )
					: esc_html__( 'Manuel', 'gi-toolkit' );
			default:
				return esc_html( (string) $item->$column_name );
		}
	}

	/**
	 * @return void
	 */
	public function prepare_items() {
		$this->items = Gi_Toolkit_Broken_Links_DB::get_recent_scans( 20 );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => count( $this->items ),
				'per_page'    => 20,
			)
		);
	}
}
