<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$deployments = MainWP_GIWeb_History::get_recent_deployments( 30 );
$view_id     = isset( $_GET['deployment_id'] ) ? absint( $_GET['deployment_id'] ) : 0;
?>
<h2><?php esc_html_e( 'Historique des déploiements', 'mainwp-giweb' ); ?></h2>

<table class="widefat striped">
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
					<td><a href="<?php echo esc_url( MainWP_GIWeb_UI::admin_page_url( array( 'tab' => 'history', 'deployment_id' => (string) (int) $row->id ) ) ); ?>"><?php esc_html_e( 'Détails', 'mainwp-giweb' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( $view_id ) :
	$site_rows = MainWP_GIWeb_History::get_deployment_sites( $view_id );
	?>
	<h3><?php esc_html_e( 'Détail du déploiement #', 'mainwp-giweb' ); ?><?php echo esc_html( (string) $view_id ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Site', 'mainwp-giweb' ); ?></th><th><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></th><th><?php esc_html_e( 'Message', 'mainwp-giweb' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $site_rows as $sr ) : ?>
				<tr>
					<td><?php echo esc_html( MainWP_GIWeb::site_name( (int) $sr->site_id, $websites ) ); ?></td>
					<td><span class="mainwp-giweb-badge <?php echo 'success' === $sr->status ? 'ok' : 'err'; ?>"><?php echo esc_html( $sr->status ); ?></span></td>
					<td><?php echo esc_html( $sr->message ?? '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
