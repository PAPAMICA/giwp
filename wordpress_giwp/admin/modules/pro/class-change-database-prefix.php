<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: informations sur le préfixe des tables (pas d’automatisation).
 */
class Gi_Toolkit_Change_Database_Prefix {

	private $disable_form = true;

	private $header_title = '';

	public function __construct() {
		$this->header_title = __( 'Change Database Prefix', 'gi-toolkit' );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			'gi-toolkit-settings-db-prefix',
			array( $this, 'render_submenu' )
		);
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		global $wpdb;
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Le changement de préfixe n’est pas automatisé ici : sauvegarde complète, renommage des tables MySQL, mise à jour de $table_prefix dans wp-config.php et correction des options sérialisées si besoin.', 'gi-toolkit' ); ?></p></div>
			<p><strong><?php esc_html_e( 'Préfixe actuel :', 'gi-toolkit' ); ?></strong> <code><?php echo esc_html( $wpdb->prefix ); ?></code></p>
			<p class="description"><?php esc_html_e( 'Utilisez une copie de site, WP-CLI ou un outil dédié pour cette opération.', 'gi-toolkit' ); ?></p>
		</div>
		<?php
		echo '</div>';
	}
}
