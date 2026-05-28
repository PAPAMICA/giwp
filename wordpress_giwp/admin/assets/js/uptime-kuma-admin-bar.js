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
				plugins: {
					legend: { display: false },
					tooltip: { enabled: false },
				},
				scales: {
					x: { display: false },
					y: { display: false },
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
