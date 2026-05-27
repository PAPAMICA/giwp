( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMatomoDashboard || {};
	var chartInstances = [];
	var worldMapInstance = null;
	var liveRefreshTimer = null;
	var currentPeriod = null;

	var palette = {
		primary: '#2271b1',
		secondary: '#72aee6',
		tertiary: '#00a32a',
		donut: [
			'#2271b1',
			'#72aee6',
			'#00a32a',
			'#dba617',
			'#d63638',
			'#8c8f94',
			'#9b51e0',
			'#2ec4b6',
		],
	};

	function destroyCharts() {
		chartInstances.forEach( function ( chart ) {
			chart.destroy();
		} );
		chartInstances = [];
	}

	function readChartsData() {
		var $json = $( '#gi-matomo-charts-data' );
		if ( ! $json.length ) {
			return null;
		}
		try {
			return JSON.parse( $json.text() );
		} catch ( e ) {
			return null;
		}
	}

	function hasChartData( series ) {
		if ( ! series ) {
			return false;
		}
		if ( Array.isArray( series.values ) && series.values.some( function ( v ) {
			return v > 0;
		} ) ) {
			return true;
		}
		if ( Array.isArray( series.visits ) && series.visits.some( function ( v ) {
			return v > 0;
		} ) ) {
			return true;
		}
		return false;
	}

	function initTimelineChart( data ) {
		var canvas = document.getElementById( 'gi-matomo-chart-timeline' );
		if ( ! canvas || typeof window.Chart === 'undefined' || ! data ) {
			return;
		}

		var labels = data.labels || [];
		if ( ! labels.length ) {
			return;
		}

		var chart = new window.Chart( canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: cfg.i18n.visits || 'Visites',
						data: data.visits || [],
						borderColor: palette.primary,
						backgroundColor: 'rgba(34, 113, 177, 0.12)',
						fill: true,
						tension: 0.35,
						pointRadius: 3,
						pointHoverRadius: 5,
					},
					{
						label: cfg.i18n.unique || 'Visiteurs uniques',
						data: data.unique || [],
						borderColor: palette.tertiary,
						backgroundColor: 'transparent',
						tension: 0.35,
						pointRadius: 2,
						pointHoverRadius: 4,
					},
					{
						label: cfg.i18n.actions || 'Pages vues',
						data: data.actions || [],
						borderColor: palette.secondary,
						backgroundColor: 'transparent',
						borderDash: [ 4, 4 ],
						tension: 0.35,
						pointRadius: 2,
						pointHoverRadius: 4,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom' },
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
					},
					y: {
						beginAtZero: true,
						ticks: { precision: 0 },
					},
				},
			},
		} );
		chartInstances.push( chart );
	}

	function destroyWorldMap() {
		if ( worldMapInstance && typeof worldMapInstance.destroy === 'function' ) {
			worldMapInstance.destroy();
		}
		worldMapInstance = null;
		var el = document.getElementById( 'gi-matomo-world-map' );
		if ( el ) {
			el.innerHTML = '';
		}
	}

	function initWorldMap( mapData ) {
		var el = document.getElementById( 'gi-matomo-world-map' );
		if ( ! el || typeof window.jsVectorMap === 'undefined' || ! mapData ) {
			return;
		}

		var values = mapData.values || {};
		if ( ! Object.keys( values ).length ) {
			el.innerHTML = '<p class="description gi-matomo-map-empty">' + ( cfg.i18n.mapEmpty || '' ) + '</p>';
			return;
		}

		destroyWorldMap();

		var mapEl = document.getElementById( 'gi-matomo-world-map' );
		if ( mapEl && mapEl.parentElement ) {
			mapEl.style.height = mapEl.parentElement.clientHeight + 'px';
		}

		worldMapInstance = new window.jsVectorMap( {
			selector: '#gi-matomo-world-map',
			map: 'world',
			zoomButtons: false,
			regionStyle: {
				initial: {
					fill: '#e9ecef',
					stroke: '#fff',
					'stroke-width': 0.5,
				},
				hover: {
					fill: '#72aee6',
					cursor: 'pointer',
				},
			},
			series: {
				regions: [
					{
						attribute: 'fill',
						values: values,
						scale: [ '#e8f1fa', '#2271b1' ],
						normalizeFunction: 'linear',
					},
				],
			},
			onRegionTooltipShow: function ( event, tooltip, code ) {
				var count = values[ code ] || 0;
				tooltip.text(
					tooltip.text() + ( count ? ' — ' + count + ' ' + ( cfg.i18n.visits || 'visites' ) : '' ),
					true
				);
			},
		} );
	}

	function initDonutChart( canvasId, data ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		var labels = ( data && data.labels ) ? data.labels : [];
		var values = ( data && data.values ) ? data.values : [];

		if ( ! hasChartData( { values: values } ) ) {
			return;
		}

		var chart = new window.Chart( canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [
					{
						data: values,
						backgroundColor: palette.donut,
						borderWidth: 2,
						borderColor: '#fff',
						hoverOffset: 6,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '62%',
				plugins: {
					legend: {
						position: 'bottom',
						labels: { boxWidth: 12, padding: 10 },
					},
					tooltip: {
						callbacks: {
							label: function ( context ) {
								var total = context.dataset.data.reduce( function ( a, b ) {
									return a + b;
								}, 0 );
								var pct = total > 0 ? Math.round( ( context.parsed / total ) * 1000 ) / 10 : 0;
								return context.label + ': ' + context.parsed + ' (' + pct + '%)';
							},
						},
					},
				},
			},
		} );
		chartInstances.push( chart );
	}

	function initCharts() {
		destroyCharts();
		destroyWorldMap();
		var charts = readChartsData();
		if ( ! charts ) {
			return;
		}
		initTimelineChart( charts.timeline );
		initDonutChart( 'gi-matomo-chart-referrers', charts.referrers );
		initDonutChart( 'gi-matomo-chart-countries', charts.countries );
		initDonutChart( 'gi-matomo-chart-devices', charts.devices );
		initWorldMap( charts.world_map );
	}

	function stopLiveRefresh() {
		if ( liveRefreshTimer ) {
			clearInterval( liveRefreshTimer );
			liveRefreshTimer = null;
		}
		$( '#gi-matomo-dashboard-wrap' ).removeClass( 'is-live-mode' );
	}

	function startLiveRefresh( seconds ) {
		stopLiveRefresh();
		var interval = ( seconds || cfg.liveRefresh || 10 ) * 1000;
		$( '#gi-matomo-dashboard-wrap' ).addClass( 'is-live-mode' );
		liveRefreshTimer = setInterval( function () {
			if ( currentPeriod === 'live' ) {
				loadPeriod( 'live', true );
			}
		}, interval );
	}

	function setLoading( isLoading, silent ) {
		var $wrap = $( '#gi-matomo-dashboard-wrap' );
		var $loader = $( '#gi-matomo-loader' );
		if ( ! $wrap.length ) {
			return;
		}
		if ( isLoading && ! silent ) {
			$wrap.addClass( 'is-loading' );
			$loader.removeAttr( 'hidden' ).attr( 'aria-busy', 'true' );
			$( '.gi-matomo-period-btn' ).addClass( 'is-disabled' ).attr( 'aria-disabled', 'true' );
		} else if ( ! isLoading ) {
			$wrap.removeClass( 'is-loading is-refreshing' );
			$loader.attr( 'hidden', 'hidden' ).attr( 'aria-busy', 'false' );
			$( '.gi-matomo-period-btn' ).removeClass( 'is-disabled' ).removeAttr( 'aria-disabled' );
		} else if ( silent ) {
			$wrap.addClass( 'is-refreshing' );
		}
	}

	function renderDashboard( html, meta ) {
		meta = meta || {};
		$( '#gi-matomo-dashboard' ).html( html );

		if ( meta.is_live ) {
			destroyCharts();
			destroyWorldMap();
			startLiveRefresh( meta.refresh_seconds );
		} else {
			stopLiveRefresh();
			initCharts();
		}

		if ( meta.matomoUrl ) {
			$( '#gi-matomo-external-link' ).attr( 'href', meta.matomoUrl );
		}
	}

	function loadPeriod( period, silent ) {
		var $root = $( '#gi-matomo-dashboard' );
		if ( ! $root.length ) {
			return;
		}

		currentPeriod = period;

		if ( period !== 'live' ) {
			stopLiveRefresh();
		}

		setLoading( true, silent );

		$.post( cfg.ajaxUrl, {
			action: 'gi_toolkit_matomo_dashboard',
			nonce: cfg.nonce,
			period: period,
		} )
			.done( function ( res ) {
				if ( ! res.success || ! res.data || ! res.data.html ) {
					if ( ! silent ) {
						window.alert( ( res.data && res.data.message ) || cfg.i18n.error );
					}
					return;
				}
				renderDashboard( res.data.html, {
					matomoUrl: res.data.matomoUrl,
					is_live: res.data.is_live,
					refresh_seconds: res.data.refresh_seconds,
				} );
				$( '.gi-matomo-period-btn' ).removeClass( 'is-active' );
				$( '.gi-matomo-period-btn[data-period="' + period + '"]' ).addClass( 'is-active' );
				$( '#gi-matomo-dashboard-wrap' ).attr( 'data-period', period );
				if ( window.history && window.history.replaceState ) {
					var url = new URL( window.location.href );
					url.searchParams.set( 'period', period );
					window.history.replaceState( {}, '', url.toString() );
				}
			} )
			.fail( function () {
				if ( ! silent ) {
					window.alert( cfg.i18n.error );
				}
			} )
			.always( function () {
				setLoading( false );
			} );
	}

	$( function () {
		var $wrap = $( '#gi-matomo-dashboard-wrap' );
		currentPeriod = $wrap.data( 'period' ) || 'last7';

		if ( currentPeriod === 'live' ) {
			startLiveRefresh( cfg.liveRefresh );
		} else {
			initCharts();
		}

		$( document ).on( 'click', '.gi-matomo-period-btn[data-period]', function ( e ) {
			var period = $( this ).data( 'period' );
			if ( ! period ) {
				return;
			}
			e.preventDefault();
			loadPeriod( period, false );
		} );

		$( window ).on( 'beforeunload', stopLiveRefresh );

		var resizeTimer;
		$( window ).on( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( function () {
				if ( worldMapInstance && typeof worldMapInstance.updateSize === 'function' ) {
					var mapNode = document.getElementById( 'gi-matomo-world-map' );
					if ( mapNode && mapNode.parentElement ) {
						mapNode.style.height = mapNode.parentElement.clientHeight + 'px';
					}
					worldMapInstance.updateSize();
				}
			}, 150 );
		} );
	} );
} )( jQuery );
