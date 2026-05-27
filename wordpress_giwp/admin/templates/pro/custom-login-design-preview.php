<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url = apply_filters( 'gi_toolkit_login_preview_logo_url', '' );
if ( '' === $logo_url && function_exists( 'get_site_icon_url' ) ) {
	$logo_url = (string) get_site_icon_url( 512 );
}
if ( '' === $logo_url ) {
	$logo_url = includes_url( 'images/w-logo-blue.png' );
}
?>
<div class="gi-toolkit-login-preview" id="gi-toolkit-login-preview">
	<div class="gi-toolkit-login-preview__toolbar">
		<strong class="gi-toolkit-login-preview__title"><?php esc_html_e( 'Aperçu', 'gi-toolkit' ); ?></strong>
		<span class="gi-toolkit-login-preview__hint description"><?php esc_html_e( 'Mis à jour en direct lors de la saisie.', 'gi-toolkit' ); ?></span>
	</div>
	<div class="gi-toolkit-login-preview__frame-wrap">
		<iframe
			id="gi-toolkit-login-preview-frame"
			class="gi-toolkit-login-preview__frame"
			title="<?php esc_attr_e( 'Aperçu de la page de connexion', 'gi-toolkit' ); ?>"
			sandbox="allow-same-origin"
			loading="lazy"
			data-logo-url="<?php echo esc_url( $logo_url ); ?>"
		></iframe>
	</div>
</div>
