( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitManagedDashboard || {};
	var charts = [];

	function lineAnimations() {
		return {
			tension: {
				duration: 2800,
				easing: 'easeOutQuart',
				from: 0.05,
				to: 0.35,
			},
			x: {
				type: 'number',
				easing: 'easeOutQuart',
				duration: 1800,
				from: NaN,
			},
			y: {
				type: 'number',
				easing: 'easeOutQuart',
				duration: 2200,
				from: function ( ctx ) {
					var chart = ctx.chart;
					var y = chart && chart.scales ? chart.scales.y : null;
					return y ? y.getPixelForValue( 0 ) : 0;
				},
			},
		};
	}

	function destroyCharts() {
		charts.forEach( function ( chart ) {
			try {
				chart.destroy();
			} catch ( e ) {
				/* ignore */
			}
		} );
		charts = [];
	}

	function initVisitsChart( root ) {
		var canvas = root.querySelector( '#gi-md-visits-chart' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}
		var visitsRoot = root.querySelector( '.gi-md-visits' );
		var timeline = {};
		try {
			timeline = JSON.parse( visitsRoot.getAttribute( 'data-timeline' ) || '{}' );
		} catch ( e ) {
			timeline = {};
		}
		var chart = new window.Chart( canvas, {
			type: 'line',
			data: {
				labels: timeline.labels || [],
				datasets: [
					{
						label: 'Visites',
						data: timeline.visits || [],
						borderColor: '#2271b1',
						backgroundColor: 'rgba(34, 113, 177, 0.12)',
						fill: true,
						borderWidth: 2,
						pointRadius: 0,
						pointHoverRadius: 4,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 2400,
					easing: 'easeOutQuart',
				},
				animations: {
					tension: lineAnimations().tension,
					x: lineAnimations().x,
					y: lineAnimations().y,
				},
				plugins: { legend: { display: false } },
				scales: {
					x: { grid: { display: false } },
					y: { beginAtZero: true, ticks: { precision: 0 } },
				},
			},
		} );
		charts.push( chart );
	}

	function initMailChart( root ) {
		var canvas = root.querySelector( '#gi-md-mail-chart' );
		var mailRoot = root.querySelector( '.gi-md-mail' );
		if ( ! canvas || ! mailRoot || typeof window.Chart === 'undefined' ) {
			return;
		}
		var labels = [];
		var sent = [];
		var failed = [];
		var spam = [];
		try {
			labels = JSON.parse( mailRoot.getAttribute( 'data-chart-labels' ) || '[]' );
			sent = JSON.parse( mailRoot.getAttribute( 'data-chart-sent' ) || '[]' );
			failed = JSON.parse( mailRoot.getAttribute( 'data-chart-failed' ) || '[]' );
			spam = JSON.parse( mailRoot.getAttribute( 'data-chart-spam' ) || '[]' );
		} catch ( e ) {
			/* ignore */
		}
		var i18n = cfg.i18n || {};
		var chart = new window.Chart( canvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						label: i18n.chartSent || 'Envoyés',
						data: sent,
						backgroundColor: 'rgba(18, 183, 106, 0.85)',
						borderRadius: 5,
					},
					{
						label: i18n.chartFailed || 'Échoués',
						data: failed,
						backgroundColor: 'rgba(240, 68, 56, 0.85)',
						borderRadius: 5,
					},
					{
						label: i18n.chartSpam || 'Spam / RBL',
						data: spam,
						backgroundColor: 'rgba(247, 144, 9, 0.85)',
						borderRadius: 5,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: { duration: 2200, easing: 'easeOutQuart' },
				plugins: { legend: { position: 'bottom' } },
				scales: {
					x: { stacked: false, grid: { display: false } },
					y: { beginAtZero: true, ticks: { precision: 0 } },
				},
			},
		} );
		charts.push( chart );
	}

	function animateCards( root ) {
		var cards = root.querySelectorAll( '.gi-md-card' );
		cards.forEach( function ( card, index ) {
			card.style.setProperty( '--gi-animate-i', String( index + 1 ) );
			card.classList.remove( 'gi-md-animate-in' );
			// Force reflow then re-add for post-AJAX animation.
			void card.offsetWidth;
			card.classList.add( 'gi-md-animate-in' );
		} );
		if ( typeof window.giToolkitAnimateUptimeDashboard === 'function' ) {
			window.giToolkitAnimateUptimeDashboard( root );
		}
	}

	function fillCards( root, cards, meta ) {
		var hidden = ( meta && meta.hidden_cards ) || [];
		hidden.forEach( function ( key ) {
			var card = root.querySelector( '.gi-md-card[data-card="' + key + '"]' );
			if ( card ) {
				card.remove();
			}
		} );

		Object.keys( cards || {} ).forEach( function ( key ) {
			var card = root.querySelector( '.gi-md-card[data-card="' + key + '"]' );
			if ( ! card ) {
				return;
			}
			var body = card.querySelector( '.gi-md-card__body' );
			if ( body ) {
				body.innerHTML = cards[ key ];
			}
		} );

		bindSupportCopy( root, ( meta && meta.support_report ) || '' );

		destroyCharts();
		initVisitsChart( root );
		initMailChart( root );
		animateCards( root );
		root.setAttribute( 'data-state', 'ready' );
	}

	function bindSupportCopy( root, report ) {
		var btn = root.querySelector( '[data-gi-md-copy]' );
		if ( ! btn ) {
			return;
		}

		btn.disabled = ! report;
		btn.dataset.report = report || '';

		if ( btn.dataset.bound === '1' ) {
			return;
		}
		btn.dataset.bound = '1';

		btn.addEventListener( 'click', function () {
			var text = btn.dataset.report || '';
			var label = btn.querySelector( '.gi-md-tech-copy__label' );
			var idle = 'Copier';
			var ok = ( cfg.i18n && cfg.i18n.copied ) || 'Copié !';
			var fail = ( cfg.i18n && cfg.i18n.copyFailed ) || 'Copie impossible';

			function setLabel( value ) {
				if ( label ) {
					label.textContent = value;
				}
			}

			function flash( value ) {
				setLabel( value );
				window.setTimeout( function () {
					setLabel( idle );
				}, 1800 );
			}

			if ( ! text ) {
				flash( fail );
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then(
					function () {
						flash( ok );
					},
					function () {
						fallbackCopy( text, flash, ok, fail );
					}
				);
				return;
			}

			fallbackCopy( text, flash, ok, fail );
		} );
	}

	function fallbackCopy( text, flash, ok, fail ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		try {
			var success = document.execCommand( 'copy' );
			flash( success ? ok : fail );
		} catch ( e ) {
			flash( fail );
		}
		document.body.removeChild( ta );
	}

	function showError( root, message ) {
		var bodies = root.querySelectorAll( '.gi-md-card__body' );
		bodies.forEach( function ( body ) {
			body.innerHTML =
				'<div class="gi-md-empty"><span class="dashicons dashicons-warning gi-md-empty__icon"></span>' +
				'<p class="gi-md-empty__text">' +
				$( '<div/>' ).text( message || ( cfg.i18n && cfg.i18n.error ) || 'Erreur' ).html() +
				'</p></div>';
		} );
		root.setAttribute( 'data-state', 'error' );
	}

	function loadDashboard() {
		var root = document.getElementById( 'gi-toolkit-managed-dashboard' );
		if ( ! root || ! cfg.ajaxUrl ) {
			return;
		}

		$.ajax( {
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: cfg.action,
				nonce: cfg.nonce,
			},
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success || ! response.data || ! response.data.cards ) {
					showError( root, cfg.i18n && cfg.i18n.error );
					return;
				}
				fillCards( root, response.data.cards, response.data );
			} )
			.fail( function () {
				showError( root, cfg.i18n && cfg.i18n.error );
			} );
	}

	$( document ).ready( loadDashboard );
} )( jQuery );
