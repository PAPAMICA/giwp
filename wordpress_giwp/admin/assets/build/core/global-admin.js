( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var cfg = window.gi_toolkit_global_admin_object || {};
		var hideSubmenu = cfg.use_wp_submenu === '0' || cfg.use_wp_submenu === 0;

		var submenu = document.querySelector( '#toplevel_page_gi-toolkit-settings .wp-submenu.wp-submenu-wrap' );
		if ( ! submenu ) {
			return;
		}

		if ( hideSubmenu ) {
			submenu.querySelectorAll( 'li:not(.wp-first-item)' ).forEach( function ( li ) {
				li.style.display = 'none';
			} );
			return;
		}

		var items = submenu.querySelectorAll( 'li' );
		if ( items.length <= 3 ) {
			return;
		}

		var firstItem = submenu.querySelector( 'li.wp-first-item' );
		if ( ! firstItem ) {
			return;
		}

		var modulesSep = submenu.querySelector( 'li.gi-toolkit-module-submenu-separator' );
		if ( ! modulesSep ) {
			modulesSep = document.createElement( 'li' );
			modulesSep.className = 'gi-toolkit-module-submenu-separator';
			modulesSep.innerHTML =
				'<span class="gi-toolkit-module-separator-text">' +
				( cfg.i18n && cfg.i18n.Modules ? cfg.i18n.Modules : 'Modules' ) +
				'</span>';
			firstItem.insertAdjacentElement( 'afterend', modulesSep );
		}

		// Déplacer les liens module restés au-dessus du séparateur « Modules ».
		var node = firstItem.nextElementSibling;
		while ( node && node !== modulesSep ) {
			var link = node.querySelector( 'a' );
			var href = link ? link.getAttribute( 'href' ) || '' : '';
			if ( href.indexOf( 'page=gi-toolkit-settings-' ) !== -1 ) {
				var toMove = node;
				node = node.nextElementSibling;
				modulesSep.insertAdjacentElement( 'afterend', toMove );
				continue;
			}
			node = node.nextElementSibling;
		}

		var allItems = submenu.querySelectorAll( 'li' );
		if ( allItems.length > 2 ) {
			var lastItem = allItems[ allItems.length - 1 ];
			if ( ! lastItem.classList.contains( 'gi-toolkit-module-submenu-separator' ) ) {
				var bottomSep = document.createElement( 'li' );
				bottomSep.className = 'gi-toolkit-module-submenu-separator';
				lastItem.insertAdjacentElement( 'beforebegin', bottomSep );
			}
		}
	} );
} )();
