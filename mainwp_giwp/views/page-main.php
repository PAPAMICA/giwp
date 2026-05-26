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
);

$modules = MainWP_GIWeb_Catalog::get_modules();
$groups  = MainWP_GIWeb_Catalog::get_groups();
$high    = MainWP_GIWeb_Catalog::high_risk_modules();
?>
<div class="wrap mainwp-giweb-wrap">
	<h1><?php esc_html_e( 'GI-Toolkit — Gestion MainWP', 'mainwp-giweb' ); ?></h1>

	<?php if ( ! MainWP_GIWeb_Catalog::load_modules_data() ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Catalogue modules introuvable. Vérifiez que wordpress_giwp est accessible depuis ce serveur.', 'mainwp-giweb' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper mainwp-giweb-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $current_page ) . '&tab=' . $key ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="mainwp-giweb-panel">
		<?php
		switch ( $tab ) {
			case 'modules':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-modules.php';
				break;
			case 'templates':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-templates.php';
				break;
			case 'deploy':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-deploy.php';
				break;
			case 'excludes':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-excludes.php';
				break;
			case 'history':
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-history.php';
				break;
			default:
				include MAINWP_GIWEB_PLUGIN_PATH . 'views/tab-overview.php';
		}
		?>
	</div>
</div>
