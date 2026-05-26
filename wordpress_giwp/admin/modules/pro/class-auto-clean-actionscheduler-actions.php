<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: purge des actions Action Scheduler terminées.
 */
class Gi_Toolkit_Auto_Clean_Actionscheduler_Actions {

	const CRON_HOOK = 'gi_toolkit_clean_as_actions';

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_as_clean';

	private $page_slug = 'gi-toolkit-settings-auto-clean-as';

	public function __construct() {
		$this->header_title = __( 'Auto clean actionscheduler_actions', 'gi-toolkit' );
		add_action( self::CRON_HOOK, array( $this, 'clean' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
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
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, $this->nonce_action ) ) {
			return;
		}
		$days = isset( $_POST['gi_toolkit_as_clean_days'] ) ? absint( $_POST['gi_toolkit_as_clean_days'] ) : 30;
		if ( $days < 7 ) {
			$days = 7;
		}
		if ( $days > 365 ) {
			$days = 365;
		}
		update_option( 'gi_toolkit_as_clean_days', (string) $days, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$days = (int) get_option( 'gi_toolkit_as_clean_days', 30 );
		if ( $days < 7 ) {
			$days = 30;
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Une tâche quotidienne supprime les lignes « complete » plus anciennes que le nombre de jours indiqué (table actionscheduler_actions).', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p>
					<label for="gi_toolkit_as_clean_days"><strong><?php esc_html_e( 'Conserver les actions terminées depuis au moins (jours)', 'gi-toolkit' ); ?></strong></label>
				</p>
				<p><input type="number" min="7" max="365" id="gi_toolkit_as_clean_days" name="gi_toolkit_as_clean_days" value="<?php echo esc_attr( (string) $days ); ?>"/></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function clean() {
		global $wpdb;
		$table = $wpdb->prefix . 'actionscheduler_actions';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return;
		}
		$days = (int) get_option( 'gi_toolkit_as_clean_days', 30 );
		$days = (int) apply_filters( 'gi_toolkit_as_clean_days', $days );
		if ( $days < 7 ) {
			$days = 7;
		}
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = %s AND last_attempt_gmt < %s",
				'complete',
				$threshold
			)
		);
	}
}
