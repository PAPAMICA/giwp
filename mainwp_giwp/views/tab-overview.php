<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bundle_modules = $working_bundle['modules'] ?? array();
$active_in_bundle = 0;
if ( is_array( $bundle_modules ) ) {
	foreach ( $bundle_modules as $mod ) {
		if ( is_array( $mod ) && ! empty( $mod['active'] ) && '1' === (string) $mod['active'] ) {
			++$active_in_bundle;
		}
	}
}
?>
<h2><?php esc_html_e( 'Sites connectés', 'mainwp-giweb' ); ?></h2>
<div class="mainwp-giweb-toolbar">
	<button type="button" class="button button-primary" id="mainwp-giweb-sync-start">
		<?php esc_html_e( 'Synchroniser les statuts', 'mainwp-giweb' ); ?>
	</button>
</div>

<table class="widefat striped mainwp-giweb-table" id="mainwp-giweb-sites-table">
	<thead>
		<tr>
			<th class="mainwp-giweb-col-site"><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
			<th class="mainwp-giweb-col-url"><?php esc_html_e( 'URL', 'mainwp-giweb' ); ?></th>
			<th class="mainwp-giweb-col-status"><?php esc_html_e( 'GI-Toolkit', 'mainwp-giweb' ); ?></th>
			<th class="mainwp-giweb-col-version"><?php esc_html_e( 'Version', 'mainwp-giweb' ); ?></th>
			<th class="mainwp-giweb-col-modules"><?php esc_html_e( 'Modules actifs', 'mainwp-giweb' ); ?></th>
			<th class="mainwp-giweb-col-actions"><?php esc_html_e( 'Actions', 'mainwp-giweb' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $websites ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'Aucun site enfant trouvé.', 'mainwp-giweb' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $websites as $site ) :
				$row    = MainWP_GIWeb_Sites::normalize_one( $site );
				$sid    = (int) $row['id'];
				$label  = $row['name'] ?: $row['url'] ?: ( '#' . $sid );
				$cache  = $status_cache[ $sid ] ?? null;
				$data   = is_array( $cache ) && ! empty( $cache['data'] ) ? $cache['data'] : array();
				$ok     = is_array( $cache ) && ! empty( $cache['success'] );
				$error  = ( is_array( $cache ) && ! empty( $cache['errors'][0] ) ) ? (string) $cache['errors'][0] : '';
				?>
				<tr data-site-id="<?php echo esc_attr( (string) $sid ); ?>">
					<td class="mainwp-giweb-col-name"><?php echo esc_html( $row['name'] ); ?></td>
					<td class="mainwp-giweb-col-url"><a href="<?php echo esc_url( $row['url'] ?: '#' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['url'] ); ?></a></td>
					<td class="mainwp-giweb-col-status">
						<?php if ( $ok ) : ?>
							<span class="mainwp-giweb-badge ok"><?php esc_html_e( 'OK', 'mainwp-giweb' ); ?></span>
						<?php else : ?>
							<span class="mainwp-giweb-badge err" title="<?php echo esc_attr( $error ); ?>"><?php esc_html_e( 'Erreur', 'mainwp-giweb' ); ?></span>
							<?php if ( $error ) : ?>
								<span class="mainwp-giweb-error-hint"><?php echo esc_html( $error ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td class="mainwp-giweb-col-version"><?php echo esc_html( $data['gi_toolkit_version'] ?? '—' ); ?></td>
					<td class="mainwp-giweb-col-modules"><?php echo esc_html( isset( $data['active_modules'] ) ? (string) $data['active_modules'] : '—' ); ?></td>
					<td class="mainwp-giweb-col-actions">
						<form
							method="post"
							action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
							class="mainwp-giweb-pull-form"
						>
							<?php
							wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' );
							include MAINWP_GIWEB_PLUGIN_PATH . 'views/partials/form-context.php';
							?>
							<input type="hidden" name="mainwp_giweb_action" value="pull_config" />
							<input type="hidden" name="source_site_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
							<button
								type="submit"
								class="button button-small mainwp-giweb-pull-config"
								data-site-id="<?php echo esc_attr( (string) $sid ); ?>"
								data-site-name="<?php echo esc_attr( $label ); ?>"
							>
								<?php esc_html_e( 'Importer config', 'mainwp-giweb' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Configuration de travail', 'mainwp-giweb' ); ?></h2>
<div id="mainwp-giweb-working-bundle">
	<?php if ( ! empty( $bundle_modules ) ) : ?>
		<p class="mainwp-giweb-bundle-loaded"><?php esc_html_e( 'Configuration chargée. Onglet Modules : catégories à gauche puis « Voir les réglages » (SMTP, etc.). Onglets Modèles ou Déploiement pour pousser vers d’autres sites.', 'mainwp-giweb' ); ?></p>
		<p class="mainwp-giweb-bundle-stats">
			<code><?php echo esc_html( (string) count( $bundle_modules ) ); ?></code>
			<?php esc_html_e( 'modules dans le bundle', 'mainwp-giweb' ); ?>
			—
			<strong><?php echo esc_html( (string) $active_in_bundle ); ?></strong>
			<?php esc_html_e( 'actifs', 'mainwp-giweb' ); ?>
		</p>
	<?php else : ?>
		<p class="description mainwp-giweb-bundle-empty"><?php esc_html_e( 'Importez la configuration depuis un site via « Importer config ».', 'mainwp-giweb' ); ?></p>
	<?php endif; ?>
</div>
