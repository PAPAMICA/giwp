<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : gestionnaire CRON — liste des tâches planifiées WordPress.
 */
class Gi_Toolkit_Cron_Manager {

	/**
	 * @var bool
	 */
	private $disable_form = true;

	/**
	 * @var string
	 */
	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'CRON Manager', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-cron-manager',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$submenu_assets = include GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/global-admin.asset.php';
		wp_enqueue_style( 'gi-toolkit-cron-manager', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.css', array(), $submenu_assets['version'], 'all' );

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->render_body();
		echo '</div>';
	}

	private function render_body() {
		$crons = _get_cron_array();
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:100%;">
			<p class="description">
				<?php esc_html_e( 'Liste des hooks enregistrés dans le cron WordPress (prochaine exécution, récurrence, arguments).', 'gi-toolkit' ); ?>
			</p>
			<table class="widefat striped" style="margin-top:1rem;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Hook', 'gi-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Prochaine exécution', 'gi-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Récurrence', 'gi-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Arguments', 'gi-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				if ( empty( $crons ) ) {
					echo '<tr><td colspan="4">' . esc_html__( 'Aucune tâche planifiée.', 'gi-toolkit' ) . '</td></tr>';
				} else {
					foreach ( $crons as $timestamp => $hooks ) {
						foreach ( $hooks as $hook => $callbacks ) {
							foreach ( $callbacks as $sig => $data ) {
								$sched = isset( $data['schedule'] ) ? (string) $data['schedule'] : __( 'unique', 'gi-toolkit' );
								$args  = isset( $data['args'] ) ? $data['args'] : array();
								$args_s = '';
								if ( is_array( $args ) && ! empty( $args ) ) {
									$args_s = wp_json_encode( $args );
									if ( strlen( $args_s ) > 120 ) {
										$args_s = substr( $args_s, 0, 117 ) . '…';
									}
								}
								echo '<tr>';
								echo '<td><code>' . esc_html( $hook ) . '</code></td>';
								echo '<td>' . esc_html( wp_date( 'Y-m-d H:i:s', (int) $timestamp ) ) . '</td>';
								echo '<td>' . esc_html( $sched ) . '</td>';
								echo '<td><code style="word-break:break-all;">' . esc_html( $args_s ? $args_s : '—' ) . '</code></td>';
								echo '</tr>';
							}
						}
					}
				}
				?>
				</tbody>
			</table>
			<p class="description" style="margin-top:1rem;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: wp-cron.php */
						__( 'Astuce : le déclenchement repose souvent sur les visites du site. Pour un cron système fiable, planifiez %s en ligne de commande ou via le panneau d’hébergement.', 'gi-toolkit' ),
						'wp-cron.php'
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
