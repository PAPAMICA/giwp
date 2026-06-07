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

	function lineChartAnimations() {
		return {
			animation: {
				duration: 1600,
				easing: 'easeOutQuart',
			},
			animations: {
				x: {
					type: 'number',
					easing: 'easeOutQuart',
					duration: 1600,
					from: NaN,
					delay: function ( ctx ) {
						return ctx.type === 'data' ? ctx.dataIndex * 18 : 0;
					},
				},
				y: {
					type: 'number',
					easing: 'easeOutCubic',
					duration: 1400,
					from: function ( ctx ) {
						if ( ! ctx.chart || ! ctx.chart.scales || ! ctx.chart.scales.y ) {
							return undefined;
						}
						return ctx.chart.scales.y.getPixelForValue( 0 );
					},
				},
			},
		};
	}

	function donutChartAnimations() {
		return {
			animation: {
				animateRotate: true,
				animateScale: true,
				duration: 1300,
				easing: 'easeOutCubic',
			},
			animations: {
				arc: {
					duration: 1300,
					easing: 'easeOutCubic',
				},
			},
		};
	}

	function animateDashboardEntrance( root ) {
		if ( ! root ) {
			return;
		}
		root.classList.add( 'is-charts-ready' );

		root.querySelectorAll( '.gi-matomo-kpi' ).forEach( function ( kpi, index ) {
			kpi.style.setProperty( '--gi-animate-i', String( index ) );
			kpi.classList.add( 'gi-matomo-animate-in' );
		} );

		root.querySelectorAll( '.gi-matomo-chart-panel' ).forEach( function ( panel, index ) {
			panel.style.setProperty( '--gi-animate-i', String( index ) );
			panel.classList.add( 'gi-matomo-animate-in' );
		} );

		root.querySelectorAll( '.gi-matomo-table-panel' ).forEach( function ( panel, index ) {
			panel.style.setProperty( '--gi-animate-i', String( index ) );
			panel.classList.add( 'gi-matomo-animate-in' );
		} );

		root.querySelectorAll( '.gi-matomo-bar-row__fill' ).forEach( function ( bar, index ) {
			bar.style.setProperty( '--gi-bar-i', String( index ) );
			bar.classList.add( 'gi-matomo-bar-animate' );
		} );

		var mapWrap = root.querySelector( '.gi-matomo-map-wrap' );
		if ( mapWrap ) {
			mapWrap.classList.add( 'gi-matomo-map-animate' );
		}
	}

	function clearDonutLegends() {
		document.querySelectorAll( '.gi-matomo-donut-legend' ).forEach( function ( el ) {
			el.remove();
		} );
	}

	function destroyCharts() {
		chartInstances.forEach( function ( chart ) {
			chart.destroy();
		} );
		chartInstances = [];
		clearDonutLegends();
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

		var granularity = data.granularity || 'day';
		var maxTicks = 12;
		if ( 'hour' === granularity ) {
			maxTicks = 8;
		} else if ( 'month' === granularity ) {
			maxTicks = 12;
		} else if ( labels.length > 45 ) {
			maxTicks = 10;
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
			options: Object.assign(
				{
					responsive: true,
					maintainAspectRatio: false,
					interaction: { mode: 'index', intersect: false },
					plugins: {
						legend: { position: 'bottom' },
					},
					scales: {
						x: {
							grid: { display: false },
							ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: maxTicks },
						},
						y: {
							beginAtZero: true,
							ticks: { precision: 0 },
						},
					},
				},
				lineChartAnimations()
			),
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

	function formatCount( value ) {
		var n = Number( value ) || 0;
		try {
			return n.toLocaleString( undefined, { maximumFractionDigits: 0 } );
		} catch ( e ) {
			return String( n );
		}
	}

	function formatPercent( value, total ) {
		if ( ! total ) {
			return '0 %';
		}
		var pct = Math.round( ( value / total ) * 1000 ) / 10;
		return pct.toLocaleString( undefined, { maximumFractionDigits: 1 } ) + ' %';
	}

	function filterChartSegments( labels, values ) {
		var outLabels = [];
		var outValues = [];
		( labels || [] ).forEach( function ( label, index ) {
			var val = Number( values[ index ] ) || 0;
			if ( val > 0 ) {
				outLabels.push( label );
				outValues.push( val );
			}
		} );
		return {
			labels: outLabels,
			values: outValues,
		};
	}

	function renderDonutLegend( canvasId, labels, values, colors ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas ) {
			return;
		}
		var wrap = canvas.closest( '.gi-matomo-chart-canvas-wrap' );
		if ( ! wrap ) {
			return;
		}

		var existing = wrap.querySelector( '.gi-matomo-donut-legend' );
		if ( existing ) {
			existing.remove();
		}

		var total = values.reduce( function ( sum, val ) {
			return sum + val;
		}, 0 );
		if ( ! total ) {
			return;
		}

		var list = document.createElement( 'ul' );
		list.className = 'gi-matomo-donut-legend';
		list.setAttribute( 'aria-hidden', 'true' );

		var rows = labels.map( function ( label, index ) {
			return {
				label: label,
				value: values[ index ],
				color: colors[ index % colors.length ],
			};
		} ).sort( function ( a, b ) {
			return b.value - a.value;
		} );

		rows.forEach( function ( row, index ) {
			var item = document.createElement( 'li' );
			item.className = 'gi-matomo-donut-legend__row gi-matomo-donut-legend__row--animate';
			item.style.setProperty( '--gi-legend-i', String( index ) );

			var dot = document.createElement( 'span' );
			dot.className = 'gi-matomo-donut-legend__dot';
			dot.style.backgroundColor = row.color;

			var labelEl = document.createElement( 'span' );
			labelEl.className = 'gi-matomo-donut-legend__label';
			labelEl.textContent = row.label;
			labelEl.title = row.label;

			var valueEl = document.createElement( 'span' );
			valueEl.className = 'gi-matomo-donut-legend__value';
			valueEl.innerHTML =
				'<strong>' + formatCount( row.value ) + '</strong>' +
				'<span class="gi-matomo-donut-legend__pct">' + formatPercent( row.value, total ) + '</span>';

			item.appendChild( dot );
			item.appendChild( labelEl );
			item.appendChild( valueEl );
			list.appendChild( item );
		} );

		wrap.appendChild( list );
	}

	function initDonutChart( canvasId, data ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		var rawLabels = ( data && data.labels ) ? data.labels : [];
		var rawValues = ( data && data.values ) ? data.values : [];
		var filtered = filterChartSegments( rawLabels, rawValues );
		var labels = filtered.labels;
		var values = filtered.values;

		if ( ! hasChartData( { values: values } ) ) {
			return;
		}

		var colors = palette.donut.slice( 0, labels.length );

		var chart = new window.Chart( canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [
					{
						data: values,
						backgroundColor: colors,
						borderWidth: 2,
						borderColor: '#fff',
						hoverOffset: 6,
					},
				],
			},
			options: Object.assign(
				{
					responsive: true,
					maintainAspectRatio: false,
					cutout: '58%',
					layout: {
						padding: { top: 4, bottom: 4 },
					},
					plugins: {
						legend: { display: false },
						tooltip: {
							callbacks: {
								label: function ( context ) {
									var total = context.dataset.data.reduce( function ( a, b ) {
										return a + b;
									}, 0 );
									return (
										context.label +
										': ' +
										formatCount( context.parsed ) +
										' (' +
										formatPercent( context.parsed, total ) +
										')'
									);
								},
							},
						},
					},
				},
				donutChartAnimations()
			),
		} );
		chartInstances.push( chart );
		renderDonutLegend( canvasId, labels, values, colors );
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
		animateDashboardEntrance( document.getElementById( 'gi-matomo-dashboard' ) );
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
		var $dashboard = $( '#gi-matomo-dashboard' );
		$dashboard.removeClass( 'is-revealed' ).html( html );

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

		requestAnimationFrame( function () {
			$dashboard.addClass( 'is-revealed' );
		} );
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

	function loadUptimeSection( forceRefresh, silent ) {
		var $content = $( '#gi-matomo-uptime-content' );
		if ( ! $content.length || ! $content.data( 'deferLoad' ) ) {
			return;
		}

		if ( ! silent ) {
			$content.addClass( 'is-loading' ).removeClass( 'is-loaded is-revealed' );
		}

		$.post( cfg.ajaxUrl, {
			action: 'gi_toolkit_matomo_uptime_section',
			nonce: cfg.nonce,
			refresh: forceRefresh ? 1 : ( $content.data( 'refresh' ) || 0 ),
		} )
			.done( function ( res ) {
				if ( ! res.success || ! res.data || ! res.data.html ) {
					return;
				}
				$content.html( res.data.html );
				if ( res.data.chart && typeof window.giToolkitInitUptimeKumaChart === 'function' ) {
					window.giToolkitInitUptimeKumaChart( {
						chart: res.data.chart,
						canvasId: 'gi-uptime-kuma-ping-chart-matomo',
						i18n: {
							pingLabel: ( cfg.i18n && cfg.i18n.pingLabel ) || 'ms',
						},
					} );
				}
				if ( typeof window.giToolkitAnimateUptimeDashboard === 'function' ) {
					window.giToolkitAnimateUptimeDashboard( $content.get( 0 ) );
				}
				requestAnimationFrame( function () {
					$content.removeClass( 'is-loading' ).addClass( 'is-loaded is-revealed' );
				} );
				if ( res.data.stale && ! forceRefresh ) {
					loadUptimeSection( true, true );
				}
			} )
			.always( function () {
				$content.data( 'refresh', 0 );
			} );
	}

	$( function () {
		var $wrap = $( '#gi-matomo-dashboard-wrap' );
		currentPeriod = $wrap.data( 'period' ) || cfg.defaultPeriod || 'last7';

		loadUptimeSection( false );

		if ( $wrap.hasClass( 'is-deferred-load' ) ) {
			loadPeriod( currentPeriod, false );
		} else if ( currentPeriod === 'live' ) {
			startLiveRefresh( cfg.liveRefresh );
		} else {
			initCharts();
		}

		$( document ).on( 'click', '#gi-matomo-uptime-refresh', function () {
			loadUptimeSection( true, false );
		} );

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
