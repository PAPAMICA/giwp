( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitBrokenLinks || {};

	function post( data ) {
		data.action = 'gi_toolkit_broken_links_scan_batch';
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function runBatch() {
		return post( {} ).done( function ( res ) {
			if ( ! res.success ) {
				$( '#gi-broken-links-scan-status' ).text( res.data && res.data.message ? res.data.message : cfg.i18n.error );
				return;
			}
			var d = res.data;
			$( '#gi-broken-links-scan-progress' ).val( d.percent || 0 );
			$( '#gi-broken-links-scan-status' ).text( d.message || '' );
			if ( ! d.done ) {
				return runBatch();
			}
			window.location.reload();
		} );
	}

	$( '#gi-broken-links-start-scan' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$( '#gi-broken-links-scan-progress' ).val( 0 );
		$( '#gi-broken-links-scan-status' ).text( cfg.i18n.scanning );
		post( { start: 1 } )
			.done( function () {
				return runBatch();
			} )
			.fail( function () {
				$( '#gi-broken-links-scan-status' ).text( cfg.i18n.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );
} )( jQuery );
