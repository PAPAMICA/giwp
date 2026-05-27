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
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function format_sync_ago( $timestamp ) {
		$timestamp = absint( $timestamp );
		if ( ! $timestamp ) {
			return '';
		}

		$now  = (int) current_time( 'timestamp' );
		$diff = max( 0, $now - $timestamp );

		if ( $diff < 60 ) {
			return __( 'Sync à l’instant', 'mainwp-giweb' );
		}

		return sprintf(
			/* translators: %s: human-readable time difference */
			__( 'Sync il y a %s', 'mainwp-giweb' ),
			human_time_diff( $timestamp, $now )
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sites Sites agrégés.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_donut_segments( $sites ) {
		$segments = array();

		foreach ( $sites as $site_id => $row ) {
			$mail = $row['mail'] ?? null;
			if ( ! is_array( $mail ) || empty( $mail['module_active'] ) || empty( $mail['table_ready'] ) ) {
				continue;
			}

			$total = (int) ( $mail['total'] ?? 0 );
			if ( $total <= 0 ) {
				continue;
			}

			$segments[] = array(
				'id'     => (int) $site_id,
				'label'  => (string) ( $row['label'] ?? ( '#' . $site_id ) ),
				'total'  => $total,
				'failed' => (int) ( $mail['failed'] ?? 0 ),
			);
		}

		usort(
			$segments,
			static function ( $a, $b ) {
				return $b['total'] - $a['total'];
			}
		);

		return $segments;
	}

	/**
	 * @param int $count Nombre de segments.
	 * @return array<int, string>
	 */
	private static function donut_palette( $count ) {
		$base = array(
			'#316bff',
			'#12b76a',
			'#f79009',
			'#7a5af8',
			'#06aed4',
			'#ee46bc',
			'#667085',
			'#f04438',
		);

		if ( $count <= count( $base ) ) {
			return array_slice( $base, 0, max( 1, $count ) );
		}

		$out = $base;
		for ( $i = count( $base ); $i < $count; $i++ ) {
			$hue        = ( $i * 47 ) % 360;
			$out[ $i ] = 'hsl(' . $hue . ', 62%, 52%)';
		}

		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $segments Segments donut.
	 * @return array{style: string, colors: array<int, string>, sum: int}
	 */
	private static function build_donut_style( $segments ) {
		$sum = 0;
		foreach ( $segments as $segment ) {
			$sum += (int) ( $segment['total'] ?? 0 );
		}

		if ( $sum <= 0 ) {
			return array(
				'style'  => '',
				'colors' => array(),
				'sum'    => 0,
			);
		}

		$colors = self::donut_palette( count( $segments ) );
		$parts  = array();
		$cursor = 0.0;

		foreach ( $segments as $i => $segment ) {
			$pct = ( (int) $segment['total'] / $sum ) * 100;
			$end = $cursor + $pct;
			$parts[] = $colors[ $i ] . ' ' . round( $cursor, 2 ) . '% ' . round( $end, 2 ) . '%';
			$cursor  = $end;
		}

		return array(
			'style'  => 'background:conic-gradient(' . implode( ', ', $parts ) . ');',
			'colors' => $colors,
			'sum'    => $sum,
		);
	}

	/**
	 * @return void
	 */
	public static function render_metabox() {
		$agg        = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$network    = $agg['network'] ?? array();
		$sites      = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated_at = ! empty( $agg['updated_at'] ) ? (int) $agg['updated_at'] : 0;
		$is_dark    = MainWP_GIWeb_UI::is_dark_theme();
		$sync_ago   = self::format_sync_ago( $updated_at );
		$sites_tracked = (int) ( $network['sites_module_active'] ?? 0 );

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

		$donut_segments = self::get_donut_segments( $sites );
		$donut          = self::build_donut_style( $donut_segments );

		$labels = isset( $network['chart_labels'] ) && is_array( $network['chart_labels'] ) ? $network['chart_labels'] : array();
		$sent   = isset( $network['chart_sent'] ) && is_array( $network['chart_sent'] ) ? $network['chart_sent'] : array();
		$failed = isset( $network['chart_failed'] ) && is_array( $network['chart_failed'] ) ? $network['chart_failed'] : array();
		$max    = 1;
		foreach ( $labels as $i => $label ) {
			unset( $label );
			$max = max( $max, (int) ( $sent[ $i ] ?? 0 ) + (int) ( $failed[ $i ] ?? 0 ) );
		}
		?>
		<div class="mainwp-giweb-mail-widget<?php echo $is_dark ? ' mainwp-giweb-mail-widget--dark' : ' mainwp-giweb-mail-widget--light'; ?>">
			<div class="mainwp-giweb-mail-widget__header">
				<?php if ( $sites_tracked > 0 ) : ?>
					<span class="mainwp-giweb-mail-widget__pill">
						<?php
						printf(
							/* translators: %d: number of tracked sites */
							esc_html( _n( '%d site suivi', '%d sites suivis', $sites_tracked, 'mainwp-giweb' ) ),
							$sites_tracked
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( $sync_ago ) : ?>
					<span class="mainwp-giweb-mail-widget__sync" title="<?php echo esc_attr( wp_date( 'Y-m-d H:i', $updated_at ) ); ?>">
						<?php echo esc_html( $sync_ago ); ?>
					</span>
				<?php elseif ( ! $sites_tracked ) : ?>
					<span class="mainwp-giweb-mail-widget__sync mainwp-giweb-mail-widget__sync--empty">
						<?php esc_html_e( 'Aucune donnée — lancez une synchronisation', 'mainwp-giweb' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="mainwp-giweb-mail-widget__layout">
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

				<?php if ( ! empty( $donut_segments ) && ! empty( $donut['style'] ) ) : ?>
					<div class="mainwp-giweb-mail-widget__donut-block">
						<p class="mainwp-giweb-mail-widget__donut-title"><?php esc_html_e( 'Répartition par site', 'mainwp-giweb' ); ?></p>
						<div class="mainwp-giweb-mail-widget__donut-row">
							<div class="mainwp-giweb-mail-widget__donut" style="<?php echo esc_attr( $donut['style'] ); ?>" role="img" aria-label="<?php esc_attr_e( 'Répartition des mails par site', 'mainwp-giweb' ); ?>">
								<div class="mainwp-giweb-mail-widget__donut-hole">
									<span class="mainwp-giweb-mail-widget__donut-sum"><?php echo esc_html( number_format_i18n( (int) $donut['sum'] ) ); ?></span>
									<span class="mainwp-giweb-mail-widget__donut-sum-label"><?php esc_html_e( 'mails', 'mainwp-giweb' ); ?></span>
								</div>
							</div>
							<ul class="mainwp-giweb-mail-widget__donut-legend">
								<?php foreach ( $donut_segments as $i => $segment ) : ?>
									<?php
									$color = $donut['colors'][ $i ] ?? '#316bff';
									$share = $donut['sum'] > 0 ? round( ( $segment['total'] / $donut['sum'] ) * 100 ) : 0;
									?>
									<li class="mainwp-giweb-mail-widget__donut-legend-item">
										<span class="mainwp-giweb-mail-widget__donut-swatch" style="background:<?php echo esc_attr( $color ); ?>"></span>
										<span class="mainwp-giweb-mail-widget__donut-legend-text">
											<span class="mainwp-giweb-mail-widget__donut-legend-name" title="<?php echo esc_attr( $segment['label'] ); ?>">
												<?php echo esc_html( $segment['label'] ); ?>
											</span>
											<span class="mainwp-giweb-mail-widget__donut-legend-meta">
												<?php
												echo esc_html(
													sprintf(
														/* translators: 1: mail count, 2: percentage */
														__( '%1$s (%2$s%%)', 'mainwp-giweb' ),
														number_format_i18n( (int) $segment['total'] ),
														(string) $share
													)
												);
												?>
												<?php if ( (int) $segment['failed'] > 0 ) : ?>
													<span class="mainwp-giweb-mail-widget__donut-fail">· <?php echo esc_html( (string) (int) $segment['failed'] ); ?> <?php esc_html_e( 'échec(s)', 'mainwp-giweb' ); ?></span>
												<?php endif; ?>
											</span>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $labels ) ) : ?>
					<div class="mainwp-giweb-mail-widget__chart">
						<p class="mainwp-giweb-mail-widget__chart-title"><?php esc_html_e( '7 derniers jours (réseau)', 'mainwp-giweb' ); ?></p>
						<div class="mainwp-giweb-mail-widget__chart-bars" aria-hidden="true">
							<?php foreach ( $labels as $i => $label ) : ?>
								<?php
								$s  = (int) ( $sent[ $i ] ?? 0 );
								$f  = (int) ( $failed[ $i ] ?? 0 );
								$sh = $max > 0 ? round( ( $s / $max ) * 100 ) : 0;
								$fh = $max > 0 ? round( ( $f / $max ) * 100 ) : 0;
								?>
								<div class="mainwp-giweb-mail-widget__bar-col" title="<?php echo esc_attr( $label . ' — ' . $s . ' OK / ' . $f . ' KO' ); ?>">
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
			</div>
		</div>
		<?php
	}
}
