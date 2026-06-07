(function () {
	'use strict';

	window.giToolkitInitUptimeKumaChart = function (cfg) {
		cfg = cfg || {};
		var canvasId = cfg.canvasId || 'gi-uptime-kuma-ping-chart';
		var canvas = document.getElementById(canvasId);

		if (!canvas || !window.Chart || !cfg.chart || !cfg.chart.data || !cfg.chart.data.length) {
			return null;
		}

		return new window.Chart(canvas, {
			type: 'line',
			data: {
				labels: cfg.chart.labels || [],
				datasets: [
					{
						label: (cfg.i18n && cfg.i18n.pingLabel) || 'Ping (ms)',
						data: cfg.chart.data || [],
						borderColor: '#22c55e',
						backgroundColor: 'rgba(34, 197, 94, 0.15)',
						borderWidth: 2,
						fill: true,
						tension: 0.25,
						pointRadius: 0,
						pointHitRadius: 8
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					mode: 'index',
					intersect: false
				},
				plugins: {
					legend: {
						display: true,
						position: 'top',
						align: 'start',
						labels: {
							boxWidth: 12,
							usePointStyle: true
						}
					},
					tooltip: {
						callbacks: {
							label: function (ctx) {
								return ctx.parsed.y + ' ms';
							}
						}
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: {
							maxTicksLimit: 8,
							font: { size: 11 }
						}
					},
					y: {
						beginAtZero: true,
						title: {
							display: true,
							text: (cfg.i18n && cfg.i18n.pingLabel) || 'ms',
							font: { size: 11 }
						},
						ticks: { font: { size: 11 } }
					}
				}
			}
		});
	};

	var bootCfg = window.giToolkitUptimeKumaDashboard || {};
	if (bootCfg.chart && bootCfg.chart.data && bootCfg.chart.data.length) {
		window.giToolkitInitUptimeKumaChart(bootCfg);
	}
})();
