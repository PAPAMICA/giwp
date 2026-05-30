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
	 * @param int|null                               $dashboard_siteid Site Overview (Manage Sites).
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes, $dashboard_siteid = null ) {
		return MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID,
			__( 'GI-Toolkit — Mails', 'mainwp-giweb' ),
			array( __CLASS__, 'render_metabox' ),
			$dashboard_siteid
		);
	}

	/**
	 * Rend le widget masquable via MainWP > Réglages > Outils MainWP.
	 *
	 * @param array<string, string> $options Widgets existants.
	 * @return array<string, string>
	 */
	public static function widgets_screen_options( $options ) {
		return MainWP_GIWeb_Metabox::append_screen_option(
			$options,
			self::WIDGET_ID,
			__( 'GI-Toolkit — Mails', 'mainwp-giweb' )
		);
	}

	/**
	 * @param string $hook Hook admin.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );
		if ( ! self::should_enqueue_assets() ) {
			return;
		}

		wp_enqueue_style(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array(),
			MAINWP_GIWEB_VERSION
		);

		wp_enqueue_script(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/dashboard-widget.js',
			array(),
			MAINWP_GIWEB_VERSION,
			true
		);
	}

	/**
	 * @return bool
	 */
	private static function should_enqueue_assets() {
		if ( isset( $_GET['page'] ) && 'managesites' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) && ! empty( $_GET['dashboard'] ) ) {
			return true;
		}

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
				'url'    => (string) ( $row['url'] ?? '' ),
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
	 * URL admin Mail Catcher sur un site enfant.
	 *
	 * @param string $site_url URL du site.
	 * @return string
	 */
	private static function site_mail_catcher_url( $site_url ) {
		$site_url = untrailingslashit( (string) $site_url );
		if ( '' === $site_url ) {
			return '';
		}
		return $site_url . '/wp-admin/admin.php?page=gi-toolkit-settings-mail-catcher';
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
		$site_id = MainWP_GIWeb_Metabox::get_render_site_id();
		if ( $site_id > 0 ) {
			self::render_site_metabox( $site_id );
			return;
		}

		$agg        = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$network    = $agg['network'] ?? array();
		$sites      = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated_at    = ! empty( $agg['updated_at'] ) ? (int) $agg['updated_at'] : 0;
		$is_dark       = MainWP_GIWeb_UI::is_dark_theme();
		$sites_tracked = (int) ( $network['sites_module_active'] ?? 0 );
		$has_failures  = (int) ( $network['failed'] ?? 0 ) > 0;

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
		$bar_h  = 56;
		foreach ( $labels as $i => $label ) {
			unset( $label );
			$max = max( $max, (int) ( $sent[ $i ] ?? 0 ) + (int) ( $failed[ $i ] ?? 0 ) );
		}
		?>
		<div class="mainwp-giweb-mail-widget<?php echo $is_dark ? ' mainwp-giweb-mail-widget--dark' : ' mainwp-giweb-mail-widget--light'; ?><?php echo $has_failures ? ' mainwp-giweb-mail-widget--has-errors' : ''; ?>">
			<div class="mainwp-giweb-mail-widget__header">
				<div class="mainwp-giweb-mail-widget__header-main">
					<?php if ( $sites_tracked > 0 ) : ?>
						<span class="mainwp-giweb-mail-widget__pill">
							<span class="mainwp-giweb-mail-widget__pill-dot" aria-hidden="true"></span>
							<?php
							printf(
								/* translators: %d: number of tracked sites */
								esc_html( _n( '%d site suivi', '%d sites suivis', $sites_tracked, 'mainwp-giweb' ) ),
								$sites_tracked
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( $updated_at > 0 ) : ?>
						<time
							class="mainwp-giweb-mail-widget__sync"
							datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>"
							data-sync-ts="<?php echo esc_attr( (string) $updated_at ); ?>"
						>
							<?php echo esc_html( self::format_sync_ago( $updated_at ) ); ?>
						</time>
					<?php elseif ( ! $sites_tracked ) : ?>
						<span class="mainwp-giweb-mail-widget__sync mainwp-giweb-mail-widget__sync--empty">
							<?php esc_html_e( 'Synchronisez vos sites MainWP pour alimenter ce widget', 'mainwp-giweb' ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<div class="mainwp-giweb-mail-widget__kpis">
				<div class="mainwp-giweb-mail-widget__kpi">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['total'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Total', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__kpi mainwp-giweb-mail-widget__kpi--ok">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['success'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__kpi <?php echo $has_failures ? 'mainwp-giweb-mail-widget__kpi--alert' : 'mainwp-giweb-mail-widget__kpi--neutral'; ?>">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['failed'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-mail-widget__kpi">
					<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['today'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Aujourd’hui', 'mainwp-giweb' ); ?></span>
				</div>
			</div>

			<div class="mainwp-giweb-mail-widget__layout">
				<?php if ( ! empty( $donut_segments ) && ! empty( $donut['style'] ) ) : ?>
					<div class="mainwp-giweb-mail-widget__donut-block">
						<h4 class="mainwp-giweb-mail-widget__section-title"><?php esc_html_e( 'Répartition par site', 'mainwp-giweb' ); ?></h4>
						<div class="mainwp-giweb-mail-widget__donut-row">
							<div class="mainwp-giweb-mail-widget__donut-wrap">
								<div class="mainwp-giweb-mail-widget__donut" style="<?php echo esc_attr( $donut['style'] ); ?>" role="img" aria-label="<?php esc_attr_e( 'Répartition des mails par site', 'mainwp-giweb' ); ?>">
									<div class="mainwp-giweb-mail-widget__donut-hole">
										<span class="mainwp-giweb-mail-widget__donut-sum"><?php echo esc_html( number_format_i18n( (int) $donut['sum'] ) ); ?></span>
										<span class="mainwp-giweb-mail-widget__donut-sum-label"><?php esc_html_e( 'mails', 'mainwp-giweb' ); ?></span>
									</div>
								</div>
							</div>
							<ul class="mainwp-giweb-mail-widget__donut-legend">
								<?php foreach ( $donut_segments as $i => $segment ) : ?>
									<?php
									$color      = $donut['colors'][ $i ] ?? '#316bff';
									$share      = $donut['sum'] > 0 ? round( ( $segment['total'] / $donut['sum'] ) * 100 ) : 0;
									$site_url   = self::site_mail_catcher_url( $segment['url'] ?? '' );
									$line_label = sprintf(
										/* translators: 1: percentage, 2: mail count */
										__( '%1$s%% (%2$s)', 'mainwp-giweb' ),
										(string) $share,
										number_format_i18n( (int) $segment['total'] )
									);
									if ( (int) $segment['failed'] > 0 ) {
										$line_label .= ' · ' . sprintf(
											/* translators: %d: failed count */
											_n( '%d échec', '%d échecs', (int) $segment['failed'], 'mainwp-giweb' ),
											(int) $segment['failed']
										);
									}
									?>
									<li class="mainwp-giweb-mail-widget__donut-legend-item">
										<span class="mainwp-giweb-mail-widget__donut-swatch" style="background:<?php echo esc_attr( $color ); ?>"></span>
										<?php if ( $site_url ) : ?>
											<a class="mainwp-giweb-mail-widget__donut-legend-line" href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $segment['label'] ); ?>">
												<span class="mainwp-giweb-mail-widget__donut-legend-name"><?php echo esc_html( $segment['label'] ); ?></span>
												<span class="mainwp-giweb-mail-widget__donut-legend-meta"><?php echo esc_html( $line_label ); ?></span>
											</a>
										<?php else : ?>
											<span class="mainwp-giweb-mail-widget__donut-legend-line">
												<span class="mainwp-giweb-mail-widget__donut-legend-name"><?php echo esc_html( $segment['label'] ); ?></span>
												<span class="mainwp-giweb-mail-widget__donut-legend-meta"><?php echo esc_html( $line_label ); ?></span>
											</span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $labels ) ) : ?>
					<div class="mainwp-giweb-mail-widget__chart">
						<h4 class="mainwp-giweb-mail-widget__section-title"><?php esc_html_e( '7 derniers jours', 'mainwp-giweb' ); ?></h4>
						<div class="mainwp-giweb-mail-widget__chart-bars" aria-hidden="true">
							<?php foreach ( $labels as $i => $label ) : ?>
								<?php
								$s      = (int) ( $sent[ $i ] ?? 0 );
								$f      = (int) ( $failed[ $i ] ?? 0 );
								$ok_h   = ( $max > 0 && $s > 0 ) ? max( 4, (int) round( ( $s / $max ) * $bar_h ) ) : 0;
								$fail_h = ( $max > 0 && $f > 0 ) ? max( 3, (int) round( ( $f / $max ) * $bar_h ) ) : 0;
								?>
								<div class="mainwp-giweb-mail-widget__bar-col" title="<?php echo esc_attr( $label . ' — ' . $s . ' OK / ' . $f . ' KO' ); ?>">
									<div class="mainwp-giweb-mail-widget__bar-stack" style="height:<?php echo esc_attr( (string) $bar_h ); ?>px">
										<?php if ( $fail_h > 0 ) : ?>
											<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--fail" style="height:<?php echo esc_attr( (string) $fail_h ); ?>px"></span>
										<?php endif; ?>
										<?php if ( $ok_h > 0 ) : ?>
											<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--ok" style="height:<?php echo esc_attr( (string) $ok_h ); ?>px"></span>
										<?php endif; ?>
										<?php if ( $ok_h <= 0 && $fail_h <= 0 ) : ?>
											<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--empty"></span>
										<?php endif; ?>
									</div>
									<span class="mainwp-giweb-mail-widget__bar-label"><?php echo esc_html( wp_trim_words( $label, 1, '' ) ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $failed_sites ) ) : ?>
					<div class="mainwp-giweb-mail-widget__sites">
						<h4 class="mainwp-giweb-mail-widget__section-title mainwp-giweb-mail-widget__section-title--alert"><?php esc_html_e( 'Sites en alerte', 'mainwp-giweb' ); ?></h4>
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

	/**
	 * Widget mail pour un site (Overview Manage Sites).
	 *
	 * @param int $site_id ID site MainWP.
	 * @return void
	 */
	public static function render_site_metabox( $site_id ) {
		$site_id = absint( $site_id );
		$agg     = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$sites   = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$row     = $sites[ $site_id ] ?? null;
		$mail    = is_array( $row ) && isset( $row['mail'] ) ? $row['mail'] : MainWP_GIWeb_Mail_Stats::get_site_mail( $site_id );
		$updated_at = ! empty( $row['synced_at'] ) ? (int) $row['synced_at'] : (int) ( $agg['updated_at'] ?? 0 );
		$is_dark    = MainWP_GIWeb_UI::is_dark_theme();
		$has_failures = is_array( $mail ) && (int) ( $mail['failed'] ?? 0 ) > 0;
		$labels     = is_array( $mail ) && isset( $mail['chart_labels'] ) && is_array( $mail['chart_labels'] ) ? $mail['chart_labels'] : array();
		$sent       = is_array( $mail ) && isset( $mail['chart_sent'] ) && is_array( $mail['chart_sent'] ) ? $mail['chart_sent'] : array();
		$failed     = is_array( $mail ) && isset( $mail['chart_failed'] ) && is_array( $mail['chart_failed'] ) ? $mail['chart_failed'] : array();
		$max        = 1;
		$bar_h      = 56;
		foreach ( $labels as $i => $label ) {
			unset( $label );
			$max = max( $max, (int) ( $sent[ $i ] ?? 0 ) + (int) ( $failed[ $i ] ?? 0 ) );
		}
		$site_url = is_array( $row ) ? self::site_mail_catcher_url( $row['url'] ?? '' ) : '';
		?>
		<div class="mainwp-giweb-mail-widget mainwp-giweb-mail-widget--single-site<?php echo $is_dark ? ' mainwp-giweb-mail-widget--dark' : ' mainwp-giweb-mail-widget--light'; ?><?php echo $has_failures ? ' mainwp-giweb-mail-widget--has-errors' : ''; ?>">
			<div class="mainwp-giweb-mail-widget__header">
				<div class="mainwp-giweb-mail-widget__header-main">
					<?php if ( $updated_at > 0 ) : ?>
						<time
							class="mainwp-giweb-mail-widget__sync"
							datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>"
							data-sync-ts="<?php echo esc_attr( (string) $updated_at ); ?>"
						>
							<?php echo esc_html( self::format_sync_ago( $updated_at ) ); ?>
						</time>
					<?php else : ?>
						<span class="mainwp-giweb-mail-widget__sync mainwp-giweb-mail-widget__sync--empty">
							<?php esc_html_e( 'Synchronisez ce site MainWP pour alimenter ce widget', 'mainwp-giweb' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $site_url ) : ?>
						<a class="mainwp-giweb-mail-widget__pill" href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Mail Catcher', 'mainwp-giweb' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! is_array( $mail ) || empty( $mail['module_active'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'Mail Catcher inactif sur ce site.', 'mainwp-giweb' ); ?></p>
			<?php elseif ( empty( $mail['table_ready'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'Module actif — en attente de données.', 'mainwp-giweb' ); ?></p>
			<?php else : ?>
				<div class="mainwp-giweb-mail-widget__kpis">
					<div class="mainwp-giweb-mail-widget__kpi">
						<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $mail['total'] ?? 0 ) ) ); ?></span>
						<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Total', 'mainwp-giweb' ); ?></span>
					</div>
					<div class="mainwp-giweb-mail-widget__kpi mainwp-giweb-mail-widget__kpi--ok">
						<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $mail['success'] ?? 0 ) ) ); ?></span>
						<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
					</div>
					<div class="mainwp-giweb-mail-widget__kpi <?php echo $has_failures ? 'mainwp-giweb-mail-widget__kpi--alert' : 'mainwp-giweb-mail-widget__kpi--neutral'; ?>">
						<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $mail['failed'] ?? 0 ) ) ); ?></span>
						<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></span>
					</div>
					<div class="mainwp-giweb-mail-widget__kpi">
						<span class="mainwp-giweb-mail-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $mail['today'] ?? 0 ) ) ); ?></span>
						<span class="mainwp-giweb-mail-widget__kpi-label"><?php esc_html_e( 'Aujourd’hui', 'mainwp-giweb' ); ?></span>
					</div>
				</div>

				<?php if ( ! empty( $labels ) ) : ?>
					<div class="mainwp-giweb-mail-widget__layout">
						<div class="mainwp-giweb-mail-widget__chart">
							<h4 class="mainwp-giweb-mail-widget__section-title"><?php esc_html_e( '7 derniers jours', 'mainwp-giweb' ); ?></h4>
							<div class="mainwp-giweb-mail-widget__chart-bars" aria-hidden="true">
								<?php foreach ( $labels as $i => $label ) : ?>
									<?php
									$s      = (int) ( $sent[ $i ] ?? 0 );
									$f      = (int) ( $failed[ $i ] ?? 0 );
									$ok_h   = ( $max > 0 && $s > 0 ) ? max( 4, (int) round( ( $s / $max ) * $bar_h ) ) : 0;
									$fail_h = ( $max > 0 && $f > 0 ) ? max( 3, (int) round( ( $f / $max ) * $bar_h ) ) : 0;
									?>
									<div class="mainwp-giweb-mail-widget__bar-col" title="<?php echo esc_attr( $label . ' — ' . $s . ' OK / ' . $f . ' KO' ); ?>">
										<div class="mainwp-giweb-mail-widget__bar-stack" style="height:<?php echo esc_attr( (string) $bar_h ); ?>px">
											<?php if ( $fail_h > 0 ) : ?>
												<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--fail" style="height:<?php echo esc_attr( (string) $fail_h ); ?>px"></span>
											<?php endif; ?>
											<?php if ( $ok_h > 0 ) : ?>
												<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--ok" style="height:<?php echo esc_attr( (string) $ok_h ); ?>px"></span>
											<?php endif; ?>
											<?php if ( $ok_h <= 0 && $fail_h <= 0 ) : ?>
												<span class="mainwp-giweb-mail-widget__bar mainwp-giweb-mail-widget__bar--empty"></span>
											<?php endif; ?>
										</div>
										<span class="mainwp-giweb-mail-widget__bar-label"><?php echo esc_html( wp_trim_words( $label, 1, '' ) ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
