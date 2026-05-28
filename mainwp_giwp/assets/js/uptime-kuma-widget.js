(function () {
	'use strict';

	document.querySelectorAll('.mainwp-giweb-uptime-kuma-mini-bars').forEach(function (wrap) {
		var raw = wrap.getAttribute('data-bars');
		if (!raw) {
			return;
		}
		var bars;
		try {
			bars = JSON.parse(raw);
		} catch (e) {
			return;
		}
		if (!Array.isArray(bars)) {
			return;
		}
		bars.forEach(function (bar) {
			var el = document.createElement('span');
			el.className = 'bar level-' + (bar.level || 'unknown');
			wrap.appendChild(el);
		});
	});
})();
