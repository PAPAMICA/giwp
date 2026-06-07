( function ( $ ) {
	'use strict';

	function syncOnboardingFields() {
		var $install = $( '#mainwp-giweb-install-toggle input[type="checkbox"]' );
		var $profile = $( '#mainwp-giweb-profile-toggle input[type="checkbox"]' );
		var $template = $( '#mainwp_giweb_template_id' );

		$( '#mainwp_giweb_install_hidden' ).val( $install.length && $install.is( ':checked' ) ? '1' : '0' );
		$( '#mainwp_giweb_apply_hidden' ).val( $profile.length && $profile.is( ':checked' ) ? '1' : '0' );
		$( '#mainwp_giweb_template_hidden' ).val( $template.length ? ( $template.val() || '' ) : '' );
	}

	$( function () {
		if ( ! $( '#mainwp-giweb-install-hidden-wrap' ).length ) {
			return;
		}

		syncOnboardingFields();

		$( document ).on(
			'change',
			'#mainwp-giweb-install-toggle input, #mainwp-giweb-profile-toggle input, #mainwp_giweb_template_id',
			syncOnboardingFields
		);

		$( document ).on( 'click', '#mainwp_managesites_add', function () {
			syncOnboardingFields();
		} );
	} );
}( jQuery ) );
