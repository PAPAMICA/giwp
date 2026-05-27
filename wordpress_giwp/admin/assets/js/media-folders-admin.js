( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMediaFolders || {};
	var state = {
		currentFolder: 0,
		selected: {},
	};

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
				.text( node.name + ( node.count ? ' (' + node.count + ')' : '' ) )
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

	function fillSelect( terms ) {
		var $sel = $( '#gi-media-folder-move-target' );
		$sel.find( 'option:not(:first)' ).remove();
		terms.forEach( function ( t ) {
			$sel.append( $( '<option/>' ).val( t.id ).text( t.name ) );
		} );
	}

	function loadTree() {
		return post( 'gi_toolkit_media_folders_tree' ).done( function ( res ) {
			if ( ! res.success ) {
				return;
			}
			var $tree = $( '#gi-media-folders-tree' );
			$tree.empty();
			var $root = $( '<ul/>' );
			var $all = $( '<li class="gi-media-folder-node is-active"/>' );
			$all.append(
				$( '<button type="button"/>' )
					.text( cfg.i18n.allMedia )
					.attr( 'data-id', '0' )
			);
			$root.append( $all );
			renderTree( res.data.tree || [], $root );
			$tree.append( $root );
			fillSelect( res.data.terms || [] );
		} );
	}

	function loadAttachments() {
		var $box = $( '#gi-media-folder-attachments' );
		$box.html( '<p>…</p>' );
		return post( 'gi_toolkit_media_folders_list_attachments', { folder_id: state.currentFolder } ).done(
			function ( res ) {
				$box.empty();
				state.selected = {};
				if ( ! res.success || ! res.data.items || ! res.data.items.length ) {
					$box.html( '<p>' + ( cfg.i18n.empty || '—' ) + '</p>' );
					return;
				}
				res.data.items.forEach( function ( item ) {
					var $card = $( '<div class="gi-media-folder-attachment"/>' ).attr( 'data-id', item.id );
					if ( item.thumb ) {
						$card.append( $( '<img/>' ).attr( 'src', item.thumb ).attr( 'alt', '' ) );
					}
					$card.append( $( '<span/>' ).text( item.title ) );
					$card.on( 'click', function () {
						var id = item.id;
						if ( state.selected[ id ] ) {
							delete state.selected[ id ];
							$card.removeClass( 'is-selected' );
						} else {
							state.selected[ id ] = true;
							$card.addClass( 'is-selected' );
						}
					} );
					$box.append( $card );
				} );
			}
		);
	}

	function setActiveFolder( id ) {
		state.currentFolder = parseInt( id, 10 ) || 0;
		$( '.gi-media-folder-node' ).removeClass( 'is-active' );
		$( '.gi-media-folder-node button[data-id="' + state.currentFolder + '"]' )
			.closest( '.gi-media-folder-node' )
			.addClass( 'is-active' );
		$( '#gi-media-folder-current-label' ).text(
			state.currentFolder
				? $( '.gi-media-folder-node.is-active button' ).first().text()
				: ''
		);
		loadAttachments();
	}

	$( document ).on( 'click', '#gi-media-folders-tree button', function () {
		setActiveFolder( $( this ).data( 'id' ) );
	} );

	$( '#gi-media-folder-create' ).on( 'click', function () {
		var name = $( '#gi-media-folder-new-name' ).val();
		if ( ! name ) {
			return;
		}
		post( 'gi_toolkit_media_folders_create', { name: name, parent: state.currentFolder } ).done( function () {
			$( '#gi-media-folder-new-name' ).val( '' );
			loadTree().then( loadAttachments );
		} );
	} );

	$( '#gi-media-folder-move-btn' ).on( 'click', function () {
		var ids = Object.keys( state.selected );
		var folder = $( '#gi-media-folder-move-target' ).val();
		if ( ! ids.length ) {
			window.alert( cfg.i18n.selectMedia );
			return;
		}
		if ( ! folder ) {
			window.alert( cfg.i18n.selectFolder );
			return;
		}
		post( 'gi_toolkit_media_folders_move', { attachment_ids: ids, folder_id: folder } ).done( loadAttachments );
	} );

	$( '#gi-media-folder-rename-btn' ).on( 'click', function () {
		if ( ! state.currentFolder ) {
			return;
		}
		var name = window.prompt( cfg.i18n.newName );
		if ( ! name ) {
			return;
		}
		post( 'gi_toolkit_media_folders_rename', { term_id: state.currentFolder, name: name } ).done( function () {
			loadTree();
		} );
	} );

	$( '#gi-media-folder-delete-btn' ).on( 'click', function () {
		if ( ! state.currentFolder || ! window.confirm( cfg.i18n.confirmDelete ) ) {
			return;
		}
		post( 'gi_toolkit_media_folders_delete', { term_id: state.currentFolder } ).done( function () {
			state.currentFolder = 0;
			loadTree().then( loadAttachments );
		} );
	} );

	$( '#gi-media-folder-auto-sort-all' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( cfg.i18n.sorting );
		post( 'gi_toolkit_media_folders_auto_sort', {} )
			.done( function ( res ) {
				if ( res.success ) {
					window.alert(
						cfg.i18n.sortDone +
							' (' +
							( res.data.moved || 0 ) +
							' / ' +
							( ( res.data.moved || 0 ) + ( res.data.skipped || 0 ) ) +
							')'
					);
					loadTree().then( loadAttachments );
				}
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( cfg.i18n.sortDoneLabel || $btn.data( 'label' ) || 'Trier' );
			} );
	} );

	$( function () {
		if ( ! $( '#gi-media-folders-tree' ).length ) {
			return;
		}
		loadTree().then( function () {
			setActiveFolder( 0 );
		} );
	} );
} )( jQuery );
