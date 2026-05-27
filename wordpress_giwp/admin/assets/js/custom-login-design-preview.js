( function () {
	'use strict';

	var textarea = document.getElementById( 'gi_toolkit_login_custom_css' );
	var iframe = document.getElementById( 'gi-toolkit-login-preview-frame' );
	var cfg = window.giToolkitLoginPreview || {};
	var i18n = cfg.i18n || {};

	if ( ! textarea || ! iframe ) {
		return;
	}

	var logoUrl = cfg.logoUrl || iframe.getAttribute( 'data-logo-url' ) || '';
	var debounceTimer = null;

	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	function buildLoginDocument() {
		return (
			'<!DOCTYPE html><html><head><meta charset="utf-8">' +
			'<meta name="viewport" content="width=device-width, initial-scale=1">' +
			'<style id="gi-toolkit-login-css-preview"></style>' +
			'</head><body class="login wp-core-ui no-js">' +
			'<div id="login">' +
			'<h1 role="presentation"><a href="#" tabindex="-1" aria-hidden="true"></a></h1>' +
			'<div class="message">' + t( 'message', 'Message' ) + '</div>' +
			'<form id="loginform" action="#" method="post">' +
			'<p><label for="user_login">' + t( 'userLabel', 'Username' ) + '</label>' +
			'<input type="text" name="log" id="user_login" class="input" value="" size="20" autocomplete="username" /></p>' +
			'<p><label for="user_pass">' + t( 'passLabel', 'Password' ) + '</label>' +
			'<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" autocomplete="current-password" /></p>' +
			'<p class="forgetmenot"><label><input name="rememberme" type="checkbox" id="rememberme" value="forever" /> ' +
			t( 'remember', 'Remember me' ) +
			'</label></p>' +
			'<p class="submit">' +
			'<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="' +
			t( 'submit', 'Log in' ) +
			'" />' +
			'</p></form>' +
			'<p id="nav"><a href="#">' + t( 'lostPassword', 'Lost password?' ) + '</a></p>' +
			'<p id="backtoblog"><a href="#">' + t( 'backToSite', 'Back to site' ) + '</a></p>' +
			'</div></body></html>'
		);
	}

	function applyPreviewCss( doc, css ) {
		var style = doc.getElementById( 'gi-toolkit-login-css-preview' );
		if ( ! style ) {
			style = doc.createElement( 'style' );
			style.id = 'gi-toolkit-login-css-preview';
			doc.head.appendChild( style );
		}
		style.textContent = css;

		if ( logoUrl && ! String( css ).trim() ) {
			var link = doc.querySelector( '.login h1 a' );
			if ( link ) {
				link.style.display = 'block';
				link.style.width = '240px';
				link.style.height = '72px';
				link.style.margin = '0 auto';
				link.style.backgroundImage = 'url("' + String( logoUrl ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ) + '")';
				link.style.backgroundSize = 'contain';
				link.style.backgroundPosition = 'center';
				link.style.backgroundRepeat = 'no-repeat';
				link.style.textIndent = '-9999px';
				link.style.overflow = 'hidden';
			}
		}
	}

	function renderPreview() {
		var css = textarea.value;
		var doc = iframe.contentDocument || iframe.contentWindow.document;

		if ( ! doc ) {
			return;
		}

		doc.open();
		doc.write( buildLoginDocument() );
		doc.close();

		applyPreviewCss( doc, css );
	}

	function schedulePreview() {
		if ( debounceTimer ) {
			window.clearTimeout( debounceTimer );
		}
		debounceTimer = window.setTimeout( renderPreview, 200 );
	}

	textarea.addEventListener( 'input', schedulePreview );
	textarea.addEventListener( 'change', schedulePreview );

	renderPreview();
} )();
