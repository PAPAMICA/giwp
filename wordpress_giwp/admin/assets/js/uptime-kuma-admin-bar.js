(function () {
	'use strict';

	var data = window.giToolkitUptimeKumaAdminBar;
	if (!data || !Array.isArray(data.bars)) {
		return;
	}

	var container = document.getElementById('gi-uptime-kuma-ab-bars');
	if (!container) {
		return;
	}

	data.bars.forEach(function (bar) {
		var el = document.createElement('span');
		el.className = 'gi-uptime-kuma-ab-bar level-' + (bar.level || 'unknown');
		el.title = (bar.uptime != null ? bar.uptime + '%' : '');
		container.appendChild(el);
	});
})();
