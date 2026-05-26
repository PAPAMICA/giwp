<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$giweb_cfg = isset( $giweb_script_config ) && is_array( $giweb_script_config ) ? $giweb_script_config : MainWP_GIWeb::script_config();
?>
<script id="mainwp-giweb-inline-boot">
(function (w) {
	'use strict';
	var NS = '[GIWP]';
	function log() {
		if (w.console && w.console.log) {
			w.console.log.apply(w.console, [NS].concat(Array.prototype.slice.call(arguments)));
		}
	}
	w.mainwpGiwebBoot = { log: log, version: <?php echo wp_json_encode( MAINWP_GIWEB_VERSION ); ?> };
	// Logs toujours actifs pour diagnostic (filtrer la console par [GIWP]).

	log('inline boot', {
		jQuery: typeof w.jQuery,
		cfg: typeof w.mainwpGiwebAdmin,
		app: !!document.getElementById('mainwp-giweb-app'),
		pullButtons: document.querySelectorAll('.mainwp-giweb-pull-config').length
	});

	function appRoot() {
		return document.getElementById('mainwp-giweb-app');
	}

	function ajaxConfig() {
		var root = appRoot();
		var cfg = w.mainwpGiwebAdmin || {};
		return {
			ajaxUrl: cfg.ajaxUrl || (root ? root.getAttribute('data-ajax-url') : '') || '',
			nonce: cfg.nonce || (root ? root.getAttribute('data-nonce') : '') || ''
		};
	}

	function pullConfig(siteId, siteName, source) {
		var c = ajaxConfig();
		log('pullConfig()', { source: source, siteId: siteId, siteName: siteName, ajaxUrl: c.ajaxUrl, hasNonce: !!c.nonce });
		if (!c.ajaxUrl || !c.nonce) {
			log('pullConfig ABORT: missing ajaxUrl or nonce');
			return false;
		}
		var body = new w.FormData();
		body.append('action', 'mainwp_giweb_pull_config');
		body.append('nonce', c.nonce);
		body.append('site_id', String(siteId));
		body.append('site_label', siteName || String(siteId));
		log('pullConfig fetch start');
		w.fetch(c.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) {
				log('pullConfig response status', r.status, r.statusText);
				return r.text().then(function (t) {
					log('pullConfig response body (first 500 chars)', t.substring(0, 500));
					try {
						return JSON.parse(t);
					} catch (e) {
						log('pullConfig JSON parse error', e);
						throw e;
					}
				});
			})
			.then(function (json) {
				log('pullConfig parsed', json);
				w.dispatchEvent(new CustomEvent('mainwp-giweb-pull-done', { detail: json }));
			})
			.catch(function (err) {
				log('pullConfig fetch error', err);
				w.dispatchEvent(new CustomEvent('mainwp-giweb-pull-done', { detail: { success: false, data: { message: String(err) } } }));
			});
		return true;
	}

	// Capture : avant MainWP / autres handlers.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.mainwp-giweb-pull-config');
		if (!btn) {
			return;
		}
		log('native CLICK capture', btn.dataset, e.type);
		e.preventDefault();
		e.stopImmediatePropagation();
		var siteId = btn.getAttribute('data-site-id') || '';
		var siteName = btn.getAttribute('data-site-name') || siteId;
		if (!pullConfig(siteId, siteName, 'native-click')) {
			var form = btn.closest('form');
			if (form) {
				log('fallback HTML form submit');
				form.submit();
			}
		}
	}, true);

	document.addEventListener('submit', function (e) {
		if (!e.target || !e.target.classList.contains('mainwp-giweb-pull-form')) {
			return;
		}
		log('native SUBMIT capture', e.type);
		e.preventDefault();
		e.stopImmediatePropagation();
		var form = e.target;
		var btn = form.querySelector('.mainwp-giweb-pull-config');
		var siteId = (btn && btn.getAttribute('data-site-id')) || (form.querySelector('[name="source_site_id"]') || {}).value || '';
		var siteName = (btn && btn.getAttribute('data-site-name')) || siteId;
		pullConfig(siteId, siteName, 'native-submit');
	}, true);
})(window);
</script>
