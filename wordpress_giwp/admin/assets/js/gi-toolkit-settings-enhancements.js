/**
 * GI-Toolkit settings UI enhancements.
 * @since 2.19.0
 */
( function () {
	'use strict';

	var cfg = window.gi_toolkit_settings_enhancements || {};
	var root = document.querySelector( '.wrap.gi-toolkit' );
	if ( ! root ) {
		return;
	}

	if ( cfg.darkTheme === '1' ) {
		root.classList.add( 'gi-toolkit--dark' );
	}

	var highRisk = cfg.highRiskModules || [];
	if ( highRisk.length ) {
		root.querySelectorAll( '.module-item' ).forEach( function ( item ) {
			var hidden = item.querySelector( 'input[type="checkbox"][name*="gi_toolkit_settings"]' );
			if ( ! hidden ) {
				return;
			}
			var match = hidden.name.match( /\[([^\]]+)\]/ );
			if ( match && highRisk.indexOf( match[1] ) !== -1 ) {
				item.setAttribute( 'data-high-risk', '1' );
			}
		} );
	}

	if ( cfg.confirmActivation === '1' ) {
		root.querySelectorAll( '.module-item input[type="checkbox"]' ).forEach( function ( input ) {
			if ( input.disabled ) {
				return;
			}
			input.addEventListener( 'change', function () {
				if ( ! input.checked ) {
					return;
				}
				var row = input.closest( '.module-item' );
				if ( row && row.getAttribute( 'data-high-risk' ) === '1' && cfg.allowHighRisk !== '1' ) {
					input.checked = false;
					window.alert( cfg.i18n.highRiskBlocked || '' );
					return;
				}
				var title = row ? row.getAttribute( 'data-title' ) : '';
				if ( title && ! window.confirm( ( cfg.i18n.confirmActivate || '' ).replace( '%s', title ) ) ) {
					input.checked = false;
				}
			} );
		} );
	}
} )();
