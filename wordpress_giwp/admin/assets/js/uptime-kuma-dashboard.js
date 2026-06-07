(function () {
	'use strict';

	function lineChartAnimations() {
		return {
			animation: {
				duration: 1500,
				easing: 'easeOutQuart'
			},
			animations: {
				x: {
					type: 'number',
					easing: 'easeOutQuart',
					duration: 1500,
					from: NaN,
					delay: function (ctx) {
						return ctx.type === 'data' ? ctx.dataIndex * 14 : 0;
					}
				},
				y: {
					type: 'number',
					easing: 'easeOutCubic',
					duration: 1300,
					from: function (ctx) {
						if (!ctx.chart || !ctx.chart.scales || !ctx.chart.scales.y) {
							return undefined;
						}
						return ctx.chart.scales.y.getPixelForValue(0);
					}
				}
			}
		};
	}

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
			options: Object.assign(
				{
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
				},
				lineChartAnimations()
			)
		});
	};

	window.giToolkitAnimateUptimeDashboard = function (root) {
		if (!root) {
			return;
		}

		var dashboard = root.querySelector('.gi-uptime-kuma-dashboard--animate');
		if (!dashboard) {
			dashboard = root.querySelector('.gi-uptime-kuma-dashboard');
		}
		if (!dashboard) {
			return;
		}

		dashboard.classList.add('gi-uptime-kuma-dashboard--ready');

		dashboard.querySelectorAll('.gi-uptime-kuma-kpi').forEach(function (kpi, index) {
			kpi.style.setProperty('--gi-animate-i', String(index));
			kpi.classList.add('gi-uptime-kuma-animate-in');
		});

		dashboard.querySelectorAll('.gi-uptime-kuma-status-badge').forEach(function (badge) {
			badge.classList.add('gi-uptime-kuma-animate-pop');
		});

		var chartPanel = dashboard.querySelector('.gi-uptime-kuma-chart-panel');
		if (chartPanel) {
			chartPanel.classList.add('gi-uptime-kuma-animate-in');
		}
	};

	var bootCfg = window.giToolkitUptimeKumaDashboard || {};
	if (bootCfg.chart && bootCfg.chart.data && bootCfg.chart.data.length) {
		window.giToolkitInitUptimeKumaChart(bootCfg);
	}
})();
