( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMediaFolders || {};
	var currentFolder = 0;
	var mediaReadyBound = false;

	function post( action, data ) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function renderTree( nodes, $ul, depth ) {
		depth = depth || 0;
		nodes.forEach( function ( node ) {
			var $li = $( '<li class="gi-media-folder-node"/>' );
			var $btn = $( '<button type="button" class="gi-media-folder-btn"/>' )
				.text( node.name + ( node.count ? ' (' + node.count + ')' : '' ) )
				.attr( 'data-id', node.id )
				.css( 'padding-left', 8 + depth * 12 + 'px' );
			$li.append( $btn );
			if ( node.children && node.children.length ) {
				var $child = $( '<ul class="gi-media-folder-children"/>' );
				renderTree( node.children, $child, depth + 1 );
				$li.append( $child );
			}
			$ul.append( $li );
		} );
	}

	function setActiveFolderButton( folderId ) {
		var $sidebar = $( '.gi-media-folders-native-sidebar' );
		$sidebar.find( '.gi-media-folder-node' ).removeClass( 'is-active' );
		$sidebar.find( 'button[data-id="' + folderId + '"]' ).closest( '.gi-media-folder-node' ).addClass( 'is-active' );
	}

	function updateUrl( folderId ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		var url = new URL( window.location.href );
		if ( folderId > 0 ) {
			url.searchParams.set( 'gi_media_folder', String( folderId ) );
		} else {
			url.searchParams.delete( 'gi_media_folder' );
		}
		window.history.replaceState( {}, '', url.toString() );
	}

	/**
	 * Rafraîchit la grille médiathèque (wp.media.frames.browse).
	 *
	 * @return {boolean}
	 */
	function refreshAttachmentsGrid() {
		if ( typeof wp === 'undefined' || ! wp.media || ! wp.media.frames || ! wp.media.frames.browse ) {
			return false;
		}

		var frame = wp.media.frames.browse;
		var library = frame.state().get( 'library' );
		if ( ! library || ! library.props ) {
			return false;
		}

		var props = library.props.toJSON();
		props.paged = 1;
		if ( currentFolder > 0 ) {
			props.gi_media_folder = currentFolder;
		} else {
			delete props.gi_media_folder;
		}
		props.ignore = +new Date();
		library.props.set( props );
		return true;
	}

	function applyFolderFilter( folderId ) {
		currentFolder = parseInt( folderId, 10 ) || 0;
		setActiveFolderButton( currentFolder );
		updateUrl( currentFolder );

		if ( refreshAttachmentsGrid() ) {
			return;
		}

		// La frame media-grid peut se charger après notre script.
		var attempts = 0;
		var timer = window.setInterval( function () {
			attempts += 1;
			if ( refreshAttachmentsGrid() || attempts > 40 ) {
				window.clearInterval( timer );
			}
		}, 150 );
	}

	function bindMediaAjaxFallback() {
		if ( mediaReadyBound ) {
			return;
		}
		mediaReadyBound = true;

		$( document ).ajaxSend( function ( _e, _xhr, settings ) {
			var data = settings && settings.data;
			if ( ! data ) {
				return;
			}

			// Ne jamais concaténer une chaîne sur un objet (cassait query-attachments).
			if ( typeof data === 'object' && !( data instanceof FormData ) ) {
				if ( data.action !== 'query-attachments' ) {
					return;
				}
				if ( ! data.query || typeof data.query !== 'object' ) {
					data.query = {};
				}
				if ( currentFolder > 0 ) {
					data.query.gi_media_folder = currentFolder;
				} else {
					delete data.query.gi_media_folder;
				}
				return;
			}

			if ( typeof data === 'string' && data.indexOf( 'action=query-attachments' ) !== -1 ) {
				if ( currentFolder > 0 ) {
					settings.data = data + '&query[gi_media_folder]=' + encodeURIComponent( currentFolder );
				}
			}
		} );
	}

	function injectSidebar() {
		if ( $( '.gi-media-folders-native-sidebar' ).length ) {
			return;
		}

		var $wrap = $( 'body.upload-php .wrap' );
		if ( ! $wrap.length ) {
			return;
		}

		$( 'body' ).addClass( 'gi-media-folders-active' );

		var $layout = $( '<div class="gi-media-folders-native-layout"/>' );
		var $sidebar = $( '<aside class="gi-media-folders-native-sidebar"/>' );
		$sidebar.append( '<h2 class="gi-media-folders-native-title">' + ( cfg.i18n.foldersTitle || 'Dossiers' ) + '</h2>' );
		$sidebar.append( '<div class="gi-media-folders-tree" id="gi-media-folders-native-tree"/>' );

		var $bulk = $( '<div class="gi-media-folders-native-bulk"/>' );
		$bulk.append(
			$( '<select id="gi-media-native-move-target"><option value="">' +
				( cfg.i18n.moveTo || 'Déplacer vers…' ) +
				'</option></select>' )
		);
		$bulk.append(
			$( '<button type="button" class="button button-small"/>' )
				.text( cfg.i18n.moveSelection || 'Déplacer la sélection' )
				.on( 'click', onMoveSelection )
		);
		$sidebar.append( $bulk );

		var $main = $( '<div class="gi-media-folders-native-main"/>' );
		$wrap.children().appendTo( $main );
		$layout.append( $sidebar ).append( $main );
		$wrap.empty().append( $layout );

		post( 'gi_toolkit_media_folders_tree' ).done( function ( res ) {
			if ( ! res.success ) {
				return;
			}
			var $tree = $( '#gi-media-folders-native-tree' );
			var $root = $( '<ul class="gi-media-folders-tree-root"/>' );
			var $all = $( '<li class="gi-media-folder-node"/>' );
			$all.append(
				$( '<button type="button" class="gi-media-folder-btn"/>' )
					.text( cfg.i18n.allMedia || 'Tous les médias' )
					.attr( 'data-id', '0' )
			);
			$root.append( $all );
			renderTree( res.data.tree || [], $root, 0 );
			$tree.append( $root );

			var $sel = $( '#gi-media-native-move-target' );
			( res.data.terms || [] ).forEach( function ( t ) {
				$sel.append( $( '<option/>' ).val( t.id ).text( t.name ) );
			} );

			var params = new URLSearchParams( window.location.search );
			var fromUrl = parseInt( params.get( 'gi_media_folder' ), 10 ) || 0;
			applyFolderFilter( fromUrl );
		} );
	}

	function onMoveSelection() {
		var folder = $( '#gi-media-native-move-target' ).val();
		if ( ! folder ) {
			window.alert( cfg.i18n.selectFolder );
			return;
		}
		var ids = [];
		$( 'input[name="media[]"]:checked' ).each( function () {
			ids.push( $( this ).val() );
		} );
		if ( ! ids.length ) {
			$( '.attachment.selected' ).each( function () {
				var id = $( this ).data( 'id' );
				if ( id ) {
					ids.push( id );
				}
			} );
		}
		if ( ! ids.length ) {
			window.alert( cfg.i18n.selectMedia );
			return;
		}
		post( 'gi_toolkit_media_folders_move', { attachment_ids: ids, folder_id: folder } ).done( function () {
			applyFolderFilter( currentFolder );
		} );
	}

	$( document ).on( 'click', '.gi-media-folders-native-sidebar .gi-media-folder-node button', function ( e ) {
		e.preventDefault();
		var folderId = parseInt( $( this ).data( 'id' ), 10 ) || 0;
		applyFolderFilter( folderId );
	} );

	$( function () {
		if ( ! $( 'body' ).hasClass( 'upload-php' ) ) {
			return;
		}
		bindMediaAjaxFallback();
		injectSidebar();
	} );
} )( jQuery );
