<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gi_toolkit_security = Gi_Toolkit_Security::get_options();
$gi_toolkit_audit    = Gi_Toolkit_Security::get_audit_log_path();
$gi_toolkit_audit_sz = file_exists( $gi_toolkit_audit ) ? size_format( (int) filesize( $gi_toolkit_audit ) ) : '0 B';
?>

<div class="gi-toolkit__body__sections__item hide-in-all gi-toolkit-security-panel" data-key="security">
	<div class="gi-toolkit__body__sections__item__top">
		<div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Sécurité globale', 'gi-toolkit' ); ?></div>
	</div>
	<div class="gi-toolkit__body__sections__item__top">
		<div class="gi-toolkit__body__sections__item__description">
			<?php esc_html_e( 'Ces options renforcent les contrôles d’accès et limitent l’activation accidentelle de modules sensibles.', 'gi-toolkit' ); ?>
		</div>
	</div>

	<?php
	$gi_toolkit_security_toggles = array(
		'ajax_strict_capabilities'       => __( 'Exiger une capability sur toutes les requêtes AJAX admin', 'gi-toolkit' ),
		'allow_high_risk_modules'        => __( 'Autoriser les modules à haut risque (Snippets, File Manager, Adminer, Search & Replace)', 'gi-toolkit' ),
		'audit_log_enabled'              => __( 'Journaliser les actions sensibles (fichier security-audit.log)', 'gi-toolkit' ),
		'search_replace_dry_run_default' => __( 'Search & Replace : mode simulation activé par défaut', 'gi-toolkit' ),
		'hide_sensitive_in_system_info'  => __( 'Masquer les clés API dans les infos système copiées', 'gi-toolkit' ),
		'confirm_module_activation'      => __( 'Demander une confirmation avant d’activer un module', 'gi-toolkit' ),
		'admin_dark_theme'               => __( 'Thème sombre sur la page des réglages GI-Toolkit', 'gi-toolkit' ),
	);
	foreach ( $gi_toolkit_security_toggles as $gi_toolkit_key => $gi_toolkit_label ) :
		$gi_toolkit_checked = ! empty( $gi_toolkit_security[ $gi_toolkit_key ] ) && '1' === $gi_toolkit_security[ $gi_toolkit_key ];
		?>
		<div class="gi-toolkit__body__sections__item__top toggle-settings">
			<div class="gi-toolkit__body__sections__item__bottom">
				<div class="gi-toolkit__body__sections__item__toggle">
					<label class="gi-toolkit__toggle">
						<input type="hidden" name="gi_toolkit_security_settings[<?php echo esc_attr( $gi_toolkit_key ); ?>]" value="0">
						<input type="checkbox" name="gi_toolkit_security_settings[<?php echo esc_attr( $gi_toolkit_key ); ?>]" value="1" <?php checked( $gi_toolkit_checked ); ?>>
						<span class="gi-toolkit__toggle__slider round"></span>
					</label>
				</div>
			</div>
			<div class="gi-toolkit__body__sections__item__title"><?php echo esc_html( $gi_toolkit_label ); ?></div>
		</div>
	<?php endforeach; ?>

	<div class="gi-toolkit__body__sections__item__space"></div>

	<div class="gi-toolkit__body__sections__item__top">
		<div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Journal d’audit', 'gi-toolkit' ); ?></div>
	</div>
	<div class="gi-toolkit__body__sections__item__description gi-toolkit-security-audit-meta">
		<code><?php echo esc_html( $gi_toolkit_audit ); ?></code>
		— <?php echo esc_html( $gi_toolkit_audit_sz ); ?>
	</div>
</div>
