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

	function collectGiwebGw(root) {
		if (!root || root === document) {
			return Array.prototype.slice.call(
				document.querySelectorAll('.giweb-gw')
			);
		}
		if (root.classList && root.classList.contains('giweb-gw')) {
			return [root];
		}
		return Array.prototype.slice.call(root.querySelectorAll('.giweb-gw'));
	}

	function initGiwebGw(root) {
		root = root || document;

		collectGiwebGw(root).forEach(function (gw) {
			if (gw.querySelector('.giweb-gw-toolbar, .giweb-gw-list')) {
				delete gw.dataset.giwebGwBound;
				bindPanel(gw);
			}

			var header = gw.querySelector('.giweb-gw-header');
			if (header) {
				bindStripTooltips(gw);
			}
		});

		bindRefreshButtons(root);
		bindAckButtons(root);
		refreshSyncLabels(root);
	}

	function bindRefreshButtons(root) {
		(root || document).querySelectorAll('.giweb-gw-refresh:not([data-giweb-refresh-bound])').forEach(function (btn) {
			btn.dataset.giwebRefreshBound = '1';
			btn.addEventListener('click', function () {
				handleWidgetRefresh(btn);
			});
		});
	}

	function handleWidgetRefresh(btn) {
		var cfg = window.mainwpGiwebWidgetShell;
		if (!cfg || !cfg.ajaxUrl || !cfg.action) {
			return;
		}
		if (btn.classList.contains('is-loading')) {
			return;
		}

		var gw = btn.closest('.giweb-gw');
		if (!gw) {
			return;
		}

		var scope = btn.getAttribute('data-refresh-scope') || '';
		var siteId = btn.getAttribute('data-refresh-site-id') || '0';
		var detailed = btn.getAttribute('data-refresh-detailed') || '0';
		var body = new FormData();

		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('scope', scope);
		body.append('site_id', siteId);
		body.append('detailed', detailed);

		btn.classList.add('is-loading');
		btn.disabled = true;

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data || !payload.data.html) {
					var message =
						(payload && payload.data && payload.data.message) ||
						(cfg.i18n && cfg.i18n.refreshError) ||
						'';
					if (message) {
						window.alert(message);
					}
					return;
				}

				replaceWidgetHtml(gw, payload.data.html);
			})
			.catch(function () {
				var message = (cfg.i18n && cfg.i18n.refreshError) || '';
				if (message) {
					window.alert(message);
				}
			})
			.finally(function () {
				btn.classList.remove('is-loading');
				btn.disabled = false;
			});
	}

	function bindAckButtons(root) {
		(root || document).querySelectorAll('.giweb-gw-ack:not([data-giweb-ack-bound])').forEach(function (btn) {
			btn.dataset.giwebAckBound = '1';
			btn.addEventListener('click', function () {
				handleCompromiseAck(btn);
			});
		});
	}

	function shellCfg() {
		return window.mainwpGiwebWidgetShell || {};
	}

	function shellI18n(key, fallback) {
		var cfg = shellCfg();
		return (cfg.i18n && cfg.i18n[key]) || fallback || '';
	}

	function extractAjaxMessage(payload, fallback) {
		if (!payload) {
			return fallback;
		}
		if (typeof payload.data === 'string' && payload.data) {
			return payload.data;
		}
		if (payload.data && payload.data.message) {
			return String(payload.data.message);
		}
		if (payload.data && payload.data.log) {
			return String(payload.data.log);
		}
		return fallback;
	}

	function postForm(fields) {
		var cfg = shellCfg();
		var body = new FormData();
		Object.keys(fields).forEach(function (key) {
			body.append(key, fields[key]);
		});

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		}).then(function (response) {
			return response.text().then(function (text) {
				var payload = null;
				try {
					payload = JSON.parse(text);
				} catch (e) {
					var preview = String(text || '')
						.replace(/<[^>]+>/g, ' ')
						.replace(/\s+/g, ' ')
						.trim()
						.slice(0, 240);
					throw new Error(
						(response.ok ? '' : 'HTTP ' + response.status + ' — ') +
							(preview || shellI18n('ackNetworkError', 'Erreur réseau'))
					);
				}
				return payload;
			});
		});
	}

	function getAckModal() {
		var el = document.getElementById('mainwp-giweb-cd-ack-modal');
		if (!el || el.dataset.giwebAckBound === '1') {
			return el;
		}

		el.dataset.giwebAckBound = '1';
		el.querySelector('.giweb-gw-modal__backdrop').addEventListener('click', function () {
			if (el.dataset.running === '1') {
				return;
			}
			closeAckModal();
		});
		el.querySelector('.giweb-gw-modal__cancel').addEventListener('click', function () {
			if (el.dataset.running === '1') {
				return;
			}
			closeAckModal();
		});
		el.querySelector('.giweb-gw-modal__close').addEventListener('click', function () {
			if (el.dataset.running === '1') {
				return;
			}
			closeAckModal();
		});
		el.querySelector('.giweb-gw-modal__confirm').addEventListener('click', function () {
			startCompromiseAck();
		});
		document.addEventListener('keydown', function (ev) {
			if (ev.key !== 'Escape' || el.dataset.running === '1' || !el.classList.contains('is-open')) {
				return;
			}
			closeAckModal();
		});

		return el;
	}

	function openAckModal(intro) {
		var el = getAckModal();
		if (!el) {
			return null;
		}

		el.dataset.running = '0';
		el.querySelector('[data-ack-intro]').textContent = intro || '';
		el.querySelector('.giweb-gw-modal__run').hidden = true;
		el.querySelector('.giweb-gw-modal__log').innerHTML = '';
		el.querySelector('.giweb-gw-modal__bar').style.width = '0%';
		el.querySelector('.giweb-gw-modal__progress-label').textContent = formatAckProgress(0, 0);
		el.querySelector('.giweb-gw-modal__bar-wrap').setAttribute('aria-valuenow', '0');
		el.querySelector('.giweb-gw-modal__cancel').hidden = false;
		el.querySelector('.giweb-gw-modal__confirm').hidden = false;
		el.querySelector('.giweb-gw-modal__confirm').disabled = false;
		el.querySelector('.giweb-gw-modal__close').hidden = true;
		el.querySelector('.giweb-gw-modal__close').disabled = true;
		el.classList.add('is-open');
		el.setAttribute('aria-hidden', 'false');
		return el;
	}

	function closeAckModal() {
		var el = document.getElementById('mainwp-giweb-cd-ack-modal');
		if (!el) {
			return;
		}
		el.classList.remove('is-open');
		el.setAttribute('aria-hidden', 'true');
		el.dataset.running = '0';
		delete el.dataset.siteId;
		delete el.dataset.detailed;
	}

	function setAckRunning(running) {
		var el = getAckModal();
		if (!el) {
			return;
		}
		el.dataset.running = running ? '1' : '0';
		el.querySelector('.giweb-gw-modal__run').hidden = false;
		el.querySelector('.giweb-gw-modal__cancel').hidden = true;
		el.querySelector('.giweb-gw-modal__confirm').hidden = true;
		el.querySelector('.giweb-gw-modal__close').hidden = false;
		el.querySelector('.giweb-gw-modal__close').disabled = !!running;
	}

	function enableAckClose() {
		var el = getAckModal();
		if (!el) {
			return;
		}
		el.dataset.running = '0';
		el.querySelector('.giweb-gw-modal__close').hidden = false;
		el.querySelector('.giweb-gw-modal__close').disabled = false;
	}

	function formatAckProgress(current, total) {
		return shellI18n('progressLabel', '%1$d / %2$d sites')
			.replace('%1$d', String(current))
			.replace('%2$d', String(total));
	}

	function setAckProgress(current, total) {
		var el = getAckModal();
		if (!el) {
			return;
		}
		var pct = total > 0 ? Math.round((current / total) * 100) : 0;
		el.querySelector('.giweb-gw-modal__bar').style.width = pct + '%';
		el.querySelector('.giweb-gw-modal__bar-wrap').setAttribute('aria-valuenow', String(pct));
		el.querySelector('.giweb-gw-modal__progress-label').textContent = formatAckProgress(current, total);
	}

	function appendAckLog(line, isOk) {
		var el = getAckModal();
		if (!el || !line) {
			return;
		}
		var log = el.querySelector('.giweb-gw-modal__log');
		var row = document.createElement('div');
		row.className = 'giweb-gw-modal__log-line';
		if (false === isOk) {
			row.classList.add('giweb-gw-modal__log-line--err');
		} else if (true === isOk) {
			row.classList.add('giweb-gw-modal__log-line--ok');
		}
		row.textContent = line;
		log.appendChild(row);
		log.scrollTop = log.scrollHeight;
	}

	function handleCompromiseAck(btn) {
		var cfg = shellCfg();
		if (!cfg.ajaxUrl || !cfg.ackAction || !cfg.ackInitAction) {
			return;
		}

		var el = openAckModal(
			(btn.getAttribute('data-refresh-site-id') || '0') !== '0'
				? shellI18n('ackConfirmSite')
				: shellI18n('ackConfirm')
		);
		if (!el) {
			window.alert(shellI18n('ackNetworkError'));
			return;
		}

		el.dataset.siteId = btn.getAttribute('data-refresh-site-id') || '0';
		el.dataset.detailed = btn.getAttribute('data-refresh-detailed') || '0';
	}

	function startCompromiseAck() {
		var cfg = shellCfg();
		var el = getAckModal();
		if (!el || el.dataset.running === '1') {
			return;
		}

		el.querySelector('.giweb-gw-modal__confirm').disabled = true;
		var siteId = el.dataset.siteId || '0';
		var detailed = el.dataset.detailed || '0';

		setAckRunning(true);
		appendAckLog(shellI18n('ackStarting', 'Préparation…'));
		setAckProgress(0, 0);

		postForm({
			action: cfg.ackInitAction,
			nonce: cfg.nonce,
			site_id: siteId
		})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data || !payload.data.sites || !payload.data.sites.length) {
					appendAckLog(
						extractAjaxMessage(payload, shellI18n('ackNoSites')),
						false
					);
					enableAckClose();
					return null;
				}

				return ackSitesSequentially(payload.data.sites).then(function (stats) {
					if (stats.fail > 0) {
						appendAckLog(shellI18n('ackDonePartial'), false);
					} else {
						appendAckLog(shellI18n('ackDone'), true);
					}
					return refreshCdWidgets(siteId, detailed).catch(function () {
						appendAckLog(shellI18n('ackRefreshError'), false);
					});
				});
			})
			.catch(function (err) {
				appendAckLog(
					(err && err.message) || shellI18n('ackNetworkError'),
					false
				);
			})
			.finally(function () {
				enableAckClose();
			});
	}

	function ackSitesSequentially(sites) {
		var total = sites.length;
		var completed = 0;
		var fail = 0;
		var cfg = shellCfg();

		setAckProgress(0, total);

		return sites.reduce(function (chain, site) {
			return chain.then(function () {
				appendAckLog(
					shellI18n('ackConnecting', 'Acquittement de %s…').replace('%s', site.name || ('#' + site.id))
				);
				return postForm({
					action: cfg.ackAction,
					nonce: cfg.nonce,
					site_id: String(site.id)
				})
					.then(function (payload) {
						completed += 1;
						if (payload && payload.success) {
							appendAckLog(
								(payload.data && payload.data.log) || site.name,
								true
							);
						} else {
							fail += 1;
							appendAckLog(
								extractAjaxMessage(payload, shellI18n('ackNetworkError')),
								false
							);
						}
						setAckProgress(completed, total);
					})
					.catch(function (err) {
						completed += 1;
						fail += 1;
						appendAckLog(
							(site.name || ('#' + site.id)) +
								' — ' +
								((err && err.message) || shellI18n('ackNetworkError')),
							false
						);
						setAckProgress(completed, total);
					});
			});
		}, Promise.resolve()).then(function () {
			return { fail: fail, total: total };
		});
	}

	function refreshCdWidgets(siteId, detailed) {
		var cfg = shellCfg();
		var seen = {};
		var jobs = [];

		document.querySelectorAll('.giweb-gw-refresh[data-refresh-scope="cd"]').forEach(function (btn) {
			var gw = btn.closest('.giweb-gw');
			if (!gw) {
				return;
			}
			var sid = btn.getAttribute('data-refresh-site-id') || '0';
			var det = btn.getAttribute('data-refresh-detailed') || '0';
			var key = sid + ':' + det;
			if (seen[key]) {
				return;
			}
			seen[key] = true;
			jobs.push(
				postForm({
					action: cfg.action,
					nonce: cfg.nonce,
					scope: 'cd',
					site_id: sid,
					detailed: det,
					skip_sync: '1'
				}).then(function (payload) {
					if (payload && payload.success && payload.data && payload.data.html) {
						replaceWidgetHtml(gw, payload.data.html);
					}
				})
			);
		});

		if (!jobs.length) {
			return postForm({
				action: cfg.action,
				nonce: cfg.nonce,
				scope: 'cd',
				site_id: siteId || '0',
				detailed: detailed || '0',
				skip_sync: '1'
			}).then(function (payload) {
				var gw = document.querySelector('.mainwp-giweb-cd-widget .giweb-gw');
				if (payload && payload.success && payload.data && payload.data.html && gw) {
					replaceWidgetHtml(gw, payload.data.html);
				}
			});
		}

		return Promise.all(jobs);
	}

	function replaceWidgetHtml(gw, html) {
		var parent = gw.parentElement;
		gw.outerHTML = html;
		var freshGw = parent ? parent.querySelector('.giweb-gw') : null;
		if (freshGw) {
			initGiwebGw(freshGw);
		} else {
			initGiwebGw(document);
		}
	}

	function init() {
		initGiwebGw(document);
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
