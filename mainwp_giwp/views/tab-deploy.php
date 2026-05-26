<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$templates = MainWP_GIWeb_Templates::all();
?>
<h2><?php esc_html_e( 'Déployer la configuration', 'mainwp-giweb' ); ?></h2>

<div class="mainwp-giweb-notice-warn">
	<?php esc_html_e( 'Le déploiement inclut les secrets (SMTP, clés API, etc.) et remplace la configuration des sites sélectionnés, sauf exclusions définies.', 'mainwp-giweb' ); ?>
</div>

<div id="mainwp-giweb-deploy-form">
	<p>
		<label for="deploy_template_id"><?php esc_html_e( 'Modèle (optionnel)', 'mainwp-giweb' ); ?></label>
		<select name="deploy_template_id" id="deploy_template_id">
			<option value=""><?php esc_html_e( 'Configuration de travail', 'mainwp-giweb' ); ?></option>
			<?php foreach ( $templates as $id => $tpl ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $tpl['name'] ?? $id ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<h3><?php esc_html_e( 'Sites cibles', 'mainwp-giweb' ); ?></h3>
	<?php if ( function_exists( 'do_action' ) ) : ?>
		<div class="mainwp-giweb-sites-box" id="mainwp-giweb-deploy-sites-box">
			<?php do_action( 'mainwp_select_sites_box', __( 'Sélectionner les sites', 'mainwp-giweb' ), 'checkbox', true, true, 'mainwp_select_sites_box_right', '', array(), array() ); ?>
		</div>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Cochez les sites :', 'mainwp-giweb' ); ?></p>
		<?php foreach ( $websites as $site ) :
			$row = MainWP_GIWeb_Sites::normalize_one( $site );
			$sid = (int) $row['id'];
			if ( ! $sid ) {
				continue;
			}
			?>
			<label class="mainwp-giweb-deploy-site-fallback" style="display:block;margin:4px 0;">
				<input type="checkbox" class="mainwp-giweb-deploy-site-cb" name="site_ids[]" value="<?php echo esc_attr( (string) $sid ); ?>" data-site-name="<?php echo esc_attr( $row['name'] ?: $row['url'] ?: (string) $sid ); ?>" />
				<?php echo esc_html( $row['name'] ?: $row['url'] ?: (string) $sid ); ?>
			</label>
		<?php endforeach; ?>
	<?php endif; ?>

	<p>
		<button
			type="button"
			class="button button-primary button-hero"
			id="mainwp-giweb-deploy-start"
			<?php disabled( empty( $working_bundle ) && empty( $templates ) ); ?>
		>
			<?php esc_html_e( 'Déployer maintenant', 'mainwp-giweb' ); ?>
		</button>
	</p>
</div>
