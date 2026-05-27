/**
 * Widget MainWP — graphique mails réseau (7 jours).
 */
( function () {
	'use strict';

	var cfg = window.mainwpGiwebMailWidget || {};
	var network = cfg.network || {};
	var canvas = document.getElementById( 'mainwp-giweb-mail-network-chart' );

	if ( ! canvas || typeof window.Chart === 'undefined' ) {
		return;
	}

	var labels = network.chart_labels || [];
	var sent = network.chart_sent || [];
	var failed = network.chart_failed || [];

	if ( ! labels.length ) {
		return;
	}

	// eslint-disable-next-line no-new
	new window.Chart( canvas, {
		type: 'bar',
		data: {
			labels: labels,
			datasets: [
				{
					label: ( cfg.i18n && cfg.i18n.sent ) || 'Envoyés',
					data: sent,
					backgroundColor: 'rgba(49, 107, 255, 0.65)',
					stack: 'mail',
				},
				{
					label: ( cfg.i18n && cfg.i18n.failed ) || 'Échecs',
					data: failed,
					backgroundColor: 'rgba(220, 38, 38, 0.75)',
					stack: 'mail',
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: true,
			plugins: {
				legend: {
					position: 'bottom',
					labels: { boxWidth: 12, font: { size: 11 } },
				},
			},
			scales: {
				x: { stacked: true, ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } } },
				y: { stacked: true, beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
			},
		},
	} );
} )();
