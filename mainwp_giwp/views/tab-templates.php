<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$templates  = MainWP_GIWeb_Templates::all();
$default_id = MainWP_GIWeb_Templates::get_default_template_id();
?>
<h2><?php esc_html_e( 'Modèles de configuration', 'mainwp-giweb' ); ?></h2>
<p class="description"><?php esc_html_e( 'Un modèle enregistre la configuration de travail complète (modules actifs + réglages importés).', 'mainwp-giweb' ); ?></p>

<div class="mainwp-giweb-inline-form" id="mainwp-giweb-save-template-form">
	<input type="text" id="mainwp-giweb-template-name" name="template_name" placeholder="<?php esc_attr_e( 'Nom du modèle', 'mainwp-giweb' ); ?>" required />
	<button
		type="button"
		class="button button-primary mainwp-giweb-save-template"
		<?php disabled( empty( $working_bundle ) ); ?>
	>
		<?php esc_html_e( 'Enregistrer la config courante', 'mainwp-giweb' ); ?>
	</button>
</div>

<table class="widefat striped" id="mainwp-giweb-templates-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Nom', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Créé le', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Hash', 'mainwp-giweb' ); ?></th>
			<th><?php esc_html_e( 'Défaut', 'mainwp-giweb' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $templates ) ) : ?>
			<tr class="mainwp-giweb-templates-empty"><td colspan="5"><?php esc_html_e( 'Aucun modèle.', 'mainwp-giweb' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $templates as $id => $tpl ) : ?>
				<tr data-template-id="<?php echo esc_attr( $id ); ?>">
					<td><?php echo esc_html( $tpl['name'] ?? $id ); ?></td>
					<td><?php echo esc_html( $tpl['created_at'] ?? '' ); ?></td>
					<td><code><?php echo esc_html( substr( $tpl['hash'] ?? '', 0, 8 ) ); ?></code></td>
					<td>
						<?php if ( $default_id === $id || ! empty( $tpl['is_default'] ) ) : ?>
							<span class="mainwp-giweb-badge-ok"><?php esc_html_e( 'Défaut', 'mainwp-giweb' ); ?></span>
						<?php else : ?>
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
								<input type="hidden" name="mainwp_giweb_action" value="set_default_template" />
								<input type="hidden" name="tab" value="templates" />
								<input type="hidden" name="page" value="<?php echo esc_attr( $current_page ?? MainWP_GIWeb_UI::PAGE_SLUG ); ?>" />
								<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>" />
								<button type="submit" class="button button-small"><?php esc_html_e( 'Définir par défaut', 'mainwp-giweb' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
					<td>
						<button
							type="button"
							class="button button-link-delete mainwp-giweb-delete-template"
							data-template-id="<?php echo esc_attr( $id ); ?>"
						>
							<?php esc_html_e( 'Supprimer', 'mainwp-giweb' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
