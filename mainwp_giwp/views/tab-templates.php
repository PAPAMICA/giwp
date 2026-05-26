<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$templates = MainWP_GIWeb_Templates::all();
?>
<h2><?php esc_html_e( 'Modèles de configuration', 'mainwp-giweb' ); ?></h2>

<form method="post" class="mainwp-giweb-inline-form">
	<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
	<input type="hidden" name="mainwp_giweb_action" value="save_template" />
	<input type="text" name="template_name" placeholder="<?php esc_attr_e( 'Nom du modèle', 'mainwp-giweb' ); ?>" required />
	<button type="submit" class="button button-primary" <?php disabled( empty( $working_bundle ) ); ?>><?php esc_html_e( 'Enregistrer la config courante', 'mainwp-giweb' ); ?></button>
</form>

<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Nom', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Créé le', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Hash', 'mainwp-giweb' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $templates ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'Aucun modèle.', 'mainwp-giweb' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $templates as $id => $tpl ) : ?>
				<tr>
					<td><?php echo esc_html( $tpl['name'] ?? $id ); ?></td>
					<td><?php echo esc_html( $tpl['created_at'] ?? '' ); ?></td>
					<td><code><?php echo esc_html( substr( $tpl['hash'] ?? '', 0, 8 ) ); ?></code></td>
					<td>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
							<input type="hidden" name="mainwp_giweb_action" value="delete_template" />
							<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>" />
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Supprimer', 'mainwp-giweb' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
