( function () {
	'use strict';

	var cfg = window.giToolkitUptimeKumaAdminBar || {};
	var chartData = cfg.chart || {};

	function initPingChart() {
		var canvas = document.getElementById( 'gi-uptime-kuma-ab-chart' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		var values = chartData.values || [];
		var labels = chartData.labels || [];
		if ( ! values.length ) {
			return;
		}

		var maxVal = Math.max.apply( null, values );
		var yMax = Math.max( 50, Math.ceil( maxVal * 1.15 / 10 ) * 10 );

		new window.Chart( canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						data: values,
						borderColor: 'rgba(74, 222, 128, 0.95)',
						backgroundColor: 'rgba(34, 197, 94, 0.18)',
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
							label: function ( ctx ) {
								return ctx.parsed.y + ' ms';
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
						grid: {
							color: 'rgba(255, 255, 255, 0.08)',
						},
						ticks: {
							maxTicksLimit: 3,
							color: 'rgba(167, 170, 173, 0.95)',
							font: { size: 9 },
							callback: function ( value ) {
								return value + ' ms';
							},
						},
						border: { display: false },
					},
				},
			},
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initPingChart );
	} else {
		initPingChart();
	}
} )();
