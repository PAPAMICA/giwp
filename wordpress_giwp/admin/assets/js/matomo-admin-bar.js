( function () {
	'use strict';

	var cfg = window.giToolkitMatomoAdminBar || {};
	var sparkline = cfg.sparkline || {};
	var flyout = cfg.flyout || {};
	var i18n = cfg.i18n || {};

	function formatCount( value ) {
		var n = Number( value ) || 0;
		try {
			return n.toLocaleString( undefined, { maximumFractionDigits: 0 } );
		} catch ( e ) {
			return String( n );
		}
	}

	function buildChartOptions( showAxes ) {
		var values = sparkline.values || [];
		var maxVal = values.length ? Math.max.apply( null, values ) : 0;
		var yMax = Math.max( 5, Math.ceil( maxVal * 1.15 ) );

		return {
			responsive: false,
			maintainAspectRatio: false,
			animation: false,
			layout: {
				padding: showAxes ? { left: 0, right: 4, top: 2, bottom: 0 } : { top: 0, bottom: 0, left: 0, right: 0 },
			},
			plugins: {
				legend: { display: false },
				tooltip: {
					enabled: showAxes,
					callbacks: {
						label: function ( context ) {
							return formatCount( context.parsed.y );
						},
					},
				},
			},
			scales: showAxes
				? {
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
				}
				: {
					x: { display: false },
					y: { display: false },
				},
		};
	}

	function initSparkline() {
		var canvas = document.getElementById( 'gi-matomo-ab-chart' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		var values = sparkline.values || [];
		var labels = sparkline.labels || [];
		if ( ! values.length ) {
			return;
		}

		new window.Chart( canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						data: values,
						borderColor: 'rgba(255, 255, 255, 0.95)',
						backgroundColor: 'rgba(114, 174, 230, 0.25)',
						borderWidth: 2,
						fill: true,
						pointRadius: 0,
						pointHoverRadius: 3,
						tension: 0.35,
					},
				],
			},
			options: buildChartOptions( false ),
		} );
	}

	function initFlyoutChart() {
		var canvas = document.getElementById( 'gi-matomo-ab-chart-detail' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		var labels = flyout.labels || [];
		var visits = flyout.visits || [];
		if ( ! visits.length ) {
			return;
		}

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

	function init() {
		initSparkline();
		initFlyoutChart();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
