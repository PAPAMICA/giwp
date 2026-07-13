<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$deployments = MainWP_GIWeb_History::get_recent_deployments( 30 );
?>
<h2><?php esc_html_e( 'Historique des déploiements', 'mainwp-giweb' ); ?></h2>

<table class="widefat striped mainwp-giweb-history-table">
	<thead>
		<tr>
			<th>ID</th>
			<th><?php esc_html_e( 'Modèle', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Date', 'mainwp-giweb' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $deployments ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'Aucun déploiement.', 'mainwp-giweb' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $deployments as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $row->id ); ?></td>
					<td><?php echo esc_html( $row->template_name ?? '' ); ?></td>
					<td><?php echo esc_html( $row->created_at ?? '' ); ?></td>
					<td>
						<button
							type="button"
							class="button button-small mainwp-giweb-history-detail"
							data-deployment-id="<?php echo esc_attr( (string) (int) $row->id ); ?>"
						>
							<?php esc_html_e( 'Détails', 'mainwp-giweb' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<div id="mainwp-giweb-history-detail" class="mainwp-giweb-history-detail" hidden>
	<h3 class="mainwp-giweb-history-detail__title"><?php esc_html_e( 'Détail du déploiement', 'mainwp-giweb' ); ?></h3>
	<p class="mainwp-giweb-history-detail__loading description" hidden><?php esc_html_e( 'Chargement…', 'mainwp-giweb' ); ?></p>
	<p class="mainwp-giweb-history-detail__error description" hidden></p>
	<table class="widefat striped mainwp-giweb-history-detail__table" hidden>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></th>
				<th><?php esc_html_e( 'Message', 'mainwp-giweb' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
</div>

<h2><?php esc_html_e( 'Journal d\'erreurs (toutes actions)', 'mainwp-giweb' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Erreurs détaillées capturées côté Dashboard MainWP (déploiements, synchronisation, etc.), avec type, durée et message précis — pas besoin d\'un accès aux logs PHP serveur.', 'mainwp-giweb' ); ?>
</p>
<?php $error_log = class_exists( 'MainWP_GIWeb_Error_Log' ) ? MainWP_GIWeb_Error_Log::get_recent( 50 ) : array(); ?>
<table class="widefat striped mainwp-giweb-error-log-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Date', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Action', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Type', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Durée', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Message', 'mainwp-giweb' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $error_log ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'Aucune erreur enregistrée.', 'mainwp-giweb' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $error_log as $err ) : ?>
				<tr>
					<td><?php echo esc_html( $err->created_at ?? '' ); ?></td>
					<td><code><?php echo esc_html( $err->ajax_action ?? '' ); ?></code></td>
					<td><?php echo esc_html( $err->site_label ?: ( $err->site_id ? ( '#' . $err->site_id ) : '—' ) ); ?></td>
					<td><span class="mainwp-giweb-badge err"><?php echo esc_html( $err->error_type ?? '' ); ?></span></td>
					<td><?php echo esc_html( ( $err->duration_ms ?? 0 ) ? ( (string) (int) $err->duration_ms . ' ms' ) : '—' ); ?></td>
					<td><?php echo esc_html( $err->message ?? '' ); ?> <span class="description">(<?php echo esc_html( $err->file ?? '' ); ?>:<?php echo esc_html( (string) ( $err->line ?? 0 ) ); ?>)</span></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
