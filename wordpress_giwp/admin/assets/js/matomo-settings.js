( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMatomo || {};

	function post( action, extra ) {
		var data = {
			action: action,
			nonce: cfg.nonce,
			matomo_url: $( '#matomo_url' ).val(),
			api_token: $( '#api_token' ).val(),
		};
		return $.post( cfg.ajaxUrl, $.extend( data, extra || {} ) );
	}

	function toggleManualRow() {
		var manual = $( 'input[name="track_mode"]:checked' ).val() === 'manual';
		$( 'body' ).toggleClass( 'gi-matomo-track-manual', manual );
	}

	$( 'input[name="track_mode"]' ).on( 'change', toggleManualRow );
	toggleManualRow();

	$( '#gi-matomo-test-connection' ).on( 'click', function () {
		var $btn = $( this );
		var $out = $( '#gi-matomo-test-result' );
		$btn.prop( 'disabled', true );
		$out.removeClass( 'is-ok is-err' ).text( cfg.i18n.testing || '…' );

		post( 'gi_toolkit_matomo_test_connection' )
			.done( function ( res ) {
				if ( res.success ) {
					$out.addClass( 'is-ok' ).text( res.data.message || 'OK' );
				} else {
					$out.addClass( 'is-err' ).text( ( res.data && res.data.message ) || cfg.i18n.error );
				}
			} )
			.fail( function () {
				$out.addClass( 'is-err' ).text( cfg.i18n.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( '#gi-matomo-sync-site' ).on( 'click', function () {
		var $btn = $( this );
		var $out = $( '#gi-matomo-sync-result' );
		$btn.prop( 'disabled', true );
		$out.removeClass( 'is-ok is-err' ).text( cfg.i18n.syncing || '…' );

		post( 'gi_toolkit_matomo_sync_site' )
			.done( function ( res ) {
				if ( res.success && res.data ) {
					$out.addClass( 'is-ok' ).text( res.data.message || 'OK' );
					if ( res.data.site_id ) {
						$( '#site_id' ).val( res.data.site_id );
					}
				} else {
					$out.addClass( 'is-err' ).text( ( res.data && res.data.message ) || cfg.i18n.error );
				}
			} )
			.fail( function () {
				$out.addClass( 'is-err' ).text( cfg.i18n.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );
} )( jQuery );
