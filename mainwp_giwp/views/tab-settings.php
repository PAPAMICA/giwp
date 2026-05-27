<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = MainWP_GIWeb_Settings::get();
$templates  = MainWP_GIWeb_Templates::all();
$default_id = MainWP_GIWeb_Templates::get_default_template_id();
$zip_url    = MainWP_GIWeb_Zip::get_public_url();
$logs       = get_option( MainWP_GIWeb_Onboarding::LOG_OPTION, array() );
?>
<h2><?php esc_html_e( 'Réglages GI-Toolkit Manager', 'mainwp-giweb' ); ?></h2>

<form method="post" action="">
	<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
	<input type="hidden" name="mainwp_giweb_action" value="save_settings" />
	<input type="hidden" name="tab" value="settings" />
	<input type="hidden" name="page" value="<?php echo esc_attr( $current_page ?? MainWP_GIWeb_UI::PAGE_SLUG ); ?>" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Profil par défaut', 'mainwp-giweb' ); ?></th>
			<td>
				<select name="default_template_id" id="mainwp_giweb_default_template_id">
					<option value=""><?php esc_html_e( '— Modèle nommé « Default » ou premier marqué défaut —', 'mainwp-giweb' ); ?></option>
					<?php foreach ( $templates as $id => $tpl ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $default_id, $id ); ?>>
							<?php echo esc_html( $tpl['name'] ?? $id ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Utilisé sur le formulaire « Ajouter un site » et lorsque l’option « Profil par défaut » est sélectionnée.', 'mainwp-giweb' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ajout de site', 'mainwp-giweb' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="install_checked_by_default" value="1" <?php checked( '1', $settings['install_checked_by_default'] ?? '1' ); ?> />
					<?php esc_html_e( 'Cocher par défaut « Installer GI-Toolkit »', 'mainwp-giweb' ); ?>
				</label>
				<br />
				<label>
					<input type="checkbox" name="apply_profile_by_default" value="1" <?php checked( '1', $settings['apply_profile_by_default'] ?? '1' ); ?> />
					<?php esc_html_e( 'Cocher par défaut « Appliquer le profil »', 'mainwp-giweb' ); ?>
				</label>
				<br />
				<label>
					<input type="checkbox" name="activate_after_install" value="1" <?php checked( '1', $settings['activate_after_install'] ?? '1' ); ?> />
					<?php esc_html_e( 'Activer GI-Toolkit après installation ZIP', 'mainwp-giweb' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Alertes mail', 'mainwp-giweb' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="mail_alert_enabled" value="1" <?php checked( '1', $settings['mail_alert_enabled'] ?? '1' ); ?> />
					<?php esc_html_e( 'Alerter après synchronisation si des emails sont en échec', 'mainwp-giweb' ); ?>
				</label>
				<p>
					<label for="mainwp_giweb_mail_alert_min_failed"><?php esc_html_e( 'Seuil par site (nombre d’échecs min.)', 'mainwp-giweb' ); ?></label>
					<input type="number" min="1" step="1" class="small-text" id="mainwp_giweb_mail_alert_min_failed" name="mail_alert_min_failed" value="<?php echo esc_attr( (string) (int) ( $settings['mail_alert_min_failed'] ?? 1 ) ); ?>" />
				</p>
				<p>
					<label for="mainwp_giweb_mail_alert_email"><?php esc_html_e( 'Email de notification (optionnel)', 'mainwp-giweb' ); ?></label><br />
					<input type="email" class="regular-text" id="mainwp_giweb_mail_alert_email" name="mail_alert_email" value="<?php echo esc_attr( (string) ( $settings['mail_alert_email'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
				</p>
				<p class="description"><?php esc_html_e( 'Les statistiques sont remontées lors de « Synchroniser les statuts » et affichées dans le widget MainWP « GI-Toolkit — Mails ».', 'mainwp-giweb' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Package ZIP', 'mainwp-giweb' ); ?></th>
			<td>
				<?php if ( $zip_url ) : ?>
					<p>
						<code><?php echo esc_html( $zip_url ); ?></code>
					</p>
					<p class="description"><?php esc_html_e( 'Le site enfant doit pouvoir télécharger cette URL (HTTPS). Le ZIP est régénéré si wordpress_giwp a été modifié.', 'mainwp-giweb' ); ?></p>
				<?php else : ?>
					<p class="description" style="color:#b45309;"><?php esc_html_e( 'ZIP indisponible — vérifiez ZipArchive et le chemin wordpress_giwp.', 'mainwp-giweb' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Enregistrer', 'mainwp-giweb' ) ); ?>
</form>

<?php if ( is_array( $logs ) && ! empty( $logs ) ) : ?>
	<h3><?php esc_html_e( 'Derniers onboardings', 'mainwp-giweb' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'mainwp-giweb' ); ?></th>
				<th><?php esc_html_e( 'Site ID', 'mainwp-giweb' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'mainwp-giweb' ); ?></th>
				<th><?php esc_html_e( 'Détail', 'mainwp-giweb' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_slice( $logs, 0, 10 ) as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
					<td><?php echo esc_html( (string) ( $entry['site_id'] ?? '' ) ); ?></td>
					<td><?php echo ! empty( $entry['success'] ) ? esc_html__( 'OK', 'mainwp-giweb' ) : esc_html__( 'Partiel / échec', 'mainwp-giweb' ); ?></td>
					<td><?php echo esc_html( implode( ' · ', (array) ( $entry['logs'] ?? array() ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
