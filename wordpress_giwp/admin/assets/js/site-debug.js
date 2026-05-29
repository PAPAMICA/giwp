( function () {
	'use strict';

	var cfg = window.giToolkitSiteDebug || {};
	var i18n = cfg.i18n || {};
	var root = document.getElementById( 'gi-site-debug' );

	if ( ! root ) {
		return;
	}

	var searchInput = root.querySelector( '.gi-sd-search__input' );
	var sections = root.querySelectorAll( '.gi-sd-section' );
	var exportSource = root.querySelector( '.gi-sd-export-source' );

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( ta );
		return ok ? Promise.resolve() : Promise.reject();
	}

	function notifyCopied() {
		window.alert( i18n.copied || 'Copied' );
	}

	function notifyCopyFailed() {
		window.alert( i18n.copyFailed || 'Copy failed' );
	}

	if ( searchInput ) {
		searchInput.addEventListener( 'input', function () {
			var q = ( searchInput.value || '' ).trim().toLowerCase();
			sections.forEach( function ( section ) {
				var blob = ( section.getAttribute( 'data-search' ) || '' ) + ' ' + section.textContent;
				section.classList.toggle( 'is-hidden', q.length > 0 && blob.toLowerCase().indexOf( q ) === -1 );
			} );
		} );
	}

	root.querySelectorAll( '.gi-sd-copy-section' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var target = document.getElementById( btn.getAttribute( 'data-copy-target' ) || '' );
			if ( ! target ) {
				return;
			}
			copyText( target.innerText ).then( notifyCopied ).catch( notifyCopyFailed );
		} );
	} );

	var copyAllBtn = root.querySelector( '.gi-sd-btn-copy-all' );
	if ( copyAllBtn && exportSource ) {
		copyAllBtn.addEventListener( 'click', function () {
			copyText( exportSource.value ).then( notifyCopied ).catch( notifyCopyFailed );
		} );
	}

	var exportBtn = root.querySelector( '.gi-sd-btn-export' );
	if ( exportBtn ) {
		exportBtn.addEventListener( 'click', function () {
			if ( exportSource && exportSource.value ) {
				var blob = new Blob( [ exportSource.value ], { type: 'application/json;charset=utf-8' } );
				var url = URL.createObjectURL( blob );
				var a = document.createElement( 'a' );
				a.href = url;
				a.download = 'gi-site-debug-' + new Date().toISOString().slice( 0, 19 ).replace( /[:T]/g, '-' ) + '.json';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
				return;
			}

			if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
				return;
			}

			exportBtn.disabled = true;
			var body = new URLSearchParams();
			body.append( 'action', 'gi_toolkit_site_debug_export' );
			body.append( 'nonce', cfg.nonce );

			fetch( cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( payload ) {
					if ( ! payload || ! payload.success || ! payload.data ) {
						throw new Error( 'export failed' );
					}
					var blob = new Blob( [ payload.data.json || '' ], { type: 'application/json;charset=utf-8' } );
					var url = URL.createObjectURL( blob );
					var a = document.createElement( 'a' );
					a.href = url;
					a.download = payload.data.filename || 'gi-site-debug.json';
					document.body.appendChild( a );
					a.click();
					document.body.removeChild( a );
					URL.revokeObjectURL( url );
				} )
				.catch( notifyCopyFailed )
				.finally( function () {
					exportBtn.disabled = false;
				} );
		} );
	}
} )();
