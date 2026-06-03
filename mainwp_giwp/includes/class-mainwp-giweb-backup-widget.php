<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget MainWP Dashboard — statuts UpdraftPlus réseau / site.
 */
class MainWP_GIWeb_Backup_Widget {

	const WIDGET_ID = 'mainwp-giweb-backup-widget';

	/**
	 * @param array<int, array<string, mixed>>|mixed $metaboxes Metaboxes existants.
	 * @param int|null                               $dashboard_siteid Site Overview.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_metabox( $metaboxes, $dashboard_siteid = null ) {
		return MainWP_GIWeb_Metabox::append(
			$metaboxes,
			self::WIDGET_ID,
			__( 'GI-Toolkit — Backups UpdraftPlus', 'mainwp-giweb' ),
			array( __CLASS__, 'render_metabox' ),
			$dashboard_siteid
		);
	}

	/**
	 * @param array<string, string> $options Widgets existants.
	 * @return array<string, string>
	 */
	public static function widgets_screen_options( $options ) {
		return MainWP_GIWeb_Metabox::append_screen_option(
			$options,
			self::WIDGET_ID,
			__( 'GI-Toolkit — Backups UpdraftPlus', 'mainwp-giweb' )
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
	 * @return void
	 */
	public static function render_metabox() {
		$site_id = MainWP_GIWeb_Metabox::get_render_site_id();
		if ( $site_id > 0 ) {
			self::render_site_metabox( $site_id );
			return;
		}

		self::render_network_metabox();
	}

	/**
	 * @return int
	 */
	private static function count_mainwp_sites() {
		global $mainwp_giweb_activator;

		$count = 0;
		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			unset( $site );
			++$count;
		}

		return $count;
	}

	/**
	 * @return void
	 */
	private static function render_network_metabox() {
		$agg            = MainWP_GIWeb_Backup_Stats::get_aggregate();
		$network        = $agg['network'] ?? array();
		$sites          = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated_at     = ! empty( $agg['updated_at'] ) ? (int) $agg['updated_at'] : 0;
		$is_dark        = MainWP_GIWeb_UI::is_dark_theme();
		$rows           = self::build_rows( $sites );
		$sites_active   = (int) ( $network['sites_active'] ?? count( $rows ) );
		$total_mainwp   = self::count_mainwp_sites();
		$stale_count    = (int) ( $network['sites_stale'] ?? 0 ) + (int) ( $network['sites_no_backup'] ?? 0 );
		$remote_missing = (int) ( $network['sites_remote_missing'] ?? 0 );
		$in_progress    = (int) ( $network['sites_in_progress'] ?? 0 );
		$no_backup      = max( 0, $total_mainwp - $sites_active );
		$fresh          = (int) ( $network['sites_fresh'] ?? 0 );
		$health         = $sites_active > 0 ? round( ( $fresh / $sites_active ) * 100, 1 ) : ( $total_mainwp > 0 ? 0 : 100 );
		$health_deg     = min( 100, max( 0, $health ) ) * 3.6;
		$issues_count   = max( 0, $stale_count + $remote_missing );
		$strip          = array_map( array( __CLASS__, 'strip_status_from_state' ), wp_list_pluck( $rows, 'state' ) );
		?>
		<div class="mainwp-giweb-backup-widget<?php echo $is_dark ? ' mainwp-giweb-backup-widget--dark' : ' mainwp-giweb-backup-widget--light'; ?>">
			<div class="giweb-gw">
				<header class="giweb-gw-header">
					<div class="giweb-gw-header__row">
						<div class="giweb-gw-brand">
							<span class="giweb-gw-brand__icon giweb-gw-brand__icon--backup" aria-hidden="true"></span>
							<div>
								<p class="giweb-gw-brand__title"><?php esc_html_e( 'Backups UpdraftPlus', 'mainwp-giweb' ); ?></p>
								<p class="giweb-gw-brand__sub">
									<?php
									printf(
										/* translators: 1: active count, 2: total MainWP sites */
										esc_html__( '%1$d / %2$d sites avec UpdraftPlus', 'mainwp-giweb' ),
										$sites_active,
										$total_mainwp
									);
									?>
								</p>
							</div>
						</div>
						<div class="giweb-gw-header__actions">
							<?php if ( $updated_at > 0 ) : ?>
								<time
									class="giweb-gw-sync"
									datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>"
									data-sync-ts="<?php echo esc_attr( (string) $updated_at ); ?>"
								>
									<?php echo esc_html( self::format_sync_ago( $updated_at ) ); ?>
								</time>
							<?php elseif ( ! $sites_active ) : ?>
								<span class="giweb-gw-sync giweb-gw-sync--empty">
									<?php esc_html_e( 'Synchronisez vos sites MainWP pour alimenter ce widget', 'mainwp-giweb' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( ! empty( $rows ) ) : ?>
						<div class="giweb-gw-overview">
							<div class="giweb-gw-score" style="--giweb-gw-score-deg: <?php echo esc_attr( (string) $health_deg ); ?>deg;">
								<span class="giweb-gw-score__value"><?php echo esc_html( number_format_i18n( $health, 1 ) ); ?>%</span>
								<span class="giweb-gw-score__label"><?php esc_html_e( 'Santé', 'mainwp-giweb' ); ?></span>
							</div>
							<div class="giweb-gw-overview__main">
								<div class="giweb-gw-strip" aria-hidden="true">
									<?php foreach ( $strip as $seg ) : ?>
										<span class="giweb-gw-strip__seg status-<?php echo esc_attr( $seg ); ?>"></span>
									<?php endforeach; ?>
								</div>
								<div class="giweb-gw-stats">
									<div class="giweb-gw-stat giweb-gw-stat--ok">
										<strong><?php echo esc_html( number_format_i18n( $fresh ) ); ?></strong>
										<span><?php esc_html_e( 'Récent (<10 j)', 'mainwp-giweb' ); ?></span>
									</div>
									<div class="giweb-gw-stat giweb-gw-stat--down">
										<strong><?php echo esc_html( number_format_i18n( $issues_count ) ); ?></strong>
										<span><?php esc_html_e( 'Alertes', 'mainwp-giweb' ); ?></span>
									</div>
									<div class="giweb-gw-stat giweb-gw-stat--warn">
										<strong><?php echo esc_html( number_format_i18n( $in_progress ) ); ?></strong>
										<span><?php esc_html_e( 'En cours', 'mainwp-giweb' ); ?></span>
									</div>
									<div class="giweb-gw-stat giweb-gw-stat--missing">
										<strong><?php echo esc_html( number_format_i18n( $no_backup ) ); ?></strong>
										<span><?php esc_html_e( 'No backup', 'mainwp-giweb' ); ?></span>
									</div>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</header>

				<?php if ( empty( $rows ) ) : ?>
					<div class="giweb-gw-empty-state">
						<p><?php esc_html_e( 'Aucun site avec UpdraftPlus actif remonté pour le moment.', 'mainwp-giweb' ); ?></p>
					</div>
				<?php else : ?>
					<div class="giweb-gw-toolbar">
						<label class="giweb-gw-search">
							<span class="screen-reader-text"><?php esc_html_e( 'Rechercher un site', 'mainwp-giweb' ); ?></span>
							<input type="search" class="giweb-gw-search__input" placeholder="<?php esc_attr_e( 'Rechercher…', 'mainwp-giweb' ); ?>" autocomplete="off" />
						</label>
						<div class="giweb-gw-filters" role="tablist">
							<button type="button" class="giweb-gw-filter is-active" data-filter="all" role="tab"><?php esc_html_e( 'Tous', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) count( $rows ) ); ?></em></button>
							<button type="button" class="giweb-gw-filter" data-filter="ok" role="tab"><?php esc_html_e( 'Récent', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $fresh ); ?></em></button>
							<button type="button" class="giweb-gw-filter" data-filter="issues" role="tab"><?php esc_html_e( 'Alertes', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $issues_count ); ?></em></button>
							<button type="button" class="giweb-gw-filter" data-filter="progress" role="tab"><?php esc_html_e( 'En cours', 'mainwp-giweb' ); ?> <em><?php echo esc_html( (string) $in_progress ); ?></em></button>
						</div>
					</div>

					<div class="giweb-gw-grid">
						<?php foreach ( $rows as $row ) : ?>
							<?php self::render_backup_card( $row ); ?>
						<?php endforeach; ?>
					</div>
					<p class="giweb-gw-no-match" hidden><?php esc_html_e( 'Aucun site ne correspond à votre recherche.', 'mainwp-giweb' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int $site_id ID site MainWP.
	 * @return void
	 */
	public static function render_site_metabox( $site_id ) {
		$site_id = absint( $site_id );
		$agg     = MainWP_GIWeb_Backup_Stats::get_aggregate();
		$sites   = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$row     = $sites[ $site_id ] ?? null;
		$backup  = is_array( $row ) && isset( $row['backup'] ) ? $row['backup'] : MainWP_GIWeb_Backup_Stats::get_site_backup( $site_id );
		$updated_at = ! empty( $row['synced_at'] ) ? (int) $row['synced_at'] : (int) ( $agg['updated_at'] ?? 0 );
		$is_dark    = MainWP_GIWeb_UI::is_dark_theme();
		$state      = MainWP_GIWeb_Backup_Stats::get_visual_state( $backup );
		$admin_url  = is_array( $row ) ? self::site_updraft_url( $row['url'] ?? '' ) : '';
		?>
		<div class="mainwp-giweb-backup-widget mainwp-giweb-backup-widget--single-site<?php echo $is_dark ? ' mainwp-giweb-backup-widget--dark' : ' mainwp-giweb-backup-widget--light'; ?>">
			<div class="giweb-gw giweb-gw--single">
				<header class="giweb-gw-header">
					<div class="giweb-gw-header__row">
						<div class="giweb-gw-brand">
							<span class="giweb-gw-brand__icon giweb-gw-brand__icon--backup" aria-hidden="true"></span>
							<div>
								<p class="giweb-gw-brand__title"><?php esc_html_e( 'Backups UpdraftPlus', 'mainwp-giweb' ); ?></p>
								<p class="giweb-gw-brand__sub"><?php esc_html_e( 'Monitor de ce site', 'mainwp-giweb' ); ?></p>
							</div>
						</div>
						<div class="giweb-gw-header__actions">
							<?php if ( $updated_at > 0 ) : ?>
								<time
									class="giweb-gw-sync"
									datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>"
									data-sync-ts="<?php echo esc_attr( (string) $updated_at ); ?>"
								>
									<?php echo esc_html( self::format_sync_ago( $updated_at ) ); ?>
								</time>
							<?php else : ?>
								<span class="giweb-gw-sync giweb-gw-sync--empty">
									<?php esc_html_e( 'Synchronisez ce site MainWP pour alimenter ce widget', 'mainwp-giweb' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</header>

				<?php if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) : ?>
					<div class="giweb-gw-empty-state">
						<p><?php esc_html_e( 'UpdraftPlus inactif sur ce site.', 'mainwp-giweb' ); ?></p>
					</div>
				<?php else : ?>
					<?php
					self::render_backup_card(
						array(
							'label'        => is_array( $row ) ? (string) ( $row['label'] ?? ( '#' . $site_id ) ) : ( '#' . $site_id ),
							'admin_url'    => $admin_url,
							'state'        => $state,
							'card_status'  => self::card_status_from_state( $state ),
							'filter_status'=> self::filter_status_from_row( $state, $backup ),
							'status_label' => MainWP_GIWeb_Backup_Stats::format_status_label( $backup ),
							'relative'     => MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) ),
							'size'         => MainWP_GIWeb_Backup_Stats::format_size_gb( $backup ),
							'remote'       => MainWP_GIWeb_Backup_Stats::format_remote_label( $backup ),
							'search'       => is_array( $row ) ? strtolower( (string) ( $row['label'] ?? '' ) ) : '',
						)
					);
					?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $state État visuel backup.
	 * @return string
	 */
	private static function strip_status_from_state( $state ) {
		switch ( (string) $state ) {
			case 'ok':
				return 'ok';
			case 'warn':
				return 'warn';
			case 'stale':
				return 'down';
			default:
				return 'missing';
		}
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
	 * @param array<int, array<string, mixed>> $sites Sites agrégés.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_rows( $sites ) {
		$rows = array();

		foreach ( $sites as $site_id => $site_row ) {
			$backup = $site_row['backup'] ?? null;
			if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
				continue;
			}

			$state = MainWP_GIWeb_Backup_Stats::get_visual_state( $backup );
			$label = (string) ( $site_row['label'] ?? ( '#' . $site_id ) );

			$rows[] = array(
				'label'         => $label,
				'admin_url'     => self::site_updraft_url( $site_row['url'] ?? '' ),
				'state'         => $state,
				'card_status'   => self::card_status_from_state( $state ),
				'filter_status' => self::filter_status_from_row( $state, $backup ),
				'status_label'  => MainWP_GIWeb_Backup_Stats::format_status_label( $backup ),
				'relative'      => MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) ),
				'size'          => MainWP_GIWeb_Backup_Stats::format_size_gb( $backup ),
				'remote'        => MainWP_GIWeb_Backup_Stats::format_remote_label( $backup ),
				'timestamp'     => (int) ( $backup['last_backup_time'] ?? 0 ),
				'search'        => strtolower( $label ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$order = array(
					'stale'    => 0,
					'warn'     => 1,
					'inactive' => 2,
					'pending'  => 3,
					'ok'       => 4,
				);
				$oa = $order[ $a['state'] ?? '' ] ?? 5;
				$ob = $order[ $b['state'] ?? '' ] ?? 5;
				if ( $oa !== $ob ) {
					return $oa <=> $ob;
				}
				return (int) ( $a['timestamp'] ?? 0 ) <=> (int) ( $b['timestamp'] ?? 0 );
			}
		);

		return $rows;
	}
}
