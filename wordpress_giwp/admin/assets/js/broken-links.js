( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitBrokenLinks || {};

	function post( data ) {
		data = data || {};
		data.action = 'gi_toolkit_broken_links_scan_batch';
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function setProgress( percent, message ) {
		var $wrap = $( '.gi-broken-links-scan-progress-wrap' );
		var $bar = $( '#gi-broken-links-scan-progress' );
		var $pct = $( '#gi-broken-links-scan-percent' );
		var $status = $( '#gi-broken-links-scan-status' );

		$wrap.prop( 'hidden', false );
		$bar.val( percent );
		$pct.text( percent + '%' );
		$status.text( message || '' );
	}

	function showError( message ) {
		setProgress( 0, message || cfg.i18n.error );
		$( '#gi-broken-links-scan-status' ).addClass( 'is-error' );
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

	$( '#gi-broken-links-start-scan' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$( '#gi-broken-links-scan-status' ).removeClass( 'is-error' ).text( cfg.i18n.scanning );
		setProgress( 0, cfg.i18n.scanning );

		post( { start: 1 } )
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
} )( jQuery );
