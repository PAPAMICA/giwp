<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Sites connectés', 'mainwp-giweb' ); ?></h2>
<form method="post">
	<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
	<input type="hidden" name="mainwp_giweb_action" value="sync_status" />
	<button type="submit" class="button button-primary"><?php esc_html_e( 'Synchroniser les statuts', 'mainwp-giweb' ); ?></button>
</form>

<table class="widefat striped mainwp-giweb-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'URL', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'GI-Toolkit', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Version', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Modules actifs', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'mainwp-giweb' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $websites ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'Aucun site enfant trouvé.', 'mainwp-giweb' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $websites as $site ) :
				$sid    = (int) ( $site->id ?? 0 );
				$cache  = $status_cache[ $sid ] ?? null;
				$data   = is_array( $cache ) && ! empty( $cache['data'] ) ? $cache['data'] : array();
				$ok     = is_array( $cache ) && ! empty( $cache['success'] );
				?>
				<tr>
					<td><?php echo esc_html( $site->name ?? '' ); ?></td>
					<td><a href="<?php echo esc_url( $site->url ?? '#' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $site->url ?? '' ); ?></a></td>
					<td><?php echo $ok ? '<span class="mainwp-giweb-badge ok">' . esc_html__( 'OK', 'mainwp-giweb' ) . '</span>' : '<span class="mainwp-giweb-badge err">' . esc_html__( 'Erreur', 'mainwp-giweb' ) . '</span>'; ?></td>
					<td><?php echo esc_html( $data['gi_toolkit_version'] ?? '—' ); ?></td>
					<td><?php echo esc_html( isset( $data['active_modules'] ) ? (string) $data['active_modules'] : '—' ); ?></td>
					<td>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
							<input type="hidden" name="mainwp_giweb_action" value="pull_config" />
							<input type="hidden" name="source_site_id" value="<?php echo esc_attr( $sid ); ?>" />
							<button type="submit" class="button button-small"><?php esc_html_e( 'Importer config', 'mainwp-giweb' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Configuration de travail', 'mainwp-giweb' ); ?></h2>
<?php if ( ! empty( $working_bundle['modules'] ) ) : ?>
	<p><?php esc_html_e( 'Une configuration est chargée en mémoire sur ce dashboard. Utilisez les onglets Modèles ou Déploiement pour la pousser vers d’autres sites.', 'mainwp-giweb' ); ?></p>
	<p><code><?php echo esc_html( count( $working_bundle['modules'] ) ); ?></code> <?php esc_html_e( 'modules dans le bundle.', 'mainwp-giweb' ); ?></p>
<?php else : ?>
	<p class="description"><?php esc_html_e( 'Importez la configuration depuis un site via « Importer config ».', 'mainwp-giweb' ); ?></p>
<?php endif; ?>
