<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statistiques mail réseau (simple / détaillé).
 */
class MainWP_GIWeb_Dashboard_Widget {

	const WIDGET_ID_SIMPLE    = 'mainwp-giweb-mail-widget-simple';
	const WIDGET_ID_DETAILED  = 'mainwp-giweb-mail-widget-detailed';

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Metaboxes existants.
	 * @param int|null                               $dashboard_siteid Site Overview (Manage Sites).
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes, $dashboard_siteid = null ) {
		$metaboxes = MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID_SIMPLE,
			__( 'GI-Toolkit — Mails (simple)', 'mainwp-giweb' ),
			array( __CLASS__, 'render_simple_metabox' ),
			$dashboard_siteid
		);

		return MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID_DETAILED,
			__( 'GI-Toolkit — Mails (détaillé)', 'mainwp-giweb' ),
			array( __CLASS__, 'render_detailed_metabox' ),
			$dashboard_siteid
		);
	}

	/**
	 * @param array<string, string> $options Widgets existants.
	 * @return array<string, string>
	 */
	public static function widgets_screen_options( $options ) {
		$options = MainWP_GIWeb_Metabox::append_screen_option(
			$options,
			self::WIDGET_ID_SIMPLE,
			__( 'GI-Toolkit — Mails (simple)', 'mainwp-giweb' )
		);

		return MainWP_GIWeb_Metabox::append_screen_option(
			$options,
			self::WIDGET_ID_DETAILED,
			__( 'GI-Toolkit — Mails (détaillé)', 'mainwp-giweb' )
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
			'mainwp-giweb-widget-shell',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/giweb-widget-shell.css',
			array(),
			MAINWP_GIWEB_VERSION
		);

		wp_enqueue_style(
			'mainwp-giweb-dashboard-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array( 'mainwp-giweb-widget-shell' ),
			MAINWP_GIWEB_VERSION
		);

		wp_enqueue_script(
			'mainwp-giweb-widget-shell',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/giweb-widget-shell.js',
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
	 * @return void
	 */
	public static function render_simple_metabox() {
		$site_id = MainWP_GIWeb_Metabox::get_render_site_id();
		if ( $site_id > 0 ) {
			self::render_site_metabox( $site_id );
			return;
		}

		self::render_network_metabox( false );
	}

	/**
	 * @return void
	 */
	public static function render_detailed_metabox() {
		$site_id = MainWP_GIWeb_Metabox::get_render_site_id();
		if ( $site_id > 0 ) {
			self::render_site_metabox( $site_id );
			return;
		}

		self::render_network_metabox( true );
	}

	/**
	 * @param bool $detailed Afficher donut, graphique, liste.
	 * @return void
	 */
	private static function render_network_metabox( $detailed ) {
		$ctx     = self::get_network_context();
		$is_dark = MainWP_GIWeb_UI::is_dark_theme();
		$mod     = $detailed ? ' mainwp-giweb-mail-widget--detailed' : ' mainwp-giweb-mail-widget--simple';
		$theme   = $is_dark ? ' mainwp-giweb-mail-widget--dark' : ' mainwp-giweb-mail-widget--light';
		?>
		<div class="mainwp-giweb-mail-widget<?php echo esc_attr( $mod . $theme ); ?>">
			<div class="giweb-gw">
				<header class="giweb-gw-header">
					<?php self::render_network_header( $ctx ); ?>
					<?php self::render_network_overview( $ctx ); ?>
				</header>

				<?php if ( $detailed ) : ?>
					<?php self::render_donut_panel( $ctx['sites'] ); ?>
					<?php self::render_chart_panel( $ctx['network'] ); ?>

					<?php if ( empty( $ctx['rows'] ) ) : ?>
						<div class="giweb-gw-empty-state">
							<p><?php esc_html_e( 'Aucun site avec Mail Catcher actif remonté pour le moment.', 'mainwp-giweb' ); ?></p>
						</div>
					<?php else : ?>
						<?php
						$list_mode = (string) ( MainWP_GIWeb_Settings::get()['mail_widget_list_mode'] ?? 'cards' );
						self::render_list_toolbar( $ctx, $list_mode );
						?>
						<div class="giweb-gw-list" data-default-view="<?php echo esc_attr( MainWP_GIWeb_Widget_UI::is_table_mode( $list_mode ) ? 'table' : 'cards' ); ?>" data-storage-key="giweb_gw_view_mail">
							<div class="giweb-gw-grid<?php echo esc_attr( MainWP_GIWeb_Widget_UI::list_view_class( $list_mode, 'cards' ) ); ?>">
								<?php foreach ( $ctx['rows'] as $row ) : ?>
									<?php self::render_mail_card( $row ); ?>
								<?php endforeach; ?>
							</div>
							<?php self::render_mail_table( $ctx['rows'], $list_mode ); ?>
						</div>
						<p class="giweb-gw-no-match" hidden><?php esc_html_e( 'Aucun site ne correspond à votre recherche.', 'mainwp-giweb' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_network_context() {
		$agg           = MainWP_GIWeb_Mail_Stats::get_aggregate();
		$network       = is_array( $agg['network'] ?? null ) ? $agg['network'] : array();
		$sites         = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated_at    = ! empty( $agg['updated_at'] ) ? (int) $agg['updated_at'] : 0;
		$sites_tracked = (int) ( $network['sites_module_active'] ?? 0 );
		$total_mainwp  = MainWP_GIWeb_Widget_UI::count_mainwp_sites();
		$rows          = self::build_mail_rows( $sites );
		$mail_total    = (int) ( $network['total'] ?? 0 );
		$mail_success  = (int) ( $network['success'] ?? 0 );
		$mail_failed   = (int) ( $network['failed'] ?? 0 );
		$mail_today    = (int) ( $network['today'] ?? 0 );
		$health        = $mail_total > 0 ? round( ( $mail_success / $mail_total ) * 100, 0 ) : 100;

		return array(
			'network'        => $network,
			'sites'          => $sites,
			'updated_at'     => $updated_at,
			'sites_tracked'  => $sites_tracked,
			'total_mainwp'   => $total_mainwp,
			'rows'           => $rows,
			'mail_total'     => $mail_total,
			'mail_success'   => $mail_success,
			'mail_failed'    => $mail_failed,
			'mail_today'     => $mail_today,
			'health'         => $health,
			'strip_segments' => MainWP_GIWeb_Widget_UI::build_mail_strip_segments( $sites ),
			'issues_count'   => count(
				array_filter(
					$rows,
					static function ( $row ) {
						return 'issues' === ( $row['filter_status'] ?? '' );
					}
				)
			),
			'ok_rows'        => count(
				array_filter(
					$rows,
					static function ( $row ) {
						return 'ok' === ( $row['filter_status'] ?? '' );
					}
				)
			),
			'inactive_rows'  => count(
				array_filter(
					$rows,
					static function ( $row ) {
						return 'inactive' === ( $row['filter_status'] ?? '' );
					}
				)
			),
		);
	}

	/**
	 * @param array<string, mixed> $ctx Contexte réseau.
	 * @return void
	 */
	private static function render_network_header( array $ctx ) {
		$subtitle = sprintf(
			/* translators: 1: tracked sites, 2: total MainWP sites */
			__( '%1$d / %2$d sites suivis', 'mainwp-giweb' ),
			(int) $ctx['sites_tracked'],
			(int) $ctx['total_mainwp']
		);
		$empty_sync = ! (int) $ctx['sites_tracked']
			? __( 'Synchronisez vos sites MainWP pour alimenter ce widget', 'mainwp-giweb' )
			: '';

		MainWP_GIWeb_Widget_UI::render_header_row(
			'mail',
			__( 'Mails', 'mainwp-giweb' ),
			$subtitle,
			(int) $ctx['updated_at'],
			$empty_sync
		);
	}

	/**
	 * @param array<string, mixed> $ctx Contexte réseau.
	 * @return void
	 */
	private static function render_network_overview( array $ctx ) {
		if ( (int) $ctx['total_mainwp'] < 1 && empty( $ctx['strip_segments'] ) ) {
			return;
		}

		$stats = array(
			array(
				'strong' => number_format_i18n( (int) $ctx['mail_total'] ),
				'label'  => __( 'Total', 'mainwp-giweb' ),
			),
			array(
				'strong'   => number_format_i18n( (int) $ctx['mail_success'] ),
				'label'    => __( 'OK', 'mainwp-giweb' ),
				'modifier' => 'ok',
			),
			array(
				'strong'   => number_format_i18n( (int) $ctx['mail_failed'] ),
				'label'    => __( 'Échecs', 'mainwp-giweb' ),
				'modifier' => 'down',
			),
			array(
				'strong' => number_format_i18n( (int) $ctx['mail_today'] ),
				'label'  => __( 'Aujourd’hui', 'mainwp-giweb' ),
			),
		);

		MainWP_GIWeb_Widget_UI::render_overview(
			(float) $ctx['health'],
			(array) $ctx['strip_segments'],
			$stats
		);
	}

	/**
	 * @param array<string, mixed> $ctx Contexte réseau.
	 * @return void
	 */
	private static function render_list_toolbar( array $ctx, $list_mode = 'cards' ) {
		?>
		<div class="giweb-gw-toolbar">
			<label class="giweb-gw-search">
				<span class="screen-reader-text"><?php esc_html_e( 'Rechercher un site', 'mainwp-giweb' ); ?></span>
				<input type="search" class="giweb-gw-search__input" placeholder="<?php esc_attr_e( 'Rechercher…', 'mainwp-giweb' ); ?>" autocomplete="off" />
			</label>
			<div class="giweb-gw-filters" role="tablist">
				<button type="button" class="giweb-gw-filter is-active" data-filter="all" role="tab"><?php esc_html_e( 'Tous', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) count( $ctx['rows'] ) ); ?></em></button>
				<button type="button" class="giweb-gw-filter" data-filter="ok" role="tab"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $ctx['ok_rows'] ); ?></em></button>
				<button type="button" class="giweb-gw-filter" data-filter="issues" role="tab"><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $ctx['issues_count'] ); ?></em></button>
				<button type="button" class="giweb-gw-filter" data-filter="inactive" role="tab"><?php esc_html_e( 'En attente', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $ctx['inactive_rows'] ); ?></em></button>
			</div>
			<?php MainWP_GIWeb_Widget_UI::render_view_toggle( $list_mode, 'giweb_gw_view_mail' ); ?>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $sites Sites agrégés.
	 * @return void
	 */
	private static function render_donut_panel( $sites ) {
		$segments = self::get_donut_segments( $sites );
		if ( empty( $segments ) ) {
			return;
		}

		$donut = self::build_donut_style( $segments );
		if ( empty( $donut['style'] ) ) {
			return;
		}
		?>
		<div class="giweb-gw-panel mainwp-giweb-mail-widget__donut-block">
			<h4 class="giweb-gw-panel__title"><?php esc_html_e( 'Répartition par site', 'mainwp-giweb' ); ?></h4>
			<div class="mainwp-giweb-mail-widget__donut-layout">
				<div class="mainwp-giweb-mail-widget__donut" style="<?php echo esc_attr( $donut['style'] ); ?>" role="img" aria-label="<?php esc_attr_e( 'Répartition des envois par site', 'mainwp-giweb' ); ?>">
					<span class="mainwp-giweb-mail-widget__donut-center"><?php echo esc_html( number_format_i18n( (int) $donut['sum'] ) ); ?></span>
				</div>
				<ul class="mainwp-giweb-mail-widget__donut-legend">
					<?php foreach ( $segments as $i => $segment ) : ?>
						<li>
							<span class="mainwp-giweb-mail-widget__donut-swatch" style="background:<?php echo esc_attr( (string) ( $donut['colors'][ $i ] ?? '#667085' ) ); ?>"></span>
							<span class="mainwp-giweb-mail-widget__donut-label"><?php echo esc_html( (string) ( $segment['label'] ?? '' ) ); ?></span>
							<span class="mainwp-giweb-mail-widget__donut-value"><?php echo esc_html( number_format_i18n( (int) ( $segment['total'] ?? 0 ) ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $network Agrégat réseau.
	 * @return void
	 */
	private static function render_chart_panel( $network ) {
		$labels = isset( $network['chart_labels'] ) && is_array( $network['chart_labels'] ) ? $network['chart_labels'] : array();
		if ( empty( $labels ) ) {
			return;
		}

		$sent   = isset( $network['chart_sent'] ) && is_array( $network['chart_sent'] ) ? $network['chart_sent'] : array();
		$failed = isset( $network['chart_failed'] ) && is_array( $network['chart_failed'] ) ? $network['chart_failed'] : array();
		$max    = 1;
		$bar_h  = 56;
		foreach ( $labels as $i => $label ) {
			unset( $label );
			$max = max( $max, (int) ( $sent[ $i ] ?? 0 ) + (int) ( $failed[ $i ] ?? 0 ) );
		}
		?>
		<div class="giweb-gw-panel">
			<h4 class="giweb-gw-panel__title"><?php esc_html_e( '7 derniers jours', 'mainwp-giweb' ); ?></h4>
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
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Lignes sites.
	 * @return void
	 */
	private static function render_mail_cards( array $rows ) {
		?>
		<div class="giweb-gw-grid">
			<?php foreach ( $rows as $row ) : ?>
				<?php self::render_mail_card( $row ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $rows      Lignes sites.
	 * @param string                            $list_mode cards|table.
	 * @return void
	 */
	private static function render_mail_table( array $rows, $list_mode = 'cards' ) {
		?>
		<div class="giweb-gw-table-wrap<?php echo esc_attr( MainWP_GIWeb_Widget_UI::list_view_class( $list_mode, 'table' ) ); ?>">
			<table class="giweb-gw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Total', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Aujourd’hui', 'mainwp-giweb' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr data-status="<?php echo esc_attr( (string) ( $row['filter_status'] ?? 'inactive' ) ); ?>" data-search="<?php echo esc_attr( (string) ( $row['search'] ?? '' ) ); ?>">
							<td>
								<?php if ( ! empty( $row['admin_url'] ) ) : ?>
									<a href="<?php echo esc_url( (string) $row['admin_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( (int) ( $row['total'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) ( $row['success'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) ( $row['failed'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) ( $row['today'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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
		$labels     = is_array( $mail ) && isset( $mail['chart_labels'] ) && is_array( $mail['chart_labels'] ) ? $mail['chart_labels'] : array();
		$sent       = is_array( $mail ) && isset( $mail['chart_sent'] ) && is_array( $mail['chart_sent'] ) ? $mail['chart_sent'] : array();
		$failed     = is_array( $mail ) && isset( $mail['chart_failed'] ) && is_array( $mail['chart_failed'] ) ? $mail['chart_failed'] : array();
		$max        = 1;
		$bar_h      = 56;
		foreach ( $labels as $i => $label ) {
			unset( $label );
			$max = max( $max, (int) ( $sent[ $i ] ?? 0 ) + (int) ( $failed[ $i ] ?? 0 ) );
		}
		$mail_row = self::build_mail_row_from_site( $site_id, is_array( $row ) ? $row : array( 'label' => '#' . $site_id ), is_array( $mail ) ? $mail : array() );
		?>
		<div class="mainwp-giweb-mail-widget mainwp-giweb-mail-widget--single-site<?php echo $is_dark ? ' mainwp-giweb-mail-widget--dark' : ' mainwp-giweb-mail-widget--light'; ?>">
			<div class="giweb-gw giweb-gw--single">
				<header class="giweb-gw-header">
					<?php
					MainWP_GIWeb_Widget_UI::render_header_row(
						'mail',
						__( 'Mails', 'mainwp-giweb' ),
						__( 'Monitor de ce site', 'mainwp-giweb' ),
						$updated_at,
						__( 'Synchronisez ce site MainWP pour alimenter ce widget', 'mainwp-giweb' )
					);
					?>
				</header>

				<?php if ( ! is_array( $mail ) || empty( $mail['module_active'] ) ) : ?>
					<div class="giweb-gw-empty-state">
						<p><?php esc_html_e( 'Mail Catcher inactif sur ce site.', 'mainwp-giweb' ); ?></p>
					</div>
				<?php elseif ( empty( $mail['table_ready'] ) ) : ?>
					<div class="giweb-gw-empty-state">
						<p><?php esc_html_e( 'Module actif — en attente de données.', 'mainwp-giweb' ); ?></p>
					</div>
				<?php else : ?>
					<?php self::render_mail_card( $mail_row ); ?>

					<?php if ( ! empty( $labels ) ) : ?>
						<div class="giweb-gw-panel">
							<h4 class="giweb-gw-panel__title"><?php esc_html_e( '7 derniers jours', 'mainwp-giweb' ); ?></h4>
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
				<?php endif; ?>
			</div>
		</div>
		<?php
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
			$hue       = ( $i * 47 ) % 360;
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
			$pct     = ( (int) $segment['total'] / $sum ) * 100;
			$end     = $cursor + $pct;
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
	 * @param array<int, array<string, mixed>> $sites Sites agrégés.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_mail_rows( $sites ) {
		$rows = array();

		foreach ( $sites as $site_id => $site_row ) {
			if ( ! is_array( $site_row ) ) {
				continue;
			}
			$mail = $site_row['mail'] ?? null;
			if ( ! is_array( $mail ) || empty( $mail['module_active'] ) ) {
				continue;
			}
			$rows[] = self::build_mail_row_from_site( $site_id, $site_row, $mail );
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$order = array(
					'issues'   => 0,
					'inactive' => 1,
					'ok'       => 2,
				);
				$oa = $order[ $a['filter_status'] ?? '' ] ?? 3;
				$ob = $order[ $b['filter_status'] ?? '' ] ?? 3;
				if ( $oa !== $ob ) {
					return $oa <=> $ob;
				}
				return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			}
		);

		return $rows;
	}

	/**
	 * @param int                  $site_id   ID site.
	 * @param array<string, mixed> $site_row  Ligne site.
	 * @param array<string, mixed> $mail      Stats mail.
	 * @return array<string, mixed>
	 */
	private static function build_mail_row_from_site( $site_id, $site_row, $mail ) {
		global $mainwp_giweb_activator;

		$normalized = MainWP_GIWeb_Sites::find_by_id( $site_id, $mainwp_giweb_activator );
		$label      = MainWP_GIWeb_Widget_UI::site_label( $normalized, $site_row );
		$failed = (int) ( $mail['failed'] ?? 0 );
		$ready  = ! empty( $mail['table_ready'] );

		if ( ! $ready ) {
			$card_status   = 'missing';
			$filter_status = 'inactive';
			$badge         = __( 'En attente', 'mainwp-giweb' );
		} elseif ( $failed > 0 ) {
			$card_status   = 'fail';
			$filter_status = 'issues';
			$badge         = sprintf(
				_n( '%d échec', '%d échecs', $failed, 'mainwp-giweb' ),
				$failed
			);
		} else {
			$card_status   = 'ok';
			$filter_status = 'ok';
			$badge         = __( 'OK', 'mainwp-giweb' );
		}

		return array(
			'label'         => $label,
			'admin_url'     => self::site_mail_catcher_url( $site_row['url'] ?? '' ),
			'card_status'   => $card_status,
			'filter_status' => $filter_status,
			'badge'         => $badge,
			'total'         => (int) ( $mail['total'] ?? 0 ),
			'success'       => (int) ( $mail['success'] ?? 0 ),
			'failed'        => $failed,
			'today'         => (int) ( $mail['today'] ?? 0 ),
			'search'        => strtolower( $label . ' ' . MainWP_GIWeb_Widget_UI::site_url_host( $site_row['url'] ?? '' ) ),
		);
	}

	/**
	 * @param array<string, mixed> $row Ligne site.
	 * @return void
	 */
	private static function render_mail_card( array $row ) {
		$card_status = (string) ( $row['card_status'] ?? 'missing' );
		?>
		<article class="giweb-gw-card status-<?php echo esc_attr( $card_status ); ?>" data-status="<?php echo esc_attr( (string) ( $row['filter_status'] ?? 'inactive' ) ); ?>" data-search="<?php echo esc_attr( (string) ( $row['search'] ?? '' ) ); ?>">
			<header class="giweb-gw-card__head">
				<span class="giweb-gw-card__badge"><?php echo esc_html( (string) ( $row['badge'] ?? '' ) ); ?></span>
				<span class="giweb-gw-card__meta"><?php echo esc_html( number_format_i18n( (int) ( $row['total'] ?? 0 ) ) ); ?></span>
			</header>
			<h3 class="giweb-gw-card__title">
				<?php if ( ! empty( $row['admin_url'] ) ) : ?>
					<a href="<?php echo esc_url( (string) $row['admin_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></a>
				<?php else : ?>
					<?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?>
				<?php endif; ?>
			</h3>
			<div class="giweb-gw-card__rows">
				<div class="giweb-gw-card__row">
					<span class="giweb-gw-card__row-label"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
					<span class="giweb-gw-card__row-value"><?php echo esc_html( number_format_i18n( (int) ( $row['success'] ?? 0 ) ) ); ?></span>
				</div>
				<div class="giweb-gw-card__row">
					<span class="giweb-gw-card__row-label"><?php esc_html_e( 'Échecs', 'mainwp-giweb' ); ?></span>
					<span class="giweb-gw-card__row-value"><?php echo esc_html( number_format_i18n( (int) ( $row['failed'] ?? 0 ) ) ); ?></span>
				</div>
				<div class="giweb-gw-card__row">
					<span class="giweb-gw-card__row-label"><?php esc_html_e( 'Aujourd’hui', 'mainwp-giweb' ); ?></span>
					<span class="giweb-gw-card__row-value"><?php echo esc_html( number_format_i18n( (int) ( $row['today'] ?? 0 ) ) ); ?></span>
				</div>
			</div>
		</article>
		<?php
	}
}
