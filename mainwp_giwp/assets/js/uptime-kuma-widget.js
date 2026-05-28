(function () {
	'use strict';

	var root = document.getElementById('mainwp-giweb-uptime-kuma-widget-root');
	if (!root) {
		return;
	}

	var activeFilter = 'all';
	var searchQuery = '';

	function getPanel() {
		return root.querySelector('.giweb-ukw');
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
				visible++;
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

	function replacePanel(html) {
		var temp = document.createElement('div');
		temp.innerHTML = html;
		var newPanel = temp.querySelector('.giweb-ukw');
		var oldPanel = getPanel();

		if (oldPanel && newPanel) {
			oldPanel.replaceWith(newPanel);
		} else {
			while (temp.firstChild) {
				root.appendChild(temp.firstChild);
			}
		}

		bindInteractions(getPanel());
		applyFilters();
	}

	bindInteractions(getPanel());
	applyFilters();

	if (typeof ajaxurl === 'undefined') {
		return;
	}

	root.addEventListener('click', function (event) {
		var btn = event.target.closest('.giweb-ukw-refresh');
		if (!btn || !root.contains(btn)) {
			return;
		}

		event.preventDefault();

		var nonce = btn.getAttribute('data-nonce');
		if (!nonce) {
			return;
		}

		var label = btn.textContent;
		btn.disabled = true;
		btn.textContent = '…';
		root.classList.add('is-loading');

		var body = new URLSearchParams();
		body.append('action', 'mainwp_giweb_uptime_kuma_refresh');
		body.append('nonce', nonce);

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data || !json.data.html) {
					var msg =
						json && json.data && json.data.message
							? json.data.message
							: 'Erreur de synchronisation.';
					var panel = getPanel();
					var errEl = panel ? panel.querySelector('.giweb-ukw-alert--error') : null;
					if (!errEl && panel) {
						errEl = document.createElement('p');
						errEl.className = 'giweb-ukw-alert giweb-ukw-alert--error';
						errEl.setAttribute('role', 'alert');
						panel.insertBefore(errEl, panel.querySelector('.giweb-ukw-toolbar') || panel.lastChild);
					}
					if (errEl) {
						errEl.textContent = msg;
					}
					return;
				}

				activeFilter = 'all';
				searchQuery = '';
				replacePanel(json.data.html);
			})
			.catch(function () {
				window.alert('Erreur réseau.');
			})
			.finally(function () {
				root.classList.remove('is-loading');
				var b = root.querySelector('.giweb-ukw-refresh');
				if (b) {
					b.disabled = false;
					if (label) {
						b.textContent = label;
					}
				}
			});
	});
})();
