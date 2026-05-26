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
	 * Suppression / renvoi en masse.
	 */
	protected function process_bulk_action() {
		$action = $this->current_action();
		if ( ! in_array( $action, array( 'delete', 'resend' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = isset( $_REQUEST[ $this->_args['singular'] ] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST[ $this->_args['singular'] ] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->mail_catcher->redirect_with_notice( 'deleted', 0 );
		}

		if ( 'delete' === $action ) {
			$deleted = $this->mail_catcher->delete_logs( $ids );
			$this->mail_catcher->redirect_with_notice( 'deleted', $deleted );
		}

		if ( 'resend' === $action ) {
			$ok    = 0;
			$fail  = 0;
			foreach ( $ids as $id ) {
				$result = $this->mail_catcher->resend_log( $id );
				if ( is_wp_error( $result ) ) {
					++$fail;
				} else {
					++$ok;
				}
			}

			if ( $fail > 0 && $ok > 0 ) {
				$this->mail_catcher->redirect_with_notice( 'resend_partial', $ok, (string) $fail );
			} elseif ( $fail > 0 ) {
				$this->mail_catcher->redirect_with_notice( 'resend_error', 0, __( 'Aucun e-mail n’a pu être renvoyé.', 'gi-toolkit' ) );
			} else {
				$this->mail_catcher->redirect_with_notice( 'resent', $ok );
			}
		}
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
			'id'         => __( 'ID', 'gi-toolkit' ),
			'receiver'   => __( 'Destinataire', 'gi-toolkit' ),
			'subject'    => __( 'Objet', 'gi-toolkit' ),
			'status'     => __( 'Statut', 'gi-toolkit' ),
			'mail_error' => __( 'Erreur', 'gi-toolkit' ),
			'resent'     => __( 'Renvois', 'gi-toolkit' ),
			'time'       => __( 'Date', 'gi-toolkit' ),
			'actions'    => '',
		);
	}

	/**
	 * @return array<string, array<int, bool>>
	 */
	public function get_sortable_columns() {
		return array(
			'time' => array( 'unixtime', true ),
		);
	}

	/**
	 * @return array<int, string>
	 */
	public function get_hidden_columns() {
		return array( 'id' );
	}

	/**
	 * @param array<string, mixed> $item        Ligne.
	 * @param string               $column_name Colonne.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'receiver':
				return wp_kses_post( nl2br( str_replace( '\n', "\n", $item['receiver'] ) ) );
			case 'subject':
				return esc_html( $item['subject'] );
			case 'status':
				return $this->get_status_badge( $item );
			case 'mail_error':
				$error = $item['error'] ?? '';
				if ( empty( $error ) ) {
					return '<span class="gi-toolkit-mail-catcher-muted">—</span>';
				}
				return '<span class="gi-toolkit-mail-catcher-error" title="' . esc_attr( $error ) . '">' . esc_html( wp_html_excerpt( $error, 80, '…' ) ) . '</span>';
			case 'resent':
				$count = absint( $item['resent_count'] ?? 0 );
				if ( $count < 1 ) {
					return '<span class="gi-toolkit-mail-catcher-muted">—</span>';
				}
				$last = ! empty( $item['last_resent_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['last_resent_at'] ) : '';
				return '<span class="gi-toolkit-mail-catcher-resent" title="' . esc_attr( $last ) . '">' . esc_html( (string) $count ) . '</span>';
			case 'time':
				return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['unixtime'] ) );
			case 'actions':
				return $this->get_actions_html( $item );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $item Ligne.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item['id'] )
		);
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
		$failed = ! empty( $item['error'] );
		if ( $failed ) {
			return '<span class="gi-toolkit-mail-catcher-badge gi-toolkit-mail-catcher-badge--failed">' . esc_html__( 'Échec', 'gi-toolkit' ) . '</span>';
		}
		return '<span class="gi-toolkit-mail-catcher-badge gi-toolkit-mail-catcher-badge--success">' . esc_html__( 'Envoyé', 'gi-toolkit' ) . '</span>';
	}

	/**
	 * Boutons d’action par ligne.
	 *
	 * @param array<string, mixed> $item Ligne.
	 */
	private function get_actions_html( $item ) {
		ob_start();
		?>
		<button class="gi-toolkit-view" type="button" name="view" value="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Aperçu', 'gi-toolkit' ); ?>">
			<?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/eye.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?>
		</button>
		<button class="gi-toolkit-resend" type="submit" name="resend" value="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Renvoyer', 'gi-toolkit' ); ?>">
			<span class="dashicons dashicons-email-alt"></span>
		</button>
		<button class="button-link-delete gi-toolkit-delete" type="submit" name="delete" value="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Supprimer', 'gi-toolkit' ); ?>">
			<?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/delete.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?>
		</button>
		<?php
		return ob_get_clean();
	}
}
