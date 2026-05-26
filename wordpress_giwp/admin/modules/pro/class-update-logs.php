<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: journal des mises à jour (extensions / thème / cœur).
 */
class Gi_Toolkit_Update_Logs {

	const OPTION_KEY = 'gi_toolkit_update_logs';

	private $disable_form = true;

	private $header_title = '';

	private $page_slug = 'gi-toolkit-settings-update-logs';

	public function __construct() {
		$this->header_title = __( 'Updates Logs', 'gi-toolkit' );
		add_action( 'upgrader_process_complete', array( $this, 'log_upgrade' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
	}

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

	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, 'gi_toolkit_save_update_logs' ) ) {
			return;
		}
		if ( ! empty( $_POST['gi_toolkit_clear_update_logs'] ) ) {
			delete_option( self::OPTION_KEY );
		}
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>" style="margin-bottom:1rem;">
				<?php wp_nonce_field( 'gi_toolkit_save_update_logs' ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<button type="submit" name="gi_toolkit_clear_update_logs" value="1" class="button"><?php esc_html_e( 'Vider le journal', 'gi-toolkit' ); ?></button>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Type', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Action', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Éléments', 'gi-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				if ( empty( $logs ) ) {
					echo '<tr><td colspan="4">' . esc_html__( 'Aucune entrée pour le moment.', 'gi-toolkit' ) . '</td></tr>';
				} else {
					foreach ( $logs as $row ) {
						$items = isset( $row['items'] ) && is_array( $row['items'] ) ? implode( ', ', $row['items'] ) : '';
						echo '<tr>';
						echo '<td>' . esc_html( $row['time'] ?? '' ) . '</td>';
						echo '<td>' . esc_html( $row['type'] ?? '' ) . '</td>';
						echo '<td>' . esc_html( $row['action'] ?? '' ) . '</td>';
						echo '<td><code style="word-break:break-all;">' . esc_html( $items ) . '</code></td>';
						echo '</tr>';
					}
				}
				?>
				</tbody>
			</table>
		</div>
		<?php
		echo '</div>';
	}

	public function log_upgrade( $upgrader, $data ) {
		unset( $upgrader );
		if ( empty( $data['type'] ) || empty( $data['action'] ) ) {
			return;
		}

		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$entry = array(
			'time'   => current_time( 'mysql' ),
			'type'   => sanitize_text_field( $data['type'] ),
			'action' => sanitize_text_field( $data['action'] ),
			'items'  => array(),
		);

		if ( ! empty( $data['plugins'] ) && is_array( $data['plugins'] ) ) {
			$entry['items'] = array_map( 'sanitize_text_field', $data['plugins'] );
		} elseif ( ! empty( $data['themes'] ) && is_array( $data['themes'] ) ) {
			$entry['items'] = array_map( 'sanitize_text_field', $data['themes'] );
		}

		array_unshift( $logs, $entry );
		$logs = array_slice( $logs, 0, 100 );
		update_option( self::OPTION_KEY, $logs, false );
	}
}
