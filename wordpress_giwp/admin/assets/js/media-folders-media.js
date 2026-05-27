( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMediaFolders || {};
	var currentFolder = 0;

	function post( action, data ) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function renderTree( nodes, $ul ) {
		nodes.forEach( function ( node ) {
			var $li = $( '<li class="gi-media-folder-node"/>' );
			var $btn = $( '<button type="button"/>' )
				.text( node.name )
				.attr( 'data-id', node.id );
			$li.append( $btn );
			if ( node.children && node.children.length ) {
				var $child = $( '<ul/>' );
				renderTree( node.children, $child );
				$li.append( $child );
			}
			$ul.append( $li );
		} );
	}

	function reloadMediaLibrary() {
		if ( typeof wp !== 'undefined' && wp.media && wp.media.frame ) {
			var frame = wp.media.frame;
			if ( frame.content && frame.content.get ) {
				var library = frame.content.get( 'library' );
				if ( library && library.collection ) {
					library.collection.props.set( { gi_media_folder: currentFolder || null } );
					library.collection.props.set( { ignore: ( +new Date() ) } );
				}
			}
		}
		if ( window.location && window.location.href.indexOf( 'upload.php' ) !== -1 ) {
			var url = new URL( window.location.href );
			if ( currentFolder ) {
				url.searchParams.set( 'gi_media_folder', currentFolder );
			} else {
				url.searchParams.delete( 'gi_media_folder' );
			}
			window.location.href = url.toString();
		}
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
		$sidebar.append( '<h2>' + ( cfg.i18n.foldersTitle || 'Dossiers' ) + '</h2>' );
		$sidebar.append( '<div class="gi-media-folders-tree" id="gi-media-folders-native-tree"/>' );
		var $bulk = $( '<div class="gi-media-folders-native-bulk"/>' );
		$bulk.append(
			$( '<select id="gi-media-native-move-target"><option value="">' +
				( cfg.i18n.moveTo || 'Déplacer vers…' ) +
				'</option></select>' )
		);
		$bulk.append(
			$( '<button type="button" class="button"/>' )
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
			var $root = $( '<ul/>' );
			var $all = $( '<li class="gi-media-folder-node"/>' );
			$all.append(
				$( '<button type="button"/>')
					.text( cfg.i18n.allMedia )
					.attr( 'data-id', '0' )
			);
			$root.append( $all );
			renderTree( res.data.tree || [], $root );
			$tree.append( $root );
			var $sel = $( '#gi-media-native-move-target' );
			( res.data.terms || [] ).forEach( function ( t ) {
				$sel.append( $( '<option/>' ).val( t.id ).text( t.name ) );
			} );
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
			reloadMediaLibrary();
		} );
	}

	$( document ).on( 'click', '.gi-media-folders-native-sidebar .gi-media-folder-node button', function () {
		currentFolder = parseInt( $( this ).data( 'id' ), 10 ) || 0;
		$( '.gi-media-folders-native-sidebar .gi-media-folder-node' ).removeClass( 'is-active' );
		$( this ).closest( '.gi-media-folder-node' ).addClass( 'is-active' );
		reloadMediaLibrary();
	} );

	if ( typeof wp !== 'undefined' && wp.media ) {
		$( document ).ajaxSend( function ( _e, _xhr, settings ) {
			if ( ! settings.data || settings.data.indexOf( 'action=query-attachments' ) === -1 ) {
				return;
			}
			if ( currentFolder ) {
				settings.data += '&gi_media_folder=' + currentFolder;
			}
		} );
	}

	$( function () {
		if ( $( 'body' ).hasClass( 'upload-php' ) ) {
			injectSidebar();
			var params = new URLSearchParams( window.location.search );
			if ( params.get( 'gi_media_folder' ) ) {
				currentFolder = parseInt( params.get( 'gi_media_folder' ), 10 ) || 0;
			}
		}
	} );
} )( jQuery );
