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
		add_action( 'admin_init', array( $this, 'handle_run_now' ) );
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

	/**
	 * @return void
	 */
	public function handle_run_now() {
		if ( ! isset( $_POST['gi_toolkit_cron_run_now'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'gi_toolkit_cron_run_now' );
		if ( class_exists( 'Gi_Toolkit_Reliable_Cron' ) ) {
			Gi_Toolkit_Reliable_Cron::run_due( 'manual' );
		} elseif ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'gi-toolkit-settings-cron-manager',
					'gi_cron_ran'     => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
		$crons  = _get_cron_array();
		$health = class_exists( 'Gi_Toolkit_Reliable_Cron' ) ? Gi_Toolkit_Reliable_Cron::get_health() : array();
		$ok     = ! empty( $health['enabled'] ) && (int) ( $health['overdue'] ?? 0 ) < 120;
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:100%;">
			<?php if ( isset( $_GET['gi_cron_ran'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tâches dues exécutées.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $health ) ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid <?php echo $ok ? '#00a32a' : '#dba617'; ?>;border-radius:4px;margin:0 0 1.25rem;padding:14px 16px;">
					<p style="margin:0 0 8px;font-size:14px;">
						<strong><?php echo $ok ? esc_html__( 'Cron fiable GI-Toolkit actif', 'gi-toolkit' ) : esc_html__( 'Cron en retard', 'gi-toolkit' ); ?></strong>
						—
						<?php esc_html_e( 'Le planning WordPress (wp_schedule_event) est inchangé. Seul le déclenchement a été remplacé pour ne plus dépendre uniquement des visites et du loopback HTTP.', 'gi-toolkit' ); ?>
					</p>
					<ul style="margin:0 0 12px;padding-left:1.2em;color:#50575e;">
						<li>
							<?php
							$last = (int) ( $health['last_run'] ?? 0 );
							echo esc_html(
								$last
									? sprintf(
										/* translators: 1: datetime, 2: source */
										__( 'Dernier passage : %1$s (%2$s)', 'gi-toolkit' ),
										wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ),
										(string) ( $health['source'] ?? '—' )
									)
									: __( 'Dernier passage : pas encore exécuté', 'gi-toolkit' )
							);
							?>
						</li>
						<li>
							<?php
							$overdue = (int) ( $health['overdue'] ?? 0 );
							if ( $overdue > 0 ) {
								echo esc_html(
									sprintf(
										/* translators: %s: human time */
										__( 'Retard : %s', 'gi-toolkit' ),
										human_time_diff( time() - $overdue, time() )
									)
								);
							} else {
								esc_html_e( 'Aucune tâche en retard.', 'gi-toolkit' );
							}
							?>
						</li>
						<?php if ( ! empty( $health['wp_cron_disabled'] ) ) : ?>
							<li><?php esc_html_e( 'DISABLE_WP_CRON est défini : un crontab système est attendu. Le runner GI sert de filet si le cron hébergeur est en retard de plus de 90 s.', 'gi-toolkit' ); ?></li>
						<?php endif; ?>
					</ul>
					<form method="post" style="margin:0 0 12px;">
						<?php wp_nonce_field( 'gi_toolkit_cron_run_now' ); ?>
						<button type="submit" class="button button-primary" name="gi_toolkit_cron_run_now" value="1"><?php esc_html_e( 'Exécuter les tâches dues maintenant', 'gi-toolkit' ); ?></button>
					</form>
					<p style="margin:0 0 6px;color:#50575e;">
						<?php if ( ! empty( $health['wp_cron_disabled'] ) ) : ?>
							<?php esc_html_e( 'Si l’hébergeur appelle déjà wp-cron.php, conservez-le. Sinon, cette URL (une fois par minute) suffit :', 'gi-toolkit' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Pour un tick chaque minute même sans visite (recommandé), ajoutez cette ligne crontab :', 'gi-toolkit' ); ?>
						<?php endif; ?>
					</p>
					<p style="margin:0;"><code style="display:block;background:#f0f0f1;padding:8px 10px;word-break:break-all;"><?php echo esc_html( Gi_Toolkit_Reliable_Cron::crontab_line() ); ?></code></p>
				</div>
			<?php endif; ?>

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
								$sched  = isset( $data['schedule'] ) ? (string) $data['schedule'] : __( 'unique', 'gi-toolkit' );
								$args   = isset( $data['args'] ) ? $data['args'] : array();
								$args_s = '';
								if ( is_array( $args ) && ! empty( $args ) ) {
									$args_s = wp_json_encode( $args );
									if ( strlen( $args_s ) > 120 ) {
										$args_s = substr( $args_s, 0, 117 ) . '…';
									}
								}
								$is_due = (int) $timestamp <= time();
								echo '<tr>';
								echo '<td><code>' . esc_html( $hook ) . '</code></td>';
								echo '<td>' . esc_html( wp_date( 'Y-m-d H:i:s', (int) $timestamp ) );
								if ( $is_due ) {
									echo ' <span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'due', 'gi-toolkit' ) . '</span>';
								}
								echo '</td>';
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
		</div>
		<?php
	}
}
