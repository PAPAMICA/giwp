( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitUnusedMedia || {};

	function post( data ) {
		data.action = 'gi_toolkit_unused_media_scan_batch';
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function runBatch() {
		return post( {} ).done( function ( res ) {
			if ( ! res.success ) {
				$( '#gi-unused-media-scan-status' ).text( res.data && res.data.message ? res.data.message : cfg.i18n.error );
				return;
			}
			var d = res.data;
			$( '#gi-unused-media-scan-progress' ).val( d.percent || 0 );
			$( '#gi-unused-media-scan-status' ).text( d.message || '' );
			if ( ! d.done ) {
				return runBatch();
			}
			window.location.reload();
		} );
	}

	$( document ).on( 'mouseenter', '.gi-unused-media-preview', function ( e ) {
		var url = $( this ).data( 'preview' );
		if ( ! url ) {
			return;
		}
		var $el = $( this )[0];
		$el.style.setProperty( '--gi-unused-preview-url', 'url("' + url + '")' );
		var x = Math.min( e.clientX + 16, window.innerWidth - 340 );
		var y = Math.min( e.clientY + 16, window.innerHeight - 340 );
		$el.style.setProperty( '--gi-unused-preview-x', x + 'px' );
		$el.style.setProperty( '--gi-unused-preview-y', y + 'px' );
	} );

	$( document ).on( 'mousemove', '.gi-unused-media-preview', function ( e ) {
		var $el = $( this )[0];
		var x = Math.min( e.clientX + 16, window.innerWidth - 340 );
		var y = Math.min( e.clientY + 16, window.innerHeight - 340 );
		$el.style.setProperty( '--gi-unused-preview-x', x + 'px' );
		$el.style.setProperty( '--gi-unused-preview-y', y + 'px' );
	} );

	$( '#gi-unused-media-start-scan' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$( '#gi-unused-media-scan-progress' ).val( 0 );
		$( '#gi-unused-media-scan-status' ).text( cfg.i18n.scanning );
		post( { reset: 1 } )
			.done( function () {
				return runBatch();
			} )
			.fail( function () {
				$( '#gi-unused-media-scan-status' ).text( cfg.i18n.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( '#doaction, #doaction2' ).on( 'click', function () {
		var action = $( this ).prev( 'select' ).val();
		if ( 'delete' === action ) {
			return window.confirm( cfg.i18n.confirmDelete );
		}
	} );
} )( jQuery );
