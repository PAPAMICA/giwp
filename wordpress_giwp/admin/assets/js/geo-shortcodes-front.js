(function () {
	'use strict';

	var cfg = window.giToolkitGeoShortcodes || {};
	if (!cfg.ajaxUrl || !cfg.action) {
		return;
	}

	function collectNodes() {
		return Array.prototype.slice.call(
			document.querySelectorAll('.gi-geo-var:not([data-gi-geo-ready="1"])')
		);
	}

	function uniqueKeys(nodes) {
		var keys = [];
		nodes.forEach(function (node) {
			var key = node.getAttribute('data-gi-geo-var');
			if (key && keys.indexOf(key) === -1) {
				keys.push(key);
			}
		});
		return keys;
	}

	function applyValues(nodes, values) {
		nodes.forEach(function (node) {
			var key = node.getAttribute('data-gi-geo-var');
			var fallback = node.getAttribute('data-gi-geo-default') || '';
			var value = Object.prototype.hasOwnProperty.call(values, key) ? values[key] : fallback;
			node.textContent = value;
			node.setAttribute('data-gi-geo-ready', '1');
			node.classList.add('gi-geo-var--ready');
		});
	}

	function resolve(nodes) {
		if (!nodes.length) {
			return;
		}

		var keys = uniqueKeys(nodes);
		if (!keys.length) {
			return;
		}

		var url = cfg.ajaxUrl +
			'?action=' + encodeURIComponent(cfg.action) +
			'&keys=' + encodeURIComponent(keys.join(',')) +
			'&_=' + Date.now();

		fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Cache-Control': 'no-cache'
			}
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) {
					applyValues(nodes, {});
					return;
				}
				applyValues(nodes, payload.data.values || {});
			})
			.catch(function () {
				applyValues(nodes, {});
			});
	}

	function boot() {
		resolve(collectNodes());
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	document.addEventListener('gi-toolkit-geo-shortcodes-refresh', boot);
})();
