( function () {
	'use strict';

	function initMailCatcher() {
		var popup = document.querySelector( '.gi-toolkit-popup' );
		var closeBtn = document.querySelector( '#JS-close-popup' );
		var overlay = document.querySelector( '#JS-popup-overlay' );
		var preview = document.querySelector( '#JS-gi-toolkit-email-preview' );
		var loader = document.querySelector( '#JS-gi-toolkit-email-loader' );
		var form = document.querySelector( '#email-list' );
		var i18n = ( window.Gi_ToolkitSubmenu && window.Gi_ToolkitSubmenu.i18n ) ? window.Gi_ToolkitSubmenu.i18n : {};

		function closePopup() {
			if ( popup ) {
				popup.classList.remove( 'show' );
			}
		}

		function openPreview( trigger ) {
			if ( ! trigger || ! preview || ! loader ) {
				return;
			}

			var emailId = trigger.dataset.emailId || trigger.value;
			if ( ! emailId || ! window.Gi_ToolkitSubmenu ) {
				return;
			}

			preview.innerHTML = '';
			loader.classList.add( 'show' );

			var body = new FormData();
			body.append( 'action', 'gi_toolkit_mail_catcher_preview' );
			body.append( 'nonce', window.Gi_ToolkitSubmenu.nonce );
			body.append( 'email_id', emailId );

			fetch( window.Gi_ToolkitSubmenu.ajaxUrl, {
				method: 'POST',
				body: body,
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					if ( data.success ) {
						preview.innerHTML = data.data;
					} else {
						preview.innerHTML = '<p>' + ( data.data || 'Error' ) + '</p>';
					}
					loader.classList.remove( 'show' );
				} )
				.catch( function () {
					loader.classList.remove( 'show' );
				} );
		}

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', closePopup );
		}
		if ( overlay ) {
			overlay.addEventListener( 'click', closePopup );
		}

		document.querySelectorAll( '.gi-toolkit-view' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				if ( popup ) {
					popup.classList.add( 'show' );
				}
				openPreview( event.currentTarget );
			} );
		} );

		document.querySelectorAll( '.gi-toolkit-delete' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( event ) {
				if ( i18n.confirmDelete && ! window.confirm( i18n.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		} );

		document.querySelectorAll( '.gi-toolkit-resend' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( event ) {
				if ( i18n.confirmResend && ! window.confirm( i18n.confirmResend ) ) {
					event.preventDefault();
				}
			} );
		} );

		function rowCheckboxes() {
			return document.querySelectorAll( '.gi-toolkit-mail-catcher-row-cb' );
		}

		function headerCheckbox() {
			return document.getElementById( 'cb-select-all-1' ) || document.getElementById( 'cb-select-all-2' );
		}

		var selectAll = headerCheckbox();
		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				rowCheckboxes().forEach( function ( cb ) {
					cb.checked = selectAll.checked;
				} );
			} );
		}

		rowCheckboxes().forEach( function ( cb ) {
			cb.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
			} );
		} );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				var submitter = event.submitter;
				if ( submitter && ( submitter.name === 'delete' || submitter.name === 'resend' ) ) {
					return;
				}

				var top = document.getElementById( 'bulk-action-selector-top' );
				var bottom = document.getElementById( 'bulk-action-selector-bottom' );
				var action = top && top.value !== '-1' ? top.value : ( bottom && bottom.value !== '-1' ? bottom.value : '' );
				if ( ! action ) {
					return;
				}

				var checked = form.querySelectorAll( '.gi-toolkit-mail-catcher-row-cb:checked' );
				if ( ! checked.length ) {
					return;
				}

				if ( action === 'delete' && i18n.confirmBulkDelete && ! window.confirm( i18n.confirmBulkDelete ) ) {
					event.preventDefault();
				}
				if ( action === 'resend' && i18n.confirmBulkResend && ! window.confirm( i18n.confirmBulkResend ) ) {
					event.preventDefault();
				}
			} );
		}

		var stats = window.Gi_ToolkitSubmenu && window.Gi_ToolkitSubmenu.stats ? window.Gi_ToolkitSubmenu.stats : null;
		if ( stats && typeof window.Chart !== 'undefined' ) {
			var volume = document.getElementById( 'gi-toolkit-mail-chart-volume' );
			var status = document.getElementById( 'gi-toolkit-mail-chart-status' );

			if ( volume ) {
				new window.Chart( volume, {
					type: 'bar',
					data: {
						labels: stats.chart_labels || [],
						datasets: [
							{
								label: i18n.chartSent || 'Envoyés',
								data: stats.chart_sent || [],
								backgroundColor: 'rgba(18, 183, 106, 0.85)',
								borderRadius: 6,
							},
							{
								label: i18n.chartFailed || 'Échoués',
								data: stats.chart_failed || [],
								backgroundColor: 'rgba(240, 68, 56, 0.85)',
								borderRadius: 6,
							},
						],
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { position: 'bottom' } },
						scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
					},
				} );
			}

			if ( status ) {
				new window.Chart( status, {
					type: 'doughnut',
					data: {
						labels: [ i18n.chartSent || 'Réussis', i18n.chartFailed || 'Échoués' ],
						datasets: [
							{
								data: [ stats.success || 0, stats.failed || 0 ],
								backgroundColor: [ '#12b76a', '#f04438' ],
								borderWidth: 0,
							},
						],
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { position: 'bottom' } },
					},
				} );
			}
		}
	}

	document.addEventListener( 'DOMContentLoaded', initMailCatcher );
} )();
