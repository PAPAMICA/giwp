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
		?>
		<div class="gi-md-wrap" id="gi-toolkit-managed-dashboard" data-state="loading">
			<header class="gi-md-hero gi-md-animate-in" style="--gi-animate-i: 0;">
				<div class="gi-md-hero__brand">
					<span class="gi-md-hero__eyebrow"><?php esc_html_e( 'Genevois Informatique', 'gi-toolkit' ); ?></span>
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

		$uptime_html  = '';
		$uptime_chart = null;
		if ( $this->is_module_enabled( 'Gi_Toolkit_Uptime_Kuma' ) && class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
			$uptime_settings = Gi_Toolkit_Uptime_Kuma::get_settings_static();
			$uptime_data     = Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_dashboard( $uptime_settings, false );
			if ( ! empty( $uptime_data['ready'] ) && ! empty( $uptime_data['chart'] ) ) {
				$uptime_chart = $uptime_data['chart'];
			}
			ob_start();
			Gi_Toolkit_Uptime_Kuma::render_dashboard_markup(
				$uptime_data,
				$uptime_settings,
				array(
					'show_section_heading' => false,
					'chart_canvas_id'      => 'gi-md-uptime-ping-chart',
					'settings_url'         => Gi_Toolkit_Uptime_Kuma::get_settings_admin_url(),
					'animate_entrance'     => true,
				)
			);
			$uptime_html = (string) ob_get_clean();
		} else {
			$uptime_html = $this->render_uptime_card();
		}

		wp_send_json_success(
			array(
				'cards' => array(
					'uptime'  => $uptime_html,
					'visits'  => $this->render_visits_card(),
					'mail'    => $this->render_mail_card(),
					'backup'  => $this->render_backup_card(),
					'updates' => $this->render_updates_card(),
				),
				'uptime_chart'     => $uptime_chart,
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
	 * @return string
	 */
	private function render_uptime_card() {
		if ( ! $this->is_module_enabled( 'Gi_Toolkit_Uptime_Kuma' ) || ! class_exists( 'Gi_Toolkit_Uptime_Kuma' ) ) {
			return $this->empty_state_html(
				'dashicons-chart-line',
				__( 'Uptime Kuma inactif', 'gi-toolkit' ),
				__( 'Activez le module Uptime Kuma pour afficher la disponibilité du site.', 'gi-toolkit' ),
				admin_url( 'admin.php?page=gi-toolkit-settings' ),
				__( 'Ouvrir GI-Toolkit', 'gi-toolkit' )
			);
		}

		Gi_Toolkit_Uptime_Kuma::load_deploy_dependencies();
		$settings  = Gi_Toolkit_Uptime_Kuma::get_settings_static();
		$dashboard = Gi_Toolkit_Uptime_Kuma_Status_Data::fetch_dashboard( $settings, false );

		ob_start();
		Gi_Toolkit_Uptime_Kuma::render_dashboard_markup(
			$dashboard,
			$settings,
			array(
				'show_section_heading' => false,
				'chart_canvas_id'      => 'gi-md-uptime-ping-chart',
				'settings_url'         => Gi_Toolkit_Uptime_Kuma::get_settings_admin_url(),
				'animate_entrance'     => true,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * @return string
	 */
	private function render_visits_card() {
		if ( ! $this->is_module_enabled( 'Gi_Toolkit_Matomo' ) || ! class_exists( 'Gi_Toolkit_Matomo' ) ) {
			return $this->empty_state_html(
				'dashicons-chart-area',
				__( 'Matomo inactif', 'gi-toolkit' ),
				__( 'Activez Connect Matomo pour afficher les statistiques de visites.', 'gi-toolkit' ),
				admin_url( 'admin.php?page=gi-toolkit-settings' ),
				__( 'Ouvrir GI-Toolkit', 'gi-toolkit' )
			);
		}

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-api.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-site.php';
		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/matomo/class-dashboard-data.php';

		$settings = Gi_Toolkit_Matomo::get_settings_static();
		if ( ! Gi_Toolkit_Matomo::is_dashboard_ready( $settings ) ) {
			return $this->empty_state_html(
				'dashicons-chart-area',
				__( 'Matomo non configuré', 'gi-toolkit' ),
				__( 'Connectez votre instance Matomo pour voir les visites des 7 derniers jours.', 'gi-toolkit' ),
				Gi_Toolkit_Matomo::get_settings_admin_url(),
				__( 'Configurer Matomo', 'gi-toolkit' )
			);
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
	 * @return string
	 */
	private function render_mail_card() {
		if ( ! $this->is_module_enabled( 'Gi_Toolkit_Mail_Catcher' ) || ! class_exists( 'Gi_Toolkit_Mail_Catcher' ) ) {
			return $this->empty_state_html(
				'dashicons-email-alt',
				__( 'Mail Catcher inactif', 'gi-toolkit' ),
				__( 'Activez Mail Catcher pour suivre les envois et les erreurs SMTP.', 'gi-toolkit' ),
				admin_url( 'admin.php?page=gi-toolkit-settings' ),
				__( 'Ouvrir GI-Toolkit', 'gi-toolkit' )
			);
		}

		$mc = Gi_Toolkit_Mail_Catcher::instance();
		if ( ! $mc ) {
			return $this->empty_state_html(
				'dashicons-email-alt',
				__( 'Mail Catcher', 'gi-toolkit' ),
				__( 'Le module est activé mais pas encore initialisé.', 'gi-toolkit' )
			);
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
	 * @return string
	 */
	private function render_backup_card() {
		if ( ! class_exists( 'Gi_Toolkit_UpdraftPlus_Status' ) ) {
			require_once GI_TOOLKIT_PLUGIN_PATH . 'includes/class-gi-toolkit-updraftplus-status.php';
		}

		$payload = Gi_Toolkit_UpdraftPlus_Status::get_mainwp_status_payload();

		if ( empty( $payload['plugin_active'] ) ) {
			return $this->empty_state_html(
				'dashicons-database',
				__( 'UpdraftPlus absent', 'gi-toolkit' ),
				__( 'Installez et activez UpdraftPlus pour suivre les sauvegardes.', 'gi-toolkit' )
			);
		}

		$status = (string) ( $payload['status'] ?? 'none' );
		$labels = array(
			'success'     => __( 'À jour', 'gi-toolkit' ),
			'ok'          => __( 'À jour', 'gi-toolkit' ),
			'stale'       => __( 'Ancienne', 'gi-toolkit' ),
			'partial'     => __( 'Partielle', 'gi-toolkit' ),
			'in_progress' => __( 'En cours', 'gi-toolkit' ),
			'none'        => __( 'Aucune', 'gi-toolkit' ),
			'warning'     => __( 'Attention', 'gi-toolkit' ),
			'error'       => __( 'Erreur', 'gi-toolkit' ),
		);
		$status_label = $labels[ $status ] ?? $status;
		$status_class = 'success' === $status ? 'ok' : $status;
		if ( ! in_array( $status_class, array( 'ok', 'stale', 'in_progress', 'none', 'warning', 'error', 'partial' ), true ) ) {
			$status_class = 'none';
		}

		$when = '';
		if ( ! empty( $payload['last_backup_time'] ) ) {
			$when = sprintf(
				/* translators: %s: relative time */
				__( 'Il y a %s', 'gi-toolkit' ),
				human_time_diff( (int) $payload['last_backup_time'], time() )
			);
		}

		ob_start();
		?>
		<div class="gi-md-backup">
			<div class="gi-md-backup__badge status-<?php echo esc_attr( $status_class ); ?>">
				<?php echo esc_html( $status_label ); ?>
			</div>
			<ul class="gi-md-backup__list">
				<li>
					<span><?php esc_html_e( 'Dernière sauvegarde', 'gi-toolkit' ); ?></span>
					<strong><?php echo esc_html( $when ? $when : '—' ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Taille', 'gi-toolkit' ); ?></span>
					<strong><?php echo esc_html( (string) ( $payload['size_human'] ?? '—' ) ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Stockage distant', 'gi-toolkit' ); ?></span>
					<strong>
						<?php
						if ( ! empty( $payload['remote_sent'] ) ) {
							esc_html_e( 'Envoyé', 'gi-toolkit' );
						} elseif ( ! empty( $payload['remote_configured'] ) ) {
							esc_html_e( 'Configuré', 'gi-toolkit' );
						} else {
							esc_html_e( 'Non configuré', 'gi-toolkit' );
						}
						?>
					</strong>
				</li>
			</ul>
			<?php if ( ! empty( $payload['last_backup_label'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $payload['last_backup_label'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string
	 */
	private function render_updates_card() {
		$logs_enabled = $this->is_module_enabled( 'Gi_Toolkit_Update_Logs' );
		$logs_url     = admin_url( 'admin.php?page=gi-toolkit-settings-update-logs' );

		if ( ! $logs_enabled ) {
			return $this->empty_state_html(
				'dashicons-update',
				__( 'Journal des mises à jour inactif', 'gi-toolkit' ),
				__( 'Activez le module Pro « Updates Logs » pour conserver l’historique des mises à jour.', 'gi-toolkit' ),
				admin_url( 'admin.php?page=gi-toolkit-settings' ),
				__( 'Ouvrir GI-Toolkit', 'gi-toolkit' )
			);
		}

		$logs = get_option( 'gi_toolkit_update_logs', array() );
		if ( ! is_array( $logs ) || empty( $logs ) ) {
			return $this->empty_state_html(
				'dashicons-update',
				__( 'Aucun historique', 'gi-toolkit' ),
				__( 'Aucune mise à jour enregistrée pour le moment.', 'gi-toolkit' ),
				$logs_url,
				__( 'Voir le journal', 'gi-toolkit' )
			);
		}

		$rows = array_slice( $logs, 0, 8 );
		ob_start();
		?>
		<div class="gi-md-updates">
			<ul class="gi-md-updates__list">
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$items = isset( $row['items'] ) && is_array( $row['items'] ) ? implode( ', ', $row['items'] ) : '';
					$type  = (string) ( $row['type'] ?? '' );
					$action = (string) ( $row['action'] ?? '' );
					?>
					<li class="gi-md-updates__item">
						<span class="gi-md-updates__time"><?php echo esc_html( (string) ( $row['time'] ?? '' ) ); ?></span>
						<span class="gi-md-updates__meta">
							<span class="gi-md-updates__badge"><?php echo esc_html( $type ); ?></span>
							<span class="gi-md-updates__action"><?php echo esc_html( $action ); ?></span>
						</span>
						<?php if ( $items ) : ?>
							<code class="gi-md-updates__items"><?php echo esc_html( $items ); ?></code>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="gi-md-card__footer">
				<a href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'Voir tout l’historique →', 'gi-toolkit' ); ?></a>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
