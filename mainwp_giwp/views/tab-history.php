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
