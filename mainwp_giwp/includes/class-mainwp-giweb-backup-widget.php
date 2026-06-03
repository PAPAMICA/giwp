<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statuts UpdraftPlus réseau / site (simple / détaillé).
 */
class MainWP_GIWeb_Backup_Widget {

	const WIDGET_ID_SIMPLE   = 'mainwp-giweb-backup-widget-simple';
	const WIDGET_ID_DETAILED = 'mainwp-giweb-backup-widget-detailed';

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Metaboxes existants.
	 * @param int|null                               $dashboard_siteid Site Overview.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes, $dashboard_siteid = null ) {
		$metaboxes = MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID_SIMPLE,
			__( 'GI-Toolkit — Backups UpdraftPlus (simple)', 'mainwp-giweb' ),
			array( __CLASS__, 'render_simple_metabox' ),
			$dashboard_siteid
		);

		return MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID_DETAILED,
			__( 'GI-Toolkit — Backups UpdraftPlus (détaillé)', 'mainwp-giweb' ),
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
			__( 'GI-Toolkit — Backups UpdraftPlus (simple)', 'mainwp-giweb' )
		);

		return MainWP_GIWeb_Metabox::append_screen_option(
			$options,
			self::WIDGET_ID_DETAILED,
			__( 'GI-Toolkit — Backups UpdraftPlus (détaillé)', 'mainwp-giweb' )
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
			'mainwp-giweb-backup-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/backup-widget.css',
			array( 'mainwp-giweb-widget-shell' ),
			MAINWP_GIWEB_VERSION
		);

		MainWP_GIWeb_Metabox::enqueue_widget_shell_script();
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
	 * @param bool $detailed Afficher toolbar et liste.
	 * @return void
	 */
	private static function render_network_metabox( $detailed ) {
		$is_dark = MainWP_GIWeb_UI::is_dark_theme();
		$mod     = $detailed ? ' mainwp-giweb-backup-widget--detailed' : ' mainwp-giweb-backup-widget--simple';
		$theme   = $is_dark ? ' mainwp-giweb-backup-widget--dark' : ' mainwp-giweb-backup-widget--light';
		?>
		<div class="mainwp-giweb-backup-widget<?php echo esc_attr( $mod . $theme ); ?>">
			<?php self::render_network_body( $detailed ); ?>
		</div>
		<?php
	}

	/**
	 * Corps HTML du widget réseau (AJAX refresh).
	 *
	 * @param bool $detailed Afficher toolbar et liste.
	 * @return void
	 */
	public static function render_network_body( $detailed ) {
		$ctx = self::get_network_context();
		?>
		<div class="giweb-gw">
			<header class="giweb-gw-header">
				<?php self::render_network_header( $ctx, $detailed ); ?>
				<?php self::render_network_overview( $ctx ); ?>
			</header>

			<?php if ( $detailed ) : ?>
				<?php if ( empty( $ctx['rows'] ) ) : ?>
					<div class="giweb-gw-empty-state">
						<p><?php esc_html_e( 'Aucun site MainWP disponible pour le moment.', 'mainwp-giweb' ); ?></p>
					</div>
				<?php else : ?>
					<?php
					$list_mode = (string) ( MainWP_GIWeb_Settings::get()['backup_widget_list_mode'] ?? 'cards' );
					self::render_list_toolbar( $ctx, $list_mode );
					?>
					<div class="giweb-gw-list" data-default-view="<?php echo esc_attr( MainWP_GIWeb_Widget_UI::is_table_mode( $list_mode ) ? 'table' : 'cards' ); ?>" data-storage-key="giweb_gw_view_backup">
						<div class="giweb-gw-grid<?php echo esc_attr( MainWP_GIWeb_Widget_UI::list_view_class( $list_mode, 'cards' ) ); ?>">
							<?php foreach ( $ctx['rows'] as $row ) : ?>
								<?php self::render_backup_card( $row ); ?>
							<?php endforeach; ?>
						</div>
						<?php self::render_backup_table( $ctx['rows'], $list_mode ); ?>
					</div>
					<p class="giweb-gw-no-match" hidden><?php esc_html_e( 'Aucun site ne correspond à votre recherche.', 'mainwp-giweb' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_network_context() {
		$agg            = MainWP_GIWeb_Backup_Stats::get_aggregate();
		$network        = is_array( $agg['network'] ?? null ) ? $agg['network'] : array();
		$sites          = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated_at     = ! empty( $agg['updated_at'] ) ? (int) $agg['updated_at'] : 0;
		$rows           = self::build_all_site_rows( $sites );
		$sites_active   = (int) ( $network['sites_active'] ?? 0 );
		$total_mainwp   = MainWP_GIWeb_Widget_UI::count_mainwp_sites();
		$stale_count    = (int) ( $network['sites_stale'] ?? 0 ) + (int) ( $network['sites_no_backup'] ?? 0 );
		$remote_missing = (int) ( $network['sites_remote_missing'] ?? 0 );
		$in_progress    = (int) ( $network['sites_in_progress'] ?? 0 );
		$no_backup      = max( 0, $total_mainwp - $sites_active );
		$fresh          = (int) ( $network['sites_fresh'] ?? 0 );
		$health         = $total_mainwp > 0 ? round( ( $fresh / $total_mainwp ) * 100, 0 ) : 100;
		$issues_count   = max( 0, $stale_count + $remote_missing + $no_backup );

		return array(
			'network'        => $network,
			'sites'          => $sites,
			'updated_at'     => $updated_at,
			'rows'           => $rows,
			'sites_active'   => $sites_active,
			'total_mainwp'   => $total_mainwp,
			'fresh'          => $fresh,
			'issues_count'   => $issues_count,
			'in_progress'    => $in_progress,
			'no_backup'      => $no_backup,
			'health'         => $health,
			'strip_segments' => MainWP_GIWeb_Widget_UI::build_backup_strip_segments( $sites ),
		);
	}

	/**
	 * @param array<string, mixed> $ctx      Contexte réseau.
	 * @param bool                 $detailed Vue détaillée (refresh AJAX).
	 * @return void
	 */
	private static function render_network_header( array $ctx, $detailed = false ) {
		$subtitle = sprintf(
			/* translators: 1: active count, 2: total MainWP sites */
			__( '%1$d / %2$d sites avec UpdraftPlus', 'mainwp-giweb' ),
			(int) $ctx['sites_active'],
			(int) $ctx['total_mainwp']
		);
		$empty_sync = ! (int) $ctx['sites_active']
			? __( 'Synchronisez vos sites MainWP pour alimenter ce widget', 'mainwp-giweb' )
			: '';

		MainWP_GIWeb_Widget_UI::render_header_row(
			'backup',
			__( 'Backups UpdraftPlus', 'mainwp-giweb' ),
			$subtitle,
			(int) $ctx['updated_at'],
			$empty_sync,
			array(
				'scope'    => 'backup',
				'site_id'  => 0,
				'detailed' => $detailed,
			)
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
				'strong'   => number_format_i18n( (int) $ctx['fresh'] ),
				'label'    => __( 'Récent (<10 j)', 'mainwp-giweb' ),
				'modifier' => 'ok',
			),
			array(
				'strong'   => number_format_i18n( (int) $ctx['issues_count'] ),
				'label'    => __( 'Alertes', 'mainwp-giweb' ),
				'modifier' => 'down',
			),
			array(
				'strong'   => number_format_i18n( (int) $ctx['in_progress'] ),
				'label'    => __( 'En cours', 'mainwp-giweb' ),
				'modifier' => 'warn',
			),
			array(
				'strong'   => number_format_i18n( (int) $ctx['no_backup'] ),
				'label'    => __( 'No backup', 'mainwp-giweb' ),
				'modifier' => 'missing',
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
				<button type="button" class="giweb-gw-filter" data-filter="ok" role="tab"><?php esc_html_e( 'Récent', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $ctx['fresh'] ); ?></em></button>
				<button type="button" class="giweb-gw-filter" data-filter="issues" role="tab"><?php esc_html_e( 'Alertes', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $ctx['issues_count'] ); ?></em></button>
				<button type="button" class="giweb-gw-filter" data-filter="progress" role="tab"><?php esc_html_e( 'En cours', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $ctx['in_progress'] ); ?></em></button>
			</div>
			<?php MainWP_GIWeb_Widget_UI::render_view_toggle( $list_mode, 'giweb_gw_view_backup' ); ?>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Lignes sites.
	 * @return void
	 */
	private static function render_backup_cards( array $rows ) {
		?>
		<div class="giweb-gw-grid">
			<?php foreach ( $rows as $row ) : ?>
				<?php self::render_backup_card( $row ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $rows      Lignes sites.
	 * @param string                            $list_mode cards|table.
	 * @return void
	 */
	private static function render_backup_table( array $rows, $list_mode = 'cards' ) {
		?>
		<div class="giweb-gw-table-wrap<?php echo esc_attr( MainWP_GIWeb_Widget_UI::list_view_class( $list_mode, 'table' ) ); ?>">
			<table class="giweb-gw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Dernier backup', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Taille Go', 'mainwp-giweb' ); ?></th>
						<th><?php esc_html_e( 'Remote', 'mainwp-giweb' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr data-status="<?php echo esc_attr( (string) ( $row['filter_status'] ?? 'issues' ) ); ?>" data-search="<?php echo esc_attr( (string) ( $row['search'] ?? '' ) ); ?>">
							<td>
								<?php if ( ! empty( $row['admin_url'] ) ) : ?>
									<a href="<?php echo esc_url( (string) $row['admin_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $row['status_label'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['relative'] ?? '—' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['size'] ?? '—' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['remote'] ?? '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @param int $site_id ID site MainWP.
	 * @return void
	 */
	public static function render_site_metabox( $site_id ) {
		$site_id = absint( $site_id );
		$is_dark = MainWP_GIWeb_UI::is_dark_theme();
		?>
		<div class="mainwp-giweb-backup-widget mainwp-giweb-backup-widget--single-site<?php echo $is_dark ? ' mainwp-giweb-backup-widget--dark' : ' mainwp-giweb-backup-widget--light'; ?>">
			<?php self::render_site_body( $site_id ); ?>
		</div>
		<?php
	}

	/**
	 * Corps HTML du widget site (AJAX refresh).
	 *
	 * @param int $site_id ID site MainWP.
	 * @return void
	 */
	public static function render_site_body( $site_id ) {
		$site_id = absint( $site_id );
		$agg     = MainWP_GIWeb_Backup_Stats::get_aggregate();
		$sites   = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$row     = $sites[ $site_id ] ?? null;
		$backup  = is_array( $row ) && isset( $row['backup'] ) ? $row['backup'] : MainWP_GIWeb_Backup_Stats::get_site_backup( $site_id );
		$updated_at = ! empty( $row['synced_at'] ) ? (int) $row['synced_at'] : (int) ( $agg['updated_at'] ?? 0 );
		$state      = MainWP_GIWeb_Backup_Stats::get_visual_state( $backup );
		$admin_url  = is_array( $row ) ? self::site_updraft_url( $row['url'] ?? '' ) : '';
		?>
		<div class="giweb-gw giweb-gw--single">
			<header class="giweb-gw-header">
				<?php
				MainWP_GIWeb_Widget_UI::render_header_row(
					'backup',
					__( 'Backups UpdraftPlus', 'mainwp-giweb' ),
					__( 'Monitor de ce site', 'mainwp-giweb' ),
					$updated_at,
					__( 'Synchronisez ce site MainWP pour alimenter ce widget', 'mainwp-giweb' ),
					array(
						'scope'    => 'backup',
						'site_id'  => $site_id,
						'detailed' => false,
					)
				);
				?>
			</header>

			<?php if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) : ?>
				<div class="giweb-gw-empty-state">
					<p><?php esc_html_e( 'UpdraftPlus inactif sur ce site.', 'mainwp-giweb' ); ?></p>
				</div>
			<?php else : ?>
				<?php
				self::render_backup_card(
					array(
						'label'         => is_array( $row ) ? (string) ( $row['label'] ?? ( '#' . $site_id ) ) : ( '#' . $site_id ),
						'admin_url'     => $admin_url,
						'state'         => $state,
						'card_status'   => self::card_status_from_state( $state ),
						'filter_status' => self::filter_status_from_row( $state, $backup ),
						'status_label'  => MainWP_GIWeb_Backup_Stats::format_status_label( $backup ),
						'relative'      => MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) ),
						'size'          => MainWP_GIWeb_Backup_Stats::format_size_gb( $backup ),
						'remote'        => MainWP_GIWeb_Backup_Stats::format_remote_label( $backup ),
						'search'        => is_array( $row ) ? strtolower( (string) ( $row['label'] ?? '' ) ) : '',
					)
				);
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string $site_url URL site.
	 * @return string
	 */
	private static function site_updraft_url( $site_url ) {
		$site_url = untrailingslashit( (string) $site_url );
		if ( '' === $site_url ) {
			return '';
		}
		return $site_url . '/wp-admin/options-general.php?page=updraftplus';
	}

	/**
	 * @param string $state État visuel backup.
	 * @return string
	 */
	private static function card_status_from_state( $state ) {
		switch ( (string) $state ) {
			case 'ok':
				return 'ok';
			case 'warn':
				return 'warn';
			case 'stale':
				return 'stale';
			default:
				return 'inactive';
		}
	}

	/**
	 * @param string               $state  État visuel.
	 * @param array<string, mixed> $backup Payload backup.
	 * @return string
	 */
	private static function filter_status_from_row( $state, $backup ) {
		if ( 'ok' === (string) $state ) {
			return 'ok';
		}
		if ( 'warn' === (string) $state && 'in_progress' === (string) ( $backup['status'] ?? '' ) ) {
			return 'progress';
		}
		return 'issues';
	}

	/**
	 * @param array<string, mixed> $row Ligne site.
	 * @return void
	 */
	private static function render_backup_card( array $row ) {
		$card_status   = (string) ( $row['card_status'] ?? 'inactive' );
		$filter_status = (string) ( $row['filter_status'] ?? 'issues' );
		$search        = (string) ( $row['search'] ?? strtolower( (string) ( $row['label'] ?? '' ) ) );
		?>
		<article class="giweb-gw-card status-<?php echo esc_attr( $card_status ); ?>" data-status="<?php echo esc_attr( $filter_status ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
			<header class="giweb-gw-card__head">
				<span class="giweb-gw-card__badge"><?php echo esc_html( (string) ( $row['status_label'] ?? '' ) ); ?></span>
				<span class="giweb-gw-card__meta"><?php echo esc_html( (string) ( $row['size'] ?? '—' ) ); ?></span>
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
					<span class="giweb-gw-card__row-label"><?php esc_html_e( 'Dernier backup', 'mainwp-giweb' ); ?></span>
					<span class="giweb-gw-card__row-value"><?php echo esc_html( (string) ( $row['relative'] ?? '—' ) ); ?></span>
				</div>
				<div class="giweb-gw-card__row">
					<span class="giweb-gw-card__row-label"><?php esc_html_e( 'Remote', 'mainwp-giweb' ); ?></span>
					<span class="giweb-gw-card__row-value"><?php echo esc_html( (string) ( $row['remote'] ?? '—' ) ); ?></span>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Fusionne tous les sites MainWP avec l’agrégat backup (inclut no backup).
	 *
	 * @param array<int, array<string, mixed>> $sites Sites agrégés.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_all_site_rows( $sites ) {
		global $mainwp_giweb_activator;

		$rows  = array();
		$by_id = is_array( $sites ) ? $sites : array();

		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			$normalized = MainWP_GIWeb_Sites::normalize_one( $site );
			$site_id  = (int) ( $normalized['id'] ?? 0 );
			$site_row = is_array( $by_id[ $site_id ] ?? null ) ? $by_id[ $site_id ] : array(
				'url' => $normalized['url'] ?? '',
			);
			$label    = MainWP_GIWeb_Widget_UI::site_label( $normalized, $site_row );
			$backup   = $site_row['backup'] ?? null;

			if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
				$rows[] = array(
					'label'         => $label,
					'admin_url'     => self::site_updraft_url( $site_row['url'] ?? $normalized['url'] ?? '' ),
					'state'         => 'no_backup',
					'card_status'   => 'stale',
					'filter_status' => 'issues',
					'status_label'  => __( 'No backup', 'mainwp-giweb' ),
					'relative'      => '—',
					'size'          => '—',
					'remote'        => '—',
					'timestamp'     => 0,
					'search'        => strtolower( $label . ' ' . MainWP_GIWeb_Widget_UI::site_url_host( $site_row['url'] ?? $normalized['url'] ?? '' ) ),
				);
				continue;
			}

			$state = MainWP_GIWeb_Backup_Stats::get_visual_state( $backup );

			$rows[] = array(
				'label'         => $label,
				'admin_url'     => self::site_updraft_url( $site_row['url'] ?? $normalized['url'] ?? '' ),
				'state'         => $state,
				'card_status'   => self::card_status_from_state( $state ),
				'filter_status' => self::filter_status_from_row( $state, $backup ),
				'status_label'  => MainWP_GIWeb_Backup_Stats::format_status_label( $backup ),
				'relative'      => MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) ),
				'size'          => MainWP_GIWeb_Backup_Stats::format_size_gb( $backup ),
				'remote'        => MainWP_GIWeb_Backup_Stats::format_remote_label( $backup ),
				'timestamp'     => (int) ( $backup['last_backup_time'] ?? 0 ),
				'search'        => strtolower( $label . ' ' . MainWP_GIWeb_Widget_UI::site_url_host( $site_row['url'] ?? $normalized['url'] ?? '' ) ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$order = array(
					'stale'     => 0,
					'no_backup' => 1,
					'warn'      => 2,
					'inactive'  => 3,
					'pending'   => 4,
					'ok'        => 5,
				);
				$oa = $order[ $a['state'] ?? '' ] ?? 6;
				$ob = $order[ $b['state'] ?? '' ] ?? 6;
				if ( $oa !== $ob ) {
					return $oa <=> $ob;
				}
				return (int) ( $a['timestamp'] ?? 0 ) <=> (int) ( $b['timestamp'] ?? 0 );
			}
		);

		return $rows;
	}
}
