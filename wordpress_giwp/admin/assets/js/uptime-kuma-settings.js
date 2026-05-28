(function ($) {
	'use strict';

	var cfg = window.giToolkitUptimeKumaSettings || {};
	var $notice = $('#gi-uptime-kuma-notice');

	function showNotice(type, message) {
		$notice
			.removeClass('notice-success notice-error')
			.addClass('notice-' + (type === 'success' ? 'success' : 'error'))
			.html('<p>' + message + '</p>')
			.show();
	}

	function post(action, $btn, loadingText) {
		var label = $btn.text();
		$btn.prop('disabled', true).text(loadingText || label);
		return $.post(cfg.ajaxUrl, {
			action: action,
			nonce: cfg.nonce,
			kuma_url: $('#gi_uptime_kuma_url').val(),
			api_token: $('#gi_uptime_kuma_token').val(),
			kuma_username: $('input[name="kuma_username"]').val(),
			kuma_password: $('input[name="kuma_password"]').val()
		})
			.always(function () {
				$btn.prop('disabled', false).text(label);
			});
	}

	$('#gi-uptime-kuma-test').on('click', function () {
		var $btn = $(this);
		post('gi_toolkit_uptime_kuma_test', $btn, cfg.i18n.testing).done(function (res) {
			if (res.success) {
				showNotice('success', res.data.message || '');
			} else {
				showNotice('error', (res.data && res.data.message) || '');
			}
		}).fail(function () {
			showNotice('error', 'Erreur réseau.');
		});
	});

	$('#gi-uptime-kuma-sync').on('click', function () {
		var $btn = $(this);
		post('gi_toolkit_uptime_kuma_sync', $btn, cfg.i18n.syncing).done(function (res) {
			if (res.success) {
				if (res.data.monitor_id) {
					$('#gi_uptime_kuma_monitor_id').val(res.data.monitor_id);
				}
				showNotice('success', res.data.message || '');
			} else {
				showNotice('error', (res.data && res.data.message) || '');
			}
		}).fail(function () {
			showNotice('error', 'Erreur réseau.');
		});
	});
})(jQuery);
