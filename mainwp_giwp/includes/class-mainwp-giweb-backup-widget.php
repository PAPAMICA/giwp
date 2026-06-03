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
			'mainwp-giweb-backup-widget',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/backup-widget.css',
			array(),
			MAINWP_GIWEB_VERSION
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

		return sprintf(
			/* translators: %s: human-readable time difference */
			__( 'Sync il y a %s', 'mainwp-giweb' ),
			human_time_diff( $timestamp, (int) current_time( 'timestamp' ) )
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
	 * @return void
	 */
	private static function render_network_metabox() {
		$agg         = MainWP_GIWeb_Backup_Stats::get_aggregate();
		$network     = $agg['network'] ?? array();
		$sites       = is_array( $agg['sites'] ?? null ) ? $agg['sites'] : array();
		$updated_at  = ! empty( $agg['updated_at'] ) ? (int) $agg['updated_at'] : 0;
		$is_dark     = MainWP_GIWeb_UI::is_dark_theme();
		$rows        = self::build_rows( $sites );
		$stale_count = (int) ( $network['sites_stale'] ?? 0 ) + (int) ( $network['sites_no_backup'] ?? 0 );
		?>
		<div class="mainwp-giweb-backup-widget<?php echo $is_dark ? ' mainwp-giweb-backup-widget--dark' : ' mainwp-giweb-backup-widget--light'; ?><?php echo $stale_count > 0 ? ' mainwp-giweb-backup-widget--has-alerts' : ''; ?>">
			<div class="mainwp-giweb-backup-widget__header">
				<?php if ( (int) ( $network['sites_active'] ?? 0 ) > 0 ) : ?>
					<span class="mainwp-giweb-backup-widget__pill">
						<?php
						printf(
							/* translators: %d: number of sites with UpdraftPlus */
							esc_html( _n( '%d site avec UpdraftPlus', '%d sites avec UpdraftPlus', (int) $network['sites_active'], 'mainwp-giweb' ) ),
							(int) $network['sites_active']
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( $updated_at > 0 ) : ?>
					<time class="mainwp-giweb-backup-widget__sync" datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>">
						<?php echo esc_html( self::format_sync_ago( $updated_at ) ); ?>
					</time>
				<?php else : ?>
					<span class="mainwp-giweb-backup-widget__sync mainwp-giweb-backup-widget__sync--empty">
						<?php esc_html_e( 'Synchronisez vos sites MainWP pour alimenter ce widget', 'mainwp-giweb' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="mainwp-giweb-backup-widget__kpis">
				<div class="mainwp-giweb-backup-widget__kpi mainwp-giweb-backup-widget__kpi--ok">
					<span class="mainwp-giweb-backup-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['sites_fresh'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-backup-widget__kpi-label"><?php esc_html_e( 'Récent (<10 j)', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-backup-widget__kpi <?php echo $stale_count > 0 ? 'mainwp-giweb-backup-widget__kpi--alert' : ''; ?>">
					<span class="mainwp-giweb-backup-widget__kpi-value"><?php echo esc_html( number_format_i18n( $stale_count ) ); ?></span>
					<span class="mainwp-giweb-backup-widget__kpi-label"><?php esc_html_e( 'Ancien / absent', 'mainwp-giweb' ); ?></span>
				</div>
				<div class="mainwp-giweb-backup-widget__kpi">
					<span class="mainwp-giweb-backup-widget__kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $network['sites_remote_missing'] ?? 0 ) ) ); ?></span>
					<span class="mainwp-giweb-backup-widget__kpi-label"><?php esc_html_e( 'Remote manquant', 'mainwp-giweb' ); ?></span>
				</div>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<p class="description"><?php esc_html_e( 'Aucun site avec UpdraftPlus actif remonté pour le moment.', 'mainwp-giweb' ); ?></p>
			<?php else : ?>
				<div class="mainwp-giweb-backup-widget__table-wrap">
					<table class="mainwp-giweb-backup-widget__table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
								<th><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></th>
								<th><?php esc_html_e( 'Dernier backup', 'mainwp-giweb' ); ?></th>
								<th><?php esc_html_e( 'Taille', 'mainwp-giweb' ); ?></th>
								<th><?php esc_html_e( 'Remote', 'mainwp-giweb' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr class="mainwp-giweb-backup-widget__row mainwp-giweb-backup-widget__row--<?php echo esc_attr( $row['state'] ); ?>">
									<td>
										<?php if ( ! empty( $row['admin_url'] ) ) : ?>
											<a href="<?php echo esc_url( $row['admin_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['label'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $row['label'] ); ?>
										<?php endif; ?>
									</td>
									<td><span class="mainwp-giweb-backup-site__badge status-<?php echo esc_attr( $row['state'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span></td>
									<td><?php echo esc_html( $row['relative'] ); ?></td>
									<td><?php echo esc_html( $row['size'] ); ?></td>
									<td><?php echo esc_html( $row['remote'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
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
		<div class="mainwp-giweb-backup-widget mainwp-giweb-backup-widget--single-site<?php echo $is_dark ? ' mainwp-giweb-backup-widget--dark' : ' mainwp-giweb-backup-widget--light'; ?> mainwp-giweb-backup-widget__row--<?php echo esc_attr( $state ); ?>">
			<div class="mainwp-giweb-backup-widget__header">
				<?php if ( $updated_at > 0 ) : ?>
					<time class="mainwp-giweb-backup-widget__sync" datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>">
						<?php echo esc_html( self::format_sync_ago( $updated_at ) ); ?>
					</time>
				<?php else : ?>
					<span class="mainwp-giweb-backup-widget__sync mainwp-giweb-backup-widget__sync--empty">
						<?php esc_html_e( 'Synchronisez ce site MainWP pour alimenter ce widget', 'mainwp-giweb' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $admin_url ) : ?>
					<a class="mainwp-giweb-backup-widget__pill" href="<?php echo esc_url( $admin_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'UpdraftPlus', 'mainwp-giweb' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'UpdraftPlus inactif sur ce site.', 'mainwp-giweb' ); ?></p>
			<?php else : ?>
				<div class="mainwp-giweb-backup-widget__detail">
					<div class="mainwp-giweb-backup-widget__detail-row">
						<span class="mainwp-giweb-backup-widget__detail-label"><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></span>
						<span class="mainwp-giweb-backup-site__badge status-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( MainWP_GIWeb_Backup_Stats::format_status_label( $backup ) ); ?></span>
					</div>
					<div class="mainwp-giweb-backup-widget__detail-row">
						<span class="mainwp-giweb-backup-widget__detail-label"><?php esc_html_e( 'Dernier backup', 'mainwp-giweb' ); ?></span>
						<span><?php echo esc_html( MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) ) ); ?></span>
					</div>
					<div class="mainwp-giweb-backup-widget__detail-row">
						<span class="mainwp-giweb-backup-widget__detail-label"><?php esc_html_e( 'Taille', 'mainwp-giweb' ); ?></span>
						<span><?php echo esc_html( (string) ( $backup['size_human'] ?? '—' ) ); ?></span>
					</div>
					<div class="mainwp-giweb-backup-widget__detail-row">
						<span class="mainwp-giweb-backup-widget__detail-label"><?php esc_html_e( 'Stockage externe', 'mainwp-giweb' ); ?></span>
						<span><?php echo esc_html( MainWP_GIWeb_Backup_Stats::format_remote_label( $backup ) ); ?></span>
					</div>
					<?php if ( ! empty( $backup['last_backup_label'] ) ) : ?>
						<div class="mainwp-giweb-backup-widget__detail-row">
							<span class="mainwp-giweb-backup-widget__detail-label"><?php esc_html_e( 'Contenu', 'mainwp-giweb' ); ?></span>
							<span><?php echo esc_html( (string) $backup['last_backup_label'] ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
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

			$rows[] = array(
				'label'        => (string) ( $site_row['label'] ?? ( '#' . $site_id ) ),
				'admin_url'    => self::site_updraft_url( $site_row['url'] ?? '' ),
				'state'        => MainWP_GIWeb_Backup_Stats::get_visual_state( $backup ),
				'status_label' => MainWP_GIWeb_Backup_Stats::format_status_label( $backup ),
				'relative'     => MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) ),
				'size'         => (string) ( $backup['size_human'] ?? '—' ),
				'remote'       => MainWP_GIWeb_Backup_Stats::format_remote_label( $backup ),
				'timestamp'    => (int) ( $backup['last_backup_time'] ?? 0 ),
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
