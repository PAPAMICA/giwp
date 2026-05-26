<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : tableau de suivi des mises à jour (plugins / thèmes / cœur) — complément aux alertes sur la page Extensions.
 */
class Gi_Toolkit_Vulnerabilities_Scan {

	/**
	 * @var bool
	 */
	private $disable_form = true;

	/**
	 * @var string
	 */
	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Vulnerabilities Scan', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'load-plugins.php', array( $this, 'notice' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-vulnerabilities-scan',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$submenu_assets = include GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/global-admin.asset.php';
		wp_enqueue_style( 'gi-toolkit-vuln-scan', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.css', array(), $submenu_assets['version'], 'all' );

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';

		$updates_plugins = get_site_transient( 'update_plugins' );
		$updates_themes  = get_site_transient( 'update_themes' );
		$updates_core    = get_site_transient( 'update_core' );

		$plugins   = get_plugins();
		$themes    = wp_get_themes();
		$wp_latest = '';
		if ( is_object( $updates_core ) && ! empty( $updates_core->updates[0]->version ) ) {
			$wp_latest = $updates_core->updates[0]->version;
		}
		$core_need_update = $wp_latest && version_compare( $GLOBALS['wp_version'], $wp_latest, '<' );
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:100%;">
			<p class="description">
				<?php esc_html_e( 'Vue d’ensemble des versions installées et des mises à jour disponibles. Pour un scan CVE dédié, utilisez un service spécialisé (WPScan, Patchstack, etc.).', 'gi-toolkit' ); ?>
			</p>

			<h2 style="margin-top:1.5rem;"><?php esc_html_e( 'WordPress', 'gi-toolkit' ); ?></h2>
			<table class="widefat striped" style="max-width:640px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Version installée', 'gi-toolkit' ); ?></th>
						<td><code><?php echo esc_html( $GLOBALS['wp_version'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dernière version connue', 'gi-toolkit' ); ?></th>
						<td>
							<?php
							echo $wp_latest ? '<code>' . esc_html( $wp_latest ) . '</code>' : '—';
							if ( $core_need_update ) {
								echo ' <span class="notice notice-warning inline" style="padding:4px 8px;margin-left:8px;">' . esc_html__( 'Mise à jour disponible', 'gi-toolkit' ) . '</span>';
								echo ' <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Mettre à jour', 'gi-toolkit' ) . '</a>';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<h2 style="margin-top:1.5rem;"><?php esc_html_e( 'Extensions', 'gi-toolkit' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Extension', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Version', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'État', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Lien', 'gi-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$response = ( is_object( $updates_plugins ) && ! empty( $updates_plugins->response ) && is_array( $updates_plugins->response ) ) ? $updates_plugins->response : array();
				foreach ( $plugins as $file => $data ) {
					$name    = $data['Name'] ?? $file;
					$ver     = $data['Version'] ?? '';
					$slug    = dirname( $file );
					if ( '.' === $slug ) {
						$slug = basename( $file, '.php' );
					}
					$has_up  = isset( $response[ $file ] );
					$new_ver = $has_up && isset( $response[ $file ]->new_version ) ? $response[ $file ]->new_version : '';
					$status  = $has_up
						? '<span style="color:#b32d2e;">' . esc_html__( 'Mise à jour disponible', 'gi-toolkit' ) . ( $new_ver ? ' → <code>' . esc_html( $new_ver ) . '</code>' : '' ) . '</span>'
						: '<span style="color:#007017;">' . esc_html__( 'Aucune mise à jour listée (API WordPress)', 'gi-toolkit' ) . '</span>';
					$repo    = 'https://wordpress.org/plugins/' . rawurlencode( $slug ) . '/';
					echo '<tr>';
					echo '<td>' . esc_html( $name ) . '</td>';
					echo '<td><code>' . esc_html( $ver ) . '</code></td>';
					echo '<td>' . $status . '</td>';
					echo '<td><a href="' . esc_url( $repo ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Fiche', 'gi-toolkit' ) . '</a></td>';
					echo '</tr>';
				}
				?>
				</tbody>
			</table>

			<h2 style="margin-top:1.5rem;"><?php esc_html_e( 'Thèmes', 'gi-toolkit' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Thème', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Version', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'État', 'gi-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$theme_response = ( is_object( $updates_themes ) && ! empty( $updates_themes->response ) && is_array( $updates_themes->response ) ) ? $updates_themes->response : array();
				foreach ( $themes as $slug => $theme ) {
					$ver    = $theme->get( 'Version' );
					$has_up = isset( $theme_response[ $slug ] );
					$new_ver = $has_up && isset( $theme_response[ $slug ]['new_version'] ) ? $theme_response[ $slug ]['new_version'] : '';
					$status = $has_up
						? '<span style="color:#b32d2e;">' . esc_html__( 'Mise à jour disponible', 'gi-toolkit' ) . ( $new_ver ? ' → <code>' . esc_html( $new_ver ) . '</code>' : '' ) . '</span>'
						: '<span style="color:#007017;">' . esc_html__( 'Aucune mise à jour listée (API WordPress)', 'gi-toolkit' ) . '</span>';
					echo '<tr>';
					echo '<td>' . esc_html( $theme->get( 'Name' ) ) . '</td>';
					echo '<td><code>' . esc_html( $ver ) . '</code></td>';
					echo '<td>' . $status . '</td>';
					echo '</tr>';
				}
				?>
				</tbody>
			</table>

			<p style="margin-top:1.5rem;">
				<a class="button" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'Mises à jour WordPress', 'gi-toolkit' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Extensions', 'gi-toolkit' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>"><?php esc_html_e( 'Thèmes', 'gi-toolkit' ); ?></a>
			</p>
		</div>
		<?php
		echo '</div>';
	}

	public function notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$updates = get_site_transient( 'update_plugins' );
		if ( empty( $updates->response ) || ! is_array( $updates->response ) ) {
			return;
		}
		$count = count( $updates->response );
		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %d: number of plugin updates */
			esc_html( _n( '%d extension a une mise à jour disponible — appliquez-les rapidement pour limiter les risques.', '%d extensions ont une mise à jour disponible — appliquez-les rapidement pour limiter les risques.', $count, 'gi-toolkit' ) ),
			(int) $count
		);
		echo ' ';
		echo esc_html__( 'Tableau détaillé : GI-Toolkit → Vulnerabilities Scan.', 'gi-toolkit' );
		echo '</p></div>';
	}
}
