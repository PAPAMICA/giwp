<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Tableau des e-mails capturés.
 *
 * @since 2.14.0
 */
class Gi_Toolkit_Mail_Catcher_Logs_List_Table extends WP_List_Table {

	/**
	 * Instance du module Mail catcher.
	 *
	 * @var Gi_Toolkit_Mail_Catcher
	 */
	protected $mail_catcher;

	/**
	 * Notices à afficher.
	 *
	 * @var array<int, array<string, string>>
	 */
	private $notices = array();

	/**
	 * @param Gi_Toolkit_Mail_Catcher $mail_catcher Module parent.
	 */
	public function __construct( Gi_Toolkit_Mail_Catcher $mail_catcher ) {
		$this->mail_catcher = $mail_catcher;

		parent::__construct(
			array(
				'singular' => 'gi_toolkit-mail-catcher-log',
				'plural'   => 'gi_toolkit-mail-catcher-logs',
				'ajax'     => false,
				'screen'   => 'gi_toolkit-mail-catcher-logs',
			)
		);
	}

	/**
	 * Actions groupées disponibles.
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Supprimer', 'gi-toolkit' ),
			'resend' => __( 'Renvoyer', 'gi-toolkit' ),
		);
	}

	/**
	 * Traite les actions groupées puis charge les lignes.
	 *
	 * @param bool $search Ignoré (compatibilité).
	 */
	public function prepare_items( $search = false ) {
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = (int) sanitize_text_field( wp_unslash( $_GET['per_page'] ?? '0' ) );
		$per_page = ( $per_page < 1 ) ? 25 : $per_page;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = $this->mail_catcher->get_logs_items( $per_page, $offset );

		$this->set_pagination_args(
			array(
				'total_items' => $this->mail_catcher->get_logs_items( $per_page, $offset, true ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Suppression / renvoi en masse (traité sur admin_init dans save_submenu).
	 */
	protected function process_bulk_action() {
		// Les actions groupées sont gérées avant le rendu HTML (class-mail-catcher.php).
	}

	/**
	 * @return array<int|string, string>
	 */
	public function get_views() {
		$views    = array();
		$statuses = array(
			0 => __( 'Tous', 'gi-toolkit' ),
			1 => __( 'Réussis', 'gi-toolkit' ),
			2 => __( 'Échoués', 'gi-toolkit' ),
			3 => __( 'Spam / RBL', 'gi-toolkit' ),
		);

		$email_log_page_url = add_query_arg( 'page', $this->mail_catcher->page_id, admin_url( 'admin.php' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '0' ) );

		foreach ( $statuses as $status => $label ) {
			$views[ $status ] = sprintf(
				'<a href="%1$s" %2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'status', $status, $email_log_page_url ) ),
				$current_status === (string) $status ? 'class="current"' : '',
				esc_html( $label ),
				absint( $this->mail_catcher->get_logs_count( $status ) )
			);
		}

		return $views;
	}

	/**
	 * @param string $text     Libellé.
	 * @param string $input_id ID champ recherche.
	 */
	public function search_box( $text, $input_id ) {
		if ( ! $this->has_items() && empty( $_REQUEST['search']['term'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search_place = ! empty( $_REQUEST['search']['place'] ) ? sanitize_key( $_REQUEST['search']['place'] ) : '';
		$search_term  = ! empty( $_REQUEST['search']['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search']['term'] ) ) : '';

		if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array( 'timestamp', 'host', 'receiver', 'subject' ), true ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_key( $_REQUEST['orderby'] ) ) . '" />';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			$order = strtoupper( sanitize_key( $_REQUEST['order'] ) );
			$order = 'ASC' === $order ? 'ASC' : 'DESC';
			echo '<input type="hidden" name="order" value="' . esc_attr( $order ) . '" />';
		}
		// phpcs:enable
		?>

		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<select name="search[place]">
				<option value="receiver" <?php selected( 'receiver', $search_place ); ?>><?php esc_html_e( 'Destinataire', 'gi-toolkit' ); ?></option>
				<option value="subject" <?php selected( 'subject', $search_place ); ?>><?php esc_html_e( 'Objet', 'gi-toolkit' ); ?></option>
				<option value="message" <?php selected( 'message', $search_place ); ?>><?php esc_html_e( 'Message', 'gi-toolkit' ); ?></option>
			</select>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="search[term]" value="<?php echo esc_attr( $search_term ); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>

		<?php
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'time'       => __( 'Jour', 'gi-toolkit' ),
			'receiver'   => __( 'Destinataire', 'gi-toolkit' ),
			'subject'    => __( 'Sujet', 'gi-toolkit' ),
			'mail_error' => __( 'Erreur', 'gi-toolkit' ),
			'actions'    => __( 'Actions', 'gi-toolkit' ),
		);
	}

	/**
	 * @return array<string, array<int, bool>>
	 */
	public function get_sortable_columns() {
		return array(
			'time'     => array( 'unixtime', true ),
			'receiver' => array( 'receiver', false ),
			'subject'  => array( 'subject', false ),
		);
	}

	/**
	 * @return array<int, string>
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" class="gi-toolkit-mail-catcher-row-cb" name="%1$s[]" value="%2$s" aria-label="%3$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item['id'] ),
			esc_attr__( 'Sélectionner cet e-mail', 'gi-toolkit' )
		);
	}

	/**
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'time';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_receiver( $item ) {
		return '<span class="gi-toolkit-mail-catcher-cell-receiver">' . wp_kses_post( nl2br( str_replace( '\n', "\n", $item['receiver'] ?? '' ) ) ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_subject( $item ) {
		return '<span class="gi-toolkit-mail-catcher-cell-subject">' . esc_html( $item['subject'] ?? '' ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_status( $item ) {
		return $this->get_status_badge( $item );
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_mail_error( $item ) {
		$error = $item['error'] ?? '';
		if ( empty( $error ) ) {
			return '<span class="gi-toolkit-mail-catcher-muted">—</span>';
		}
		return '<span class="gi-toolkit-mail-catcher-error" title="' . esc_attr( $error ) . '">' . esc_html( wp_html_excerpt( $error, 80, '…' ) ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_resent( $item ) {
		$count = absint( $item['resent_count'] ?? 0 );
		if ( $count < 1 ) {
			return '<span class="gi-toolkit-mail-catcher-muted">—</span>';
		}
		$last = ! empty( $item['last_resent_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['last_resent_at'] ) : '';
		return '<span class="gi-toolkit-mail-catcher-resent" title="' . esc_attr( $last ) . '">' . esc_html( (string) $count ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_time( $item ) {
		$timestamp = (int) ( $item['unixtime'] ?? 0 );
		return '<span class="gi-toolkit-mail-catcher-cell-time">' . esc_html( wp_date( 'Y-m-d H:i:s', $timestamp ) ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	protected function column_actions( $item ) {
		return $this->get_actions_html( $item );
	}

	/**
	 * Affiche les notices internes (legacy).
	 */
	public function display_notices() {
		foreach ( $this->notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible inline"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Badge succès / échec.
	 *
	 * @param array<string, mixed> $item Ligne.
	 */
	private function get_status_badge( $item ) {
		$status = Gi_Toolkit_Mail_Catcher::get_row_send_status( $item );
		if ( Gi_Toolkit_Mail_Catcher::SEND_STATUS_FAILED === $status ) {
			return '<span class="gi-toolkit-mail-catcher-badge gi-toolkit-mail-catcher-badge--failed">' . esc_html__( 'Échec', 'gi-toolkit' ) . '</span>';
		}
		if ( Gi_Toolkit_Mail_Catcher::SEND_STATUS_SPAM === $status ) {
			return '<span class="gi-toolkit-mail-catcher-badge gi-toolkit-mail-catcher-badge--spam">' . esc_html__( 'Spam / RBL', 'gi-toolkit' ) . '</span>';
		}
		return '<span class="gi-toolkit-mail-catcher-badge gi-toolkit-mail-catcher-badge--success">' . esc_html__( 'Envoyé', 'gi-toolkit' ) . '</span>';
	}

	/**
	 * Boutons d’action par ligne.
	 *
	 * @param array<string, mixed> $item Ligne.
	 */
	private function get_actions_html( $item ) {
		$id = absint( $item['id'] ?? 0 );
		ob_start();
		?>
		<div class="gi-toolkit-mail-catcher-actions">
			<button type="button" class="gi-toolkit-mail-catcher-action gi-toolkit-mail-catcher-action--view gi-toolkit-view" data-email-id="<?php echo esc_attr( (string) $id ); ?>" title="<?php esc_attr_e( 'Aperçu', 'gi-toolkit' ); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Aperçu', 'gi-toolkit' ); ?></span>
			</button>
			<button type="submit" class="gi-toolkit-mail-catcher-action gi-toolkit-mail-catcher-action--resend gi-toolkit-resend" name="resend" value="<?php echo esc_attr( (string) $id ); ?>" title="<?php esc_attr_e( 'Renvoyer', 'gi-toolkit' ); ?>">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Renvoyer', 'gi-toolkit' ); ?></span>
			</button>
			<button type="submit" class="gi-toolkit-mail-catcher-action gi-toolkit-mail-catcher-action--delete gi-toolkit-delete" name="delete" value="<?php echo esc_attr( (string) $id ); ?>" title="<?php esc_attr_e( 'Supprimer', 'gi-toolkit' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Supprimer', 'gi-toolkit' ); ?></span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}
}
