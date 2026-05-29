( function () {
	'use strict';

	var cfg = window.giToolkitMatomoAdminBar || {};
	var flyout = cfg.flyout || {};
	var i18n = cfg.i18n || {};
	var chartInitialized = false;

	function formatCount( value ) {
		var n = Number( value ) || 0;
		try {
			return n.toLocaleString( undefined, { maximumFractionDigits: 0 } );
		} catch ( e ) {
			return String( n );
		}
	}

	function initFlyoutChart() {
		if ( chartInitialized ) {
			return;
		}

		var canvas = document.getElementById( 'gi-matomo-ab-chart-detail' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		var labels = flyout.labels || [];
		var visits = flyout.visits || [];
		if ( ! visits.length ) {
			return;
		}

		chartInitialized = true;

		var flyoutMax = Math.max.apply( null, visits );
		var yMax = Math.max( 5, Math.ceil( flyoutMax * 1.15 ) );

		new window.Chart( canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: i18n.visits || 'Visites',
						data: visits,
						borderColor: 'rgba(114, 174, 230, 0.95)',
						backgroundColor: 'rgba(114, 174, 230, 0.18)',
						borderWidth: 2,
						fill: true,
						pointRadius: 0,
						pointHoverRadius: 3,
						tension: 0.35,
					},
				],
			},
			options: {
				responsive: false,
				maintainAspectRatio: false,
				animation: false,
				layout: {
					padding: { left: 0, right: 4, top: 2, bottom: 0 },
				},
				plugins: {
					legend: { display: false },
					tooltip: {
						enabled: true,
						callbacks: {
							afterLabel: function ( context ) {
								var idx = context.dataIndex;
								var unique = flyout.unique || [];
								var actions = flyout.actions || [];
								var lines = [];
								if ( unique[ idx ] != null ) {
									lines.push( ( i18n.unique || 'Visiteurs uniques' ) + ': ' + formatCount( unique[ idx ] ) );
								}
								if ( actions[ idx ] != null ) {
									lines.push( ( i18n.actions || 'Pages vues' ) + ': ' + formatCount( actions[ idx ] ) );
								}
								return lines;
							},
							label: function ( context ) {
								return ( i18n.visits || 'Visites' ) + ': ' + formatCount( context.parsed.y );
							},
						},
					},
				},
				scales: {
					x: {
						display: true,
						grid: { display: false },
						ticks: {
							maxTicksLimit: 4,
							maxRotation: 0,
							color: 'rgba(167, 170, 173, 0.95)',
							font: { size: 9 },
						},
						border: { display: false },
					},
					y: {
						display: true,
						min: 0,
						max: yMax,
						grid: { color: 'rgba(255, 255, 255, 0.08)' },
						ticks: {
							maxTicksLimit: 3,
							color: 'rgba(167, 170, 173, 0.95)',
							font: { size: 9 },
							callback: function ( value ) {
								return formatCount( value );
							},
						},
						border: { display: false },
					},
				},
			},
		} );
	}

	function loadChartJs( callback ) {
		if ( typeof window.Chart !== 'undefined' ) {
			callback();
			return;
		}

		var url = cfg.chartJsUrl;
		if ( ! url ) {
			return;
		}

		var existing = document.querySelector( 'script[data-gi-chartjs="1"]' );
		if ( existing ) {
			existing.addEventListener( 'load', callback, { once: true } );
			return;
		}

		var script = document.createElement( 'script' );
		script.src = url;
		script.async = true;
		script.setAttribute( 'data-gi-chartjs', '1' );
		script.addEventListener( 'load', callback, { once: true } );
		document.head.appendChild( script );
	}

	function bindLazyChart() {
		var menu = document.getElementById( 'wp-admin-bar-gi-matomo-toolbar-stats' );
		if ( ! menu ) {
			return;
		}

		menu.addEventListener(
			'mouseenter',
			function () {
				loadChartJs( initFlyoutChart );
			},
			{ once: true }
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bindLazyChart );
	} else {
		bindLazyChart();
	}
} )();
