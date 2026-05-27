/**
 * MainWP GI-Toolkit Manager — admin scripts
 */
( function () {
	'use strict';

	var $ = window.jQuery;
	var cfg = window.mainwpGiwebAdmin || {};

	function log() {
		if ( ! cfg.debug && ! ( window.mainwpGiwebBoot && window.mainwpGiwebBoot.log ) ) {
			return;
		}
		var fn = ( window.mainwpGiwebBoot && window.mainwpGiwebBoot.log ) || window.console.log;
		if ( fn ) {
			fn.apply( window.console, [ '[GIWP/admin.js]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	if ( ! $ ) {
		log( 'ERROR: jQuery indisponible — import/sync via inline-boot uniquement' );
	}

	function initConfig() {
		var $root = $( '#mainwp-giweb-app' );
		if ( ! $root.length ) {
			log( 'initConfig: #mainwp-giweb-app absent' );
			return;
		}
		if ( ! cfg.ajaxUrl ) {
			cfg.ajaxUrl = $root.attr( 'data-ajax-url' ) || '';
		}
		if ( ! cfg.nonce ) {
			cfg.nonce = $root.attr( 'data-nonce' ) || '';
		}
		log( 'initConfig', { ajaxUrl: cfg.ajaxUrl, hasNonce: !! cfg.nonce, version: cfg.version } );
	}

	function i18n( key, fallback ) {
		return ( cfg.i18n && cfg.i18n[ key ] ) ? cfg.i18n[ key ] : fallback;
	}

	function hasAjax() {
		return !!( cfg.ajaxUrl && cfg.nonce );
	}

	function postAjax( action, data ) {
		log( 'postAjax', action, data );
		return $.ajax( {
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend(
				{
					action: action,
					nonce: cfg.nonce,
				},
				data || {}
			),
		} );
	}

	function formatProgress( current, total ) {
		var tpl = i18n( 'progressLabel', '%1$d / %2$d sites' );
		return tpl.replace( '%1$d', String( current ) ).replace( '%2$d', String( total ) );
	}

	function SyncModal( $modal ) {
		this.$modal = $modal;
		this.$bar = $modal.find( '.mainwp-giweb-progress__bar' );
		this.$progress = $modal.find( '.mainwp-giweb-progress' );
		this.$label = $modal.find( '.mainwp-giweb-progress__label' );
		this.$log = $modal.find( '.mainwp-giweb-sync-log' );
		this.$close = $modal.find( '.mainwp-giweb-modal__close' );
	}

	SyncModal.prototype.open = function () {
		this.$modal.addClass( 'mainwp-giweb-modal--open' ).attr( 'aria-hidden', 'false' );
		this.$log.empty();
		this.$close.prop( 'disabled', true );
		this.setProgress( 0, 0 );
	};

	SyncModal.prototype.close = function () {
		this.$modal.removeClass( 'mainwp-giweb-modal--open' ).attr( 'aria-hidden', 'true' );
	};

	SyncModal.prototype.setProgress = function ( current, total ) {
		var pct = total > 0 ? Math.round( ( current / total ) * 100 ) : 0;
		this.$bar.css( 'width', pct + '%' );
		this.$progress.attr( 'aria-valuenow', pct );
		this.$label.text( formatProgress( current, total ) );
	};

	SyncModal.prototype.appendLog = function ( line, isOk ) {
		var $line = $( '<div/>' ).addClass( 'mainwp-giweb-sync-log__line' );
		if ( false === isOk ) {
			$line.addClass( 'mainwp-giweb-sync-log__line--err' );
		} else if ( true === isOk ) {
			$line.addClass( 'mainwp-giweb-sync-log__line--ok' );
		}
		$line.text( line );
		this.$log.append( $line );
		this.$log.scrollTop( this.$log[ 0 ].scrollHeight );
	};

	SyncModal.prototype.enableClose = function () {
		this.$close.prop( 'disabled', false );
	};

	function showInlineNotice( message, type ) {
		log( 'showInlineNotice', type, message );
		var $wrap = $( '#mainwp-giweb-app, .mainwp-giweb-wrap' ).first();
		if ( ! $wrap.length ) {
			window.alert( message );
			return;
		}
		$wrap.find( '.mainwp-giweb-inline-notice' ).remove();
		var cls = 'notice-info';
		if ( 'success' === type ) {
			cls = 'notice-success';
		} else if ( 'error' === type ) {
			cls = 'notice-error';
		}
		var $n = $( '<div class="notice is-dismissible mainwp-giweb-inline-notice ' + cls + '"><p></p></div>' );
		$n.find( 'p' ).text( message );
		if ( $wrap.find( '.mainwp-giweb-flash-notice' ).first().length ) {
			$wrap.find( '.mainwp-giweb-flash-notice' ).first().after( $n );
		} else if ( $wrap.find( '.mainwp-giweb-tabs' ).length ) {
			$wrap.find( '.mainwp-giweb-tabs' ).before( $n );
		} else {
			$wrap.prepend( $n );
		}
		if ( $n.offset() ) {
			$( 'html, body' ).animate( { scrollTop: $n.offset().top - 80 }, 200 );
		}
	}

	function updateWorkingBundle( stats ) {
		var $box = $( '#mainwp-giweb-working-bundle' );
		if ( ! $box.length || ! stats ) {
			return;
		}
		var total = stats.module_count || 0;
		var active = stats.active_count || 0;
		$box.html(
			'<p class="mainwp-giweb-bundle-loaded">' +
				i18n( 'bundleLoaded', 'Configuration chargée.' ) +
				'</p>' +
				'<p class="mainwp-giweb-bundle-stats"><code>' +
				total +
				'</code> modules — <strong>' +
				active +
				'</strong> actifs</p>'
		);
	}

	function handlePullResponse( response, $btn ) {
		log( 'handlePullResponse', response );
		if ( response && ! response.success && response.data ) {
			log( 'pull error detail', {
				code: response.data.code,
				preflight: response.data.preflight,
				status_ok: response.data.status_ok,
				raw: response.data.raw,
			} );
		}
		if ( response && response.success && response.data ) {
			showInlineNotice( response.data.message || i18n( 'pullSuccess', 'OK' ), 'success' );
			updateWorkingBundle( response.data );
		} else {
			showInlineNotice(
				( response && response.data && response.data.message ) || i18n( 'pullError', 'Erreur' ),
				'error'
			);
		}
		if ( $btn && $btn.length ) {
			setPullButtonLoading( $btn, false );
		}
	}

	function updateTableRow( siteId, payload ) {
		var $row = $( '#mainwp-giweb-sites-table tr[data-site-id="' + siteId + '"]' );
		if ( ! $row.length || ! payload ) {
			return;
		}

		var ok = !! payload.success;
		var data = payload.data || {};
		var errMsg = payload.message || '';
		var $status = $row.find( '.mainwp-giweb-col-status' );

		if ( ok ) {
			$status.html( '<span class="mainwp-giweb-badge ok">' + i18n( 'badgeOk', 'OK' ) + '</span>' );
		} else {
			$status.html(
				'<span class="mainwp-giweb-badge err">' + i18n( 'badgeErr', 'Erreur' ) + '</span>' +
					( errMsg ? '<span class="mainwp-giweb-error-hint">' + $( '<div/>' ).text( errMsg ).html() + '</span>' : '' )
			);
		}

		$row.find( '.mainwp-giweb-col-version' ).text( data.gi_toolkit_version || '—' );
		$row.find( '.mainwp-giweb-col-modules' ).text(
			typeof data.active_modules !== 'undefined' ? String( data.active_modules ) : '—'
		);

		var $mailCol = $row.find( '.mainwp-giweb-col-mail' );
		if ( $mailCol.length ) {
			if ( payload.mail_html ) {
				$mailCol.html( payload.mail_html );
			}
		}
	}

	function showMailSyncAlert( mailSummary ) {
		if ( ! mailSummary || ! mailSummary.sites_with_failures ) {
			return;
		}
		var tpl = i18n(
			'mailAlertSync',
			'%d site(s) ont des emails en échec. Consultez la vue d’ensemble ou le widget MainWP.'
		);
		showInlineNotice(
			tpl.replace( '%d', String( mailSummary.sites_with_failures ) ),
			'warning'
		);
	}

	function setPullButtonLoading( $btn, loading ) {
		if ( ! $btn || ! $btn.length ) {
			return;
		}
		if ( loading ) {
			if ( ! $btn.data( 'giweb-label' ) ) {
				$btn.data( 'giweb-label', $btn.text() );
			}
			$btn.prop( 'disabled', true ).text( '…' );
		} else {
			$btn.prop( 'disabled', false ).text( $btn.data( 'giweb-label' ) || $btn.text() );
		}
	}

	function runPullAjax( $btn, siteId, siteName ) {
		log( 'runPullAjax', { siteId: siteId, siteName: siteName } );
		if ( ! hasAjax() ) {
			log( 'runPullAjax: pas d’AJAX — abandon (inline-boot ou POST)' );
			return false;
		}
		var loadingTpl = i18n( 'pullLoading', 'Import en cours depuis %s…' );
		setPullButtonLoading( $btn, true );
		showInlineNotice( loadingTpl.replace( '%s', siteName ), 'info' );

		postAjax( 'mainwp_giweb_pull_config', {
			site_id: siteId,
			site_label: siteName,
		} )
			.done( function ( response ) {
				handlePullResponse( response, $btn );
			} )
			.fail( function ( jqXHR, textStatus, err ) {
				log( 'runPullAjax.fail', textStatus, err, jqXHR.status, jqXHR.responseText );
				var msg = i18n( 'pullError', 'Erreur réseau' );
				try {
					var j = jqXHR.responseJSON;
					if ( j && j.data && j.data.message ) {
						msg = j.data.message;
					}
				} catch ( e ) {
					log( 'parse fail response', e );
				}
				showInlineNotice( msg, 'error' );
				setPullButtonLoading( $btn, false );
			} );
		return true;
	}

	function collectDeploySiteIds() {
		var ids = [];
		var $root = $( '#mainwp-giweb-deploy-form, #mainwp-giweb-deploy-sites-box' );
		$root.find( 'input[type="checkbox"]:checked' ).each( function () {
			var val = $( this ).val();
			if ( ! val || val === 'on' ) {
				return;
			}
			var id = parseInt( val, 10 );
			if ( id > 0 && ids.indexOf( id ) === -1 ) {
				ids.push( id );
			}
		} );
		return ids;
	}

	function runPluginDeploy( modal ) {
		var $btn = $( '#mainwp-giweb-plugin-deploy-start' );
		if ( ! window.confirm( i18n( 'pluginDeployConfirm', 'Déployer sur tous les sites ?' ) ) ) {
			return;
		}
		$btn.prop( 'disabled', true );
		modal.open();
		modal.appendLog( i18n( 'pluginDeployStarting', 'Préparation…' ) );
		modal.$modal.find( '.mainwp-giweb-plugin-deploy-url-hint' ).prop( 'hidden', true );

		postAjax( 'mainwp_giweb_plugin_deploy_init', {} )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					modal.appendLog(
						( response && response.data && response.data.message ) || i18n( 'syncError', 'Erreur' ),
						false
					);
					modal.enableClose();
					$btn.prop( 'disabled', false );
					return;
				}

				var data = response.data;
				if ( data.package_version ) {
					modal.appendLog(
						i18n( 'pluginDeployTargetVersion', 'Version cible (ZIP) : %s' ).replace(
							'%s',
							data.package_version
						),
						true
					);
				}
				if ( data.zip_url ) {
					modal.$modal
						.find( '.mainwp-giweb-plugin-deploy-url-hint' )
						.prop( 'hidden', false )
						.text( i18n( 'pluginDeployZip', 'URL ZIP : %s' ).replace( '%s', data.zip_url ) );
				}

				var sites = data.sites || [];
				var total = data.total || sites.length;
				var jobId = data.job_id;
				var concurrency = Math.max( 1, parseInt( cfg.syncConcurrency, 10 ) || 5 );

				if ( ! total || ! jobId ) {
					modal.appendLog( i18n( 'pluginDeployNoSites', 'Aucun site' ), false );
					modal.enableClose();
					$btn.prop( 'disabled', false );
					return;
				}

				modal.appendLog(
					( i18n( 'pluginDeployParallel', 'Déploiement en parallèle (%d sites à la fois).' ) || '' ).replace(
						'%d',
						String( concurrency )
					),
					true
				);

				var completed = 0;
				var okCount = 0;
				var errCount = 0;

				runSitesParallel(
					sites,
					function ( site, siteDone ) {
						modal.appendLog(
							i18n( 'pluginDeployConnecting', 'Déploiement vers %s…' ).replace( '%s', site.name )
						);

						postAjax( 'mainwp_giweb_plugin_deploy_site', {
							job_id: jobId,
							site_id: site.id,
							site_label: site.name,
						} )
							.done( function ( siteResponse ) {
								if ( siteResponse && siteResponse.success && siteResponse.data ) {
									modal.appendLog( siteResponse.data.log, siteResponse.data.success );
									if ( siteResponse.data.success ) {
										okCount += 1;
									} else {
										errCount += 1;
									}
								} else {
									errCount += 1;
									modal.appendLog(
										( siteResponse && siteResponse.data && siteResponse.data.message ) ||
											i18n( 'syncError', 'Erreur' ),
										false
									);
								}
							} )
							.fail( function () {
								errCount += 1;
								modal.appendLog( site.name + ' — ' + i18n( 'syncError', 'Erreur réseau' ), false );
							} )
							.always( function () {
								completed += 1;
								modal.setProgress( completed, total );
								siteDone();
							} );
					},
					concurrency,
					function () {
						modal.setProgress( total, total );
						var summary;
						if ( errCount === 0 ) {
							summary = i18n( 'pluginDeployDoneOk', '%d OK' ).replace( '%d', String( okCount ) );
							modal.appendLog( i18n( 'pluginDeployDone', 'Terminé.' ), true );
						} else if ( okCount === 0 ) {
							summary = i18n( 'pluginDeployDoneFailed', 'Échec' ).replace( '%d', String( errCount ) );
							modal.appendLog( summary, false );
						} else {
							summary = i18n( 'pluginDeployDonePartial', 'Partiel' )
								.replace( '%1$d', String( okCount ) )
								.replace( '%2$d', String( errCount ) );
							modal.appendLog( summary, false );
						}
						postAjax( 'mainwp_giweb_plugin_deploy_cleanup', { job_id: jobId } );
						showInlineNotice( summary, errCount === 0 ? 'success' : 'warning' );
						modal.enableClose();
						$btn.prop( 'disabled', false );
					}
				);
			} )
			.fail( function () {
				modal.appendLog( i18n( 'syncError', 'Erreur réseau' ), false );
				modal.enableClose();
				$btn.prop( 'disabled', false );
			} );
	}

	function runDeploy( modal ) {
		var $btn = $( '#mainwp-giweb-deploy-start' );
		var siteIds = collectDeploySiteIds();
		var tplId = $( '#deploy_template_id' ).val() || '';

		if ( ! siteIds.length ) {
			showInlineNotice( i18n( 'deployNoSites', 'Sélectionnez au moins un site.' ), 'warning' );
			return;
		}

		$btn.prop( 'disabled', true );
		modal.open();
		modal.appendLog( i18n( 'deployStarting', 'Préparation…' ) );

		postAjax( 'mainwp_giweb_deploy_init', {
			deploy_template_id: tplId,
			selected_sites: siteIds,
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					modal.appendLog(
						( response && response.data && response.data.message ) || i18n( 'syncError', 'Erreur' ),
						false
					);
					modal.enableClose();
					$btn.prop( 'disabled', false );
					return;
				}

				var sites = response.data.sites || [];
				var total = response.data.total || sites.length;
				var deploymentId = response.data.deployment_id;

				if ( ! total ) {
					modal.appendLog( i18n( 'deployNoSites', 'Aucun site' ), false );
					modal.enableClose();
					$btn.prop( 'disabled', false );
					return;
				}

				var concurrency = Math.max( 1, parseInt( cfg.syncConcurrency, 10 ) || 5 );
				var completed = 0;
				var okCount = 0;
				var errCount = 0;

				modal.appendLog(
					( i18n( 'deployParallel', 'Déploiement en parallèle (%d sites à la fois).' ) || '' ).replace(
						'%d',
						String( concurrency )
					),
					true
				);

				runSitesParallel(
					sites,
					function ( site, siteDone ) {
						modal.appendLog( i18n( 'deployConnecting', 'Déploiement vers %s…' ).replace( '%s', site.name ) );

						postAjax( 'mainwp_giweb_deploy_site', {
							deployment_id: deploymentId,
							site_id: site.id,
							site_label: site.name,
						} )
							.done( function ( siteResponse ) {
								if ( siteResponse && siteResponse.success && siteResponse.data ) {
									modal.appendLog( siteResponse.data.log, siteResponse.data.success );
									if ( siteResponse.data.success ) {
										okCount += 1;
									} else {
										errCount += 1;
									}
								} else {
									errCount += 1;
									modal.appendLog(
										( siteResponse && siteResponse.data && siteResponse.data.message ) ||
											i18n( 'syncError', 'Erreur' ),
										false
									);
								}
							} )
							.fail( function () {
								errCount += 1;
								modal.appendLog( site.name + ' — ' + i18n( 'syncError', 'Erreur réseau' ), false );
							} )
							.always( function () {
								completed += 1;
								modal.setProgress( completed, total );
								siteDone();
							} );
					},
					concurrency,
					function () {
						modal.setProgress( total, total );
						var summary;
						var noticeType = 'success';
						if ( errCount === 0 ) {
							summary = i18n( 'deployDoneOk', '%d OK' ).replace( '%d', String( okCount ) );
							modal.appendLog( i18n( 'deployDone', 'Terminé.' ), true );
						} else if ( okCount === 0 ) {
							summary = i18n( 'deployDoneFailed', 'Tous en échec' ).replace( '%d', String( errCount ) );
							noticeType = 'error';
							modal.appendLog( summary, false );
						} else {
							summary = i18n( 'deployDonePartial', 'Partiel' )
								.replace( '%1$d', String( okCount ) )
								.replace( '%2$d', String( errCount ) );
							noticeType = 'warning';
							modal.appendLog( summary, false );
						}
						postAjax( 'mainwp_giweb_deploy_cleanup', { deployment_id: deploymentId } );
						showInlineNotice( summary, noticeType );
						modal.enableClose();
						$btn.prop( 'disabled', false );
					}
				);
			} )
			.fail( function () {
				modal.appendLog( i18n( 'syncError', 'Erreur réseau' ), false );
				modal.enableClose();
				$btn.prop( 'disabled', false );
			} );
	}

	function runSitesParallel( sites, worker, concurrency, done ) {
		var total = sites.length;
		var index = 0;
		var running = 0;
		var finished = 0;

		function pump() {
			while ( running < concurrency && index < total ) {
				var site = sites[ index ];
				index += 1;
				running += 1;
				worker( site, function () {
					running -= 1;
					finished += 1;
					pump();
				} );
			}
			if ( finished >= total ) {
				done();
			}
		}

		pump();
	}

	function runSync( modal ) {
		var $btn = $( '#mainwp-giweb-sync-start' );
		$btn.prop( 'disabled', true );
		modal.open();
		modal.appendLog( i18n( 'syncStarting', 'Préparation…' ) );

		postAjax( 'mainwp_giweb_sync_init', {} )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					modal.appendLog(
						( response && response.data && response.data.message ) || i18n( 'syncError', 'Erreur' ),
						false
					);
					modal.enableClose();
					$btn.prop( 'disabled', false );
					return;
				}

				var sites = response.data.sites || [];
				var total = response.data.total || sites.length;
				var concurrency = Math.max( 1, parseInt( cfg.syncConcurrency, 10 ) || 5 );

				if ( ! total ) {
					modal.appendLog( i18n( 'syncNoSites', 'Aucun site' ), false );
					modal.enableClose();
					$btn.prop( 'disabled', false );
					return;
				}

				var completed = 0;
				var lastMailSummary = null;

				runSitesParallel(
					sites,
					function ( site, siteDone ) {
						modal.appendLog( i18n( 'syncConnecting', 'Interrogation de %s…' ).replace( '%s', site.name ) );

						postAjax( 'mainwp_giweb_sync_site', {
							site_id: site.id,
							site_label: site.name,
						} )
							.done( function ( siteResponse ) {
								if ( siteResponse && siteResponse.success && siteResponse.data ) {
									modal.appendLog( siteResponse.data.log, siteResponse.data.success );
									updateTableRow( site.id, siteResponse.data );
									if ( siteResponse.data.mail_summary ) {
										lastMailSummary = siteResponse.data.mail_summary;
									}
								} else {
									modal.appendLog(
										( siteResponse && siteResponse.data && siteResponse.data.message ) ||
											i18n( 'syncError', 'Erreur' ),
										false
									);
								}
							} )
							.fail( function () {
								modal.appendLog( site.name + ' — ' + i18n( 'syncError', 'Erreur réseau' ), false );
							} )
							.always( function () {
								completed += 1;
								modal.setProgress( completed, total );
								siteDone();
							} );
					},
					concurrency,
					function () {
						modal.setProgress( total, total );
						modal.appendLog( i18n( 'syncDone', 'Terminé.' ), true );
						showMailSyncAlert( lastMailSummary );
						modal.enableClose();
						$btn.prop( 'disabled', false );
					}
				);
			} )
			.fail( function () {
				modal.appendLog( i18n( 'syncError', 'Erreur réseau' ), false );
				modal.enableClose();
				$btn.prop( 'disabled', false );
			} );
	}

	function switchGiwebTab( tabKey ) {
		if ( ! tabKey ) {
			return;
		}

		$( '.mainwp-giweb-tab' ).removeClass( 'nav-tab-active' );
		$( '.mainwp-giweb-tab[data-mainwp-giweb-tab="' + tabKey + '"]' ).addClass( 'nav-tab-active' );
		$( '.mainwp-giweb-panel[data-giweb-panel]' ).attr( 'hidden', 'hidden' );
		$( '#mainwp-giweb-panel-' + tabKey ).removeAttr( 'hidden' );

		if ( window.history && window.history.replaceState ) {
			try {
				var url = new URL( window.location.href );
				url.searchParams.set( 'tab', tabKey );
				window.history.replaceState( {}, '', url.toString() );
			} catch ( err ) {
				log( 'switchGiwebTab url', err );
			}
		}
	}

	function onReady() {
		log( 'document.ready', { pulls: $( '.mainwp-giweb-pull-config' ).length } );
		initConfig();

		var $wrap = $( '#mainwp-giweb-app, .mainwp-giweb-wrap' ).first();
		if ( $wrap.length && document.body.classList.contains( 'mainwp-custom-theme' ) ) {
			$wrap.addClass( 'mainwp-giweb--dark' ).removeClass( 'mainwp-giweb--light' );
		}

		var $syncModalEl = $( '#mainwp-giweb-sync-modal' );
		if ( $syncModalEl.length && hasAjax() ) {
			var syncModal = new SyncModal( $syncModalEl );
			$( '#mainwp-giweb-sync-start' ).on( 'click', function () {
				log( 'sync click' );
				runSync( syncModal );
			} );
			syncModal.$close.on( 'click', function () {
				if ( ! $( this ).prop( 'disabled' ) ) {
					syncModal.close();
				}
			} );
			$syncModalEl.find( '.mainwp-giweb-modal__backdrop' ).on( 'click', function () {
				if ( ! syncModal.$close.prop( 'disabled' ) ) {
					syncModal.close();
				}
			} );
		}

		var $deployModalEl = $( '#mainwp-giweb-deploy-modal' );
		if ( $deployModalEl.length && hasAjax() ) {
			var deployModal = new SyncModal( $deployModalEl );
			$( '#mainwp-giweb-deploy-start' ).on( 'click', function () {
				log( 'deploy click' );
				runDeploy( deployModal );
			} );
			deployModal.$close.on( 'click', function () {
				if ( ! $( this ).prop( 'disabled' ) ) {
					deployModal.close();
				}
			} );
			$deployModalEl.find( '.mainwp-giweb-modal__backdrop' ).on( 'click', function () {
				if ( ! deployModal.$close.prop( 'disabled' ) ) {
					deployModal.close();
				}
			} );
		}

		var $pluginDeployModalEl = $( '#mainwp-giweb-plugin-deploy-modal' );
		if ( $pluginDeployModalEl.length && hasAjax() ) {
			var pluginDeployModal = new SyncModal( $pluginDeployModalEl );
			$( '#mainwp-giweb-plugin-deploy-start' ).on( 'click', function () {
				log( 'plugin deploy click' );
				runPluginDeploy( pluginDeployModal );
			} );
			pluginDeployModal.$close.on( 'click', function () {
				if ( ! $( this ).prop( 'disabled' ) ) {
					pluginDeployModal.close();
				}
			} );
			$pluginDeployModalEl.find( '.mainwp-giweb-modal__backdrop' ).on( 'click', function () {
				if ( ! pluginDeployModal.$close.prop( 'disabled' ) ) {
					pluginDeployModal.close();
				}
			} );
		}

		$( document ).on( 'click', '.mainwp-giweb-tab', function ( e ) {
			e.preventDefault();
			var tab = $( this ).data( 'mainwpGiwebTab' ) || $( this ).attr( 'data-mainwp-giweb-tab' );
			switchGiwebTab( tab );
		} );

		var initialTab = ( function () {
			try {
				return new URL( window.location.href ).searchParams.get( 'tab' );
			} catch ( err ) {
				return null;
			}
		} )();
		if ( initialTab && $( '#mainwp-giweb-panel-' + initialTab ).length ) {
			switchGiwebTab( initialTab );
		}

		var $historyDetail = $( '#mainwp-giweb-history-detail' );
		$( document ).on( 'click', '.mainwp-giweb-history-detail', function () {
			var deploymentId = parseInt( $( this ).data( 'deploymentId' ) || $( this ).attr( 'data-deployment-id' ), 10 );
			if ( ! deploymentId || ! hasAjax() || ! $historyDetail.length ) {
				return;
			}
			switchGiwebTab( 'history' );
			$historyDetail.removeAttr( 'hidden' );
			$historyDetail.find( '.mainwp-giweb-history-detail__title' ).text(
				( i18n( 'historyDetailTitle', 'Détail du déploiement #' ) || 'Détail du déploiement #' ) + deploymentId
			);
			$historyDetail.find( '.mainwp-giweb-history-detail__loading' ).removeAttr( 'hidden' );
			$historyDetail.find( '.mainwp-giweb-history-detail__error' ).attr( 'hidden', 'hidden' ).text( '' );
			$historyDetail.find( '.mainwp-giweb-history-detail__table' ).attr( 'hidden', 'hidden' );
			$historyDetail.find( 'tbody' ).empty();

			postAjax( 'mainwp_giweb_get_deployment_detail', { deployment_id: deploymentId } )
				.done( function ( response ) {
					$historyDetail.find( '.mainwp-giweb-history-detail__loading' ).attr( 'hidden', 'hidden' );
					if ( ! response || ! response.success || ! response.data || ! response.data.sites ) {
						$historyDetail
							.find( '.mainwp-giweb-history-detail__error' )
							.removeAttr( 'hidden' )
							.text(
								( response && response.data && response.data.message ) ||
									i18n( 'historyDetailError', 'Impossible de charger le détail.' )
							);
						return;
					}
					var $tbody = $historyDetail.find( 'tbody' );
					response.data.sites.forEach( function ( row ) {
						var ok = row.status === 'success';
						var $tr = $( '<tr/>' );
						$tr.append( $( '<td/>' ).text( row.name || '#' + row.site_id ) );
						$tr.append(
							$( '<td/>' ).html(
								'<span class="mainwp-giweb-badge ' +
									( ok ? 'ok' : 'err' ) +
									'">' +
									( row.status || '' ) +
									'</span>'
							)
						);
						$tr.append( $( '<td/>' ).text( row.message || '' ) );
						$tbody.append( $tr );
					} );
					$historyDetail.find( '.mainwp-giweb-history-detail__table' ).removeAttr( 'hidden' );
				} )
				.fail( function () {
					$historyDetail.find( '.mainwp-giweb-history-detail__loading' ).attr( 'hidden', 'hidden' );
					$historyDetail
						.find( '.mainwp-giweb-history-detail__error' )
						.removeAttr( 'hidden' )
						.text( i18n( 'historyDetailError', 'Impossible de charger le détail.' ) );
				} );
		} );

		$( document ).on( 'click', '.mainwp-giweb-pull-config', function ( e ) {
			log( 'jQuery click pull', this.dataset );
			if ( ! hasAjax() ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			var $btn = $( this );
			runPullAjax( $btn, $btn.data( 'site-id' ), $btn.data( 'site-name' ) );
		} );

		$( document ).on( 'submit', '.mainwp-giweb-pull-form', function ( e ) {
			log( 'jQuery submit pull' );
			if ( ! hasAjax() ) {
				return;
			}
			e.preventDefault();
			var $form = $( this );
			var $btn = $form.find( '.mainwp-giweb-pull-config' );
			runPullAjax( $btn, $btn.data( 'site-id' ) || $form.find( '[name="source_site_id"]' ).val(), $btn.data( 'site-name' ) );
		} );

		window.addEventListener( 'mainwp-giweb-pull-done', function ( ev ) {
			log( 'event mainwp-giweb-pull-done', ev.detail );
			var $btn = $( '.mainwp-giweb-pull-config:disabled' ).first();
			if ( ! $btn.length ) {
				$btn = $( '.mainwp-giweb-pull-config' ).first();
			}
			handlePullResponse( ev.detail || {}, $btn );
		} );

		var $optionsModal = $( '#mainwp-giweb-module-options-modal' );
		var optionsModuleClass = '';

		function setModuleOptionsEditorMode( editorType ) {
			var isCss = editorType === 'css';
			$optionsModal
				.toggleClass( 'mainwp-giweb-module-options-modal--css', isCss )
				.find( '.mainwp-giweb-module-options-css' )
				.prop( 'hidden', ! isCss );
			$optionsModal.find( '.mainwp-giweb-module-options-json' ).prop( 'hidden', isCss );
			$optionsModal.find( '.mainwp-giweb-module-options-save' ).prop( 'hidden', ! isCss );
			$optionsModal.find( '.mainwp-giweb-module-options-hint' ).prop( 'hidden', isCss );
			$optionsModal.find( '.mainwp-giweb-module-options-css-hint' ).prop( 'hidden', ! isCss );
		}

		if ( $optionsModal.length ) {
			$optionsModal.find( '.mainwp-giweb-module-options-close, .mainwp-giweb-modal__backdrop' ).on( 'click', function () {
				$optionsModal.removeClass( 'mainwp-giweb-modal--open' ).attr( 'aria-hidden', 'true' );
				optionsModuleClass = '';
			} );

			$optionsModal.find( '.mainwp-giweb-module-options-save' ).on( 'click', function () {
				if ( ! hasAjax() || ! optionsModuleClass ) {
					return;
				}
				var $saveBtn = $( this );
				var cssValue = $optionsModal.find( '.mainwp-giweb-module-options-css-textarea' ).val();
				$saveBtn.prop( 'disabled', true );
				postAjax( 'mainwp_giweb_save_module_options', {
					module_class: optionsModuleClass,
					css_value: cssValue,
				} )
					.done( function ( response ) {
						if ( response && response.success ) {
							showInlineNotice(
								( response.data && response.data.message ) || i18n( 'optionsCssSaved', 'CSS enregistré.' ),
								'success'
							);
						} else {
							showInlineNotice(
								( response && response.data && response.data.message ) || i18n( 'pullError', 'Erreur' ),
								'error'
							);
						}
					} )
					.fail( function () {
						showInlineNotice( i18n( 'syncError', 'Erreur réseau' ), 'error' );
					} )
					.always( function () {
						$saveBtn.prop( 'disabled', false );
					} );
			} );
		}

		$( document ).on( 'click', '.mainwp-giweb-view-module-options', function ( e ) {
			e.preventDefault();
			if ( ! hasAjax() || ! $optionsModal.length ) {
				return;
			}
			var $btn = $( this );
			var moduleClass = $btn.data( 'module-class' );
			var moduleName = $btn.data( 'module-name' ) || moduleClass;
			optionsModuleClass = moduleClass;
			setModuleOptionsEditorMode( $btn.data( 'editor-type' ) || 'json' );
			$optionsModal.addClass( 'mainwp-giweb-modal--open' ).attr( 'aria-hidden', 'false' );
			$optionsModal.find( '#mainwp-giweb-module-options-title' ).text( moduleName );
			$optionsModal.find( '.mainwp-giweb-module-options-json' ).text( i18n( 'optionsLoading', 'Chargement…' ) );
			$optionsModal.find( '.mainwp-giweb-module-options-css-textarea' ).val( '' );
			postAjax( 'mainwp_giweb_get_module_options', { module_class: moduleClass } )
				.done( function ( response ) {
					if ( ! response || ! response.success || ! response.data ) {
						setModuleOptionsEditorMode( 'json' );
						$optionsModal.find( '.mainwp-giweb-module-options-json' ).text(
							( response && response.data && response.data.message ) || i18n( 'pullError', 'Erreur' )
						);
						return;
					}
					var data = response.data;
					setModuleOptionsEditorMode( data.editor_type || 'json' );
					if ( data.editor_type === 'css' ) {
						$optionsModal
							.find( '.mainwp-giweb-module-options-css-label' )
							.text( data.css_label || i18n( 'optionsCssLabel', 'CSS' ) );
						$optionsModal.find( '.mainwp-giweb-module-options-css-textarea' ).val( data.css_value || '' );
					} else if ( data.json ) {
						$optionsModal.find( '.mainwp-giweb-module-options-json' ).text( data.json );
					}
				} )
				.fail( function () {
					setModuleOptionsEditorMode( 'json' );
					$optionsModal.find( '.mainwp-giweb-module-options-json' ).text( i18n( 'syncError', 'Erreur réseau' ) );
				} );
		} );

		$( '.mainwp-giweb-save-template' ).on( 'click', function () {
			if ( ! hasAjax() ) {
				return;
			}
			var name = $( '#mainwp-giweb-template-name' ).val();
			if ( ! name ) {
				return;
			}
			var $btn = $( this );
			$btn.prop( 'disabled', true );
			postAjax( 'mainwp_giweb_save_template', { template_name: name } )
				.done( function ( response ) {
					if ( response && response.success && response.data ) {
						showInlineNotice( response.data.message || i18n( 'templateSaved', 'OK' ), 'success' );
						var tpl = response.data.template;
						if ( tpl && tpl.id ) {
							var $tbody = $( '#mainwp-giweb-templates-table tbody' );
							$tbody.find( '.mainwp-giweb-templates-empty' ).remove();
							if ( ! $tbody.find( 'tr[data-template-id="' + tpl.id + '"]' ).length ) {
								$tbody.append(
									'<tr data-template-id="' +
										tpl.id +
										'"><td>' +
										( tpl.name || '' ) +
										'</td><td>' +
										( tpl.created_at || '' ) +
										'</td><td><code>' +
										( tpl.hash || '' ) +
										'</code></td><td><button type="button" class="button button-link-delete mainwp-giweb-delete-template" data-template-id="' +
										tpl.id +
										'">Supprimer</button></td></tr>'
								);
							}
						}
						$( '#mainwp-giweb-template-name' ).val( '' );
					} else {
						showInlineNotice(
							( response && response.data && response.data.message ) || i18n( 'pullError', 'Erreur' ),
							'error'
						);
					}
				} )
				.fail( function () {
					showInlineNotice( i18n( 'syncError', 'Erreur réseau' ), 'error' );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );

		$( document ).on( 'click', '.mainwp-giweb-delete-template', function () {
			if ( ! hasAjax() || ! window.confirm( 'Supprimer ce modèle ?' ) ) {
				return;
			}
			var $btn = $( this );
			var tplId = $btn.data( 'template-id' );
			$btn.prop( 'disabled', true );
			postAjax( 'mainwp_giweb_delete_template', { template_id: tplId } )
				.done( function ( response ) {
					if ( response && response.success ) {
						showInlineNotice( response.data.message || i18n( 'templateDeleted', 'OK' ), 'success' );
						$( '#mainwp-giweb-templates-table tr[data-template-id="' + tplId + '"]' ).remove();
					} else {
						showInlineNotice(
							( response && response.data && response.data.message ) || i18n( 'pullError', 'Erreur' ),
							'error'
						);
					}
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );

		$( '.mainwp-giweb-modules-form' ).on( 'submit', function ( e ) {
			if ( ! hasAjax() ) {
				return;
			}
			e.preventDefault();
			var $form = $( this );
			var $floater = $form.find( '.gi-toolkit__save-button' );
			postAjax( 'mainwp_giweb_save_working_modules', $form.serialize() )
				.done( function ( response ) {
					if ( response && response.success ) {
						showInlineNotice( response.data.message || i18n( 'modulesSaved', 'OK' ), 'success' );
						$floater.removeClass( 'show' );
					} else {
						showInlineNotice(
							( response && response.data && response.data.message ) || i18n( 'pullError', 'Erreur' ),
							'error'
						);
					}
				} )
				.fail( function () {
					showInlineNotice( i18n( 'syncError', 'Erreur réseau' ), 'error' );
				} );
		} );
	}

	if ( $ ) {
		$( onReady );
	} else {
		window.addEventListener( 'mainwp-giweb-pull-done', function ( ev ) {
			var d = ev.detail || {};
			var msg = ( d.data && d.data.message ) || ( d.success ? 'OK' : 'Erreur' );
			window.alert( msg );
		} );
	}
}() );
