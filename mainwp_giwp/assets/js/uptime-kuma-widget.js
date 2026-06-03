(function () {
	'use strict';

	document.querySelectorAll('.mainwp-giweb-uptime-kuma-widget--detailed').forEach(function (root) {
		var activeFilter = 'all';
		var searchQuery = '';

		function getPanel() {
			return root.querySelector('.giweb-gw');
		}

		function applyListView(view) {
			var listRoot = root.querySelector('.giweb-ukw-list');
			if (!listRoot) {
				return;
			}
			var grid = listRoot.querySelector('.giweb-ukw-grid');
			var table = listRoot.querySelector('.giweb-ukw-table-wrap');
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

		function applyFilters() {
			var panel = getPanel();
			if (!panel) {
				return;
			}

			var items = panel.querySelectorAll('.giweb-ukw-card, .giweb-ukw-table-wrap tbody tr[data-status]');
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

			var noMatch = panel.querySelector('.giweb-ukw-no-match');
			if (noMatch) {
				noMatch.hidden = visible > 0 || items.length === 0;
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

			var toggle = scope.querySelector('.giweb-gw-view-toggle');
			var listRoot = scope.querySelector('.giweb-ukw-list');
			if (toggle && listRoot && !toggle.dataset.bound) {
				toggle.dataset.bound = '1';
				var initialView = resolveListView(listRoot, toggle);
				applyListView(initialView);
				syncViewToggle(toggle, initialView);
				toggle.querySelectorAll('.giweb-gw-view').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var view = btn.getAttribute('data-view') || 'cards';
						applyListView(view);
						syncViewToggle(toggle, view);
						var storageKey = toggle.getAttribute('data-storage-key');
						if (storageKey) {
							try {
								window.localStorage.setItem(storageKey, view);
							} catch (e) {
								// ignore
							}
						}
					});
				});
			}
		}

		bindInteractions(getPanel());
		applyFilters();
	});
})();
