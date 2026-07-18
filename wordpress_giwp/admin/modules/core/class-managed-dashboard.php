<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Managed Dashboard
 * Description: Remplace le tableau de bord WordPress par une page d’accueil Genevois Informatique.
 *
 * @since 2.29.0
 */
class Gi_Toolkit_Managed_Dashboard {

	const CLIENT_URL = 'https://client.genevois-informatique.com/';
	const AJAX_ACTION = 'gi_toolkit_managed_dashboard';
	const NONCE_ACTION = 'gi_toolkit_managed_dashboard';

	/**
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'clear_default_dashboard' ), 999 );
		add_action( 'admin_head-index.php', array( $this, 'admin_head_styles' ) );
		add_action( 'current_screen', array( $this, 'disable_help_tabs' ) );
		add_action( 'all_admin_notices', array( $this, 'render_shell' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dashboard' ) );
	}

	/**
	 * Retire widgets et panneau de bienvenue WordPress.
	 *
	 * @return void
	 */
	public function clear_default_dashboard() {
		global $wp_meta_boxes;

		$wp_meta_boxes['dashboard'] = array(
			'normal'   => array(),
			'side'     => array(),
			'column3'  => array(),
			'column4'  => array(),
		);

		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	/**
	 * Masque le chrome WP restant sur index.php.
	 *
	 * @return void
	 */
	public function admin_head_styles() {
		?>
		<style id="gi-toolkit-managed-dashboard-hide-wp">
			body.index-php #dashboard-widgets-wrap,
			body.index-php .welcome-panel,
			body.index-php #screen-options-link-wrap,
			body.index-php #contextual-help-link-wrap,
			body.index-php #screen-meta,
			body.index-php #screen-meta-links,
			body.index-php .wrap > h1:first-child,
			body.index-php .wrap > .wp-header-end {
				display: none !important;
			}
			body.index-php #wpbody-content > .wrap {
				margin: 0;
				max-width: none;
			}
		</style>
		<?php
	}

	/**
	 * Désactive les onglets d’aide WordPress sur le tableau de bord.
	 *
	 * @param WP_Screen $screen Écran courant.
	 * @return void
	 */
	public function disable_help_tabs( $screen ) {
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		$screen->remove_help_tabs();
		$screen->set_help_sidebar( '' );
	}

	/**
	 * Assets uniquement sur le tableau de bord.
	 *
	 * @param string $hook_suffix Hook admin.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'index.php' !== $hook_suffix ) {
			return;
		}

		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);

		wp_enqueue_style(
			'gi-toolkit-matomo',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/matomo.css',
			array(),
			$version
		);

		if ( class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			wp_enqueue_style(
				'gi-toolkit-uptime-kuma',
				GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/uptime-kuma.css',
				array(),
				$version
			);
			wp_enqueue_script(
				'gi-toolkit-uptime-kuma-dashboard',
				GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/uptime-kuma-dashboard.js',
				array( 'chartjs' ),
				$version,
				true
			);
		}

		wp_enqueue_style(
			'gi-toolkit-managed-dashboard',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/managed-dashboard.css',
			array( 'gi-toolkit-matomo', 'dashicons' ),
			$version
		);

		wp_enqueue_script(
			'gi-toolkit-managed-dashboard',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/managed-dashboard.js',
			array( 'jquery', 'chartjs' ),
			$version,
			true
		);

		wp_localize_script(
			'gi-toolkit-managed-dashboard',
			'giToolkitManagedDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'action'  => self::AJAX_ACTION,
				'i18n'    => array(
					'loading' => __( 'Chargement…', 'gi-toolkit' ),
					'error'   => __( 'Impossible de charger le tableau de bord.', 'gi-toolkit' ),
					'chartSent'   => __( 'Envoyés', 'gi-toolkit' ),
					'chartFailed' => __( 'Échoués', 'gi-toolkit' ),
					'chartSpam'   => __( 'Spam / RBL', 'gi-toolkit' ),
				),
			)
		);
	}

	/**
	 * Shell HTML injecté en haut du dashboard.
	 *
	 * @return void
	 */
	public function render_shell() {
		if ( ! $this->is_dashboard_screen() || ! current_user_can( 'read' ) ) {
			return;
		}

		$user = wp_get_current_user();
		$name = $user->display_name ? $user->display_name : $user->user_login;
		$logo = GI_TOOLKIT_PLUGIN_URL . 'admin/assets/img/logo-genevois-informatique-white.png';
		?>
		<div class="gi-md-wrap" id="gi-toolkit-managed-dashboard" data-state="loading">
			<header class="gi-md-hero gi-md-animate-in" style="--gi-animate-i: 0;">
				<div class="gi-md-hero__left">
					<img
						class="gi-md-hero__logo"
						src="<?php echo esc_url( $logo ); ?>"
						alt="<?php esc_attr_e( 'Genevois Informatique', 'gi-toolkit' ); ?>"
						width="220"
						height="55"
					/>
					<div class="gi-md-hero__copy">
						<p class="gi-md-hero__kicker"><?php esc_html_e( 'Espace managé', 'gi-toolkit' ); ?></p>
						<h1 class="gi-md-hero__title">
							<?php
							printf(
								/* translators: %s: user display name */
								esc_html__( 'Bonjour %s', 'gi-toolkit' ),
								esc_html( $name )
							);
							?>
						</h1>
						<p class="gi-md-hero__subtitle">
							<?php esc_html_e( 'Votre site est managé par Genevois Informatique', 'gi-toolkit' ); ?>
						</p>
					</div>
				</div>
				<div class="gi-md-hero__actions">
					<a
						class="gi-md-btn gi-md-btn--primary"
						href="<?php echo esc_url( self::CLIENT_URL ); ?>"
						target="_blank"
						rel="noopener noreferrer"
					>
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<?php esc_html_e( 'Espace client', 'gi-toolkit' ); ?>
					</a>
				</div>
			</header>

			<div class="gi-md-grid" id="gi-md-grid">
				<?php
				$cards = array(
					'uptime'  => __( 'Disponibilité', 'gi-toolkit' ),
					'visits'  => __( 'Visites', 'gi-toolkit' ),
					'mail'    => __( 'E-mails', 'gi-toolkit' ),
					'backup'  => __( 'Sauvegardes', 'gi-toolkit' ),
					'updates' => __( 'Mises à jour', 'gi-toolkit' ),
					'tech'    => __( 'Informations techniques', 'gi-toolkit' ),
				);
				$i = 1;
				foreach ( $cards as $key => $label ) :
					?>
					<section
						class="gi-md-card gi-md-animate-in"
						data-card="<?php echo esc_attr( $key ); ?>"
						style="--gi-animate-i: <?php echo (int) $i; ?>;"
					>
						<header class="gi-md-card__head">
							<h2 class="gi-md-card__title"><?php echo esc_html( $label ); ?></h2>
						</header>
						<div class="gi-md-card__body">
							<div class="gi-md-skeleton" aria-hidden="true">
								<div class="gi-md-skeleton__line gi-md-skeleton__line--lg"></div>
								<div class="gi-md-skeleton__line"></div>
								<div class="gi-md-skeleton__line gi-md-skeleton__line--sm"></div>
							</div>
						</div>
					</section>
					<?php
					++$i;
				endforeach;
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX : HTML des cartes.
	 *
	 * @return void
	 */
	public function ajax_dashboard() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ), 403 );
		}

		$cards        = array();
		$hidden_cards = array();
		$uptime_chart = null;

		if ( $this->is_module_enabled( 'Gi_Toolkit_Uptime_Kuma' ) && class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			$uptime_settings = Gi_Toolkit_Uptime_Kuma::get_settings_static();
			$uptime_data     = Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_dashboard( $uptime_settings, false );
			if ( ! empty( $uptime_data['ready'] ) ) {
				ob_start();
				Gi_Toolkit_Uptime_Kuma::render_dashboard_markup(
					$uptime_data,
					$uptime_settings,
					array(
						'show_section_heading'  => false,
						'chart_canvas_id'       => 'gi-md-uptime-ping-chart',
						'settings_url'          => Gi_Toolkit_Uptime_Kuma::get_settings_admin_url(),
						'animate_entrance'      => true,
						'hide_response_metrics' => true,
					)
				);
				$cards['uptime'] = (string) ob_get_clean();
			} else {
				$hidden_cards[] = 'uptime';
			}
		} else {
			$hidden_cards[] = 'uptime';
		}

		$visits = $this->render_visits_card();
		if ( null === $visits ) {
			$hidden_cards[] = 'visits';
		} else {
			$cards['visits'] = $visits;
		}

		$mail = $this->render_mail_card();
		if ( null === $mail ) {
			$hidden_cards[] = 'mail';
		} else {
			$cards['mail'] = $mail;
		}

		$backup = $this->render_backup_card();
		if ( null === $backup ) {
			$hidden_cards[] = 'backup';
		} else {
			$cards['backup'] = $backup;
		}

		$updates = $this->render_updates_card();
		if ( null === $updates ) {
			$hidden_cards[] = 'updates';
		} else {
			$cards['updates'] = $updates;
		}

		$cards['tech'] = $this->render_tech_card();

		wp_send_json_success(
			array(
				'cards'            => $cards,
				'hidden_cards'     => $hidden_cards,
				'uptime_chart'     => null,
				'uptime_canvas_id' => 'gi-md-uptime-ping-chart',
			)
		);
	}

	/**
	 * @return bool
	 */
	private function is_dashboard_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && 'dashboard' === $screen->id;
	}

	/**
	 * @param string $class Nom de classe module.
	 * @return bool
	 */
	private function is_module_enabled( $class ) {
		$options = get_option( GI_TOOLKIT_PLUGIN_SETTINGS, array() );
		return is_array( $options ) && isset( $options[ $class ] ) && '1' === (string) $options[ $class ];
	}

	/**
	 * @param string $icon    Dashicon.
	 * @param string $title   Titre.
	 * @param string $message Message.
	 * @param string $url     Lien optionnel.
	 * @param string $cta     Libellé CTA.
	 * @return string
	 */
	private function empty_state_html( $icon, $title, $message, $url = '', $cta = '' ) {
		ob_start();
		?>
		<div class="gi-md-empty">
			<span class="dashicons <?php echo esc_attr( $icon ); ?> gi-md-empty__icon" aria-hidden="true"></span>
			<strong class="gi-md-empty__title"><?php echo esc_html( $title ); ?></strong>
			<p class="gi-md-empty__text"><?php echo esc_html( $message ); ?></p>
			<?php if ( $url && $cta ) : ?>
				<p>
					<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string|null HTML ou null pour masquer la carte.
	 */
	private function render_visits_card() {
		if ( ! $this->is_module_enabled( 'Gi_Toolkit_Matomo' ) || ! class_exists( 'Gi_Toolkit_Matomo' ) ) {
			return null;
		}

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-site.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-dashboard-data.php';

		$settings = Gi_Toolkit_Matomo::get_settings_static();
		if ( ! Gi_Toolkit_Matomo::is_dashboard_ready( $settings ) ) {
			return null;
		}

		$data = Gi_Toolkit_Matomo_Dashboard_Data::fetch( $settings, 'last7' );
		if ( empty( $data['success'] ) ) {
			return $this->empty_state_html(
				'dashicons-warning',
				__( 'Statistiques indisponibles', 'gi-toolkit' ),
				(string) ( $data['message'] ?? __( 'Impossible de récupérer les données Matomo.', 'gi-toolkit' ) ),
				admin_url( 'admin.php?page=' . Gi_Toolkit_Matomo::STATS_PAGE_SLUG ),
				__( 'Voir les statistiques', 'gi-toolkit' )
			);
		}

		$kpis     = is_array( $data['kpis'] ?? null ) ? $data['kpis'] : array();
		$timeline = is_array( $data['charts']['timeline'] ?? null ) ? $data['charts']['timeline'] : array();
		$stats_url = admin_url( 'admin.php?page=' . Gi_Toolkit_Matomo::STATS_PAGE_SLUG );

		ob_start();
		?>
		<div class="gi-md-visits" data-timeline="<?php echo esc_attr( wp_json_encode( $timeline ) ); ?>">
			<div class="gi-md-kpi-row">
				<div class="gi-matomo-kpi gi-matomo-kpi--primary">
					<div class="gi-matomo-kpi__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
					<div class="gi-matomo-kpi__body">
						<div class="gi-matomo-kpi__value"><?php echo esc_html( (string) ( $kpis['nb_visits'] ?? '0' ) ); ?></div>
						<div class="gi-matomo-kpi__label"><?php esc_html_e( 'Visites (7 j)', 'gi-toolkit' ); ?></div>
						<?php $this->render_trend( $kpis['trend_visits'] ?? null ); ?>
					</div>
				</div>
				<div class="gi-matomo-kpi">
					<div class="gi-matomo-kpi__icon"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></div>
					<div class="gi-matomo-kpi__body">
						<div class="gi-matomo-kpi__value"><?php echo esc_html( (string) ( $kpis['nb_actions'] ?? '0' ) ); ?></div>
						<div class="gi-matomo-kpi__label"><?php esc_html_e( 'Actions', 'gi-toolkit' ); ?></div>
						<?php $this->render_trend( $kpis['trend_actions'] ?? null ); ?>
					</div>
				</div>
				<div class="gi-matomo-kpi">
					<div class="gi-matomo-kpi__icon"><span class="dashicons dashicons-migrate" aria-hidden="true"></span></div>
					<div class="gi-matomo-kpi__body">
						<div class="gi-matomo-kpi__value"><?php echo esc_html( (string) ( $kpis['bounce_rate'] ?? '0%' ) ); ?></div>
						<div class="gi-matomo-kpi__label"><?php esc_html_e( 'Taux de rebond', 'gi-toolkit' ); ?></div>
						<?php $this->render_trend( $kpis['trend_bounce'] ?? null ); ?>
					</div>
				</div>
			</div>
			<div class="gi-md-chart-wrap">
				<canvas id="gi-md-visits-chart" height="120"></canvas>
			</div>
			<p class="gi-md-card__footer">
				<a href="<?php echo esc_url( $stats_url ); ?>"><?php esc_html_e( 'Voir toutes les statistiques →', 'gi-toolkit' ); ?></a>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed>|null $trend Trend Matomo.
	 * @return void
	 */
	private function render_trend( $trend ) {
		if ( ! is_array( $trend ) || empty( $trend['value'] ) ) {
			return;
		}
		$class = (string) ( $trend['class'] ?? 'gi-matomo-trend--flat' );
		printf(
			'<span class="gi-matomo-trend %1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( (string) $trend['value'] )
		);
	}

	/**
	 * @return string|null HTML ou null pour masquer la carte.
	 */
	private function render_mail_card() {
		if ( ! $this->is_module_enabled( 'Gi_Toolkit_Mail_Catcher' ) || ! class_exists( 'Gi_Toolkit_Mail_Catcher' ) ) {
			return null;
		}

		$mc = Gi_Toolkit_Mail_Catcher::instance();
		if ( ! $mc ) {
			return null;
		}

		$stats = $mc->get_mail_statistics();
		$url   = Gi_Toolkit_Mail_Catcher::get_module_admin_url();

		ob_start();
		?>
		<div
			class="gi-md-mail"
			data-chart-sent="<?php echo esc_attr( wp_json_encode( $stats['chart_sent'] ?? array() ) ); ?>"
			data-chart-failed="<?php echo esc_attr( wp_json_encode( $stats['chart_failed'] ?? array() ) ); ?>"
			data-chart-spam="<?php echo esc_attr( wp_json_encode( $stats['chart_spam'] ?? array() ) ); ?>"
			data-chart-labels="<?php echo esc_attr( wp_json_encode( $stats['chart_labels'] ?? array() ) ); ?>"
		>
			<div class="gi-md-kpi-row gi-md-kpi-row--compact">
				<div class="gi-md-stat">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $stats['success'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Réussis', 'gi-toolkit' ); ?></span>
				</div>
				<div class="gi-md-stat gi-md-stat--fail">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $stats['failed'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Échecs', 'gi-toolkit' ); ?></span>
				</div>
				<div class="gi-md-stat gi-md-stat--spam">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $stats['spam'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Spam / RBL', 'gi-toolkit' ); ?></span>
				</div>
				<div class="gi-md-stat">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $stats['today'] ?? 0 ) ) ); ?></strong>
					<span><?php esc_html_e( 'Aujourd’hui', 'gi-toolkit' ); ?></span>
				</div>
			</div>
			<div class="gi-md-chart-wrap">
				<canvas id="gi-md-mail-chart" height="110"></canvas>
			</div>
			<p class="gi-md-card__footer">
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Ouvrir Mail Catcher →', 'gi-toolkit' ); ?></a>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string|null HTML ou null pour masquer la carte.
	 */
	private function render_backup_card() {
		if ( ! class_exists( 'Gi_Toolkit_UpdraftPlus_Status' ) ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-updraftplus-status.php';
		}

		$payload = Gi_Toolkit_UpdraftPlus_Status::get_mainwp_status_payload();

		if ( empty( $payload['plugin_active'] ) ) {
			return null;
		}

		$status = (string) ( $payload['status'] ?? 'none' );
		$labels = array(
			'success'     => __( 'Sauvegarde OK', 'gi-toolkit' ),
			'ok'          => __( 'Sauvegarde OK', 'gi-toolkit' ),
			'stale'       => __( 'À rafraîchir', 'gi-toolkit' ),
			'partial'     => __( 'Partielle', 'gi-toolkit' ),
			'in_progress' => __( 'En cours…', 'gi-toolkit' ),
			'none'        => __( 'Aucune sauvegarde', 'gi-toolkit' ),
			'warning'     => __( 'Attention', 'gi-toolkit' ),
			'error'       => __( 'Erreur', 'gi-toolkit' ),
		);
		$status_label = $labels[ $status ] ?? $status;
		$status_class = 'success' === $status ? 'ok' : $status;
		if ( ! in_array( $status_class, array( 'ok', 'stale', 'in_progress', 'none', 'warning', 'error', 'partial' ), true ) ) {
			$status_class = 'none';
		}

		$when_abs = '';
		$when_rel = '-';
		if ( ! empty( $payload['last_backup_time'] ) ) {
			$ts       = (int) $payload['last_backup_time'];
			$when_abs = wp_date( 'd/m/Y H:i', $ts );
			$when_rel = sprintf(
				/* translators: %s: relative time */
				__( 'il y a %s', 'gi-toolkit' ),
				human_time_diff( $ts, time() )
			);
		}

		$remote_ok  = ! empty( $payload['remote_sent'] );
		$remote_cfg = ! empty( $payload['remote_configured'] );
		$age_days   = isset( $payload['last_backup_age_days'] ) ? (int) $payload['last_backup_age_days'] : null;
		$size       = (string) ( $payload['size_human'] ?? '' );
		$parts_raw  = (string) ( $payload['last_backup_label'] ?? '' );
		$parts      = array_filter( array_map( 'trim', preg_split( '/\s*[+|,]\s*|\s+et\s+/u', str_replace( '—', ',', $parts_raw ) ) ) );

		$updraft_url = admin_url( 'options-general.php?page=updraftplus' );

		ob_start();
		?>
		<div class="gi-md-backup status-<?php echo esc_attr( $status_class ); ?>">
			<div class="gi-md-backup__status">
				<span class="gi-md-backup__icon" aria-hidden="true">
					<span class="dashicons dashicons-backup"></span>
				</span>
				<div class="gi-md-backup__status-text">
					<span class="gi-md-backup__badge"><?php echo esc_html( $status_label ); ?></span>
					<strong class="gi-md-backup__when"><?php echo esc_html( $when_rel ); ?></strong>
					<?php if ( $when_abs ) : ?>
						<span class="gi-md-backup__date"><?php echo esc_html( $when_abs ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<ul class="gi-md-backup__stats">
				<li>
					<span><?php esc_html_e( 'Taille', 'gi-toolkit' ); ?></span>
					<strong><?php echo esc_html( '' !== $size ? $size : '-' ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Âge', 'gi-toolkit' ); ?></span>
					<strong>
						<?php
						echo null !== $age_days
							? esc_html(
								sprintf(
									/* translators: %d: days */
									_n( '%d jour', '%d jours', $age_days, 'gi-toolkit' ),
									$age_days
								)
							)
							: '-';
						?>
					</strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Distant', 'gi-toolkit' ); ?></span>
					<strong class="<?php echo $remote_ok ? 'is-ok' : ( $remote_cfg ? 'is-warn' : 'is-off' ); ?>">
						<?php
						if ( $remote_ok ) {
							esc_html_e( 'Envoyé', 'gi-toolkit' );
						} elseif ( $remote_cfg ) {
							esc_html_e( 'Configuré', 'gi-toolkit' );
						} else {
							esc_html_e( 'Local', 'gi-toolkit' );
						}
						?>
					</strong>
				</li>
			</ul>

			<?php if ( ! empty( $parts ) ) : ?>
				<ul class="gi-md-backup__chips">
					<?php foreach ( $parts as $part ) : ?>
						<li><?php echo esc_html( (string) $part ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="gi-md-card__footer">
				<a href="<?php echo esc_url( $updraft_url ); ?>"><?php esc_html_e( 'Ouvrir UpdraftPlus →', 'gi-toolkit' ); ?></a>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string|null HTML ou null pour masquer la carte.
	 */
	private function render_updates_card() {
		if ( ! $this->is_module_enabled( 'Gi_Toolkit_Update_Logs' ) ) {
			return null;
		}

		if ( ! class_exists( 'Gi_Toolkit_Update_Logs', false ) ) {
			$update_logs = GI_TOOLKIT_PLUGIN_PATH . 'admin/modules/pro/class-update-logs.php';
			if ( is_readable( $update_logs ) ) {
				require_once $update_logs;
			}
		}

		$logs = get_option( 'gi_toolkit_update_logs', array() );
		if ( ! is_array( $logs ) || empty( $logs ) ) {
			return null;
		}

		$logs_url = admin_url( 'admin.php?page=gi-toolkit-settings-update-logs' );
		$rows     = array_slice( $logs, 0, 6 );

		$type_labels = array(
			'plugin'      => __( 'Extension', 'gi-toolkit' ),
			'theme'       => __( 'Thème', 'gi-toolkit' ),
			'core'        => __( 'WordPress', 'gi-toolkit' ),
			'translation' => __( 'Traduction', 'gi-toolkit' ),
		);
		$action_labels = array(
			'update'  => __( 'Mise à jour', 'gi-toolkit' ),
			'install' => __( 'Installation', 'gi-toolkit' ),
		);

		ob_start();
		?>
		<div class="gi-md-updates">
			<ol class="gi-md-timeline">
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php
					$type        = (string) ( $row['type'] ?? 'core' );
					$action      = (string) ( $row['action'] ?? '' );
					$type_label  = $type_labels[ $type ] ?? ucfirst( $type ? $type : 'core' );
					$action_label = $action_labels[ $action ] ?? ( $action ? ucfirst( $action ) : '' );
					$labels      = class_exists( 'Gi_Toolkit_Update_Logs' )
						? Gi_Toolkit_Update_Logs::resolve_entry_labels( $row )
						: ( isset( $row['items'] ) && is_array( $row['items'] ) ? $row['items'] : array() );
					$icon        = 'plugin' === $type
						? 'dashicons-admin-plugins'
						: ( 'theme' === $type
							? 'dashicons-admin-appearance'
							: ( 'translation' === $type ? 'dashicons-translation' : 'dashicons-wordpress' ) );
					$time_raw    = (string) ( $row['time'] ?? '' );
					$time_display = $time_raw;
					$ts           = $time_raw ? strtotime( $time_raw ) : false;
					if ( $ts ) {
						$time_display = wp_date( 'd/m/Y H:i', $ts );
					}
					?>
					<li class="gi-md-timeline__item" style="--gi-animate-i: <?php echo (int) $index; ?>;">
						<span class="gi-md-timeline__dot">
							<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
						</span>
						<div class="gi-md-timeline__body">
							<div class="gi-md-timeline__head">
								<span class="gi-md-timeline__badge"><?php echo esc_html( $type_label ); ?></span>
								<?php if ( $action_label ) : ?>
									<span class="gi-md-timeline__action"><?php echo esc_html( $action_label ); ?></span>
								<?php endif; ?>
								<span class="gi-md-timeline__time"><?php echo esc_html( $time_display ); ?></span>
							</div>
							<?php if ( ! empty( $labels ) ) : ?>
								<ul class="gi-md-timeline__names">
									<?php foreach ( $labels as $label ) : ?>
										<li><?php echo esc_html( (string) $label ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
			<p class="gi-md-card__footer">
				<a href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'Voir tout l’historique →', 'gi-toolkit' ); ?></a>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Carte SSL / versions / hébergeur.
	 *
	 * @return string
	 */
	private function render_tech_card() {
		$ssl      = $this->get_ssl_status();
		$versions = $this->get_tech_versions();
		$hosting  = $this->get_hosting_info();

		ob_start();
		?>
		<div class="gi-md-tech">
			<div class="gi-md-tech__security">
				<div class="gi-md-tech__pill <?php echo ! empty( $ssl['https'] ) ? 'is-ok' : 'is-bad'; ?>">
					<span class="dashicons <?php echo ! empty( $ssl['https'] ) ? 'dashicons-lock' : 'dashicons-unlock'; ?>" aria-hidden="true"></span>
					<span><?php echo ! empty( $ssl['https'] ) ? esc_html__( 'HTTPS actif', 'gi-toolkit' ) : esc_html__( 'HTTPS inactif', 'gi-toolkit' ); ?></span>
				</div>
				<div class="gi-md-tech__pill <?php echo ! empty( $ssl['valid'] ) ? 'is-ok' : ( ! empty( $ssl['https'] ) ? 'is-warn' : 'is-bad' ); ?>">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<span>
						<?php
						if ( ! empty( $ssl['valid'] ) ) {
							esc_html_e( 'SSL valide', 'gi-toolkit' );
						} elseif ( ! empty( $ssl['https'] ) ) {
							esc_html_e( 'SSL à vérifier', 'gi-toolkit' );
						} else {
							esc_html_e( 'Pas de SSL', 'gi-toolkit' );
						}
						?>
					</span>
				</div>
				<?php if ( ! empty( $ssl['expires'] ) ) : ?>
					<div class="gi-md-tech__pill is-neutral">
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<span>
							<?php
							printf(
								/* translators: %s: certificate expiry date */
								esc_html__( 'Expire le %s', 'gi-toolkit' ),
								esc_html( (string) $ssl['expires'] )
							);
							?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $ssl['issuer'] ) ) : ?>
					<div class="gi-md-tech__pill is-neutral">
						<span class="dashicons dashicons-id" aria-hidden="true"></span>
						<span><?php echo esc_html( (string) $ssl['issuer'] ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $hosting['public_ip'] ) || ! empty( $hosting['asn'] ) ) : ?>
				<div class="gi-md-tech__host">
					<?php if ( ! empty( $hosting['logo_url'] ) ) : ?>
						<img
							class="gi-md-tech__host-logo"
							src="<?php echo esc_url( (string) $hosting['logo_url'] ); ?>"
							alt=""
							width="28"
							height="28"
							loading="lazy"
						/>
					<?php else : ?>
						<span class="gi-md-tech__host-fallback dashicons dashicons-cloud" aria-hidden="true"></span>
					<?php endif; ?>
					<div class="gi-md-tech__host-body">
						<strong class="gi-md-tech__host-name">
							<?php echo esc_html( (string) ( $hosting['asn'] ?: ( $hosting['isp'] ?: __( 'Hébergeur inconnu', 'gi-toolkit' ) ) ) ); ?>
						</strong>
						<span class="gi-md-tech__host-meta">
							<?php
							$bits = array();
							if ( ! empty( $hosting['public_ip'] ) ) {
								$bits[] = (string) $hosting['public_ip'];
							}
							if ( ! empty( $hosting['ptr_subdomain'] ) ) {
								$bits[] = (string) $hosting['ptr_subdomain'];
							}
							echo esc_html( implode( ' · ', $bits ) );
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<ul class="gi-md-tech__versions">
				<?php foreach ( $versions as $row ) : ?>
					<li>
						<span><?php echo esc_html( (string) $row['label'] ); ?></span>
						<strong><?php echo esc_html( (string) $row['value'] ); ?></strong>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array{https:bool, valid:bool, expires:string, issuer:string, error:string}
	 */
	private function get_ssl_status() {
		$host   = (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: '' );
		$scheme = (string) ( wp_parse_url( home_url(), PHP_URL_SCHEME ) ?: '' );
		$https  = ( 'https' === strtolower( $scheme ) ) || is_ssl();

		$result = array(
			'https'  => $https,
			'valid'  => false,
			'expires'=> '',
			'issuer' => '',
			'error'  => '',
		);

		if ( '' === $host || ! $https ) {
			return $result;
		}

		if ( ! function_exists( 'stream_socket_client' ) || ! function_exists( 'openssl_x509_parse' ) ) {
			$result['error'] = 'openssl';
			return $result;
		}

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => true,
					'verify_peer_name'  => true,
					'peer_name'         => $host,
				),
			)
		);

		$errno  = 0;
		$errstr = '';
		$client = @stream_socket_client( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'ssl://' . $host . ':443',
			$errno,
			$errstr,
			6,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $client ) {
			$result['error'] = $errstr ? (string) $errstr : 'connect';
			return $result;
		}

		$params = stream_context_get_params( $client );
		fclose( $client );

		$cert = $params['options']['ssl']['peer_certificate'] ?? null;
		if ( ! $cert ) {
			return $result;
		}

		$parsed = openssl_x509_parse( $cert );
		if ( ! is_array( $parsed ) ) {
			return $result;
		}

		$result['valid'] = true;
		if ( ! empty( $parsed['validTo_time_t'] ) ) {
			$result['expires'] = wp_date( 'd/m/Y', (int) $parsed['validTo_time_t'] );
			if ( (int) $parsed['validTo_time_t'] < time() ) {
				$result['valid'] = false;
			}
		}
		if ( ! empty( $parsed['issuer']['O'] ) ) {
			$result['issuer'] = sanitize_text_field( (string) $parsed['issuer']['O'] );
		} elseif ( ! empty( $parsed['issuer']['CN'] ) ) {
			$result['issuer'] = sanitize_text_field( (string) $parsed['issuer']['CN'] );
		}

		return $result;
	}

	/**
	 * @return array<int, array{label:string, value:string}>
	 */
	private function get_tech_versions() {
		global $wpdb, $wp_version;

		$db_version = '';
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			if ( method_exists( $wpdb, 'db_server_info' ) ) {
				$db_version = (string) $wpdb->db_server_info();
			} elseif ( method_exists( $wpdb, 'db_version' ) ) {
				$db_version = (string) $wpdb->db_version();
			}
		}

		$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

		$rows = array(
			array(
				'label' => 'WordPress',
				'value' => (string) ( $wp_version ?? get_bloginfo( 'version' ) ),
			),
			array(
				'label' => 'PHP',
				'value' => PHP_VERSION,
			),
			array(
				'label' => 'GI-Toolkit',
				'value' => defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '-',
			),
		);

		if ( '' !== $db_version ) {
			$rows[] = array(
				'label' => __( 'Base de données', 'gi-toolkit' ),
				'value' => $db_version,
			);
		}

		if ( '' !== $server ) {
			$rows[] = array(
				'label' => __( 'Serveur web', 'gi-toolkit' ),
				'value' => $server,
			);
		}

		return $rows;
	}

	/**
	 * @return array<string, string>
	 */
	private function get_hosting_info() {
		$resolver = GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/migration-helper/class-ip-resolver.php';
		if ( ! class_exists( 'Gi_Toolkit_Migration_Helper_IP_Resolver', false ) && is_readable( $resolver ) ) {
			require_once $resolver;
		}

		if ( ! class_exists( 'Gi_Toolkit_Migration_Helper_IP_Resolver' ) ) {
			return array();
		}

		$payload = Gi_Toolkit_Migration_Helper_IP_Resolver::get_toolbar_payload();
		$info    = is_array( $payload['ip_info'] ?? null ) ? $payload['ip_info'] : array();

		return array(
			'public_ip'     => (string) ( $payload['public_ip'] ?? '' ),
			'server_ip'     => (string) ( $payload['server_ip'] ?? '' ),
			'reverse_dns'   => (string) ( $info['reverse_dns'] ?? '' ),
			'ptr_subdomain' => (string) ( $info['ptr_subdomain'] ?? '' ),
			'asn'           => str_replace( ' — ', ' · ', (string) ( $info['asn'] ?? '' ) ),
			'isp'           => (string) ( $info['isp'] ?? ( $info['org'] ?? '' ) ),
			'isp_domain'    => (string) ( $info['isp_domain'] ?? '' ),
			'logo_url'      => (string) ( $info['logo_url'] ?? ( $payload['header_logo'] ?? '' ) ),
			'country'       => (string) ( $info['country'] ?? '' ),
		);
	}
}
