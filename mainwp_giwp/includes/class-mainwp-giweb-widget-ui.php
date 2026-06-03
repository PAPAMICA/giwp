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
	public static function render_score( $health, $decimals = 1 ) {
		$health     = (float) $health;
		$health     = min( 100, max( 0, $health ) );
		$is_full    = $health >= 100;
		$health_deg = min( 359.9, $health * 3.6 );
		$class      = 'giweb-gw-score' . ( $is_full ? ' giweb-gw-score--full' : '' );
		$style      = $is_full ? '' : ' style="--giweb-gw-score-deg: ' . esc_attr( (string) $health_deg ) . 'deg;"';
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<span class="giweb-gw-score__value"><?php echo esc_html( number_format_i18n( $health, $decimals ) ); ?>%</span>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, string>> $segments label, status, title (optionnel).
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
				$status = (string) ( $segment['status'] ?? 'missing' );
				$title  = (string) ( $segment['title'] ?? $segment['label'] ?? '' );
				?>
				<span
					class="giweb-gw-strip__seg status-<?php echo esc_attr( $status ); ?>"
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
	public static function render_overview( $health, array $segments, array $stats, $decimals = 1 ) {
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
	 * @param int    $updated_at    Timestamp sync.
	 * @param string $empty_sync    Message sync vide.
	 * @return void
	 */
	public static function render_header_row( $icon_modifier, $title, $subtitle, $updated_at, $empty_sync = '' ) {
		?>
		<div class="giweb-gw-header__row">
			<?php self::render_brand( $icon_modifier, $title, $subtitle ); ?>
			<div class="giweb-gw-header__actions">
				<?php self::render_sync( $updated_at, $empty_sync ); ?>
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
	 * @param array<string, mixed> $sites Agrégat sites sync.
	 * @return array<int, array<string, string>>
	 */
	public static function build_mail_strip_segments( $sites ) {
		global $mainwp_giweb_activator;

		$segments = array();
		$by_id    = is_array( $sites ) ? $sites : array();

		foreach ( MainWP_GIWeb_Sites::fetch_all( $mainwp_giweb_activator ) as $site ) {
			$row     = MainWP_GIWeb_Sites::normalize_one( $site );
			$site_id = (int) ( $row['id'] ?? 0 );
			$label   = (string) ( $row['label'] ?? ( '#' . $site_id ) );
			$mail    = is_array( $by_id[ $site_id ] ?? null ) ? ( $by_id[ $site_id ]['mail'] ?? null ) : null;

			if ( ! is_array( $mail ) || empty( $mail['module_active'] ) || empty( $mail['table_ready'] ) ) {
				$segments[] = array(
					'label'  => $label,
					'title'  => $label,
					'status' => 'missing',
				);
				continue;
			}

			$failed = (int) ( $mail['failed'] ?? 0 );
			$segments[] = array(
				'label'  => $label,
				'title'  => $label,
				'status' => $failed > 0 ? 'down' : 'ok',
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
			$row     = MainWP_GIWeb_Sites::normalize_one( $site );
			$site_id = (int) ( $row['id'] ?? 0 );
			$label   = (string) ( $row['label'] ?? ( '#' . $site_id ) );
			$backup  = is_array( $by_id[ $site_id ] ?? null ) ? ( $by_id[ $site_id ]['backup'] ?? null ) : null;

			if ( ! is_array( $backup ) || empty( $backup['plugin_active'] ) ) {
				$segments[] = array(
					'label'  => $label,
					'title'  => $label,
					'status' => 'missing',
				);
				continue;
			}

			$state = MainWP_GIWeb_Backup_Stats::get_visual_state( $backup );
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

			$segments[] = array(
				'label'  => $label,
				'title'  => $label,
				'status' => $status,
			);
		}

		return $segments;
	}

	/**
	 * @param array<int, array<string, mixed>> $sites Lignes Kuma.
	 * @return array<int, array<string, string>>
	 */
	public static function build_kuma_strip_segments( array $sites ) {
		$segments = array();

		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}
			$label  = (string) ( $site['label'] ?? '' );
			$status = (string) ( $site['status'] ?? 'missing' );
			if ( in_array( $status, array( 'unknown', 'paused' ), true ) ) {
				$status = 'missing';
			}
			$segments[] = array(
				'label'  => $label,
				'title'  => $label,
				'status' => $status,
			);
		}

		return $segments;
	}
}
