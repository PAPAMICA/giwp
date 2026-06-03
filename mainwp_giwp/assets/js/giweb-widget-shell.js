(function () {
	'use strict';

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

	function bindPanel(panel) {
		if (!panel || panel.dataset.giwebGwBound) {
			return;
		}
		panel.dataset.giwebGwBound = '1';

		var activeFilter = 'all';
		var searchQuery = '';

		function applyFilters() {
			var cards = panel.querySelectorAll('.giweb-gw-card');
			var q = searchQuery.trim().toLowerCase();
			var visible = 0;

			cards.forEach(function (card) {
				var status = card.getAttribute('data-status') || '';
				var blob = (card.getAttribute('data-search') || '').toLowerCase();
				var matchFilter =
					activeFilter === 'all' ||
					status === activeFilter ||
					(activeFilter === 'issues' && status === 'issues');
				var matchSearch = !q || blob.indexOf(q) !== -1;
				var show = matchFilter && matchSearch;

				card.classList.toggle('is-hidden', !show);
				if (show) {
					visible += 1;
				}
			});

			var noMatch = panel.querySelector('.giweb-gw-no-match');
			if (noMatch) {
				noMatch.hidden = visible > 0 || cards.length === 0;
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

		applyFilters();
	}

	function init() {
		document
			.querySelectorAll('.mainwp-giweb-mail-widget .giweb-gw, .mainwp-giweb-backup-widget .giweb-gw')
			.forEach(bindPanel);
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
