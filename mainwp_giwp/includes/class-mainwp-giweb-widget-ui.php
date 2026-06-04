<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composants UI partagés des widgets MainWP GI-Toolkit.
 */
class MainWP_GIWeb_Widget_UI {

	/**
	 * @return int
	 */
	public static function count_mainwp_sites() {
		global $mainwp_giweb_activator;

		$count = 0;
		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			unset( $site );
			++$count;
		}

		return $count;
	}

	/**
	 * @param float $health Score 0–100.
	 * @param int   $decimals Décimales affichées.
	 * @return void
	 */
	public static function render_score( $health, $decimals = 0 ) {
		$health     = (float) $health;
		$health     = min( 100, max( 0, $health ) );
		$is_full    = $health >= 100;
		$health_deg = min( 359.9, $health * 3.6 );
		$class      = 'giweb-gw-score' . ( $is_full ? ' giweb-gw-score--full' : ' giweb-gw-score--low' );
		$style      = $is_full ? '' : ' style="--giweb-gw-score-deg: ' . esc_attr( (string) $health_deg ) . 'deg;"';
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<span class="giweb-gw-score__value"><?php echo esc_html( number_format_i18n( $health, $decimals ) ); ?>%</span>
		</div>
		<?php
	}

	/**
	 * @param string               $title         Nom du site.
	 * @param string               $status        ok|warn|down|missing.
	 * @param string               $status_label  Libellé badge.
	 * @param array<int, array<string, string>> $stats label, value, tone (optionnel).
	 * @return array<string, mixed>
	 */
	public static function strip_tip_meta( $title, $status, $status_label, array $stats ) {
		return array(
			'title'       => $title,
			'status'      => $status,
			'statusLabel' => $status_label,
			'stats'       => $stats,
		);
	}

	/**
	 * @param string $label Libellé stat.
	 * @param string $value Valeur.
	 * @param string $tone  ok|warn|down|missing (optionnel).
	 * @return array<string, string>
	 */
	public static function strip_stat( $label, $value, $tone = '' ) {
		$stat = array(
			'label' => $label,
			'value' => $value,
		);
		if ( '' !== $tone ) {
			$stat['tone'] = $tone;
		}
		return $stat;
	}

	/**
	 * @param array<int, array<string, string>> $segments label, status, title, tip_meta (optionnel).
	 * @return void
	 */
	public static function render_status_strip( array $segments ) {
		if ( empty( $segments ) ) {
			return;
		}
		?>
		<div class="giweb-gw-strip" role="img" aria-label="<?php esc_attr_e( 'Statut par site', 'mainwp-giweb' ); ?>">
			<?php foreach ( $segments as $segment ) : ?>
				<?php
				$status   = (string) ( $segment['status'] ?? 'missing' );
				$tip      = (string) ( $segment['tip'] ?? $segment['title'] ?? $segment['label'] ?? '' );
				$title    = (string) ( $segment['title'] ?? $tip );
				$tip_meta = is_array( $segment['tip_meta'] ?? null ) ? $segment['tip_meta'] : null;
				?>
				<span
					class="giweb-gw-strip__seg status-<?php echo esc_attr( $status ); ?>"
					<?php echo is_array( $tip_meta ) ? ' data-tip-meta="' . esc_attr( wp_json_encode( $tip_meta ) ) . '"' : ''; ?>
					<?php echo '' !== $tip ? ' data-tip="' . esc_attr( $tip ) . '"' : ''; ?>
					<?php echo '' !== $title ? ' title="' . esc_attr( $title ) . '"' : ''; ?>
				></span>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $stats strong, label, modifier (optionnel ok|warn|down|missing).
	 * @return void
	 */
	public static function render_stats( array $stats ) {
		if ( empty( $stats ) ) {
			return;
		}
		?>
		<div class="giweb-gw-stats">
			<?php foreach ( $stats as $stat ) : ?>
				<?php
				$modifier = ! empty( $stat['modifier'] ) ? ' giweb-gw-stat--' . sanitize_html_class( (string) $stat['modifier'] ) : '';
				?>
				<div class="giweb-gw-stat<?php echo esc_attr( $modifier ); ?>">
					<strong><?php echo esc_html( (string) ( $stat['strong'] ?? '0' ) ); ?></strong>
					<span><?php echo esc_html( (string) ( $stat['label'] ?? '' ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param float                              $health   Score 0–100.
	 * @param array<int, array<string, string>>  $segments Bandeau statuts.
	 * @param array<int, array<string, mixed>>   $stats    Cartes stats.
	 * @param int                                $decimals Décimales score.
	 * @return void
	 */
	public static function render_overview( $health, array $segments, array $stats, $decimals = 0 ) {
		if ( empty( $segments ) && empty( $stats ) ) {
			return;
		}
		?>
		<div class="giweb-gw-overview">
			<?php self::render_score( $health, $decimals ); ?>
			<div class="giweb-gw-overview__main">
				<?php self::render_status_strip( $segments ); ?>
				<?php self::render_stats( $stats ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $icon_modifier mail|backup|kuma.
	 * @param string $title         Titre widget.
	 * @param string $subtitle      Sous-titre.
	 * @return void
	 */
	public static function render_brand( $icon_modifier, $title, $subtitle ) {
		$icon_class = 'giweb-gw-brand__icon';
		if ( in_array( $icon_modifier, array( 'mail', 'backup', 'kuma' ), true ) ) {
			$icon_class .= ' giweb-gw-brand__icon--' . $icon_modifier;
		}
		?>
		<div class="giweb-gw-brand">
			<span class="<?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
			<div>
				<p class="giweb-gw-brand__title"><?php echo esc_html( $title ); ?></p>
				<p class="giweb-gw-brand__sub"><?php echo esc_html( $subtitle ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $refresh scope, site_id, detailed.
	 * @return void
	 */
	public static function render_refresh_button( array $refresh ) {
		$scope    = sanitize_key( (string) ( $refresh['scope'] ?? '' ) );
		$site_id  = absint( $refresh['site_id'] ?? 0 );
		$detailed = ! empty( $refresh['detailed'] ) ? '1' : '0';

		if ( ! in_array( $scope, array( 'mail', 'backup', 'kuma' ), true ) ) {
			return;
		}
		?>
		<button
			type="button"
			class="giweb-gw-refresh"
			data-refresh-scope="<?php echo esc_attr( $scope ); ?>"
			data-refresh-site-id="<?php echo esc_attr( (string) $site_id ); ?>"
			data-refresh-detailed="<?php echo esc_attr( $detailed ); ?>"
			title="<?php esc_attr_e( 'Actualiser les données depuis les sites', 'mainwp-giweb' ); ?>"
			aria-label="<?php esc_attr_e( 'Actualiser les données', 'mainwp-giweb' ); ?>"
		>
			<svg class="giweb-gw-refresh__icon" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
				<path fill="currentColor" d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08a5.99 5.99 0 0 1-5.65 4c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
			</svg>
		</button>
		<?php
	}

	/**
	 * @param int    $updated_at   Timestamp sync.
	 * @param string $empty_message Message si pas de sync.
	 * @return void
	 */
	public static function render_sync( $updated_at, $empty_message = '' ) {
		$updated_at = absint( $updated_at );
		if ( $updated_at > 0 ) {
			?>
			<time
				class="giweb-gw-sync"
				datetime="<?php echo esc_attr( gmdate( 'c', $updated_at ) ); ?>"
				data-sync-ts="<?php echo esc_attr( (string) $updated_at ); ?>"
			>
				<?php
				printf(
					/* translators: %s: human-readable time difference */
					esc_html__( 'Sync il y a %s', 'mainwp-giweb' ),
					esc_html( human_time_diff( $updated_at, (int) current_time( 'timestamp' ) ) )
				);
				?>
			</time>
			<?php
			return;
		}

		if ( '' !== $empty_message ) {
			?>
			<span class="giweb-gw-sync giweb-gw-sync--empty"><?php echo esc_html( $empty_message ); ?></span>
			<?php
		}
	}

	/**
	 * @param string $icon_modifier mail|backup|kuma.
	 * @param string $title         Titre.
	 * @param string $subtitle      Sous-titre.
	 * @param int                         $updated_at    Timestamp sync.
	 * @param string                      $empty_sync    Message sync vide.
	 * @param array<string, mixed>|null   $refresh       scope, site_id, detailed (optionnel).
	 * @return void
	 */
	public static function render_header_row( $icon_modifier, $title, $subtitle, $updated_at, $empty_sync = '', $refresh = null ) {
		?>
		<div class="giweb-gw-header__row">
			<?php self::render_brand( $icon_modifier, $title, $subtitle ); ?>
			<div class="giweb-gw-header__actions">
				<div class="giweb-gw-sync-wrap">
					<?php self::render_sync( $updated_at, $empty_sync ); ?>
					<?php
					if ( is_array( $refresh ) && ! empty( $refresh['scope'] ) ) {
						self::render_refresh_button( $refresh );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public static function render_toolbar( $search_placeholder = null ) {
		unset( $search_placeholder );
		// Toolbar rendered by each widget with filters.
	}

	/**
	 * @param string $list_mode cards|table.
	 * @return bool
	 */
	public static function is_table_mode( $list_mode ) {
		return 'table' === (string) $list_mode;
	}

	/**
	 * @param string $url URL site.
	 * @return string
	 */
	public static function site_url_host( $url ) {
		$url = untrailingslashit( trim( (string) $url ) );
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}
		return $url;
	}

	/**
	 * @param array<string, mixed> $normalized id, name, url.
	 * @param array<string, mixed> $site_row    Ligne agrégat (optionnel).
	 * @return string
	 */
	public static function site_label( array $normalized, array $site_row = array() ) {
		if ( ! empty( $site_row['label'] ) ) {
			return (string) $site_row['label'];
		}
		if ( ! empty( $normalized['name'] ) ) {
			return (string) $normalized['name'];
		}
		$url = (string) ( $site_row['url'] ?? $normalized['url'] ?? '' );
		$host = self::site_url_host( $url );
		if ( '' !== $host ) {
			return $host;
		}
		$site_id = (int) ( $normalized['id'] ?? 0 );
		return $site_id > 0 ? ( '#' . $site_id ) : '';
	}

	/**
	 * @param string $list_mode   cards|table.
	 * @param string $storage_key Clé localStorage.
	 * @return void
	 */
	public static function render_view_toggle( $list_mode, $storage_key ) {
		$is_table = self::is_table_mode( $list_mode );
		?>
		<div class="giweb-gw-view-toggle" role="group" data-storage-key="<?php echo esc_attr( $storage_key ); ?>">
			<button type="button" class="giweb-gw-view<?php echo $is_table ? '' : ' is-active'; ?>" data-view="cards"><?php esc_html_e( 'Cartes', 'mainwp-giweb' ); ?></button>
			<button type="button" class="giweb-gw-view<?php echo $is_table ? ' is-active' : ''; ?>" data-view="table"><?php esc_html_e( 'Tableau', 'mainwp-giweb' ); ?></button>
		</div>
		<?php
	}

	/**
	 * @param string $list_mode cards|table.
	 * @return string
	 */
	public static function list_view_class( $list_mode, $view ) {
		$active = ( 'table' === $view ) === self::is_table_mode( $list_mode );
		return $active ? '' : ' is-hidden';
	}

	/**
	 * @param array<string, mixed> $sites Agrégat sites sync.
	 * @return array<int, array<string, string>>
	 */
	public static function build_mail_strip_segments( $sites ) {
		global $mainwp_giweb_activator;

		$segments = array();
		$by_id    = is_array( $sites ) ? $sites : array();

		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			$row      = MainWP_GIWeb_Sites::normalize_one( $site );
			$site_id  = (int) ( $row['id'] ?? 0 );
			$site_row = is_array( $by_id[ $site_id ] ?? null ) ? $by_id[ $site_id ] : array();
			$label    = self::site_label( $row, $site_row );
			$mail     = is_array( $site_row['mail'] ?? null ) ? $site_row['mail'] : null;

			if ( ! is_array( $mail ) || empty( $mail['module_active'] ) || empty( $mail['table_ready'] ) ) {
				$inactive = __( 'Mail Catcher inactif ou en attente', 'mainwp-giweb' );
				$segments[] = array(
					'label'    => $label,
					'title'    => $label,
					'tip'      => $label . ' — ' . $inactive,
					'status'   => 'missing',
					'tip_meta' => self::strip_tip_meta(
						$label,
						'missing',
						__( 'Inactif', 'mainwp-giweb' ),
						array(
							self::strip_stat( __( 'Module', 'mainwp-giweb' ), $inactive, 'missing' ),
						)
					),
				);
				continue;
			}

			$total  = (int) ( $mail['total'] ?? 0 );
			$failed = MainWP_GIWeb_Mail_Stats::get_failed_count( $mail );
			$ok     = (int) ( $mail['success'] ?? max( 0, $total - $failed ) );
			$today  = (int) ( $mail['today'] ?? 0 );
			$status = MainWP_GIWeb_Mail_Stats::has_mail_failures( $mail ) ? 'down' : 'ok';

			if ( MainWP_GIWeb_Mail_Stats::has_mail_failures( $mail ) ) {
				$tip = sprintf(
					/* translators: 1: site, 2: failed count, 3: total count */
					__( '%1$s — %2$s échecs · %3$s total', 'mainwp-giweb' ),
					$label,
					number_format_i18n( $failed ),
					number_format_i18n( $total )
				);
				$status_label = sprintf(
					/* translators: %s: failed mail count */
					__( '%s échecs', 'mainwp-giweb' ),
					number_format_i18n( $failed )
				);
			} else {
				$tip = sprintf(
					/* translators: 1: site, 2: total, 3: ok, 4: failed, 5: today */
					__( '%1$s — %2$s total · %3$s OK · %4$s échec · %5$s aujourd’hui', 'mainwp-giweb' ),
					$label,
					number_format_i18n( $total ),
					number_format_i18n( $ok ),
					number_format_i18n( $failed ),
					number_format_i18n( $today )
				);
				$status_label = __( 'OK', 'mainwp-giweb' );
			}

			$segments[] = array(
				'label'    => $label,
				'title'    => $label,
				'tip'      => $tip,
				'status'   => $status,
				'tip_meta' => self::strip_tip_meta(
					$label,
					$status,
					$status_label,
					array(
						self::strip_stat( __( 'Total', 'mainwp-giweb' ), number_format_i18n( $total ) ),
						self::strip_stat( __( 'OK', 'mainwp-giweb' ), number_format_i18n( $ok ), 'ok' ),
						self::strip_stat( __( 'Échecs', 'mainwp-giweb' ), number_format_i18n( $failed ), $failed > 0 ? 'down' : 'ok' ),
						self::strip_stat( __( 'Aujourd’hui', 'mainwp-giweb' ), number_format_i18n( $today ) ),
					)
				),
			);
		}

		return $segments;
	}

	/**
	 * @param array<int, array<string, mixed>> $agg_sites Sites agrégés backup.
	 * @return array<int, array<string, string>>
	 */
	public static function build_backup_strip_segments( $agg_sites ) {
		global $mainwp_giweb_activator;

		$segments = array();
		$by_id    = is_array( $agg_sites ) ? $agg_sites : array();

		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			$row      = MainWP_GIWeb_Sites::normalize_one( $site );
			$site_id  = (int) ( $row['id'] ?? 0 );
			$site_row = is_array( $by_id[ $site_id ] ?? null ) ? $by_id[ $site_id ] : array();
			$label    = self::site_label( $row, $site_row );
			$backup   = is_array( $site_row['backup'] ?? null ) ? $site_row['backup'] : null;

			if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
				$no_backup = __( 'No backup (UpdraftPlus absent)', 'mainwp-giweb' );
				$segments[] = array(
					'label'    => $label,
					'title'    => $label,
					'tip'      => $label . ' — ' . $no_backup,
					'status'   => 'down',
					'tip_meta' => self::strip_tip_meta(
						$label,
						'down',
						__( 'No backup', 'mainwp-giweb' ),
						array(
							self::strip_stat( __( 'UpdraftPlus', 'mainwp-giweb' ), $no_backup, 'down' ),
						)
					),
				);
				continue;
			}

			$state        = MainWP_GIWeb_Backup_Stats::get_visual_state( $backup );
			$status_label = MainWP_GIWeb_Backup_Stats::format_status_label( $backup );
			$relative     = MainWP_GIWeb_Backup_Stats::format_relative_time( (int) ( $backup['last_backup_time'] ?? 0 ) );
			$size         = MainWP_GIWeb_Backup_Stats::format_size_gb( $backup );
			$remote       = MainWP_GIWeb_Backup_Stats::format_remote_label( $backup );
			switch ( $state ) {
				case 'ok':
					$status = 'ok';
					break;
				case 'warn':
					$status = 'warn';
					break;
				case 'stale':
					$status = 'down';
					break;
				default:
					$status = 'missing';
			}

			$tip = sprintf(
				/* translators: 1: site, 2: status, 3: last backup, 4: size, 5: remote */
				__( '%1$s — %2$s · %3$s · %4$s · Remote: %5$s', 'mainwp-giweb' ),
				$label,
				$status_label,
				$relative,
				$size,
				$remote
			);
			$stat_tone = 'ok';
			if ( 'warn' === $status ) {
				$stat_tone = 'warn';
			} elseif ( 'down' === $status ) {
				$stat_tone = 'down';
			} elseif ( 'missing' === $status ) {
				$stat_tone = 'missing';
			}

			$segments[] = array(
				'label'    => $label,
				'title'    => $label,
				'tip'      => $tip,
				'status'   => $status,
				'tip_meta' => self::strip_tip_meta(
					$label,
					$status,
					$status_label,
					array(
						self::strip_stat( __( 'Dernier backup', 'mainwp-giweb' ), $relative, $stat_tone ),
						self::strip_stat( __( 'Taille', 'mainwp-giweb' ), $size ),
						self::strip_stat( __( 'Remote', 'mainwp-giweb' ), $remote ),
					)
				),
			);
		}

		return $segments;
	}

	/**
	 * @param array<int, array<string, mixed>> $sites Lignes Kuma.
	 * @return array<int, array<string, string>>
	 */
	public static function build_kuma_strip_segments( array $sites ) {
		$segments      = array();
		$status_labels = array(
			'ok'      => __( 'En ligne', 'mainwp-giweb' ),
			'warn'    => __( 'Dégradé', 'mainwp-giweb' ),
			'down'    => __( 'Hors ligne', 'mainwp-giweb' ),
			'paused'  => __( 'Pause', 'mainwp-giweb' ),
			'missing' => __( 'Sans monitor', 'mainwp-giweb' ),
			'unknown' => __( 'Inconnu', 'mainwp-giweb' ),
		);

		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}
			$label  = (string) ( $site['label'] ?? '' );
			if ( '' === $label ) {
				$url   = (string) ( $site['url'] ?? '' );
				$label = self::site_url_host( $url );
			}
			$status = (string) ( $site['status'] ?? 'missing' );
			if ( in_array( $status, array( 'unknown', 'paused' ), true ) ) {
				$status = 'missing';
			}
			$status_text = $status_labels[ $status ] ?? $status_labels['unknown'];
			$ping        = (int) ( $site['avg_ping'] ?? 0 );
			$uptime24    = isset( $site['uptime_24h'] ) ? (float) $site['uptime_24h'] : null;
			$uptime30    = isset( $site['uptime_30d'] ) ? (float) $site['uptime_30d'] : null;
			$tip         = $label . ' — ' . $status_text;
			if ( $ping > 0 ) {
				$tip .= ' · ' . $ping . ' ms';
			}
			if ( null !== $uptime24 ) {
				$tip .= ' · ' . number_format_i18n( $uptime24, 1 ) . ' % (24 h)';
			}

			$stats = array(
				self::strip_stat( __( 'Statut', 'mainwp-giweb' ), $status_text, $status ),
			);
			if ( $ping > 0 ) {
				$stats[] = self::strip_stat( __( 'Ping', 'mainwp-giweb' ), $ping . ' ms' );
			}
			if ( null !== $uptime24 ) {
				$stats[] = self::strip_stat(
					__( 'Uptime 24 h', 'mainwp-giweb' ),
					number_format_i18n( $uptime24, 1 ) . ' %',
					$uptime24 >= 99 ? 'ok' : ( $uptime24 >= 95 ? 'warn' : 'down' )
				);
			}
			if ( null !== $uptime30 ) {
				$stats[] = self::strip_stat(
					__( 'Uptime 30 j', 'mainwp-giweb' ),
					number_format_i18n( $uptime30, 1 ) . ' %',
					$uptime30 >= 99 ? 'ok' : ( $uptime30 >= 95 ? 'warn' : 'down' )
				);
			}

			$segments[] = array(
				'label'    => $label,
				'title'    => $label,
				'tip'      => $tip,
				'status'   => $status,
				'tip_meta' => self::strip_tip_meta( $label, $status, $status_text, $stats ),
			);
		}

		return $segments;
	}
}
