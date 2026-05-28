(function () {
	'use strict';

	var root = document.getElementById('mainwp-giweb-uptime-kuma-widget-root');
	if (!root || typeof ajaxurl === 'undefined') {
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
					var errEl = root.querySelector('.giweb-ukw-error');
					if (!errEl) {
						errEl = document.createElement('p');
						errEl.className = 'giweb-ukw-error';
						errEl.setAttribute('role', 'alert');
						root.insertBefore(errEl, root.firstChild);
					}
					errEl.textContent = msg;
					return;
				}
				var temp = document.createElement('div');
				temp.innerHTML = json.data.html;
				root.innerHTML = '';
				while (temp.firstChild) {
					root.appendChild(temp.firstChild);
				}
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
