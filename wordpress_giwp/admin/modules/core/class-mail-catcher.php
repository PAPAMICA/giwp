<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Mail catcher
 * Description: Track every email WordPress sends with an easy-to-use admin viewer.
 * @since 2.14.0
 */
class Gi_Toolkit_Mail_Catcher {

	/**
	 * Instance unique (évite une double instanciation depuis la list table).
	 *
	 * @var self|null
	 */
	private static $instance = null;

	private $option_id;
    private $nonce;
    private $nonce_name;
	private $header_title;
	public $page_id;

	/**
     * Invoke the hooks.
     * 
     * @since   2.14.0
     */
    public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		$this->option_id  = GI_TOOLKIT_PLUGIN_SETTINGS . '_mail_catcher';
        $this->nonce      = $this->option_id . '_action';
        $this->nonce_name = $this->option_id . '_name';
		$this->page_id    = 'gi-toolkit-settings-mail-catcher';

		add_action( 'init', array( $this, 'class_init' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'admin_init', array( $this, 'show_email_message' ) );
		add_filter( 'wp_mail', array( $this, 'save_email_log' ), PHP_INT_MAX );
		add_action( 'wp_mail_failed', array( $this, 'save_email_failed_log' ) );
		add_action( 'wp_ajax_gi_toolkit_mail_catcher_preview', array( $this, 'mail_catcher_preview' ) );
	}

	/**
     * Initialize the class
	 * 
	 * @since   2.14.0
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Mail catcher', 'gi-toolkit' );
		$this->maybe_upgrade_table();
    }

	/**
	 * Retourne l’instance du module.
	 *
	 * @return self
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Add admin body class
	 * 
	 * @since   2.14.0
	 */
	public function admin_body_class( $classes ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && $_GET['page'] === $this->page_id ) {
			$classes .= ' gi-toolkit-modern-post-list';
		}

		return $classes;
	}

	/**
	 * Save email log
	 * 
	 * @since   2.14.0
	 */
	public function save_email_log( $atts ) {
		global $wpdb, $gi_toolkit_current_mail_id;

		if ( $this->is_reach_limit() ) {
			return $atts;
		}

		if ( ! is_array( $atts ) ) {
            return $atts;
        }

		try {
			$receiver    = $this->get_mail_receiver( $atts );
			$subject     = $this->get_mail_subject( $atts );
			$message     = $this->get_mail_message( $atts );
			$headers     = $this->get_mail_headers( $atts );
			$attachments = $this->get_mail_attachments( $atts );
			$host        = sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ?? '' ) );

			if ( ! $this->is_table_exist() ) {
				$this->create_table();
			}

			$data = array(
				'receiver'    => $receiver,
				'subject'     => $subject,
				'message'     => $message,
				'headers'     => $headers,
				'attachments' => $attachments,
				'error'       => '',
				'host'        => $host,
				'unixtime'    => time(),
			);
			$data_format = array(
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%d', // integer
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$this->get_table_name(),
				$data,
				$data_format
			);

			$gi_toolkit_current_mail_id = $wpdb->insert_id;	
		} catch (\Throwable $th) {
		}

		$this->add_to_limit_counter();

		return $atts;
	}

	/**
	 * Save email failed log
	 * 
	 * @since   2.14.0
	 */
	public function save_email_failed_log( $error ) {
		global $wpdb, $gi_toolkit_current_mail_id;

		if ( ! isset( $gi_toolkit_current_mail_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->get_table_name(),
			array( 'error' => $error->get_error_message() ),
			array( 'id' => $gi_toolkit_current_mail_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mail catcher preview AJAX
	 * 
	 * @since   2.14.0
	 */
	public function mail_catcher_preview() {
		gi_toolkit_ajax_require_cap( 'manage_options', $this->nonce );

		$email_id = sanitize_text_field( wp_unslash( $_POST['email_id'] ?? '' ) );
		if ( empty( $email_id ) ) {
			wp_send_json_error( __( 'This email does not exist.', 'gi-toolkit' ) );
		}

		$item = $this->render_preview( $email_id );
		if ( empty( $item ) ) {
			wp_send_json_error( __( 'This email does not exist.', 'gi-toolkit' ) );
		}

		wp_send_json_success( $item );
	}

	/**
	 * Get logs items
	 * 
	 * @since   2.14.0
	 */
	public function get_logs_items( $per_page, $offset, $count_only = false ) {
		global $wpdb;

		if ( $this->is_table_exist() ) {
			// phpcs:disable
			$status       = sanitize_text_field( wp_unslash( $_GET['status'] ?? '0' ) );
			$order_by     = sanitize_text_field( wp_unslash( $_GET['orderby'] ?? 'id' ) );
			$order        = sanitize_text_field( wp_unslash( $_GET['order'] ?? 'desc' ) );
			$search_place = sanitize_text_field( wp_unslash( $_REQUEST['search']['place'] ?? '' ) );
			$search       = sanitize_text_field( wp_unslash( $_REQUEST['search']['term'] ?? '' ) );
			// phpcs:enable

			// Build `SELECT` clause.
			if ( $count_only ) {
				$select = 'SELECT COUNT(*) ';
			} else {
				$select = 'SELECT * ';
			}
			$select .= 'FROM ' . esc_sql( $this->get_table_name() );

			$status_where = '';
			switch( $status ) {
				case 1:
					$status_where .= " WHERE `error` IS NULL OR `error` = ''";
				break;
				case 2:
					$status_where .= " WHERE `error` IS NOT NULL AND `error` != ''";
				break;
			}

			$search_where = '';
			if ( ! empty( $search ) ) {
				if ( empty( $status_where ) ) {
					$search_where = ' WHERE (';
				} else {
					$search_where .= ' AND (';
				}

				$search_where .=  '`' . esc_sql( $search_place ) . '` LIKE "%' . esc_sql( $search ) . '%" OR ';

				// Remove the last ' OR ' and add the closing ')';
				$search_where = substr( $search_where, 0, -4 ) . ')';
			}

			// Build query.
        	$query = $select . $status_where . $search_where;

			if ( $count_only ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            	$results = $wpdb->get_var( $query );
			} else {
				// SORT BY, LIMIT, and ORDER are only applicable if we're not counting the results.
				if ( ! empty( $order_by ) ) {
					$query .= ' ORDER BY `' . esc_sql( $order_by ) . '`';
	
					if ( ! empty( $order ) && in_array( $order, array( 'asc', 'desc' ), true ) ) {
						$query .= ' ' . esc_sql( $order );
					}
				}
	
				if ( ! empty( $per_page ) ) {
					$query .= ' LIMIT ' . absint( $per_page );
				}
	
				if ( ! empty( $offset ) ) {
					$query .= ' OFFSET ' . absint( $offset );
				}
	
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$results = $wpdb->get_results( $query, ARRAY_A );
			}

			if ( empty( $results ) ) {
				if ( $count_only ) {
					return 0;
				}

				return array();
			}

			return $results;
		} else {
			if ( $count_only ) {
				return 0;
			}

			return array();
		}
	}

	/**
	 * Get logs count
	 * 
	 * @since   2.14.0
	 */
	public function get_logs_count( $status = null ) {
		global $wpdb;

		if ( $this->is_table_exist() ) {

			$status_where = '';
			switch( $status ) {
				case 1:
					$status_where .= " WHERE `error` IS NULL OR `error` = ''";
				break;
				case 2:
					$status_where .= " WHERE `error` IS NOT NULL AND `error` != ''";
				break;
			}

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_name()} {$status_where}" );
		} else {
			return 0;
		}
	}

	/**
	 * Add submenu
	 * 
	 * @since   2.14.0
	 */
	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
            'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			$this->page_id,
			array( $this, 'render_submenu'),
			null
		);
	}

	/**
	 * Render the submenu
	 * 
	 * @since   2.14.0
	 */
	public function render_submenu() {
		$this->disable_form = true;

		wp_enqueue_style( 'Gi_Toolkit_submenu_fontawesome', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/lib/core/font-awesome.min.css', array(), '4.7.0', 'all' );

		$replace_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/mail-catcher.asset.php' );
		wp_enqueue_style( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/mail-catcher.css', array(), $replace_assets['version'], 'all' );
		wp_enqueue_script( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/mail-catcher.js', array_merge( $replace_assets['dependencies'], array( 'chartjs' ) ), $replace_assets['version'], true );
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);

		wp_localize_script(
			'Gi_Toolkit_submenu',
			'Gi_ToolkitSubmenu',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( $this->nonce ),
				'stats'   => $this->get_mail_statistics(),
				'i18n'    => array(
					'confirmBulkDelete' => __( 'Supprimer les e-mails sélectionnés ?', 'gi-toolkit' ),
					'confirmBulkResend' => __( 'Renvoyer les e-mails sélectionnés ?', 'gi-toolkit' ),
					'confirmDelete'     => __( 'Supprimer cet e-mail ?', 'gi-toolkit' ),
					'confirmResend'     => __( 'Renvoyer cet e-mail ?', 'gi-toolkit' ),
					'chartSent'         => __( 'Envoyés', 'gi-toolkit' ),
					'chartFailed'       => __( 'Échoués', 'gi-toolkit' ),
					'chartVolume'       => __( 'Volume (7 jours)', 'gi-toolkit' ),
					'chartSuccessRate'  => __( 'Taux de succès', 'gi-toolkit' ),
				),
			)
		);

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * Save the submenu option
	 * 
	 * @since   2.14.0
	 */
	public function save_submenu() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_POST['page'] ?? '' ) );
		if ( $page !== $this->page_id ) {
			return;
		}

		$bulk_action = $this->get_list_bulk_action();
		if ( $bulk_action ) {
			$this->handle_list_bulk_action( $bulk_action );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ $this->nonce_name ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $this->nonce_name ] ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete'] ) && '' !== $_POST['delete'] ) {
			$id = absint( $_POST['delete'] );
			if ( $id > 0 ) {
				$this->delete_logs( array( $id ) );
				$this->redirect_with_notice( 'deleted', 1 );
			}
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['resend'] ) && '' !== $_POST['resend'] ) {
			$id = absint( $_POST['resend'] );
			if ( $id > 0 ) {
				$result = $this->resend_log( $id );
				if ( is_wp_error( $result ) ) {
					$this->redirect_with_notice( 'resend_error', 0, $result->get_error_message() );
				} else {
					$this->redirect_with_notice( 'resent', 1 );
				}
			}
		}
	}

	/**
	 * Action groupée demandée (select en haut/bas du tableau).
	 *
	 * @return string
	 */
	private function get_list_bulk_action() {
		$action = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			$action = sanitize_key( wp_unslash( $_POST['action'] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_POST['action2'] ) );
		}

		if ( ! in_array( $action, array( 'delete', 'resend' ), true ) ) {
			return '';
		}

		return $action;
	}

	/**
	 * Suppression / renvoi en masse (admin_init, avant tout HTML).
	 *
	 * @param string $action delete|resend.
	 * @return void
	 */
	public function handle_list_bulk_action( $action ) {
		check_admin_referer( 'bulk-gi_toolkit-mail-catcher-logs' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = isset( $_POST['gi_toolkit-mail-catcher-log'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['gi_toolkit-mail-catcher-log'] ) ) : array();
		$ids = array_values( array_filter( $ids ) );

		if ( empty( $ids ) ) {
			return;
		}

		if ( 'delete' === $action ) {
			$deleted = $this->delete_logs( $ids );
			$this->redirect_with_notice( 'deleted', $deleted );
		}

		if ( 'resend' === $action ) {
			$ok   = 0;
			$fail = 0;
			foreach ( $ids as $id ) {
				$result = $this->resend_log( $id );
				if ( is_wp_error( $result ) ) {
					++$fail;
				} else {
					++$ok;
				}
			}

			if ( $fail > 0 && $ok > 0 ) {
				$this->redirect_with_notice( 'resend_partial', $ok, (string) $fail );
			} elseif ( $fail > 0 ) {
				$this->redirect_with_notice( 'resend_error', 0, __( 'Aucun e-mail n’a pu être renvoyé.', 'gi-toolkit' ) );
			} else {
				$this->redirect_with_notice( 'resent', $ok );
			}
		}
	}

	/**
	 * Show email message
	 * 
	 * @since   2.14.0
	 */
	public function show_email_message() {
		$nonce_get = sanitize_text_field( wp_unslash( $_GET[ $this->nonce_name ] ?? '' ) );
		if ( wp_verify_nonce( $nonce_get, $this->nonce ) ) {
			$email_id = sanitize_text_field( wp_unslash( $_GET['email_id'] ?? '' ) );
			if ( ! empty( $email_id ) ) {
				$item = $this->get_one_item_by_id( $email_id );

				if ( ! empty( $item ) ) {
					$message = $item['message'] ?? '';

					if ( ! $this->is_html_mail( $item ) ) {
						nocache_headers();
						header( 'Content-Type: text/html; charset=UTF-8' );
						echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;padding:16px;font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#1d2327;background:#fff;white-space:pre-wrap;word-break:break-word;}</style></head><body>';
						echo esc_html( $this->get_plain_mail_text( $message ) );
						echo '</body></html>';
						exit();
					}

					// Strip <xml> and comment tags.
        			$message = preg_replace( '/<xml\b[^>]*>(.*?)<\/xml>/is', '', $message );
        			$message = preg_replace( '/<!--(.*?)-->/', '', $message );

					$allowed_html              = wp_kses_allowed_html( 'post' );
        			$allowed_html['style'][''] = true;
					echo wp_kses( $message, $allowed_html, wp_allowed_protocols() );
					exit();
				}
			}
		}
	}

	/**
	 * Détecte si le message est HTML.
	 *
	 * @param array<string, mixed> $item Ligne mail.
	 * @return bool
	 */
	private function is_html_mail( $item ) {
		$message = (string) ( $item['message'] ?? '' );
		if ( '' === trim( $message ) ) {
			return false;
		}

		// HTML si le corps contient au moins un paragraphe <p>.
		return (bool) preg_match( '/<p[\s>]/i', $message );
	}

	/**
	 * Normalise le texte brut d’un mail.
	 *
	 * @param string $message Contenu message.
	 * @return string
	 */
	private function get_plain_mail_text( $message ) {
		$text = wp_strip_all_tags( (string) $message );
		$text = str_replace( array( "\r\n", "\n", "\r" ), "\n", $text );
		return trim( $text );
	}

	/**
	 * Données exportables pour l’onglet JSON de l’aperçu.
	 *
	 * @param array<string, mixed> $item Ligne mail.
	 * @return array<string, mixed>
	 */
	private function get_preview_export_item( $item ) {
		return array(
			'id'            => (int) ( $item['id'] ?? 0 ),
			'receiver'      => (string) ( $item['receiver'] ?? '' ),
			'subject'       => (string) ( $item['subject'] ?? '' ),
			'headers'       => (string) ( $item['headers'] ?? '' ),
			'message'       => (string) ( $item['message'] ?? '' ),
			'error'         => (string) ( $item['error'] ?? '' ),
			'attachments'   => (string) ( $item['attachments'] ?? '' ),
			'unixtime'      => (int) ( $item['unixtime'] ?? 0 ),
			'sent_at'       => ! empty( $item['unixtime'] ) ? wp_date( 'Y-m-d H:i:s', (int) $item['unixtime'] ) : '',
			'resent_count'  => (int) ( $item['resent_count'] ?? 0 ),
			'last_resent_at'=> ! empty( $item['last_resent_at'] ) ? (int) $item['last_resent_at'] : null,
			'is_html'       => $this->is_html_mail( $item ),
		);
	}

	/**
	 * Ligne label / valeur pour l’aperçu.
	 *
	 * @param string $label Libellé.
	 * @param string $value Contenu HTML déjà échappé.
	 * @param string $class Classe optionnelle sur la valeur.
	 * @return string
	 */
	private function render_preview_field( $label, $value, $class = '' ) {
		$value_class = 'gi-toolkit-email-preview__content__item__content__value';
		if ( '' !== $class ) {
			$value_class .= ' ' . $class;
		}

		return sprintf(
			'<div class="gi-toolkit-email-preview__content__item"><div class="gi-toolkit-email-preview__content__item__title">%1$s</div><div class="gi-toolkit-email-preview__content__item__content"><div class="%2$s">%3$s</div></div></div>',
			esc_html( $label ),
			esc_attr( $value_class ),
			$value
		);
	}

	/**
	 * Bloc métadonnées (Jour, destinataire, sujet, etc.) pour les onglets HTML / RAW.
	 *
	 * @param array<string, mixed> $item             Ligne mail.
	 * @param string               $time_str         Date formatée.
	 * @param string               $receiver_html    Destinataire (HTML).
	 * @param string               $headers_html     En-têtes (HTML).
	 * @param string               $attachments_html Pièces jointes (HTML).
	 * @param string               $plain_message    Message texte échappé.
	 * @param bool                 $include_message  Inclure le corps du message.
	 * @return string
	 */
	private function render_preview_meta_fields( $item, $time_str, $receiver_html, $headers_html, $attachments_html, $plain_message, $include_message = true ) {
		$html  = $this->render_preview_field( __( 'Jour', 'gi-toolkit' ), esc_html( $time_str ) );
		$html .= $this->render_preview_field( __( 'Destinataire', 'gi-toolkit' ), $receiver_html );
		$html .= $this->render_preview_field( __( 'Sujet', 'gi-toolkit' ), esc_html( $item['subject'] ?? '' ) );
		$html .= $this->render_preview_field( __( 'En-têtes', 'gi-toolkit' ), $headers_html );

		if ( ! empty( $item['error'] ) ) {
			$html .= $this->render_preview_field(
				__( 'Erreur', 'gi-toolkit' ),
				esc_html( $item['error'] ),
				'gi-toolkit-email-preview__content__item__content__error'
			);
		}

		if ( $include_message ) {
			$html .= $this->render_preview_field(
				__( 'Message', 'gi-toolkit' ),
				'<pre class="gi-toolkit-email-preview__plaintext">' . $plain_message . '</pre>',
				'gi-toolkit-email-preview__content__item__content__message'
			);
		}

		if ( $attachments_html ) {
			$html .= $this->render_preview_field( __( 'Pièces jointes', 'gi-toolkit' ), wp_kses_post( $attachments_html ) );
		}

		return $html;
	}


	/**
	 * Get mail receiver
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_receiver( $mail_data ) {
		$receiver = $mail_data['to'];

		if( is_array( $receiver ) ) {
			$receiver_array = $receiver;
		} else {
			$receiver_array = preg_split( "/(,|,\s)/", $receiver );
		}

		return implode( ',\n', $receiver_array );
	}

	/**
	 * Check reach limit
	 * 
	 * @since   2.14.0
	 */
	private function is_reach_limit() {
		if ( gi_toolkit_is_pro() ) {
			return false;
		}

		$today   = wp_date( 'Y-m-d' );
		$data    = get_option( $this->option_id, array() );
		$counter = $data['counter'] ?? 0;
		$date    = $data['date'] ?? '';

		if ( $date !== $today ) {
			return false;
		}

		return $counter >= 5;
	}

	/**
	 * Add to limit counter
	 * 
	 * @since   2.14.0
	 */
	private function add_to_limit_counter() {
		$today   = wp_date( 'Y-m-d' );
		$data    = get_option( $this->option_id, array() );
		$counter = $data['counter'] ?? 0;
		$date    = $data['date'] ?? '';

		if ( $date !== $today ) {
			$counter = 0;
		}

		$data = array(
			'counter' => $counter + 1,
			'date'    => $today,
		);

		update_option( $this->option_id, $data );
	}

	/**
	 * Get mail subject
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_subject( $mail_data ) {
		if ( ! empty( $mail_data['subject'] ) && mb_strlen( $mail_data['subject'] ) > 200 ) {
            $mail_data['subject'] = mb_substr( $mail_data['subject'], 0, 195 ) . '...';
        }

		return $mail_data['subject'];
	}

	/**
	 * Get mail message
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_message( $mail_data ) {
		if ( isset( $mail_data['message'] ) ) {
			return $mail_data['message'];
		} elseif ( isset( $mail_data['html'] ) ) {
			return $mail_data['html'];
		}
		return '';
	}

	/**
	 * Get mail headers
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_headers( $mail_data ) {
		
		$mail_headers = '';
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$content_type = 'Content-Type: ' . apply_filters( 'wp_mail_content_type', 'text/html' );
		if ( empty( $mail_data['headers'] ) ) {
            $mail_headers = [ $content_type ];
        }

		$mail_headers = $mail_data['headers'];
		if ( ! is_array( $mail_headers ) ) {
			$clean_headers = str_replace(
				array(
					"\\r\\n",
					"\r\n",
					",\n",
					",\\n"
				),
				"\n",
				$mail_headers
			);

			$headers = explode( "\n", $clean_headers );

			$mail_headers = array_filter( array_map( function( $header ) {
				return rtrim( $header, "," );
			}, $headers ) );
		}

        if ( empty( $mail_headers ) ) {
            $mail_headers = [ $content_type ];
        }

		$should_force_add_content_type = true;
        foreach ( $mail_headers as $mail_header ) {
            $header_arr = explode( ":", $mail_header );

            if ( ! empty( $header_arr[0] ) && strtolower( $header_arr[0] ) === 'content-type' ) {
                $should_force_add_content_type = false;
            }
        }

		if ( $should_force_add_content_type ) {
            $mail_headers[] = $content_type;
        }

		if ( is_array( $mail_headers ) ) {
			$mail_headers = implode( ',\n', $mail_headers );
		}

		return $mail_headers;
	}

	/**
	 * Get mail attachments
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_attachments( $mail_data ) {
		$attachment_abs_paths = isset( $mail_data['attachments'] ) ? $mail_data['attachments'] : array();

		if( ! is_array( $attachment_abs_paths ) ) {
            $attachment_abs_paths = preg_split( "/(,|,\s)/", $attachment_abs_paths );
        }

		$attachment_urls = [];
        foreach ( $attachment_abs_paths as $attachment_abs_path ) {
			$attachment_urls[] = str_replace( ABSPATH, '', $attachment_abs_path );
        }

		return implode( ',\n', $attachment_urls );
	}

	/**
	 * Delete attempt
	 * 
	 * @since 2.14.0
	 */
	/**
	 * Supprime un ou plusieurs journaux d’e-mails.
	 *
	 * @param int[] $ids Identifiants des lignes.
	 * @return int Nombre de lignes supprimées.
	 */
	public function delete_logs( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) || ! $this->is_table_exist() ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->get_table_name()} WHERE id IN ($placeholders)", $ids ) );
	}

	/**
	 * Renvoie un e-mail capturé via wp_mail().
	 *
	 * @param int $id ID du journal.
	 * @return true|WP_Error
	 */
	public function resend_log( $id ) {
		$item = $this->get_log_by_id( $id );
		if ( empty( $item ) ) {
			return new WP_Error( 'gi_toolkit_mail_not_found', __( 'Cet e-mail n’existe pas.', 'gi-toolkit' ) );
		}

		$to          = $this->parse_list_field( $item['receiver'] ?? '' );
		$headers     = $this->parse_list_field( $item['headers'] ?? '' );
		$subject     = $item['subject'] ?? '';
		$message     = $item['message'] ?? '';
		$attachments = $this->parse_attachment_paths( $item['attachments'] ?? '' );

		if ( empty( $to ) ) {
			return new WP_Error( 'gi_toolkit_mail_no_receiver', __( 'Destinataire manquant.', 'gi-toolkit' ) );
		}

		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( $sent ) {
			$this->mark_log_resent( $id, '' );
			return true;
		}

		global $phpmailer;
		$error_message = __( 'Échec de l’envoi.', 'gi-toolkit' );
		if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
			$error_message = sanitize_text_field( $phpmailer->ErrorInfo );
		}

		$this->mark_log_resent( $id, $error_message );

		return new WP_Error( 'gi_toolkit_mail_resend_failed', $error_message );
	}

	/**
	 * Statistiques pour le tableau de bord.
	 *
	 * @return array<string, mixed>
	 */
	public function get_mail_statistics() {
		global $wpdb;

		$empty = array(
			'total'        => 0,
			'success'      => 0,
			'failed'       => 0,
			'today'        => 0,
			'resent_total' => 0,
			'chart_labels' => array(),
			'chart_sent'   => array(),
			'chart_failed' => array(),
		);

		if ( ! $this->is_table_exist() ) {
			return $empty;
		}

		$table = $this->get_table_name();
		$today = strtotime( 'today', (int) current_time( 'timestamp' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['total']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$empty['success'] = (int) $this->get_logs_count( 1 );
		$empty['failed']  = (int) $this->get_logs_count( 2 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['today'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE unixtime >= %d", $today ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['resent_total'] = (int) $wpdb->get_var( "SELECT COALESCE(SUM(resent_count), 0) FROM {$table}" );

		for ( $i = 6; $i >= 0; $i-- ) {
			$day_start = strtotime( '-' . $i . ' days', $today );
			$day_end   = $day_start + DAY_IN_SECONDS;
			$label     = wp_date( 'D d/m', $day_start );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sent = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE unixtime >= %d AND unixtime < %d AND (error IS NULL OR error = '')",
					$day_start,
					$day_end
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$failed = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE unixtime >= %d AND unixtime < %d AND error IS NOT NULL AND error != ''",
					$day_start,
					$day_end
				)
			);

			$empty['chart_labels'][] = $label;
			$empty['chart_sent'][]   = $sent;
			$empty['chart_failed'][] = $failed;
		}

		return $empty;
	}

	/**
	 * Derniers envois en échec (pour MainWP / API distante).
	 *
	 * @param int $limit Nombre max.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_failures( $limit = 5 ) {
		global $wpdb;

		$limit = max( 1, min( 20, absint( $limit ) ) );
		if ( ! $this->is_table_exist() ) {
			return array();
		}

		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, receiver, subject, error, unixtime FROM {$table} WHERE error IS NOT NULL AND error != '' ORDER BY unixtime DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'        => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'receiver'  => isset( $row['receiver'] ) ? (string) $row['receiver'] : '',
				'subject'   => isset( $row['subject'] ) ? (string) $row['subject'] : '',
				'error'     => isset( $row['error'] ) ? (string) $row['error'] : '',
				'unixtime'  => isset( $row['unixtime'] ) ? (int) $row['unixtime'] : 0,
				'sent_at'   => ! empty( $row['unixtime'] ) ? wp_date( 'Y-m-d H:i', (int) $row['unixtime'] ) : '',
			);
		}

		return $out;
	}

	/**
	 * Statistiques mail pour l’API MainWP (sans graphiques lourds).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_mainwp_status_payload() {
		$db_options = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		$active     = is_array( $db_options )
			&& ! empty( $db_options['Gi_Toolkit_Mail_Catcher'] )
			&& '1' === (string) $db_options['Gi_Toolkit_Mail_Catcher'];

		if ( ! $active ) {
			return array(
				'module_active' => false,
			);
		}

		$mc = self::instance();
		if ( ! $mc ) {
			return array(
				'module_active' => true,
				'table_ready'   => false,
			);
		}

		if ( ! $mc->is_table_exist() ) {
			return array(
				'module_active' => true,
				'table_ready'   => false,
				'total'         => 0,
				'success'       => 0,
				'failed'        => 0,
				'today'         => 0,
				'resent_total'  => 0,
			);
		}

		$stats = $mc->get_mail_statistics();

		return array(
			'module_active'    => true,
			'table_ready'      => true,
			'total'            => (int) ( $stats['total'] ?? 0 ),
			'success'          => (int) ( $stats['success'] ?? 0 ),
			'failed'           => (int) ( $stats['failed'] ?? 0 ),
			'today'            => (int) ( $stats['today'] ?? 0 ),
			'resent_total'     => (int) ( $stats['resent_total'] ?? 0 ),
			'chart_labels'     => isset( $stats['chart_labels'] ) && is_array( $stats['chart_labels'] ) ? $stats['chart_labels'] : array(),
			'chart_sent'       => isset( $stats['chart_sent'] ) && is_array( $stats['chart_sent'] ) ? array_map( 'intval', $stats['chart_sent'] ) : array(),
			'chart_failed'     => isset( $stats['chart_failed'] ) && is_array( $stats['chart_failed'] ) ? array_map( 'intval', $stats['chart_failed'] ) : array(),
			'recent_failures'  => $mc->get_recent_failures( 5 ),
		);
	}

	/**
	 * Récupère une entrée par ID.
	 *
	 * @param int $id ID.
	 * @return array<string, mixed>|false
	 */
	public function get_log_by_id( $id ) {
		return $this->get_one_item_by_id( $id );
	}

	/**
	 * Construit l’URL de redirection avec notice.
	 *
	 * @param string $code    Code notice.
	 * @param int    $count   Nombre d’éléments concernés.
	 * @param string $message Message optionnel.
	 * @return string
	 */
	private function build_notice_redirect_url( $code, $count = 0, $message = '' ) {
		$url = add_query_arg(
			array(
				'page'               => $this->page_id,
				'gi_mc_notice'       => $code,
				'gi_mc_notice_count' => max( 0, (int) $count ),
				'gi_mc_notice_msg'   => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		// Conserver filtres liste (POST ou GET).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$source = array_merge( $_GET, $_POST );
		foreach ( array( 'status', 'orderby', 'order', 'paged', 's' ) as $key ) {
			if ( ! empty( $source[ $key ] ) ) {
				$url = add_query_arg( $key, sanitize_text_field( wp_unslash( $source[ $key ] ) ), $url );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $source['search']['term'] ) ) {
			$url = add_query_arg(
				array(
					'search[place]' => sanitize_key( wp_unslash( $source['search']['place'] ?? 'receiver' ) ),
					'search[term]'  => sanitize_text_field( wp_unslash( $source['search']['term'] ) ),
				),
				$url
			);
		}

		return $url;
	}

	/**
	 * Redirection PRG avec notice admin.
	 *
	 * @param string $code    Code notice.
	 * @param int    $count   Nombre d’éléments concernés.
	 * @param string $message Message optionnel.
	 * @return void
	 */
	public function redirect_with_notice( $code, $count = 0, $message = '' ) {
		$url = $this->build_notice_redirect_url( $code, $count, $message );

		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		// Secours si du HTML a déjà été envoyé (évite les warnings PHP).
		nocache_headers();
		$title        = esc_html__( 'Mail catcher', 'gi-toolkit' );
		$body_message = esc_html__( 'Action effectuée. Redirection…', 'gi-toolkit' );
		$link         = esc_html__( 'Continuer', 'gi-toolkit' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . '</title>';
		echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $url ) . '">';
		echo '<script>window.location.replace(' . wp_json_encode( $url ) . ');</script>';
		echo '</head><body><p>' . $body_message . '</p>';
		echo '<p><a href="' . esc_url( $url ) . '">' . $link . '</a></p></body></html>';
		exit;
	}

	/**
	 * Affiche les notices après action groupée ou unitaire.
	 */
	private function render_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_key( wp_unslash( $_GET['gi_mc_notice'] ?? '' ) );
		if ( '' === $code ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count   = absint( $_GET['gi_mc_notice_count'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['gi_mc_notice_msg'] ?? '' ) );

		$type    = 'success';
		$text    = '';

		switch ( $code ) {
			case 'deleted':
				/* translators: %d: number of emails */
				$text = sprintf( _n( '%d e-mail supprimé.', '%d e-mails supprimés.', $count, 'gi-toolkit' ), $count );
				break;
			case 'resent':
				/* translators: %d: number of emails */
				$text = sprintf( _n( '%d e-mail renvoyé.', '%d e-mails renvoyés.', $count, 'gi-toolkit' ), $count );
				break;
			case 'resend_error':
				$type = 'error';
				$text = $message ? $message : __( 'Échec du renvoi.', 'gi-toolkit' );
				break;
			case 'resend_partial':
				$type = 'warning';
				/* translators: 1: success count, 2: failed count */
				$text = sprintf( __( '%1$d renvoyé(s), %2$d échec(s).', 'gi-toolkit' ), $count, absint( $message ) );
				break;
			default:
				return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible gi-toolkit-mail-catcher-notice"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}

	/**
	 * Met à jour les métadonnées de renvoi.
	 *
	 * @param int    $id    ID journal.
	 * @param string $error Message d’erreur éventuel.
	 */
	private function mark_log_resent( $id, $error ) {
		global $wpdb;

		if ( ! $this->is_table_exist() ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->get_table_name()} SET resent_count = resent_count + 1, last_resent_at = %d, error = %s WHERE id = %d",
				time(),
				$error,
				$id
			)
		);
	}

	/**
	 * Découpe un champ multi-lignes stocké en base.
	 *
	 * @param string $value Valeur brute.
	 * @return array<int, string>
	 */
	private function parse_list_field( $value ) {
		$value = str_replace( '\n', "\n", (string) $value );
		$parts = preg_split( '/,\s*|\n/', $value );
		$parts = is_array( $parts ) ? $parts : array( $value );
		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}

	/**
	 * Chemins absolus des pièces jointes.
	 *
	 * @param string $value Valeur brute.
	 * @return array<int, string>
	 */
	private function parse_attachment_paths( $value ) {
		$paths = $this->parse_list_field( str_replace( ",\n", "\n", (string) $value ) );
		$abs   = array();

		foreach ( $paths as $rel ) {
			$rel = ltrim( $rel, '/' );
			$file = ABSPATH . $rel;
			if ( is_file( $file ) ) {
				$abs[] = $file;
			}
		}

		return $abs;
	}

	/**
	 * Ajoute les colonnes resent_count / last_resent_at si besoin.
	 */
	private function maybe_upgrade_table() {
		global $wpdb;

		if ( ! $this->is_table_exist() ) {
			return;
		}

		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

		if ( ! in_array( 'resent_count', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN resent_count int(10) unsigned NOT NULL DEFAULT 0" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
		}
		if ( ! in_array( 'last_resent_at', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN last_resent_at int(10) unsigned NOT NULL DEFAULT 0" );
		}
	}

	/**
	 * Get table name
	 * 
	 * @since 2.14.0
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->option_id;
	}

	/**
	 * Check if table exist
	 * 
	 * @since 2.14.0
	 */
	private function is_table_exist() {
		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->get_table_name() ) ) ) === $this->get_table_name();
	}

	/**
	 * Create table
	 * 
	 * @since 2.14.0
	 */
	private function create_table() {
		global $wpdb;

        if ( ! empty( $wpdb->charset ) ) {
            $charset_collation_sql = "DEFAULT CHARACTER SET $wpdb->charset";
        }

        if ( ! empty( $wpdb->collate ) ) {
            $charset_collation_sql .= " COLLATE $wpdb->collate";
        }

		$table_name = $this->get_table_name();

		// Drop table if already exists
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );

		// Create database table.
		$sql =
        "CREATE TABLE {$table_name} (
            id int(6) unsigned NOT NULL auto_increment,
            receiver varchar(200) NOT NULL DEFAULT '',
            subject varchar(200) NOT NULL DEFAULT '',
            message text NULL,
            headers text NULL,
            attachments varchar(800) NOT NULL DEFAULT '',
            error varchar(400) NULL DEFAULT '',
            host varchar(200) NOT NULL DEFAULT '',
            unixtime int(10) NOT NULL DEFAULT '0',
            resent_count int(10) unsigned NOT NULL DEFAULT '0',
            last_resent_at int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (id),
			FULLTEXT KEY idx_message (message)
        ) {$charset_collation_sql}";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get one item by id
	 * 
	 * @since 2.14.0
	 */
	private function get_one_item_by_id( $id ) {
		global $wpdb;

		if ( $this->is_table_exist() ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ), ARRAY_A );
		} else {
			return false;
		}
	}

	/**
	 * Get iframe src
	 * 
	 * @since 2.14.0
	 */
	private function get_iframe_src( $item ) {
		$admin_base = admin_url( 'admin.php' );
		$admin_base = add_query_arg( 'page', $this->page_id, $admin_base );

		return add_query_arg(
			array( 'email_id' => $item['id'] ),
			wp_nonce_url( $admin_base, $this->nonce, $this->nonce_name )
		);
	}

	/**
	 * Get attachments html
	 * 
	 * @since 2.14.0
	 */
	private function get_attachments_html( $item ) {

		$attachments = $item['attachments'] ?? '';
		if ( empty( $attachments ) ) {
			return false;
		}

		$attachment_append    = '';
        $attachment_rel_paths = explode( ',\n', $item['attachments'] );
        $attachment_rel_paths = is_array( $attachment_rel_paths ) ? $attachment_rel_paths : array( $attachment_rel_paths );
        $attachment_rel_paths = array_filter( $attachment_rel_paths );

		foreach ( $attachment_rel_paths as $attachment_rel_path ) {
			$attachment_title    = basename( $attachment_rel_path );
			$attachment_abs_path = ABSPATH . $attachment_rel_path;

			if ( file_exists( $attachment_abs_path ) ) {
				$icon           = $this->determine_mime_icon( $attachment_abs_path );
				$attachment_url = str_replace( ABSPATH, home_url('/') , $attachment_abs_path );
				$attachment_append .= '<a target="_blank" href="' . $attachment_url . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></a> ';
			} else {
				/* Translators: %s attachment title */
				$message = sprintf( __( 'Attachment %s is not present', 'gi-toolkit' ), $attachment_title );
				$attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
			}
		}

		return $attachment_append;
	}

	/**
	 * Determine mime icon
	 * 
	 * @since 2.14.0
	 */
	private function determine_mime_icon( $file_path ) {

		$icon_class = 'file';
		if( function_exists('mime_content_type') ) {
			
			$mime = mime_content_type( $file_path );
			if ( false !== $mime ) {
				
				$mime_parts = explode( '/', $mime );
				$attribute  = $mime_parts[0];
				$supported  = array(
					'archive' => array(
						'application/zip',
						'application/x-rar-compressed',
						'application/x-rar',
						'application/x-gzip',
						'application/x-msdownload',
						'application/x-msdownload',
						'application/vnd.ms-cab-compressed',
					),
					'audio',
					'code' => array(
						'text/x-c',
						'text/x-c++'
					),
					'excel' => array( 'application/vnd.ms-excel' ),
					'image',
					'text',
					'movie',
					'pdf' => array( 'application/pdf' ),
					'photo',
					'picture',
					'powerpoint' => array( 'application/vnd.ms-powerpoint' ),
					'sound',
					'video',
					'word' => array( 'application/msword' ),
					'zip',
				);

				if ( in_array( $attribute, $supported ) ) {
					$icon_class = $attribute;
				} else {
					foreach ( $supported as $key => $value ) {
						if ( $mime === $value ) {
							$icon_class = $key;
						}
					}
				}
			}
		}

		$supported = array(
			'archive' => 'media-archive',
			'audio'   => 'media-audio',
			'code'    => 'media-code',
			'excel'   => 'media-spreadsheet',
			'image'   => 'format-image',
			'movie'   => 'media-video',
			'pdf'     => 'pdf',
			'photo'   => 'format-image',
			'picture' => 'format-image',
			'sound'   => 'media-audio',
			'video'   => 'media-video',
			'zip'     => 'media-archive',
		);

		if ( ! array_key_exists( $icon_class, $supported ) ) {
			return 'media-document';
		}

		return $supported[ $icon_class ];
	}

	/**
	 * Render preview
	 * 
	 * @since 2.14.0
	 */
	private function render_preview( $id ) {
		$item = $this->get_one_item_by_id( $id );
		if ( empty( $item ) ) {
			return false;
		}

		$is_html          = $this->is_html_mail( $item );
		$default_tab      = $is_html ? 'html' : 'raw';
		$attachments_html = $this->get_attachments_html( $item );
		$time_str         = wp_date( 'Y-m-d H:i:s', (int) ( $item['unixtime'] ?? 0 ) );
		$receiver_html    = wp_kses_post( nl2br( str_replace( '\n', "\n", $item['receiver'] ?? '' ) ) );
		$headers_html     = wp_kses_post( nl2br( str_replace( '\n', "\n", $item['headers'] ?? '' ) ) );
		$plain_message    = esc_html( $this->get_plain_mail_text( $item['message'] ?? '' ) );
		$iframe_src       = $is_html ? $this->get_iframe_src( $item ) : '';
		$meta_raw         = $this->render_preview_meta_fields( $item, $time_str, $receiver_html, $headers_html, $attachments_html, $plain_message, true );
		$json             = wp_json_encode(
			$this->get_preview_export_item( $item ),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		ob_start();
		?>
		<div class="gi-toolkit-mail-preview" data-default-tab="<?php echo esc_attr( $default_tab ); ?>">
			<nav class="gi-toolkit-mail-preview__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Format d’aperçu', 'gi-toolkit' ); ?>">
				<button type="button" class="gi-toolkit-mail-preview__tab<?php echo 'html' === $default_tab ? ' is-active' : ''; ?>" data-preview-tab="html" role="tab" aria-selected="<?php echo 'html' === $default_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'HTML', 'gi-toolkit' ); ?></button>
				<button type="button" class="gi-toolkit-mail-preview__tab<?php echo 'raw' === $default_tab ? ' is-active' : ''; ?>" data-preview-tab="raw" role="tab" aria-selected="<?php echo 'raw' === $default_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'RAW', 'gi-toolkit' ); ?></button>
				<button type="button" class="gi-toolkit-mail-preview__tab" data-preview-tab="json" role="tab" aria-selected="false"><?php esc_html_e( 'JSON', 'gi-toolkit' ); ?></button>
			</nav>

			<div class="gi-toolkit-mail-preview__panels">
				<div class="gi-toolkit-mail-preview__panel<?php echo 'html' === $default_tab ? ' is-active' : ''; ?>" data-preview-panel="html" role="tabpanel">
					<div class="gi-toolkit-email-preview__content gi-toolkit-email-preview__content--fields gi-toolkit-email-preview__content--html">
						<?php
						echo $this->render_preview_meta_fields( $item, $time_str, $receiver_html, $headers_html, $attachments_html, $plain_message, ! $is_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						if ( $is_html && $iframe_src ) :
							?>
							<div class="gi-toolkit-email-preview__content__item gi-toolkit-email-preview__content__item--message-html">
								<div class="gi-toolkit-email-preview__content__item__title"><?php esc_html_e( 'Message', 'gi-toolkit' ); ?></div>
								<div class="gi-toolkit-email-preview__content__item__content">
									<iframe class="gi-toolkit-email-preview__iframe" data-src="<?php echo esc_url( $iframe_src ); ?>" frameborder="0" title="<?php esc_attr_e( 'Aperçu HTML', 'gi-toolkit' ); ?>"></iframe>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="gi-toolkit-mail-preview__panel<?php echo 'raw' === $default_tab ? ' is-active' : ''; ?>" data-preview-panel="raw" role="tabpanel">
					<div class="gi-toolkit-email-preview__content gi-toolkit-email-preview__content--fields gi-toolkit-email-preview__content--raw">
						<?php echo $meta_raw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>

				<div class="gi-toolkit-mail-preview__panel" data-preview-panel="json" role="tabpanel">
					<pre class="gi-toolkit-mail-preview__json"><?php echo esc_html( (string) $json ); ?></pre>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add the submenu content
	 * 
	 * @since   2.14.0
	 */
	private function submenu_content() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
		$page   = sanitize_text_field( wp_unslash( $_REQUEST['page'] ?? '' ) );
		$status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '0' ) );
		$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ?? '' ) );
		$order   = sanitize_text_field( wp_unslash( $_GET['order'] ?? '' ) );
		$paged   = absint( $_GET['paged'] ?? 0 );
		$search_place = sanitize_key( wp_unslash( $_REQUEST['search']['place'] ?? '' ) );
		$search_term  = sanitize_text_field( wp_unslash( $_REQUEST['search']['term'] ?? '' ) );
		// phpcs:enable

		$table  = $this->get_list_table();
		$table->prepare_items( $search );
		$table->display_notices();

		$stats         = $this->get_mail_statistics();
		$success_rate  = $stats['total'] > 0 ? round( ( $stats['success'] / $stats['total'] ) * 100 ) : 0;
		?>
			<?php $this->render_admin_notices(); ?>

			<div class="gi-toolkit-mail-catcher-stats">
				<div class="gi-toolkit-mail-catcher-stats__card">
					<span class="gi-toolkit-mail-catcher-stats__label"><?php esc_html_e( 'Total capturés', 'gi-toolkit' ); ?></span>
					<strong class="gi-toolkit-mail-catcher-stats__value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong>
				</div>
				<div class="gi-toolkit-mail-catcher-stats__card gi-toolkit-mail-catcher-stats__card--success">
					<span class="gi-toolkit-mail-catcher-stats__label"><?php esc_html_e( 'Réussis', 'gi-toolkit' ); ?></span>
					<strong class="gi-toolkit-mail-catcher-stats__value"><?php echo esc_html( number_format_i18n( $stats['success'] ) ); ?></strong>
				</div>
				<div class="gi-toolkit-mail-catcher-stats__card gi-toolkit-mail-catcher-stats__card--failed">
					<span class="gi-toolkit-mail-catcher-stats__label"><?php esc_html_e( 'Échoués', 'gi-toolkit' ); ?></span>
					<strong class="gi-toolkit-mail-catcher-stats__value"><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></strong>
				</div>
				<div class="gi-toolkit-mail-catcher-stats__card">
					<span class="gi-toolkit-mail-catcher-stats__label"><?php esc_html_e( 'Aujourd’hui', 'gi-toolkit' ); ?></span>
					<strong class="gi-toolkit-mail-catcher-stats__value"><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?></strong>
				</div>
				<div class="gi-toolkit-mail-catcher-stats__card">
					<span class="gi-toolkit-mail-catcher-stats__label"><?php esc_html_e( 'Renvois effectués', 'gi-toolkit' ); ?></span>
					<strong class="gi-toolkit-mail-catcher-stats__value"><?php echo esc_html( number_format_i18n( $stats['resent_total'] ) ); ?></strong>
				</div>
				<div class="gi-toolkit-mail-catcher-stats__card gi-toolkit-mail-catcher-stats__card--rate">
					<span class="gi-toolkit-mail-catcher-stats__label"><?php esc_html_e( 'Taux de succès', 'gi-toolkit' ); ?></span>
					<strong class="gi-toolkit-mail-catcher-stats__value"><?php echo esc_html( $success_rate ); ?>%</strong>
				</div>
			</div>

			<div class="gi-toolkit-mail-catcher-charts">
				<div class="gi-toolkit-mail-catcher-charts__panel">
					<h3><?php esc_html_e( 'Volume sur 7 jours', 'gi-toolkit' ); ?></h3>
					<canvas id="gi-toolkit-mail-chart-volume" height="120"></canvas>
				</div>
				<div class="gi-toolkit-mail-catcher-charts__panel gi-toolkit-mail-catcher-charts__panel--donut">
					<h3><?php esc_html_e( 'Répartition succès / échecs', 'gi-toolkit' ); ?></h3>
					<canvas id="gi-toolkit-mail-chart-status" height="120"></canvas>
				</div>
			</div>

			<form id="email-list" method="post" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<?php if ( '' !== $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
				<?php endif; ?>
				<?php if ( '' !== $orderby ) : ?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
				<?php endif; ?>
				<?php if ( '' !== $order ) : ?>
					<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
				<?php endif; ?>
				<?php if ( $paged > 0 ) : ?>
					<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $paged ); ?>" />
				<?php endif; ?>
				<?php if ( '' !== $search_place && '' !== $search_term ) : ?>
					<input type="hidden" name="search[place]" value="<?php echo esc_attr( $search_place ); ?>" />
					<input type="hidden" name="search[term]" value="<?php echo esc_attr( $search_term ); ?>" />
				<?php endif; ?>
				<div class="gi-toolkit-mail-catcher-table gi-toolkit-mail-catcher-table--log">
				<?php
					wp_nonce_field( $this->nonce, $this->nonce_name );
					$table->search_box( __( 'Search', 'gi-toolkit' ), 's' );
					$table->views();
					$table->display();
				?>
				</div>
			</form>

			<div class="gi-toolkit-popup gi-toolkit-popup--mail-catcher">
				<div class="gi-toolkit-popup__overlay" id="JS-popup-overlay"></div>
				<div class="gi-toolkit-popup__content">
					<div class="gi-toolkit-popup__header">
						<div class="gi-toolkit-popup__header__left">
							<div class="gi-toolkit-popup__header__title"><?php esc_html_e( 'Message', 'gi-toolkit' ); ?></div>
						</div>
						<div class="gi-toolkit-popup__header__right">
							<div class="gi-toolkit-popup__header__close" id="JS-close-popup">
								<?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?>
							</div>
						</div>
					</div>
					<div class="gi-toolkit-popup__body">
						<div class="gi-toolkit-popup__body__content">
							<div class="gi-toolkit-email-preview">
								<div id="JS-gi-toolkit-email-preview" class="gi-toolkit-email-preview__content"></div>
								<div id="JS-gi-toolkit-email-loader" class="gi-toolkit-email-preview__loader"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * Initialises and returns the list table for logs.
	 * 
	 * @since    2.14.0
	 */
	private function get_list_table() {
		static $table = null;

		if ( ! $table ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/mail-catcher/class-logs-list-table.php';
			$table = new Gi_Toolkit_Mail_Catcher_Logs_List_Table( $this );
		}

		return $table;
	}
}
