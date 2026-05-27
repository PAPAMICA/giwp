( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMatomoDashboard || {};

	function drawChart( chartData ) {
		var canvas = document.getElementById( 'gi-matomo-visits-chart' );
		if ( ! canvas || ! chartData ) {
			return;
		}

		var labels = chartData.labels || [];
		var values = chartData.values || [];
		if ( ! labels.length ) {
			return;
		}

		var ctx = canvas.getContext( '2d' );
		var dpr = window.devicePixelRatio || 1;
		var width = canvas.parentElement ? canvas.parentElement.clientWidth : 800;
		var height = 280;

		canvas.width = width * dpr;
		canvas.height = height * dpr;
		canvas.style.width = width + 'px';
		canvas.style.height = height + 'px';
		ctx.scale( dpr, dpr );

		var pad = { top: 20, right: 16, bottom: 40, left: 48 };
		var plotW = width - pad.left - pad.right;
		var plotH = height - pad.top - pad.bottom;
		var max = Math.max.apply( null, values.concat( [ 1 ] ) );

		ctx.clearRect( 0, 0, width, height );
		ctx.fillStyle = '#f6f7f7';
		ctx.fillRect( pad.left, pad.top, plotW, plotH );

		ctx.strokeStyle = '#dcdcde';
		ctx.lineWidth = 1;
		for ( var g = 0; g <= 4; g++ ) {
			var y = pad.top + ( plotH * g ) / 4;
			ctx.beginPath();
			ctx.moveTo( pad.left, y );
			ctx.lineTo( pad.left + plotW, y );
			ctx.stroke();
		}

		var barW = plotW / values.length;
		var gap = Math.min( 8, barW * 0.2 );

		ctx.fillStyle = '#2271b1';
		values.forEach( function ( val, i ) {
			var bh = ( val / max ) * plotH;
			var x = pad.left + i * barW + gap / 2;
			var y = pad.top + plotH - bh;
			ctx.fillRect( x, y, barW - gap, bh );
		} );

		ctx.fillStyle = '#646970';
		ctx.font = '11px sans-serif';
		ctx.textAlign = 'center';
		labels.forEach( function ( label, i ) {
			if ( labels.length > 14 && i % 2 !== 0 ) {
				return;
			}
			var x = pad.left + i * barW + barW / 2;
			ctx.fillText( label, x, height - 12 );
		} );
	}

	function renderDashboard( html ) {
		$( '#gi-matomo-dashboard' ).html( html );
		var $json = $( '#gi-matomo-chart-data' );
		if ( $json.length ) {
			try {
				drawChart( JSON.parse( $json.text() ) );
			} catch ( e ) {
				// ignore
			}
		}
	}

	function loadPeriod( period ) {
		var $root = $( '#gi-matomo-dashboard' );
		if ( ! $root.length ) {
			return;
		}
		$root.addClass( 'is-loading' );

		$.post( cfg.ajaxUrl, {
			action: 'gi_toolkit_matomo_dashboard',
			nonce: cfg.nonce,
			period: period,
		} )
			.done( function ( res ) {
				if ( ! res.success || ! res.data || ! res.data.html ) {
					window.alert( ( res.data && res.data.message ) || cfg.i18n.error );
					return;
				}
				renderDashboard( res.data.html );
				$( '.gi-matomo-period-btn' ).removeClass( 'is-active' );
				$( '.gi-matomo-period-btn[data-period="' + period + '"]' ).addClass( 'is-active' );
				if ( window.history && window.history.replaceState ) {
					var url = new URL( window.location.href );
					url.searchParams.set( 'period', period );
					window.history.replaceState( {}, '', url.toString() );
				}
			} )
			.fail( function () {
				window.alert( cfg.i18n.error );
			} )
			.always( function () {
				$root.removeClass( 'is-loading' );
			} );
	}

	$( function () {
		var $json = $( '#gi-matomo-chart-data' );
		if ( $json.length ) {
			try {
				drawChart( JSON.parse( $json.text() ) );
			} catch ( e ) {
				// ignore
			}
		}

		$( document ).on( 'click', '.gi-matomo-period-btn[data-period]', function ( e ) {
			var period = $( this ).data( 'period' );
			if ( ! period ) {
				return;
			}
			e.preventDefault();
			loadPeriod( period );
		} );

		$( window ).on( 'resize', function () {
			var $json = $( '#gi-matomo-chart-data' );
			if ( $json.length ) {
				try {
					drawChart( JSON.parse( $json.text() ) );
				} catch ( err ) {
					// ignore
				}
			}
		} );
	} );
} )( jQuery );
