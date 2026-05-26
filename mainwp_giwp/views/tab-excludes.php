<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$override_site = isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0;
?>
<h2><?php esc_html_e( 'Exclusions par site', 'mainwp-giweb' ); ?></h2>
<p class="description"><?php esc_html_e( 'Les modules exclus conservent leur état local lors d’un déploiement. Les modules « options exclues » gardent leurs réglages locaux.', 'mainwp-giweb' ); ?></p>

<form method="get">
	<input type="hidden" name="page" value="<?php echo esc_attr( $current_page ); ?>" />
	<input type="hidden" name="tab" value="excludes" />
	<select name="site_id" onchange="this.form.submit()">
		<option value=""><?php esc_html_e( 'Choisir un site…', 'mainwp-giweb' ); ?></option>
		<?php foreach ( $websites as $site ) :
			$sid = (int) ( $site->id ?? 0 );
			?>
			<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $override_site, $sid ); ?>><?php echo esc_html( $site->name ?? $site->url ?? $sid ); ?></option>
		<?php endforeach; ?>
	</select>
</form>

<?php if ( $override_site ) :
	$ov = MainWP_GIWeb_Overrides::get( $override_site );
	?>
	<form method="post">
		<?php wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' ); ?>
		<input type="hidden" name="mainwp_giweb_action" value="save_overrides" />
		<input type="hidden" name="override_site_id" value="<?php echo esc_attr( $override_site ); ?>" />

		<h3><?php echo esc_html( MainWP_GIWeb::site_name( $override_site, $websites ) ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Module', 'mainwp-giweb' ); ?></th><th><?php esc_html_e( 'Exclure activation', 'mainwp-giweb' ); ?></th><th><?php esc_html_e( 'Exclure options', 'mainwp-giweb' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $modules as $class => $mod ) : ?>
				<tr>
					<td><?php echo esc_html( $mod['name'] ?? $class ); ?></td>
					<td><input type="checkbox" name="excluded_modules[]" value="<?php echo esc_attr( $class ); ?>" <?php checked( in_array( $class, $ov['excluded_modules'], true ) ); ?> /></td>
					<td><input type="checkbox" name="excluded_option_modules[]" value="<?php echo esc_attr( $class ); ?>" <?php checked( in_array( $class, $ov['excluded_option_modules'], true ) ); ?> /></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer les exclusions', 'mainwp-giweb' ); ?></button></p>
	</form>
<?php endif; ?>
