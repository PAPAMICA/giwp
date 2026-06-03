(function () {
	'use strict';

	document.querySelectorAll('.mainwp-giweb-uptime-kuma-widget--detailed').forEach(function (root) {
		var activeFilter = 'all';
		var searchQuery = '';

		function getPanel() {
			return root.querySelector('.giweb-gw');
		}

		function applyFilters() {
			var panel = getPanel();
			if (!panel) {
				return;
			}

			var cards = panel.querySelectorAll('.giweb-ukw-card');
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

			var noMatch = panel.querySelector('.giweb-ukw-no-match');
			if (noMatch) {
				noMatch.hidden = visible > 0 || cards.length === 0;
			}
		}

		function bindInteractions(scope) {
			if (!scope) {
				return;
			}

			var searchInput = scope.querySelector('.giweb-ukw-search__input');
			if (searchInput && !searchInput.dataset.bound) {
				searchInput.dataset.bound = '1';
				searchInput.addEventListener('input', function () {
					searchQuery = searchInput.value || '';
					applyFilters();
				});
			}

			scope.querySelectorAll('.giweb-ukw-filter').forEach(function (btn) {
				if (btn.dataset.bound) {
					return;
				}
				btn.dataset.bound = '1';
				btn.addEventListener('click', function () {
					activeFilter = btn.getAttribute('data-filter') || 'all';
					scope.querySelectorAll('.giweb-ukw-filter').forEach(function (b) {
						b.classList.toggle('is-active', b === btn);
					});
					applyFilters();
				});
			});
		}

		bindInteractions(getPanel());
		applyFilters();
	});
})();
