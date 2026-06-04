( function () {
	'use strict';

	if ( typeof giToolkitMigrationHelper === 'undefined' ) {
		return;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.gi-migration-ab-refresh' );
		if ( ! button ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		var nonce = button.getAttribute( 'data-nonce' );
		if ( ! nonce ) {
			return;
		}

		button.disabled = true;
		var originalText = button.textContent;
		button.textContent = giToolkitMigrationHelper.i18n.refreshing || '…';

		var body = new URLSearchParams();
		body.append( 'action', giToolkitMigrationHelper.action );
		body.append( 'nonce', nonce );

		fetch( giToolkitMigrationHelper.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					throw new Error( 'refresh_failed' );
				}
				window.location.reload();
			} )
			.catch( function () {
				button.disabled = false;
				button.textContent = originalText;
				window.alert( giToolkitMigrationHelper.i18n.error || 'Error' );
			} );
	} );
} )();
