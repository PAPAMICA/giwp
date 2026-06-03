/**
 * Widget MainWP — temps relatif basé sur l’heure du navigateur.
 */
( function () {
	'use strict';

	function getLocale() {
		var lang = document.documentElement.lang || 'fr';
		return lang.indexOf( 'fr' ) === 0 ? 'fr' : lang;
	}

	function formatRelativeSync( unixSeconds ) {
		var ts = parseInt( unixSeconds, 10 ) * 1000;
		if ( ! ts ) {
			return '';
		}

		var diffSec = Math.max( 0, Math.floor( ( Date.now() - ts ) / 1000 ) );
		if ( diffSec < 45 ) {
			return 'Sync à l\u2019instant';
		}

		var units = [
			[ 'year', 31536000 ],
			[ 'month', 2592000 ],
			[ 'week', 604800 ],
			[ 'day', 86400 ],
			[ 'hour', 3600 ],
			[ 'minute', 60 ],
		];

		var locale = getLocale();
		if ( typeof Intl !== 'undefined' && Intl.RelativeTimeFormat ) {
			var rtf = new Intl.RelativeTimeFormat( locale, { numeric: 'auto' } );
			var i;
			for ( i = 0; i < units.length; i += 1 ) {
				var unit = units[ i ][ 0 ];
				var secs = units[ i ][ 1 ];
				if ( diffSec >= secs ) {
					var val = Math.round( diffSec / secs ) * -1;
					return 'Sync ' + rtf.format( val, unit );
				}
			}
		}

		var mins = Math.floor( diffSec / 60 );
		if ( mins < 60 ) {
			return 'Sync il y a ' + mins + ' min';
		}
		var hrs = Math.floor( mins / 60 );
		return 'Sync il y a ' + hrs + ' h';
	}

	function formatLocalTooltip( unixSeconds ) {
		var ts = parseInt( unixSeconds, 10 ) * 1000;
		if ( ! ts ) {
			return '';
		}
		try {
			return new Date( ts ).toLocaleString( getLocale(), {
				dateStyle: 'medium',
				timeStyle: 'short',
			} );
		} catch ( e ) {
			return new Date( ts ).toLocaleString();
		}
	}

	function refreshSyncLabels() {
		document.querySelectorAll( '.mainwp-giweb-mail-widget__sync[data-sync-ts], .mainwp-giweb-backup-widget__sync[data-sync-ts]' ).forEach( function ( el ) {
			var ts = el.getAttribute( 'data-sync-ts' );
			var label = formatRelativeSync( ts );
			if ( label ) {
				el.textContent = label;
				el.setAttribute( 'title', formatLocalTooltip( ts ) );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', refreshSyncLabels );
	} else {
		refreshSyncLabels();
	}

	setInterval( refreshSyncLabels, 60000 );
} )();
