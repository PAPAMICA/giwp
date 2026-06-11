(function ($) {
	'use strict';

	var cfg = window.giToolkitGeoShortcodesAdmin || {};
	var state = normalizeSettings(cfg.settings || {});

	function normalizeSettings(raw) {
		return {
			default_country: (raw.default_country || 'FR').toUpperCase(),
			geoip_db_path: raw.geoip_db_path || '',
			variables: raw.variables && typeof raw.variables === 'object' ? raw.variables : {}
		};
	}

	function slugify(value) {
		return String(value || '')
			.toLowerCase()
			.replace(/[^a-z0-9_]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.replace(/_+/g, '_');
	}

	function isValidSlug(slug) {
		return /^[a-z][a-z0-9_]{0,39}$/.test(slug);
	}

	function escapeHtml(text) {
		return $('<div/>').text(text == null ? '' : String(text)).html();
	}

	function variableOrder() {
		return Object.keys(state.variables);
	}

	function renderVariables() {
		var $root = $('#gi-geo-variables-root');
		$root.empty();

		var slugs = variableOrder();
		if (!slugs.length) {
			$root.append('<p class="description">' + escapeHtml('Aucune variable. Ajoutez-en une pour commencer.') + '</p>');
			renderPreview();
			return;
		}

		slugs.forEach(function (slug) {
			$root.append(renderVariableCard(slug, state.variables[slug]));
		});

		renderPreview();
	}

	function renderVariableCard(slug, data) {
		data = data || {};
		var countries = data.countries || {};
		var countryRows = Object.keys(countries).map(function (code) {
			return renderCountryRow(slug, code, countries[code]);
		}).join('');

		var html = ''
			+ '<article class="gi-geo-variable" data-slug="' + escapeHtml(slug) + '">'
			+ '<header class="gi-geo-variable__head">'
			+ '<div><strong>[geo_' + escapeHtml(slug) + ']</strong>'
			+ '<button type="button" class="button-link gi-geo-copy-shortcode" data-shortcode="[geo_' + escapeHtml(slug) + ']">' + escapeHtml('Copier') + '</button></div>'
			+ '<button type="button" class="button-link-delete gi-geo-delete-variable">' + escapeHtml('Supprimer') + '</button>'
			+ '</header>'
			+ '<div class="gi-geo-variable__grid">'
			+ '<p><label>' + escapeHtml('Identifiant') + '<br><input type="text" class="regular-text gi-geo-field-slug" value="' + escapeHtml(slug) + '"></label></p>'
			+ '<p><label>' + escapeHtml('Libellé') + '<br><input type="text" class="regular-text gi-geo-field-label" value="' + escapeHtml(data.label || slug) + '"></label></p>'
			+ '<p><label>' + escapeHtml('Valeur par défaut') + '<br><input type="text" class="regular-text gi-geo-field-default" value="' + escapeHtml(data.default || '') + '"></label></p>'
			+ '</div>'
			+ '<div class="gi-geo-countries">'
			+ '<div class="gi-geo-countries__head"><strong>' + escapeHtml('Valeurs par pays (ISO2)') + '</strong>'
			+ '<button type="button" class="button button-small gi-geo-add-country">' + escapeHtml('Ajouter un pays') + '</button></div>'
			+ '<div class="gi-geo-countries__rows">' + countryRows + '</div>'
			+ '</div>'
			+ '</article>';

		return html;
	}

	function renderCountryRow(slug, code, value) {
		return ''
			+ '<div class="gi-geo-country-row">'
			+ '<input type="text" class="small-text gi-geo-field-country-code" maxlength="2" value="' + escapeHtml(code) + '" placeholder="FR">'
			+ '<input type="text" class="regular-text gi-geo-field-country-value" value="' + escapeHtml(value || '') + '" placeholder="' + escapeHtml('Valeur') + '">'
			+ '<button type="button" class="button-link-delete gi-geo-remove-country" aria-label="' + escapeHtml('Retirer') + '">&times;</button>'
			+ '</div>';
	}

	function readStateFromDom() {
		var variables = {};

		$('.gi-geo-variable').each(function () {
			var $card = $(this);
			var slug = slugify($card.find('.gi-geo-field-slug').val());
			if (!isValidSlug(slug)) {
				return;
			}

			var countries = {};
			$card.find('.gi-geo-country-row').each(function () {
				var code = String($(this).find('.gi-geo-field-country-code').val() || '').toUpperCase();
				if (!/^[A-Z]{2}$/.test(code)) {
					return;
				}
				countries[code] = String($(this).find('.gi-geo-field-country-value').val() || '');
			});

			variables[slug] = {
				label: String($card.find('.gi-geo-field-label').val() || slug),
				default: String($card.find('.gi-geo-field-default').val() || ''),
				countries: countries
			};
		});

		state.variables = variables;
		state.default_country = String($('#gi-geo-default-country').val() || 'FR').toUpperCase();
		state.geoip_db_path = String($('#gi-geo-geoip-path').val() || '');
	}

	function resolvePreviewValue(slug, country) {
		var variable = state.variables[slug];
		if (!variable) {
			return '';
		}
		country = String(country || state.default_country || 'FR').toUpperCase();
		if (variable.countries && Object.prototype.hasOwnProperty.call(variable.countries, country)) {
			return variable.countries[country];
		}
		return variable.default || '';
	}

	function renderPreview() {
		var country = String($('#gi-geo-preview-country').val() || cfg.previewCountry || state.default_country || 'FR').toUpperCase();
		var $out = $('#gi-geo-preview-output');
		var slugs = variableOrder();
		if (!slugs.length) {
			$out.html('<p class="description">' + escapeHtml('Ajoutez des variables pour prévisualiser.') + '</p>');
			return;
		}

		var rows = slugs.map(function (slug) {
			var value = resolvePreviewValue(slug, country);
			return '<tr><th><code>[geo_' + escapeHtml(slug) + ']</code></th><td>' + escapeHtml(value) + '</td></tr>';
		}).join('');

		$out.html(
			'<p><strong>' + escapeHtml('Aperçu pour ') + escapeHtml(country) + '</strong></p>'
			+ '<table class="widefat striped gi-geo-preview-table"><tbody>' + rows + '</tbody></table>'
		);
	}

	function serializeToForm() {
		readStateFromDom();
		$('#gi-geo-shortcodes-json').val(JSON.stringify(state));
	}

	$('#gi-geo-add-variable').on('click', function () {
		readStateFromDom();
		var base = 'variable';
		var slug = base;
		var i = 2;
		while (state.variables[slug]) {
			slug = base + '_' + i;
			i += 1;
		}
		state.variables[slug] = {
			label: slug,
			default: '',
			countries: {}
		};
		renderVariables();
	});

	$(document).on('click', '.gi-geo-delete-variable', function () {
		if (!window.confirm(cfg.i18n.confirmDelete || 'Supprimer ?')) {
			return;
		}
		readStateFromDom();
		var slug = $(this).closest('.gi-geo-variable').data('slug');
		delete state.variables[slug];
		renderVariables();
	});

	$(document).on('click', '.gi-geo-add-country', function () {
		var $rows = $(this).closest('.gi-geo-countries').find('.gi-geo-countries__rows');
		var slug = $(this).closest('.gi-geo-variable').data('slug');
		$rows.append(renderCountryRow(slug, '', ''));
		renderPreview();
	});

	$(document).on('click', '.gi-geo-remove-country', function () {
		$(this).closest('.gi-geo-country-row').remove();
		renderPreview();
	});

	$(document).on('input change', '.gi-geo-variable input, #gi-geo-default-country, #gi-geo-preview-country', function () {
		renderPreview();
	});

	$(document).on('click', '.gi-geo-copy-shortcode', function () {
		var text = $(this).data('shortcode');
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text);
		}
	});

	$('#gi-geo-preview-apply').on('click', function () {
		var country = String($('#gi-geo-preview-country').val() || '').toUpperCase();
		$.post(cfg.ajaxUrl, {
			action: cfg.previewAction,
			nonce: cfg.nonce,
			country: country
		}).done(function (res) {
			if (res.success) {
				window.alert(cfg.i18n.previewSet || res.data.message);
			}
		});
	});

	$('#gi-geo-preview-clear').on('click', function () {
		$.post(cfg.ajaxUrl, {
			action: cfg.previewAction,
			nonce: cfg.nonce,
			clear: 1
		}).done(function () {
			$('#gi-geo-preview-country').val('');
			window.alert(cfg.i18n.previewCleared || '');
		});
	});

	$('#gi-geo-shortcodes-form').on('submit', function () {
		readStateFromDom();
		serializeToForm();
	});

	if (cfg.previewCountry) {
		$('#gi-geo-preview-country').val(cfg.previewCountry);
	} else if (cfg.detectedCountry) {
		$('#gi-geo-preview-country').val(cfg.detectedCountry);
	}

	renderVariables();
})(jQuery);
