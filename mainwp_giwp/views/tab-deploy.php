<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$templates       = MainWP_GIWeb_Templates::all();
$install_zip_url = MainWP_GIWeb_Zip::get_install_url();
$package_version = MainWP_GIWeb_Zip::get_package_version();
$can_config      = ! empty( $working_bundle ) || ! empty( $templates );
?>
<section class="mainwp-giweb-deploy-section" id="mainwp-giweb-deploy-plugin">
	<h2><?php esc_html_e( 'Déployer la dernière version', 'mainwp-giweb' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Installe ou met à jour GI-Toolkit (ZIP) sur tous les sites enfants MainWP.', 'mainwp-giweb' ); ?>
		<?php if ( $package_version ) : ?>
			<?php
			printf(
				/* translators: %s: package version */
				esc_html__( 'Version du package : %s.', 'mainwp-giweb' ),
				esc_html( $package_version )
			);
			?>
		<?php endif; ?>
	</p>
	<?php if ( ! $install_zip_url ) : ?>
		<p class="description mainwp-giweb-settings-warn"><?php esc_html_e( 'Aucune URL ZIP disponible — vérifiez ZipArchive et le dossier wordpress_giwp (réglages).', 'mainwp-giweb' ); ?></p>
	<?php endif; ?>
	<p>
		<button
			type="button"
			class="button button-secondary button-hero"
			id="mainwp-giweb-plugin-deploy-start"
			<?php disabled( ! $install_zip_url ); ?>
		>
			<?php esc_html_e( 'Déployer la dernière version sur tous les sites', 'mainwp-giweb' ); ?>
		</button>
	</p>
</section>

<hr class="mainwp-giweb-deploy-sep" />

<section class="mainwp-giweb-deploy-section" id="mainwp-giweb-deploy-config">
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
			<?php
			foreach ( $websites as $site ) :
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

		<p class="mainwp-giweb-deploy-actions">
			<button
				type="button"
				class="button button-primary button-hero"
				id="mainwp-giweb-deploy-start"
				<?php disabled( ! $can_config ); ?>
			>
				<?php esc_html_e( 'Déployer la configuration', 'mainwp-giweb' ); ?>
			</button>
			<button
				type="button"
				class="button button-primary button-hero"
				id="mainwp-giweb-full-deploy-start"
				<?php disabled( ! $install_zip_url || ! $can_config ); ?>
			>
				<?php esc_html_e( 'Mettre à jour le plugin puis déployer la configuration', 'mainwp-giweb' ); ?>
			</button>
		</p>
		<p class="description">
			<?php esc_html_e( 'Le bouton combiné met à jour GI-Toolkit sur les sites sélectionnés, puis pousse la configuration.', 'mainwp-giweb' ); ?>
		</p>
	</div>
</section>
