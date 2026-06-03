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
			stripTipEl.innerHTML = '';
		}
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function parseTipMeta(seg) {
		var raw = seg.getAttribute('data-tip-meta');
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function isWidgetDark(widget) {
		if (!widget) {
			return document.body.classList.contains('mainwp-custom-theme');
		}
		return (
			widget.classList.contains('mainwp-giweb-mail-widget--dark') ||
			widget.classList.contains('mainwp-giweb-backup-widget--dark') ||
			widget.classList.contains('mainwp-giweb-uptime-kuma-widget--dark') ||
			document.body.classList.contains('mainwp-custom-theme')
		);
	}

	function buildStripTipHtml(meta) {
		var html = '<div class="giweb-gw-strip-tip__head">';
		html += '<span class="giweb-gw-strip-tip__title">' + escapeHtml(meta.title) + '</span>';
		if (meta.statusLabel) {
			html +=
				'<span class="giweb-gw-strip-tip__badge giweb-gw-strip-tip__badge--' +
				escapeHtml(meta.status || 'missing') +
				'">' +
				escapeHtml(meta.statusLabel) +
				'</span>';
		}
		html += '</div>';

		if (meta.stats && meta.stats.length) {
			html += '<div class="giweb-gw-strip-tip__stats">';
			meta.stats.forEach(function (stat) {
				var tone = stat.tone ? ' giweb-gw-strip-tip__stat--' + stat.tone : '';
				html += '<div class="giweb-gw-strip-tip__stat' + tone + '">';
				html += '<span class="giweb-gw-strip-tip__stat-label">' + escapeHtml(stat.label) + '</span>';
				html += '<span class="giweb-gw-strip-tip__stat-value">' + escapeHtml(stat.value) + '</span>';
				html += '</div>';
			});
			html += '</div>';
		}

		return html;
	}

	function positionStripTip(tip, x, y) {
		tip.hidden = false;
		tip.style.left = '0px';
		tip.style.top = '0px';

		var offset = 14;
		var rect = tip.getBoundingClientRect();
		var left = x + offset;
		var top = y + offset;

		if (left + rect.width > window.innerWidth - 8) {
			left = Math.max(8, x - rect.width - offset);
		}
		if (top + rect.height > window.innerHeight - 8) {
			top = Math.max(8, y - rect.height - offset);
		}

		tip.style.left = left + 'px';
		tip.style.top = top + 'px';
	}

	function showStripTipFromSegment(seg, x, y) {
		var tip = ensureStripTip();
		var widget = seg.closest(
			'.mainwp-giweb-mail-widget, .mainwp-giweb-backup-widget, .mainwp-giweb-uptime-kuma-widget'
		);
		var isDark = isWidgetDark(widget);

		tip.classList.toggle('giweb-gw-strip-tip--dark', isDark);
		tip.classList.toggle('giweb-gw-strip-tip--light', !isDark);

		var meta = parseTipMeta(seg);
		if (meta && meta.title) {
			tip.innerHTML = buildStripTipHtml(meta);
		} else {
			tip.textContent = seg.getAttribute('data-tip') || seg.getAttribute('title') || '';
		}

		positionStripTip(tip, x, y);
	}

	function bindStripTooltips(root) {
		(root || document)
			.querySelectorAll('.giweb-gw-strip__seg[data-tip-meta], .giweb-gw-strip__seg[data-tip]')
			.forEach(function (seg) {
				if (seg.dataset.giwebStripTipBound) {
					return;
				}
				seg.dataset.giwebStripTipBound = '1';
				seg.addEventListener('mouseenter', function (event) {
					showStripTipFromSegment(seg, event.clientX, event.clientY);
				});
				seg.addEventListener('mousemove', function (event) {
					showStripTipFromSegment(seg, event.clientX, event.clientY);
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
