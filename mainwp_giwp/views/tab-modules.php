<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bundle_modules = $working_bundle['modules'] ?? array();
?>
<h2><?php esc_html_e( 'Modules (configuration de travail)', 'mainwp-giweb' ); ?></h2>

<?php if ( in_array( 'Gi_Toolkit_Code_Snippets', $high, true ) ) : ?>
	<div class="mainwp-giweb-notice-warn">
		<?php esc_html_e( 'Attention : certains modules sont à haut risque (Snippets, File Manager, Adminer, Search & Replace). Le déploiement les activera sur les sites ciblés.', 'mainwp-giweb' ); ?>
	</div>
<?php endif; ?>

<?php if ( empty( $bundle_modules ) && empty( $modules ) ) : ?>
	<p><?php esc_html_e( 'Chargez d’abord une configuration depuis l’onglet Vue d’ensemble.', 'mainwp-giweb' ); ?></p>
<?php else : ?>
	<?php foreach ( $groups as $group_key => $group_data ) :
		if ( ! empty( $group_data['exception'] ) ) {
			continue;
		}
		$has = false;
		foreach ( $modules as $class => $mod ) {
			if ( ( $mod['group'] ?? '' ) === $group_key ) {
				$has = true;
				break;
			}
		}
		if ( ! $has ) {
			continue;
		}
		?>
		<h3><?php echo esc_html( $group_data['name'] ?? $group_key ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Module', 'mainwp-giweb' ); ?></th><th><?php esc_html_e( 'Actif', 'mainwp-giweb' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $modules as $class => $mod ) :
				if ( ( $mod['group'] ?? '' ) !== $group_key ) {
					continue;
				}
				$active = ( $bundle_modules[ $class ]['active'] ?? '0' ) === '1';
				$is_risk = in_array( $class, $high, true );
				?>
				<tr<?php echo $is_risk ? ' class="mainwp-giweb-risk"' : ''; ?>>
					<td>
						<?php echo esc_html( $mod['name'] ?? $class ); ?>
						<?php if ( $is_risk ) : ?><span class="mainwp-giweb-risk-tag">⚠</span><?php endif; ?>
					</td>
					<td>
						<form method="post">
							<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
							<input type="hidden" name="mainwp_giweb_action" value="toggle_module_working" />
							<input type="hidden" name="module_class" value="<?php echo esc_attr( $class ); ?>" />
							<label>
								<input type="checkbox" name="module_active" value="1" <?php checked( $active ); ?> onchange="this.form.submit()" />
							</label>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>
<?php endif; ?>
