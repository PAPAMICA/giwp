( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitUnusedMedia || {};

	function post( data ) {
		data = data || {};
		data.action = 'gi_toolkit_unused_media_scan_batch';
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function setProgress( percent, message ) {
		var $wrap = $( '.gi-unused-media-scan-progress-wrap' );
		var $bar = $( '#gi-unused-media-scan-progress' );
		var $pct = $( '#gi-unused-media-scan-percent' );
		var $status = $( '#gi-unused-media-scan-status' );

		$wrap.prop( 'hidden', false );
		$bar.val( percent );
		$pct.text( percent + '%' );
		$status.text( message || '' ).removeClass( 'is-error' );
	}

	function showError( message ) {
		var $wrap = $( '.gi-unused-media-scan-progress-wrap' );
		$wrap.prop( 'hidden', false );
		$( '#gi-unused-media-scan-status' ).addClass( 'is-error' ).text( message || cfg.i18n.error );
	}

	function runBatch() {
		return post( {} ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				showError( res && res.data && res.data.message ? res.data.message : cfg.i18n.error );
				return;
			}
			var d = res.data || {};
			setProgress( d.percent || 0, d.message || '' );
			if ( ! d.done ) {
				return runBatch();
			}
			setProgress( 100, d.message || cfg.i18n.done || 'Terminé.' );
			window.setTimeout( function () {
				window.location.reload();
			}, 600 );
		} );
	}

	$( document ).on( 'mouseenter', '.gi-unused-media-preview', function ( e ) {
		var url = $( this ).data( 'preview' );
		if ( ! url ) {
			return;
		}
		var el = this;
		el.style.setProperty( '--gi-unused-preview-url', 'url("' + String( url ).replace( /"/g, '\\"' ) + '")' );
		var x = Math.min( e.clientX + 16, window.innerWidth - 340 );
		var y = Math.min( e.clientY + 16, window.innerHeight - 340 );
		el.style.setProperty( '--gi-unused-preview-x', x + 'px' );
		el.style.setProperty( '--gi-unused-preview-y', y + 'px' );
	} );

	$( document ).on( 'mousemove', '.gi-unused-media-preview', function ( e ) {
		var el = this;
		var x = Math.min( e.clientX + 16, window.innerWidth - 340 );
		var y = Math.min( e.clientY + 16, window.innerHeight - 340 );
		el.style.setProperty( '--gi-unused-preview-x', x + 'px' );
		el.style.setProperty( '--gi-unused-preview-y', y + 'px' );
	} );

	$( '#gi-unused-media-start-scan' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		setProgress( 0, cfg.i18n.scanning );

		post( { reset: 1 } )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					showError( res && res.data && res.data.message ? res.data.message : cfg.i18n.error );
					return;
				}
				return runBatch();
			} )
			.fail( function ( xhr ) {
				var msg = cfg.i18n.error;
				if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
					msg = xhr.responseJSON.data.message;
				} else if ( xhr.status ) {
					msg = cfg.i18n.error + ' (HTTP ' + xhr.status + ')';
				}
				showError( msg );
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
