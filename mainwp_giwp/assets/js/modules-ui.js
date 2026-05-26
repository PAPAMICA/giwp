/**
 * Filtres / recherche — même logique que settings.js (GI-Toolkit).
 */
( function () {
	'use strict';

	function boot() {
		var items = document.querySelectorAll( '.mainwp-giweb-modules-host .gi-toolkit__body__sections__item' );
		var groups = document.querySelectorAll( '.mainwp-giweb-modules-host .gi-toolkit__body__groups__item' );
		var search = document.querySelector( '.mainwp-giweb-modules-host .gi-toolkit__header__right__search input' );
		if ( ! items.length || ! groups.length ) {
			return;
		}

		var timer = null;

		function setGroup( key ) {
			groups.forEach( function ( el ) {
				el.classList.toggle( 'active', el.dataset.key === key );
			} );
		}

		function filterGroup( key ) {
			items.forEach( function ( el ) {
				var keys = ( el.dataset.key || '' ).split( ' ' );
				var show = 'all' === key && ! el.classList.contains( 'hide-in-all' );
				if ( ! show ) {
					show = keys.indexOf( key ) !== -1;
				}
				el.classList.toggle( 'show', show );
			} );
		}

		var hash = window.location.hash.substr( 1 );
		if ( ! hash ) {
			hash = 'all';
			window.location.hash = hash;
		}
		filterGroup( hash );
		setGroup( hash );

		groups.forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				var key = el.dataset.key || 'all';
				window.location.hash = key;
				if ( search ) {
					search.value = '';
				}
				filterGroup( key );
				setGroup( key );
			} );
		} );

		if ( search ) {
			search.addEventListener( 'keyup', function ( ev ) {
				clearTimeout( timer );
				timer = setTimeout( function () {
					window.location.hash = 'all';
					setGroup( 'all' );
					var q = ( ev.target.value || '' ).toLowerCase();
					q = q.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
					items.forEach( function ( el ) {
						var title = ( el.dataset.title || '' ).toLowerCase().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
						var orig = ( el.dataset.originaltitle || '' ).toLowerCase().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
						el.classList.toggle( 'show', ! q || title.indexOf( q ) !== -1 || orig.indexOf( q ) !== -1 );
					} );
				}, 300 );
			} );
		}

		var form = document.querySelector( '.mainwp-giweb-modules-host form.mainwp-giweb-modules-form' );
		var floater = document.querySelector( '.mainwp-giweb-modules-host .gi-toolkit__save-button' );
		if ( form && floater ) {
			form.addEventListener( 'change', function () {
				floater.classList.add( 'show' );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
