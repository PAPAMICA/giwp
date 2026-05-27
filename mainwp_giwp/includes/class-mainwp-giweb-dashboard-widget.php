<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statistiques mail réseau.
 */
class MainWP_GIWeb_Dashboard_Widget {

	const WIDGET_ID = 'mainwp-giweb-mail-widget';

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Metaboxes existants.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes ) {
		global $mainwp_giweb_activator;

		if ( ! $mainwp_giweb_activator || empty( $mainwp_giweb_activator->childEnabled ) ) {
			return $metaboxes;
		}

		if ( ! MainWP_GIWeb_Capabilities::can_access() ) {
			return $metaboxes;
		}

		if ( ! is_array( $metaboxes ) ) {
			$metaboxes = array();
		}

		$key = is_array( $mainwp_giweb_activator->childEnabled ) && ! empty( $mainwp_giweb_activator->childEnabled['key'] )
			? $mainwp_giweb_activator->childEnabled['key']
			: $mainwp_giweb_activator->childKey;

		$metaboxes[] = array(
			'id'            => self::WIDGET_ID,
			'plugin'        => MAINWP_GIWEB_PLUGIN_FILE,
			'key'           => $key,
			'metabox_title' => __( 'GI-Toolkit — Mails', 'mainwp-giweb' ),
			'callback'      => array( __CLASS__, 'render_metabox' ),
		);

		return $metaboxes;
	}

	/**
	 * Rend le widget masquable via MainWP > Réglages > Outils MainWP.
	 *
	 * @param array<string, string> $options Widgets existants.
	 * @return array<string, string>
	 */
	public static function widgets_screen_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options[ self::WIDGET_ID ] = __( 'GI-Toolkit — Mails', 'mainwp-giweb' );
		return $options;
	}

	/**
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );
		if ( ! self::is_overview_screen() ) {
			return;
		}

		wp_enqueue_style(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array(),
			MAINWP_GIWEB_VERSION
		);
	}

	/**
	 * @return bool
	 */
	private static function is_overview_screen() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return in_array( $page, array( 'mainwp_tab', 'mainwp-setup' ), true );
	}

	/**
	 * @return void
	 */
	public static function render_metabox() {
		$agg     = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$network = $agg['network'] ?? array();
		$sites   = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated = ! empty( $agg['updated_at'] ) ? wp_date( 'Y-m-d H:i', (int) $agg['updated_at'] ) : '';
		$is_dark = MainWP_GIWeb_UI::is_dark_theme();

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

		$labels = isset( $network['chart_labels'] ) && is_array( $network['chart_labels'] ) ? $network['chart_labels'] : array();
		$sent   = isset( $network['chart_sent'] ) && is_array( $network['chart_sent'] ) ? $network['chart_sent'] : array();
		$failed = isset( $network['chart_failed'] ) && is_array( $network['chart_failed'] ) ? $network['chart_failed'] : array();
		$max    = 1;
		foreach ( $labels as $i => $label ) {
			unset( $label );
			$max = max( $max, (int) ( $sent[ $i ] ?? 0 ) + (int) ( $failed[ $i ] ?? 0 ) );
		}

		$manager_url = MainWP_GIWeb_UI::admin_page_url();
		?>
		<div class="mainwp-giweb-mail-widget<?php echo $is_dark ? ' mainwp-giweb-mail-widget--dark' : ' mainwp-giweb-mail-widget--light'; ?>">
			<?php if ( $updated ) : ?>
				<p class="mainwp-giweb-mail-widget__meta">
					<?php
					printf(
						/* translators: %s: datetime */
						esc_html__( 'Sync %s', 'mainwp-giweb' ),
						esc_html( $updated )
					);
					?>
				</p>
			<?php else : ?>
				<p class="mainwp-giweb-mail-widget__empty">
					<?php esc_html_e( 'Synchronisez les statuts dans GI-Toolkit Manager pour alimenter ce widget.', 'mainwp-giweb' ); ?>
				</p>
			<?php endif; ?>

			<div class="mainwp-giweb-mail-widget__grid">
				<div class="mainwp-giweb-mail-widget__kpi">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['total'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Total', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__kpi mainwp-giweb-mail-widget__kpi--ok">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['success'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__kpi <?php echo (int) ( $network['failed'] ?? 0 ) > 0 ? 'mainwp-giweb-mail-widget__kpi--alert' : ''; ?>">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['failed'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__kpi">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['today'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Auj.', 'mainwp-giweb' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $labels ) ) : ?>
				<div class="mainwp-giweb-mail-widget__chart" aria-hidden="true">
					<div class="mainwp-giweb-mail-widget__chart-bars">
						<?php foreach ( $labels as $i => $label ) : ?>
							<?php
							$s = (int) ( $sent[ $i ] ?? 0 );
							$f = (int) ( $failed[ $i ] ?? 0 );
							$sh = $max > 0 ? round( ( $s / $max ) * 100 ) : 0;
							$fh = $max > 0 ? round( ( $f / $max ) * 100 ) : 0;
							?>
							<div class="mainwp-giweb-mail-widget__bar-col" title="<?php echo esc_attr( $label . ' — ' . $s . ' / ' . $f ); ?>">
								<div class="mainwp-giweb-mail-widget__bar-stack">
									<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--fail" style="height:<?php echo esc_attr( (string) $fh ); ?>%"></span>
									<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--ok" style="height:<?php echo esc_attr( (string) $sh ); ?>%"></span>
								</div>
								<span class="mainwp-giweb-mail-widget__bar-label"><?php echo esc_html( wp_trim_words( $label, 1, '' ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="mainwp-giweb-mail-widget__legend">
						<span><i class="mainwp-giweb-mail-widget__dot mainwp-giweb-mail-widget__dot--ok"></i><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
						<span><i class="mainwp-giweb-mail-widget__dot mainwp-giweb-mail-widget__dot--fail"></i><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $failed_sites ) ) : ?>
				<div class="mainwp-giweb-mail-widget__sites">
					<strong><?php esc_html_e( 'Sites en alerte', 'mainwp-giweb' ); ?></strong>
					<ul>
						<?php foreach ( array_slice( $failed_sites, 0, 5, true ) as $site_id => $row ) : ?>
							<li>
								<span class="mainwp-giweb-mail-widget__site-name"><?php echo esc_html( $row['label'] ?? ( '#' . $site_id ) ); ?></span>
								<span class="mainwp-giweb-mail-widget__site-fail"><?php echo esc_html( (string) (int) ( $row['mail']['failed'] ?? 0 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="mainwp-giweb-mail-widget__footer">
				<a class="button button-small" href="<?php echo esc_url( $manager_url ); ?>">
					<?php esc_html_e( 'Ouvrir le manager', 'mainwp-giweb' ); ?>
				</a>
				<?php if ( (int) ( $network['sites_module_active'] ?? 0 ) > 0 ) : ?>
					<span class="mainwp-giweb-mail-widget__sites-count">
						<?php
						printf(
							/* translators: %d: site count */
							esc_html__( '%d site(s) suivis', 'mainwp-giweb' ),
							(int) $network['sites_module_active']
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
