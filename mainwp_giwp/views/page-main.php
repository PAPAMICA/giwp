<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'overview'  => __( 'Vue d’ensemble', 'mainwp-giweb' ),
	'modules'   => __( 'Modules', 'mainwp-giweb' ),
	'templates' => __( 'Modèles', 'mainwp-giweb' ),
	'deploy'    => __( 'Déploiement', 'mainwp-giweb' ),
	'excludes'  => __( 'Exclusions', 'mainwp-giweb' ),
	'history'   => __( 'Historique', 'mainwp-giweb' ),
	'settings'  => __( 'Réglages', 'mainwp-giweb' ),
);

$modules = MainWP_GIWeb_Catalog::get_modules();
$groups  = MainWP_GIWeb_Catalog::get_groups();
$high    = MainWP_GIWeb_Catalog::high_risk_modules();
?>
<?php
$giweb_cfg = isset( $giweb_script_config ) && is_array( $giweb_script_config ) ? $giweb_script_config : MainWP_GIWeb::script_config();
?>
<div
	id="mainwp-giweb-app"
	class="<?php echo esc_attr( MainWP_GIWeb_UI::wrap_class_attr() ); ?>"
	data-ajax-url="<?php echo esc_url( $giweb_cfg['ajaxUrl'] ?? admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( $giweb_cfg['nonce'] ?? '' ); ?>"
>
	<h1 class="mainwp-giweb-screen-title"><?php echo esc_html( MainWP_GIWeb_UI::page_title() ); ?></h1>

	<?php MainWP_GIWeb_Notices::render(); ?>

	<?php if ( ! MainWP_GIWeb_Catalog::load_modules_data() ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Catalogue modules introuvable. Vérifiez que wordpress_giwp est accessible depuis ce serveur.', 'mainwp-giweb' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper mainwp-giweb-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( MainWP_GIWeb_UI::admin_page_url( array( 'tab' => $key ) ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="mainwp-giweb-panel">
		<?php
		switch ( $tab ) {
			case 'modules':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-modules.php';
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/modal-module-options.php';
				break;
			case 'templates':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-templates.php';
				break;
			case 'deploy':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-deploy.php';
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/modal-deploy.php';
				break;
			case 'excludes':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-excludes.php';
				break;
			case 'history':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-history.php';
				break;
			case 'settings':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-settings.php';
				break;
			default:
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-overview.php';
		}
		?>
	</div>
	<?php if ( 'overview' === $tab ) : ?>
		<?php include MAINWP_GIWEB_PLUGIN_PATH . 'views/modal-sync.php'; ?>
	<?php endif; ?>
</div>
<?php include MAINWP_GIWEB_PLUGIN_PATH . 'views/partials/inline-boot.php'; ?>
