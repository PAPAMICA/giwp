(function () {
	'use strict';

	var stripTipEl = null;

	function getLocale() {
		var lang = document.documentElement.lang || 'fr';
		return lang.indexOf('fr') === 0 ? 'fr' : lang;
	}

	function formatRelativeSync(unixSeconds) {
		var ts = parseInt(unixSeconds, 10) * 1000;
		if (!ts) {
			return '';
		}

		var diffSec = Math.max(0, Math.floor((Date.now() - ts) / 1000));
		if (diffSec < 45) {
			return 'Sync à l\u2019instant';
		}

		var units = [
			['year', 31536000],
			['month', 2592000],
			['week', 604800],
			['day', 86400],
			['hour', 3600],
			['minute', 60]
		];
		var locale = getLocale();

		if (typeof Intl !== 'undefined' && Intl.RelativeTimeFormat) {
			var rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
			var i;
			for (i = 0; i < units.length; i += 1) {
				var unit = units[i][0];
				var secs = units[i][1];
				if (diffSec >= secs) {
					return 'Sync ' + rtf.format(Math.round(diffSec / secs) * -1, unit);
				}
			}
		}

		var mins = Math.floor(diffSec / 60);
		if (mins < 60) {
			return 'Sync il y a ' + mins + ' min';
		}
		return 'Sync il y a ' + Math.floor(mins / 60) + ' h';
	}

	function formatLocalTooltip(unixSeconds) {
		var ts = parseInt(unixSeconds, 10) * 1000;
		if (!ts) {
			return '';
		}
		try {
			return new Date(ts).toLocaleString(getLocale(), {
				dateStyle: 'medium',
				timeStyle: 'short'
			});
		} catch (e) {
			return new Date(ts).toLocaleString();
		}
	}

	function refreshSyncLabels(root) {
		(root || document).querySelectorAll('.giweb-gw-sync[data-sync-ts]').forEach(function (el) {
			var ts = el.getAttribute('data-sync-ts');
			var label = formatRelativeSync(ts);
			if (label) {
				el.textContent = label;
				el.setAttribute('title', formatLocalTooltip(ts));
			}
		});
	}

	function ensureStripTip() {
		if (!stripTipEl) {
			stripTipEl = document.createElement('div');
			stripTipEl.className = 'giweb-gw-strip-tip';
			stripTipEl.hidden = true;
			document.body.appendChild(stripTipEl);
		}
		return stripTipEl;
	}

	function hideStripTip() {
		if (stripTipEl) {
			stripTipEl.hidden = true;
		}
	}

	function showStripTip(text, x, y) {
		var tip = ensureStripTip();
		tip.textContent = text;
		tip.hidden = false;
		tip.style.left = Math.max(8, x + 12) + 'px';
		tip.style.top = Math.max(8, y + 12) + 'px';
	}

	function bindStripTooltips(root) {
		(root || document).querySelectorAll('.giweb-gw-strip__seg[data-tip]').forEach(function (seg) {
			if (seg.dataset.giwebStripTipBound) {
				return;
			}
			seg.dataset.giwebStripTipBound = '1';
			seg.addEventListener('mouseenter', function (event) {
				showStripTip(seg.getAttribute('data-tip') || '', event.clientX, event.clientY);
			});
			seg.addEventListener('mousemove', function (event) {
				showStripTip(seg.getAttribute('data-tip') || '', event.clientX, event.clientY);
			});
			seg.addEventListener('mouseleave', hideStripTip);
		});
	}

	function applyListView(listRoot, view) {
		if (!listRoot) {
			return;
		}
		var grid = listRoot.querySelector('.giweb-gw-grid');
		var table = listRoot.querySelector('.giweb-gw-table-wrap');
		if (grid) {
			grid.classList.toggle('is-hidden', view !== 'cards');
		}
		if (table) {
			table.classList.toggle('is-hidden', view !== 'table');
		}
	}

	function resolveListView(listRoot, toggle) {
		var storageKey = toggle ? toggle.getAttribute('data-storage-key') : listRoot.getAttribute('data-storage-key');
		var stored = storageKey ? window.localStorage.getItem(storageKey) : null;
		if (stored === 'cards' || stored === 'table') {
			return stored;
		}
		return listRoot.getAttribute('data-default-view') || 'cards';
	}

	function syncViewToggle(toggle, view) {
		if (!toggle) {
			return;
		}
		toggle.querySelectorAll('.giweb-gw-view').forEach(function (btn) {
			btn.classList.toggle('is-active', btn.getAttribute('data-view') === view);
		});
	}

	function bindViewToggle(panel) {
		var toggle = panel.querySelector('.giweb-gw-view-toggle');
		var listRoot = panel.querySelector('.giweb-gw-list');
		if (!toggle || !listRoot || toggle.dataset.giwebViewBound) {
			return;
		}
		toggle.dataset.giwebViewBound = '1';

		var initialView = resolveListView(listRoot, toggle);
		applyListView(listRoot, initialView);
		syncViewToggle(toggle, initialView);

		toggle.querySelectorAll('.giweb-gw-view').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var view = btn.getAttribute('data-view') || 'cards';
				applyListView(listRoot, view);
				syncViewToggle(toggle, view);
				var storageKey = toggle.getAttribute('data-storage-key');
				if (storageKey) {
					try {
						window.localStorage.setItem(storageKey, view);
					} catch (e) {
						// ignore quota errors
					}
				}
			});
		});
	}

	function bindPanel(panel) {
		if (!panel || panel.dataset.giwebGwBound) {
			return;
		}
		panel.dataset.giwebGwBound = '1';

		var activeFilter = 'all';
		var searchQuery = '';

		function applyFilters() {
			var items = panel.querySelectorAll('.giweb-gw-card, .giweb-gw-table tbody tr[data-status]');
			var q = searchQuery.trim().toLowerCase();
			var visible = 0;

			items.forEach(function (item) {
				var status = item.getAttribute('data-status') || '';
				var blob = (item.getAttribute('data-search') || '').toLowerCase();
				var matchFilter =
					activeFilter === 'all' ||
					status === activeFilter ||
					(activeFilter === 'issues' && status === 'issues');
				var matchSearch = !q || blob.indexOf(q) !== -1;
				var show = matchFilter && matchSearch;

				item.classList.toggle('is-hidden', !show);
				if (show) {
					visible += 1;
				}
			});

			var noMatch = panel.querySelector('.giweb-gw-no-match');
			if (noMatch) {
				noMatch.hidden = visible > 0 || items.length === 0;
			}
		}

		var searchInput = panel.querySelector('.giweb-gw-search__input');
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				searchQuery = searchInput.value || '';
				applyFilters();
			});
		}

		panel.querySelectorAll('.giweb-gw-filter').forEach(function (btn) {
			btn.addEventListener('click', function () {
				activeFilter = btn.getAttribute('data-filter') || 'all';
				panel.querySelectorAll('.giweb-gw-filter').forEach(function (b) {
					b.classList.toggle('is-active', b === btn);
				});
				applyFilters();
			});
		});

		bindViewToggle(panel);
		bindStripTooltips(panel);
		applyFilters();
	}

	function init() {
		document
			.querySelectorAll(
				'.mainwp-giweb-mail-widget--detailed .giweb-gw, .mainwp-giweb-backup-widget--detailed .giweb-gw, .mainwp-giweb-uptime-kuma-widget--detailed .giweb-gw'
			)
			.forEach(bindPanel);
		document
			.querySelectorAll('.mainwp-giweb-mail-widget .giweb-gw-header, .mainwp-giweb-backup-widget .giweb-gw-header, .mainwp-giweb-uptime-kuma-widget .giweb-gw-header')
			.forEach(bindStripTooltips);
		refreshSyncLabels();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	setInterval(function () {
		refreshSyncLabels();
	}, 60000);
})();
