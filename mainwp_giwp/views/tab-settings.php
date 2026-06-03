<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings        = MainWP_GIWeb_Settings::get();
$templates       = MainWP_GIWeb_Templates::all();
$default_id      = MainWP_GIWeb_Templates::get_default_template_id();
$auto_zip_url    = MainWP_GIWeb_Zip::get_public_url();
$install_zip_url = MainWP_GIWeb_Zip::get_install_url();
$logs            = get_option( MainWP_GIWeb_Onboarding::LOG_OPTION, array() );
?>
<h2><?php esc_html_e( 'Réglages GI-Toolkit Manager', 'mainwp-giweb' ); ?></h2>

<form method="post" action="" class="mainwp-giweb-settings-form">
	<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
	<input type="hidden" name="mainwp_giweb_action" value="save_settings" />
	<input type="hidden" name="tab" value="settings" />
	<input type="hidden" name="page" value="<?php echo esc_attr( $current_page ?? MainWP_GIWeb_UI::PAGE_SLUG ); ?>" />

	<div class="mainwp-giweb-settings-sections">
		<section class="mainwp-giweb-settings-section">
			<h3 class="mainwp-giweb-settings-section__title"><?php esc_html_e( 'Général', 'mainwp-giweb' ); ?></h3>
			<div class="mainwp-giweb-settings-grid">
				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title"><?php esc_html_e( 'Profil par défaut', 'mainwp-giweb' ); ?></h4>
					<p class="mainwp-giweb-settings-card__desc"><?php esc_html_e( 'Utilisé sur le formulaire « Ajouter un site » et lorsque l’option « Profil par défaut » est sélectionnée.', 'mainwp-giweb' ); ?></p>
					<select name="default_template_id" id="mainwp_giweb_default_template_id" class="regular-text">
						<option value=""><?php esc_html_e( '— Modèle nommé « Default » ou premier marqué défaut —', 'mainwp-giweb' ); ?></option>
						<?php foreach ( $templates as $id => $tpl ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $default_id, $id ); ?>>
								<?php echo esc_html( $tpl['name'] ?? $id ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title"><?php esc_html_e( 'Ajout de site', 'mainwp-giweb' ); ?></h4>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="install_checked_by_default" value="1" <?php checked( '1', $settings['install_checked_by_default'] ?? '1' ); ?> />
						<?php esc_html_e( 'Cocher par défaut « Installer GI-Toolkit »', 'mainwp-giweb' ); ?>
					</label>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="apply_profile_by_default" value="1" <?php checked( '1', $settings['apply_profile_by_default'] ?? '1' ); ?> />
						<?php esc_html_e( 'Cocher par défaut « Appliquer le profil »', 'mainwp-giweb' ); ?>
					</label>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="activate_after_install" value="1" <?php checked( '1', $settings['activate_after_install'] ?? '1' ); ?> />
						<?php esc_html_e( 'Activer GI-Toolkit après installation ZIP', 'mainwp-giweb' ); ?>
					</label>
				</div>

				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title"><?php esc_html_e( 'Synchronisation', 'mainwp-giweb' ); ?></h4>
					<p>
						<label for="mainwp_giweb_sync_concurrency"><?php esc_html_e( 'Sites interrogés en parallèle (sync et déploiement)', 'mainwp-giweb' ); ?></label>
						<input type="number" min="1" max="15" step="1" class="small-text" id="mainwp_giweb_sync_concurrency" name="sync_concurrency" value="<?php echo esc_attr( (string) (int) ( $settings['sync_concurrency'] ?? 5 ) ); ?>" />
					</p>
					<p class="description"><?php esc_html_e( 'Utilisé pour la synchronisation des statuts et le déploiement de configuration (défaut : 5).', 'mainwp-giweb' ); ?></p>
				</div>

				<div class="mainwp-giweb-settings-card mainwp-giweb-settings-card--wide">
					<h4 class="mainwp-giweb-settings-card__title"><?php esc_html_e( 'URL ZIP client', 'mainwp-giweb' ); ?></h4>
					<input type="url" class="large-text code" id="mainwp_giweb_client_zip_url" name="client_zip_url" value="<?php echo esc_attr( (string) ( $settings['client_zip_url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( is_string( $auto_zip_url ) ? $auto_zip_url : '' ); ?>" />
					<p class="description"><?php esc_html_e( 'URL téléchargeable par les sites enfants pour installer GI-Toolkit. Laissez vide pour utiliser le ZIP généré automatiquement sur ce dashboard.', 'mainwp-giweb' ); ?></p>
					<?php if ( $auto_zip_url ) : ?>
						<p class="description">
							<?php esc_html_e( 'ZIP auto-généré :', 'mainwp-giweb' ); ?>
							<code><?php echo esc_html( $auto_zip_url ); ?></code>
						</p>
					<?php endif; ?>
					<?php if ( $install_zip_url ) : ?>
						<p class="description">
							<?php esc_html_e( 'URL utilisée à l’installation :', 'mainwp-giweb' ); ?>
							<code><?php echo esc_html( $install_zip_url ); ?></code>
						</p>
					<?php else : ?>
						<p class="description mainwp-giweb-settings-warn"><?php esc_html_e( 'Aucune URL ZIP disponible — définissez une URL personnalisée ou vérifiez ZipArchive et wordpress_giwp.', 'mainwp-giweb' ); ?></p>
					<?php endif; ?>
					<p style="margin-top:12px;">
						<button type="button" class="button button-secondary" id="mainwp-giweb-plugin-deploy-start" <?php disabled( ! $install_zip_url ); ?>>
							<?php esc_html_e( 'Déployer la dernière version sur tous les sites', 'mainwp-giweb' ); ?>
						</button>
					</p>
					<p class="description">
						<?php
						printf(
							/* translators: %d: parallel site count from settings */
							esc_html__(
								'Installe ou met à jour GI-Toolkit sur chaque site enfant via l’URL ZIP ci-dessus. Les sites sont traités en parallèle (%d à la fois).',
								'mainwp-giweb'
							),
							max( 1, min( 15, (int) ( $settings['sync_concurrency'] ?? 5 ) ) )
						);
						?>
					</p>
				</div>
			</div>
		</section>

		<section class="mainwp-giweb-settings-section">
			<h3 class="mainwp-giweb-settings-section__title"><?php esc_html_e( 'Widgets dashboard', 'mainwp-giweb' ); ?></h3>
			<div class="mainwp-giweb-settings-grid">
				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title"><?php esc_html_e( 'Affichage liste Mails (détaillé)', 'mainwp-giweb' ); ?></h4>
					<select name="mail_widget_list_mode" id="mainwp_giweb_mail_widget_list_mode">
						<option value="cards" <?php selected( 'cards', $settings['mail_widget_list_mode'] ?? 'cards' ); ?>><?php esc_html_e( 'Cartes', 'mainwp-giweb' ); ?></option>
						<option value="table" <?php selected( 'table', $settings['mail_widget_list_mode'] ?? 'cards' ); ?>><?php esc_html_e( 'Tableau', 'mainwp-giweb' ); ?></option>
					</select>
				</div>
				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title"><?php esc_html_e( 'Affichage liste Backups (détaillé)', 'mainwp-giweb' ); ?></h4>
					<select name="backup_widget_list_mode" id="mainwp_giweb_backup_widget_list_mode">
						<option value="cards" <?php selected( 'cards', $settings['backup_widget_list_mode'] ?? 'cards' ); ?>><?php esc_html_e( 'Cartes', 'mainwp-giweb' ); ?></option>
						<option value="table" <?php selected( 'table', $settings['backup_widget_list_mode'] ?? 'cards' ); ?>><?php esc_html_e( 'Tableau', 'mainwp-giweb' ); ?></option>
					</select>
				</div>
				<div class="mainwp-giweb-settings-card mainwp-giweb-settings-card--wide">
					<p class="description"><?php esc_html_e( 'Activez les widgets simple et/ou détaillé indépendamment dans MainWP > Réglages > Outils MainWP.', 'mainwp-giweb' ); ?></p>
				</div>
			</div>
		</section>

		<section class="mainwp-giweb-settings-section">
			<h3 class="mainwp-giweb-settings-section__title"><?php esc_html_e( 'Alertes mail', 'mainwp-giweb' ); ?></h3>
			<div class="mainwp-giweb-settings-grid">
				<div class="mainwp-giweb-settings-card mainwp-giweb-settings-card--wide">
					<label class="mainwp-giweb-settings-check">
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
					<p class="description"><?php esc_html_e( 'Les statistiques mail sont mises à jour lors de la synchronisation globale MainWP (Overview ou par site) et via « Synchroniser les statuts » dans GI-Toolkit Manager.', 'mainwp-giweb' ); ?></p>
				</div>
			</div>
		</section>

		<section class="mainwp-giweb-settings-section">
			<h3 class="mainwp-giweb-settings-section__title"><?php esc_html_e( 'Intégrations', 'mainwp-giweb' ); ?></h3>
			<div class="mainwp-giweb-settings-grid">
				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title">
						<?php esc_html_e( 'Matomo (multi-sites)', 'mainwp-giweb' ); ?>
						<?php if ( MainWP_GIWeb_Matomo::is_configured() ) : ?>
							<span class="mainwp-giweb-settings-badge mainwp-giweb-settings-badge--ok"><?php esc_html_e( 'Configuré', 'mainwp-giweb' ); ?></span>
						<?php endif; ?>
					</h4>
					<p>
						<label for="mainwp_giweb_matomo_url"><?php esc_html_e( 'URL Matomo', 'mainwp-giweb' ); ?></label><br />
						<input type="url" class="large-text code" id="mainwp_giweb_matomo_url" name="matomo_url" value="<?php echo esc_attr( (string) ( $settings['matomo_url'] ?? '' ) ); ?>" placeholder="https://matomo.example.com" />
					</p>
					<p>
						<label for="mainwp_giweb_matomo_api_token"><?php esc_html_e( 'Token API Matomo', 'mainwp-giweb' ); ?></label><br />
						<input type="password" class="large-text code" id="mainwp_giweb_matomo_api_token" name="matomo_api_token" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['matomo_api_token'] ) ? esc_attr__( '•••••••• (laisser vide pour conserver)', 'mainwp-giweb' ) : ''; ?>" />
					</p>
					<p class="description"><?php esc_html_e( 'Lors d’un déploiement, l’URL et le token sont injectés dans le bundle et chaque site enfant crée ou associe son site Matomo.', 'mainwp-giweb' ); ?></p>
				</div>

				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title">
						<?php esc_html_e( 'Uptime Kuma (multi-sites)', 'mainwp-giweb' ); ?>
						<?php if ( MainWP_GIWeb_Uptime_Kuma::is_configured() ) : ?>
							<span class="mainwp-giweb-settings-badge mainwp-giweb-settings-badge--ok"><?php esc_html_e( 'Configuré', 'mainwp-giweb' ); ?></span>
						<?php endif; ?>
					</h4>
					<p>
						<label for="mainwp_giweb_kuma_url"><?php esc_html_e( 'URL Uptime Kuma', 'mainwp-giweb' ); ?></label><br />
						<input type="url" class="large-text code" id="mainwp_giweb_kuma_url" name="kuma_url" value="<?php echo esc_attr( (string) ( $settings['kuma_url'] ?? '' ) ); ?>" placeholder="https://status.example.com" />
					</p>
					<p>
						<label for="mainwp_giweb_kuma_username"><?php esc_html_e( 'Utilisateur Uptime Kuma', 'mainwp-giweb' ); ?></label><br />
						<input type="text" class="regular-text" id="mainwp_giweb_kuma_username" name="kuma_username" value="<?php echo esc_attr( (string) ( $settings['kuma_username'] ?? '' ) ); ?>" autocomplete="username" />
					</p>
					<p>
						<label for="mainwp_giweb_kuma_password"><?php esc_html_e( 'Mot de passe Uptime Kuma', 'mainwp-giweb' ); ?></label><br />
						<input type="password" class="large-text code" id="mainwp_giweb_kuma_password" name="kuma_password" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['kuma_password'] ) ? esc_attr__( '•••••••• (laisser vide pour conserver)', 'mainwp-giweb' ) : ''; ?>" />
					</p>
					<p class="description"><?php esc_html_e( 'Uptime Kuma 2.3.x — identifiants administrateur (Socket.IO). Widget dashboard mis à jour toutes les 5 minutes.', 'mainwp-giweb' ); ?></p>
				</div>

				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title">
						<?php esc_html_e( 'Zabbix (monitoring)', 'mainwp-giweb' ); ?>
						<?php if ( MainWP_GIWeb_Zabbix::is_configured() ) : ?>
							<span class="mainwp-giweb-settings-badge mainwp-giweb-settings-badge--ok"><?php esc_html_e( 'Configuré', 'mainwp-giweb' ); ?></span>
						<?php endif; ?>
					</h4>
					<p>
						<label for="mainwp_giweb_zabbix_url"><?php esc_html_e( 'URL serveur Zabbix', 'mainwp-giweb' ); ?></label><br />
						<input type="url" class="large-text code" id="mainwp_giweb_zabbix_url" name="zabbix_url" value="<?php echo esc_attr( (string) ( $settings['zabbix_url'] ?? '' ) ); ?>" placeholder="https://zabbix.example.com" />
					</p>
					<p>
						<label for="mainwp_giweb_zabbix_api_token"><?php esc_html_e( 'Clé API Zabbix (Bearer)', 'mainwp-giweb' ); ?></label><br />
						<input type="password" class="large-text code" id="mainwp_giweb_zabbix_api_token" name="zabbix_api_token" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['zabbix_api_token'] ) ? esc_attr__( '•••••••• (laisser vide pour conserver)', 'mainwp-giweb' ) : ''; ?>" />
					</p>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="zabbix_auto_create" value="1" <?php checked( '1', $settings['zabbix_auto_create'] ?? '0' ); ?> />
						<?php esc_html_e( 'Créer automatiquement un host Zabbix à l’ajout d’un site MainWP', 'mainwp-giweb' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Host : WEB - domaine.tld — groupes WEBSITES et GENEVOIS-INFORMATIQUE — templates MainWP, PageSpeed et Website by Browser.', 'mainwp-giweb' ); ?></p>
					<p style="margin-top:12px;">
						<button type="button" class="button button-secondary" id="mainwp-giweb-zabbix-test" <?php disabled( ! MainWP_GIWeb_Zabbix::is_configured() ); ?>>
							<?php esc_html_e( 'Tester la connexion Zabbix', 'mainwp-giweb' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="mainwp-giweb-zabbix-provision-all" <?php disabled( ! MainWP_GIWeb_Zabbix::is_configured() ); ?>>
							<?php esc_html_e( 'Créer les hosts pour tous les sites', 'mainwp-giweb' ); ?>
						</button>
					</p>
					<p id="mainwp-giweb-zabbix-feedback" class="description" hidden></p>
				</div>

				<div class="mainwp-giweb-settings-card">
					<h4 class="mainwp-giweb-settings-card__title">
						<?php esc_html_e( 'Backup FTP', 'mainwp-giweb' ); ?>
						<?php if ( MainWP_GIWeb_Ftp_Backup::is_configured() ) : ?>
							<span class="mainwp-giweb-settings-badge mainwp-giweb-settings-badge--ok"><?php esc_html_e( 'Configuré', 'mainwp-giweb' ); ?></span>
						<?php endif; ?>
					</h4>
					<p>
						<label for="mainwp_giweb_ftp_host"><?php esc_html_e( 'Hôte FTP', 'mainwp-giweb' ); ?></label><br />
						<input type="text" class="regular-text code" id="mainwp_giweb_ftp_host" name="ftp_host" value="<?php echo esc_attr( (string) ( $settings['ftp_host'] ?? '' ) ); ?>" placeholder="ftp.example.com" autocomplete="off" />
					</p>
					<p>
						<label for="mainwp_giweb_ftp_port"><?php esc_html_e( 'Port', 'mainwp-giweb' ); ?></label><br />
						<input type="number" min="1" max="65535" step="1" class="small-text" id="mainwp_giweb_ftp_port" name="ftp_port" value="<?php echo esc_attr( (string) (int) ( $settings['ftp_port'] ?? 21 ) ); ?>" />
					</p>
					<p>
						<label for="mainwp_giweb_ftp_username"><?php esc_html_e( 'Utilisateur FTP', 'mainwp-giweb' ); ?></label><br />
						<input type="text" class="regular-text" id="mainwp_giweb_ftp_username" name="ftp_username" value="<?php echo esc_attr( (string) ( $settings['ftp_username'] ?? '' ) ); ?>" autocomplete="username" />
					</p>
					<p>
						<label for="mainwp_giweb_ftp_password"><?php esc_html_e( 'Mot de passe FTP', 'mainwp-giweb' ); ?></label><br />
						<input type="password" class="large-text code" id="mainwp_giweb_ftp_password" name="ftp_password" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['ftp_password'] ) ? esc_attr__( '•••••••• (laisser vide pour conserver)', 'mainwp-giweb' ) : ''; ?>" />
					</p>
					<p>
						<label for="mainwp_giweb_ftp_path"><?php esc_html_e( 'Chemin du dossier de backup', 'mainwp-giweb' ); ?></label><br />
						<input type="text" class="large-text code" id="mainwp_giweb_ftp_path" name="ftp_path" value="<?php echo esc_attr( (string) ( $settings['ftp_path'] ?? '/BACKUPS_WORDPRESS/%siteurl%' ) ); ?>" placeholder="/BACKUPS_WORDPRESS/%siteurl%" />
					</p>
					<p class="description"><?php esc_html_e( 'Variables : %siteurl% (nom de domaine sans www) et %sitename% (nom du site MainWP).', 'mainwp-giweb' ); ?></p>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="ftp_passive" value="1" <?php checked( '1', $settings['ftp_passive'] ?? '1' ); ?> />
						<?php esc_html_e( 'Mode passif FTP', 'mainwp-giweb' ); ?>
					</label>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="ftp_ssl" value="1" <?php checked( '1', $settings['ftp_ssl'] ?? '0' ); ?> />
						<?php esc_html_e( 'Connexion FTPS (FTP over SSL)', 'mainwp-giweb' ); ?>
					</label>
					<label class="mainwp-giweb-settings-check">
						<input type="checkbox" name="ftp_auto_on_deploy" value="1" <?php checked( '1', $settings['ftp_auto_on_deploy'] ?? '1' ); ?> />
						<?php esc_html_e( 'Créer ou vérifier le dossier FTP à chaque déploiement de configuration', 'mainwp-giweb' ); ?>
					</label>
					<p style="margin-top:12px;">
						<button type="button" class="button button-secondary" id="mainwp-giweb-ftp-test" <?php disabled( ! MainWP_GIWeb_Ftp_Backup::is_configured() ); ?>>
							<?php esc_html_e( 'Tester la connexion FTP', 'mainwp-giweb' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="mainwp-giweb-ftp-verify-all" <?php disabled( ! MainWP_GIWeb_Ftp_Backup::is_configured() ); ?>>
							<?php esc_html_e( 'Vérifier tous les sites', 'mainwp-giweb' ); ?>
						</button>
					</p>
					<p id="mainwp-giweb-ftp-feedback" class="description" hidden></p>
					<div id="mainwp-giweb-ftp-results" hidden></div>
				</div>
			</div>
		</section>
	</div>

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
