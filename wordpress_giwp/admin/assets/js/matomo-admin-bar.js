( function () {
	'use strict';

	var cfg = window.giToolkitMatomoAdminBar || {};
	var sparkline = cfg.sparkline || {};

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
		document.addEventListener( 'DOMContentLoaded', initSparkline );
	} else {
		initSparkline();
	}
} )();
