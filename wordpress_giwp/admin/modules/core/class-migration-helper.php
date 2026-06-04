<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module Name: Migration helper
 * Description: Affiche l’IP publique du site dans la barre d’administration (debug migration).
 */
class Gi_Toolkit_Migration_Helper {

	const AJAX_ACTION = 'gi_toolkit_migration_helper_refresh';

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return void
	 */
	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/migration-helper/class-ip-resolver.php';

		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar' ), 102 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_refresh_cache' ) );
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Barre admin.
	 * @return void
	 */
	public function register_admin_bar( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data  = Gi_Toolkit_Migration_Helper_IP_Resolver::get_toolbar_payload();
		$title = self::render_admin_bar_title( $data );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'gi-migration-helper',
				'parent' => 'top-secondary',
				'title'  => $title,
				'href'   => false,
				'meta'   => array(
					'class' => 'gi-migration-ab-root',
					'title' => self::build_admin_bar_tooltip( $data ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'gi-migration-helper',
				'id'     => 'gi-migration-helper-flyout',
				'title'  => self::render_admin_bar_flyout_html( $data ),
				'meta'   => array(
					'class'    => 'gi-migration-ab-flyout-item',
					'tabindex' => -1,
				),
			)
		);
	}

	/**
	 * Titre barre admin : logo ISP + sous-domaine PTR.
	 *
	 * @param array<string, mixed> $data Données barre admin.
	 * @return string
	 */
	public static function render_admin_bar_title( array $data ) {
		$label    = (string) ( $data['header_label'] ?? '' );
		$logo_url = (string) ( $data['header_logo'] ?? '' );
		$info     = is_array( $data['ip_info'] ?? null ) ? $data['ip_info'] : array();
		$isp_name = (string) ( $info['isp'] ?? $info['org'] ?? '' );

		if ( '' === $label ) {
			return '<span class="gi-migration-ab-wrap gi-migration-ab-wrap--empty"><span class="gi-migration-ab-host gi-migration-ab-host--muted">' . esc_html__( 'Hébergement inconnu', 'gi-toolkit' ) . '</span></span>';
		}

		$logo_html = '';
		if ( '' !== $logo_url ) {
			$logo_html = sprintf(
				'<img class="gi-migration-ab-logo" src="%1$s" alt="" width="18" height="18" loading="lazy" decoding="async"%2$s />',
				esc_url( $logo_url ),
				'' !== $isp_name ? ' title="' . esc_attr( $isp_name ) . '"' : ''
			);
		} else {
			$logo_html = '<span class="gi-migration-ab-logo gi-migration-ab-logo--fallback" aria-hidden="true"></span>';
		}

		return sprintf(
			'<span class="gi-migration-ab-wrap">%1$s<span class="gi-migration-ab-host">%2$s</span></span>',
			$logo_html,
			esc_html( $label )
		);
	}

	/**
	 * @param array<string, mixed> $data Données barre admin.
	 * @return string
	 */
	public static function build_admin_bar_tooltip( array $data ) {
		$info  = is_array( $data['ip_info'] ?? null ) ? $data['ip_info'] : array();
		$parts = array();

		if ( ! empty( $data['header_label'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: PTR subdomain / host label */
				__( 'Nœud : %s', 'gi-toolkit' ),
				(string) $data['header_label']
			);
		}

		if ( ! empty( $info['reverse_dns'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: full PTR hostname */
				__( 'PTR : %s', 'gi-toolkit' ),
				(string) $info['reverse_dns']
			);
		}

		$parts[] = sprintf(
			/* translators: %s: site URL */
			__( 'Site : %s', 'gi-toolkit' ),
			(string) ( $data['site_url'] ?? '' )
		);

		if ( ! empty( $data['public_ip'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: public IP */
				__( 'IP publique : %s', 'gi-toolkit' ),
				(string) $data['public_ip']
			);
		}

		if ( ! empty( $data['server_ip'] ) && (string) $data['server_ip'] !== (string) ( $data['public_ip'] ?? '' ) ) {
			$parts[] = sprintf(
				/* translators: %s: server IP */
				__( 'IP serveur : %s', 'gi-toolkit' ),
				(string) $data['server_ip']
			);
		}

		if ( ! empty( $info['asn'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: ASN label */
				__( 'ASN : %s', 'gi-toolkit' ),
				(string) $info['asn']
			);
		}

		return implode( "\n", $parts );
	}

	/**
	 * @param array<string, mixed> $data Données barre admin.
	 * @return string
	 */
	public static function render_admin_bar_flyout_html( array $data ) {
		$info       = is_array( $data['ip_info'] ?? null ) ? $data['ip_info'] : array();
		$public_ip  = (string) ( $data['public_ip'] ?? '' );
		$server_ip  = (string) ( $data['server_ip'] ?? '' );
		$host       = (string) ( $data['host'] ?? '' );
		$site_url   = (string) ( $data['site_url'] ?? '' );
		$nonce      = wp_create_nonce( self::AJAX_ACTION );

		$rows = array(
			array(
				'label' => __( 'Site WordPress', 'gi-toolkit' ),
				'value' => $host ? $host : $site_url,
			),
		);

		if ( '' !== $public_ip ) {
			$rows[] = array(
				'label' => __( 'IP publique', 'gi-toolkit' ),
				'value' => $public_ip,
				'mono'  => true,
			);
		}

		if ( '' !== $server_ip && $server_ip !== $public_ip ) {
			$rows[] = array(
				'label' => __( 'IP serveur (SERVER_ADDR)', 'gi-toolkit' ),
				'value' => $server_ip,
				'mono'  => true,
			);
		}

		if ( ! empty( $info['ptr_subdomain'] ) ) {
			$rows[] = array(
				'label' => __( 'Nœud (PTR)', 'gi-toolkit' ),
				'value' => (string) $info['ptr_subdomain'],
				'mono'  => true,
			);
		}

		if ( ! empty( $info['reverse_dns'] ) ) {
			$rows[] = array(
				'label' => __( 'Reverse DNS complet', 'gi-toolkit' ),
				'value' => (string) $info['reverse_dns'],
				'mono'  => true,
			);
		}

		if ( ! empty( $info['isp_domain'] ) ) {
			$rows[] = array(
				'label' => __( 'Domaine hébergeur', 'gi-toolkit' ),
				'value' => (string) $info['isp_domain'],
				'mono'  => true,
			);
		}

		if ( ! empty( $info['asn'] ) ) {
			$rows[] = array(
				'label' => __( 'ASN / opérateur', 'gi-toolkit' ),
				'value' => (string) $info['asn'],
			);
		}

		if ( ! empty( $info['isp'] ) && (string) $info['isp'] !== (string) ( $info['org'] ?? '' ) ) {
			$rows[] = array(
				'label' => __( 'Fournisseur (ISP)', 'gi-toolkit' ),
				'value' => (string) $info['isp'],
			);
		} elseif ( ! empty( $info['org'] ) ) {
			$rows[] = array(
				'label' => __( 'Organisation', 'gi-toolkit' ),
				'value' => (string) $info['org'],
			);
		}

		if ( ! empty( $info['country'] ) ) {
			$rows[] = array(
				'label' => __( 'Pays (géoloc.)', 'gi-toolkit' ),
				'value' => (string) $info['country'],
			);
		}

		if ( ! empty( $info['lookup_error'] ) ) {
			$rows[] = array(
				'label' => __( 'Note', 'gi-toolkit' ),
				'value' => (string) $info['lookup_error'],
				'muted' => true,
			);
		}

		ob_start();
		?>
		<div class="gi-migration-ab-flyout">
			<div class="gi-migration-ab-flyout__head">
				<span class="gi-migration-ab-flyout__title"><?php esc_html_e( 'Migration helper', 'gi-toolkit' ); ?></span>
				<span class="gi-migration-ab-flyout__subtitle"><?php esc_html_e( 'Identifier l’environnement connecté', 'gi-toolkit' ); ?></span>
			</div>

			<dl class="gi-migration-ab-flyout__list">
				<?php foreach ( $rows as $row ) : ?>
					<div class="gi-migration-ab-flyout__row">
						<dt><?php echo esc_html( (string) $row['label'] ); ?></dt>
						<dd class="<?php echo ! empty( $row['mono'] ) ? 'gi-migration-ab-mono' : ''; ?><?php echo ! empty( $row['muted'] ) ? ' gi-migration-ab-muted' : ''; ?>">
							<?php echo esc_html( (string) $row['value'] ); ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>

			<p class="gi-migration-ab-flyout__actions">
				<button type="button" class="gi-migration-ab-refresh" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Actualiser', 'gi-toolkit' ); ?>
				</button>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return void
	 */
	public function ajax_refresh_cache() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		Gi_Toolkit_Migration_Helper_IP_Resolver::bust_cache( false );

		wp_send_json_success(
			array(
				'message' => __( 'Données réseau actualisées. Rechargez la page si l’IP affichée ne change pas.', 'gi-toolkit' ),
			)
		);
	}

	/**
	 * @param string $hook_suffix Hook page.
	 * @return void
	 */
	public function enqueue_admin_bar_assets( $hook_suffix = '' ) {
		unset( $hook_suffix );

		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';

		wp_enqueue_style(
			'gi-toolkit-migration-helper-admin-bar',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/migration-helper-admin-bar.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'gi-toolkit-migration-helper-admin-bar',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/migration-helper-admin-bar.js',
			array(),
			$version,
			true
		);

		wp_localize_script(
			'gi-toolkit-migration-helper-admin-bar',
			'giToolkitMigrationHelper',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'i18n'    => array(
					'refreshing' => __( 'Actualisation…', 'gi-toolkit' ),
					'done'       => __( 'Cache réseau vidé. La page va se recharger.', 'gi-toolkit' ),
					'error'      => __( 'Échec de l’actualisation.', 'gi-toolkit' ),
				),
			)
		);
	}
}
