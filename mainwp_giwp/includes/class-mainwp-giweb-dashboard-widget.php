<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statistiques mail réseau.
 */
class MainWP_GIWeb_Dashboard_Widget {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'mainwp_getmetaboxes', array( __CLASS__, 'register_metabox' ), 20, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Metaboxes existants.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes ) {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator || empty( $mainwp_giweb_activator->childEnabled ) ) {
			return $metaboxes;
		}

		if ( ! is_array( $metaboxes ) ) {
			$metaboxes = array();
		}

		$key = is_array( $mainwp_giweb_activator->childEnabled ) && ! empty( $mainwp_giweb_activator->childEnabled['key'] )
			? $mainwp_giweb_activator->childEnabled['key']
			: $mainwp_giweb_activator->childKey;

		$metaboxes[] = array(
			'id'            => 'mainwp-giweb-mail-widget',
			'plugin'        => MAINWP_GIWEB_PLUGIN_FILE,
			'key'           => $key,
			'metabox_title' => __( 'GI-Toolkit — Mails', 'mainwp-giweb' ),
			'callback'      => array( __CLASS__, 'render_metabox' ),
		);

		return $metaboxes;
	}

	/**
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! self::is_dashboard_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array(),
			MAINWP_GIWEB_VERSION
		);

		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);

		wp_enqueue_script(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/dashboard-widget.js',
			array( 'chart-js' ),
			MAINWP_GIWEB_VERSION,
			true
		);

		$agg     = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$network = $agg['network'] ?? array();

		wp_localize_script(
			'mainwp-giweb-dashboard-widget',
			'mainwpGiwebMailWidget',
			array(
				'network' => $network,
				'i18n'    => array(
					'sent'   => __( 'Envoyés', 'mainwp-giweb' ),
					'failed' => __( 'Échecs', 'mainwp-giweb' ),
				),
			)
		);
	}

	/**
	 * @param string $hook Hook admin.
	 * @return bool
	 */
	private static function is_dashboard_screen( $hook ) {
		unset( $hook );
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return in_array( $page, array( 'mainwp_tab', 'mainwp-setup', 'mainwp' ), true )
			|| false !== strpos( $page, 'mainwp' ) && false === strpos( $page, 'Extensions' );
	}

	/**
	 * @return void
	 */
	public static function render_metabox() {
		$agg     = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$network = $agg['network'] ?? array();
		$sites   = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated = ! empty( $agg['updated_at'] ) ? wp_date( 'Y-m-d H:i', (int) $agg['updated_at'] ) : '';

		$failed_sites = array();
		foreach ( $sites as $site_id => $row ) {
			$mail = $row['mail'] ?? null;
			if ( ! is_array( $mail ) || empty( $mail['module_active'] ) || empty( $mail['table_ready'] ) ) {
				continue;
			}
			if ( (int) ( $mail['failed'] ?? 0 ) > 0 ) {
				$failed_sites[ $site_id ] = $row;
			}
		}

		$manager_url = admin_url( 'admin.php?page=' . rawurlencode( MainWP_GIWeb_UI::PAGE_SLUG ) );
		?>
		<div class="mainwp-giweb-mail-widget">
			<?php if ( $updated ) : ?>
				<p class="mainwp-giweb-mail-widget__meta description">
					<?php
					printf(
						/* translators: %s: datetime */
						esc_html__( 'Dernière synchro : %s', 'mainwp-giweb' ),
						esc_html( $updated )
					);
					?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Lancez une synchronisation dans GI-Toolkit Manager pour remplir ce widget.', 'mainwp-giweb' ); ?></p>
			<?php endif; ?>

			<div class="mainwp-giweb-mail-widget__stats">
				<div class="mainwp-giweb-mail-widget__stat">
					<span class="mainwp-giweb-mail-widget__stat-value"><?php echo esc_html( (string) (int) ( $network['total'] ?? 0 ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__stat-label"><?php esc_html_e( 'Total', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__stat mainwp-giweb-mail-widget__stat--ok">
					<span class="mainwp-giweb-mail-widget__stat-value"><?php echo esc_html( (string) (int) ( $network['success'] ?? 0 ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__stat-label"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__stat <?php echo (int) ( $network['failed'] ?? 0 ) > 0 ? 'mainwp-giweb-mail-widget__stat--alert' : ''; ?>">
					<span class="mainwp-giweb-mail-widget__stat-value"><?php echo esc_html( (string) (int) ( $network['failed'] ?? 0 ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__stat-label"><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__stat">
					<span class="mainwp-giweb-mail-widget__stat-value"><?php echo esc_html( (string) (int) ( $network['today'] ?? 0 ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__stat-label"><?php esc_html_e( 'Aujourd’hui', 'mainwp-giweb' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $network['chart_labels'] ) ) : ?>
				<div class="mainwp-giweb-mail-widget__chart-wrap">
					<canvas id="mainwp-giweb-mail-network-chart" height="120" aria-hidden="true"></canvas>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $failed_sites ) ) : ?>
				<div class="mainwp-giweb-mail-widget__alerts">
					<strong><?php esc_html_e( 'Sites avec échecs', 'mainwp-giweb' ); ?></strong>
					<ul>
						<?php foreach ( $failed_sites as $site_id => $row ) : ?>
							<?php
							$m = $row['mail'];
							?>
							<li>
								<?php echo esc_html( $row['label'] ?? ( '#' . $site_id ) ); ?>
								—
								<span class="mainwp-giweb-badge err"><?php echo esc_html( (string) (int) ( $m['failed'] ?? 0 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<p class="mainwp-giweb-mail-widget__footer">
				<a class="button button-small" href="<?php echo esc_url( $manager_url ); ?>">
					<?php esc_html_e( 'GI-Toolkit Manager', 'mainwp-giweb' ); ?>
				</a>
				<?php if ( (int) ( $network['sites_module_active'] ?? 0 ) > 0 ) : ?>
					<span class="description">
						<?php
						printf(
							/* translators: %d: site count */
							esc_html__( '%d site(s) avec Mail Catcher actif', 'mainwp-giweb' ),
							(int) $network['sites_module_active']
						);
						?>
					</span>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}

MainWP_GIWeb_Dashboard_Widget::init();
