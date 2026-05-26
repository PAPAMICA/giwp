/**
 * MainWP GI-Web Extension — admin scripts
 */
( function ( $ ) {
	'use strict';
	$( document ).ready( function () {
		$( '.mainwp-giweb-wrap form' ).on( 'submit', function () {
			var $btn = $( this ).find( 'button[type="submit"].button-primary' );
			if ( $btn.length ) {
				$btn.prop( 'disabled', true );
			}
		} );
	} );
}( jQuery ) );
