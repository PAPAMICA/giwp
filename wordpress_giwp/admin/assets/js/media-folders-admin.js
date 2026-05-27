( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMediaFolders || {};
	var state = {
		currentFolder: 0,
		selected: {},
	};
	var treeData = [];
	var folderIndex = {};
	var currentView = 'explorer';
	var STORAGE_KEY = 'gi_media_folders_view';

	function post( action, data ) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	function getStoredView() {
		try {
			var v = localStorage.getItem( STORAGE_KEY );
			return v === 'tree' ? 'tree' : 'explorer';
		} catch ( err ) {
			return 'explorer';
		}
	}

	function indexTree( nodes, parentId ) {
		nodes.forEach( function ( node ) {
			folderIndex[ node.id ] = {
				id: node.id,
				name: node.name,
				parent: parentId || 0,
				count: node.count || 0,
			};
			if ( node.children && node.children.length ) {
				indexTree( node.children, node.id );
			}
		} );
	}

	function getBreadcrumbPath( folderId ) {
		var path = [];
		var id = folderId;
		while ( id && folderIndex[ id ] ) {
			path.unshift( folderIndex[ id ] );
			id = folderIndex[ id ].parent;
		}
		return path;
	}

	function renderClassicTree( nodes, $ul ) {
		nodes.forEach( function ( node ) {
			var $li = $( '<li class="gi-media-folder-node"/>' ).attr( 'data-id', node.id );
			var $btn = $( '<button type="button" class="gi-media-folder-btn"/>' )
				.text( node.name + ( node.count ? ' (' + node.count + ')' : '' ) )
				.attr( 'data-id', node.id )
				.attr( 'data-search', node.name.toLowerCase() );
			$li.append( $btn );
			if ( node.children && node.children.length ) {
				var $child = $( '<ul class="gi-media-folder-children"/>' );
				renderClassicTree( node.children, $child );
				$li.append( $child );
			}
			$ul.append( $li );
		} );
	}

	function renderExplorerTree( nodes, $ul ) {
		nodes.forEach( function ( node ) {
			var hasChildren = node.children && node.children.length;
			var $li = $( '<li class="gi-media-folder-node"/>' ).attr( 'data-id', node.id );
			var $row = $( '<div class="gi-explorer-row"/>' );
			var $toggle = $( '<button type="button" class="gi-explorer-toggle"/>' ).attr( 'aria-expanded', 'false' );
			if ( hasChildren ) {
				$toggle.append( '<span class="gi-explorer-chevron" aria-hidden="true"></span>' );
			} else {
				$toggle.addClass( 'gi-explorer-toggle--spacer' ).prop( 'disabled', true );
			}
			var $item = $( '<button type="button" class="gi-explorer-item"/>' )
				.attr( 'data-id', node.id )
				.attr( 'data-search', node.name.toLowerCase() );
			$item.append(
				'<span class="gi-explorer-icon dashicons dashicons-portfolio" aria-hidden="true"></span>'
			);
			$item.append( $( '<span class="gi-explorer-label"/>' ).text( node.name ) );
			if ( node.count ) {
				$item.append( $( '<span class="gi-explorer-count"/>' ).text( node.count ) );
			}
			$row.append( $toggle ).append( $item );
			$li.append( $row );
			if ( hasChildren ) {
				var $child = $( '<ul class="gi-explorer-children gi-media-folder-children"/>' ).prop( 'hidden', true );
				renderExplorerTree( node.children, $child );
				$li.append( $child );
			}
			$ul.append( $li );
		} );
	}

	function appendAllMediaNode( $root ) {
		var allLabel = cfg.i18n.allMedia || 'Tous les médias';
		var $all = $( '<li class="gi-media-folder-node gi-media-folder-node--root"/>' ).attr( 'data-id', '0' );
		if ( currentView === 'explorer' ) {
			var $row = $( '<div class="gi-explorer-row"/>' );
			$row.append( '<span class="gi-explorer-toggle gi-explorer-toggle--spacer" aria-hidden="true"></span>' );
			var $item = $( '<button type="button" class="gi-explorer-item gi-explorer-item--all"/>' )
				.attr( 'data-id', '0' )
				.attr( 'data-search', allLabel.toLowerCase() );
			$item.append(
				'<span class="gi-explorer-icon dashicons dashicons-admin-media" aria-hidden="true"></span>'
			);
			$item.append( $( '<span class="gi-explorer-label"/>' ).text( allLabel ) );
			$row.append( $item );
			$all.append( $row );
		} else {
			$all.append(
				$( '<button type="button" class="gi-media-folder-btn"/>' )
					.text( allLabel )
					.attr( 'data-id', '0' )
			);
		}
		$root.append( $all );
	}

	function updateBreadcrumb( folderId ) {
		var $crumb = $( '#gi-media-folders-breadcrumb' );
		if ( ! $crumb.length ) {
			return;
		}
		$crumb.empty();
		var $home = $( '<button type="button" class="gi-explorer-crumb gi-explorer-crumb--home"/>' )
			.attr( 'data-id', '0' )
			.html(
				'<span class="dashicons dashicons-admin-home" aria-hidden="true"></span> ' +
					( cfg.i18n.breadcrumbHome || cfg.i18n.allMedia )
			);
		$crumb.append( $home );
		getBreadcrumbPath( folderId ).forEach( function ( item, i, arr ) {
			$crumb.append( '<span class="gi-explorer-crumb-sep" aria-hidden="true">›</span>' );
			var $btn = $( '<button type="button" class="gi-explorer-crumb"/>' )
				.attr( 'data-id', item.id )
				.text( item.name );
			if ( i === arr.length - 1 ) {
				$btn.addClass( 'is-current' ).prop( 'disabled', true );
			}
			$crumb.append( $btn );
		} );
		$crumb.toggleClass( 'is-empty', folderId === 0 );
	}

	function expandToFolder( folderId ) {
		if ( ! folderId || currentView !== 'explorer' ) {
			return;
		}
		var id = folderId;
		while ( id && folderIndex[ id ] ) {
			var $node = $( '#gi-media-folders-tree .gi-media-folder-node[data-id="' + id + '"]' );
			$node.children( '.gi-explorer-children' ).prop( 'hidden', false );
			$node.find( '> .gi-explorer-row .gi-explorer-toggle:not(.gi-explorer-toggle--spacer)' )
				.attr( 'aria-expanded', 'true' )
				.addClass( 'is-expanded' );
			id = folderIndex[ id ].parent;
		}
	}

	function rebuildTree() {
		var $tree = $( '#gi-media-folders-tree' );
		$tree.empty();
		var $root = $( '<ul class="gi-media-folders-tree-root"/>' );
		appendAllMediaNode( $root );
		if ( currentView === 'explorer' ) {
			renderExplorerTree( treeData, $root );
		} else {
			renderClassicTree( treeData, $root );
		}
		$tree.append( $root );
		setActiveFolder( state.currentFolder );
	}

	function setView( view ) {
		currentView = view === 'tree' ? 'tree' : 'explorer';
		try {
			localStorage.setItem( STORAGE_KEY, currentView );
		} catch ( err ) {
			// ignore
		}
		$( '.gi-toolkit-media-folders-sidebar' )
			.removeClass( 'gi-media-folders-view-tree gi-media-folders-view-explorer' )
			.addClass( 'gi-media-folders-view-' + currentView );
		$( '.gi-media-folders-view-btn' ).removeClass( 'is-active' );
		$( '.gi-media-folders-view-btn[data-view="' + currentView + '"]' ).addClass( 'is-active' );
		rebuildTree();
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
			treeData = res.data.tree || [];
			folderIndex = {};
			indexTree( treeData, 0 );
			fillSelect( res.data.terms || [] );
			rebuildTree();
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
		$( '.gi-explorer-item, .gi-media-folder-btn' ).removeClass( 'is-selected' );
		var $target = $(
			'.gi-explorer-item[data-id="' + state.currentFolder + '"], .gi-media-folder-btn[data-id="' + state.currentFolder + '"]'
		).first();
		$target.addClass( 'is-selected' ).closest( '.gi-media-folder-node' ).addClass( 'is-active' );
		var label = $target.find( '.gi-explorer-label' ).text() || $target.text();
		$( '#gi-media-folder-current-label' ).text( state.currentFolder ? label : '' );
		updateBreadcrumb( state.currentFolder );
		if ( currentView === 'explorer' ) {
			expandToFolder( state.currentFolder );
		}
		loadAttachments();
	}

	function injectExplorerChrome() {
		var $sidebar = $( '.gi-toolkit-media-folders-sidebar' );
		if ( $sidebar.find( '.gi-media-folders-sidebar-header' ).length ) {
			return;
		}
		currentView = getStoredView();
		$sidebar.addClass( 'gi-media-folders-view-' + currentView );

		var $title = $sidebar.find( 'h2, h3' ).first();
		var $header = $( '<div class="gi-media-folders-sidebar-header"/>' );
		if ( $title.length ) {
			$title.detach().appendTo( $header );
		}
		var $toggle = $( '<div class="gi-media-folders-view-toggle" role="group"/>' );
		$toggle.append(
			$( '<button type="button" class="gi-media-folders-view-btn" data-view="explorer"/>' )
				.html(
					'<span class="dashicons dashicons-category" aria-hidden="true"></span> ' +
						( cfg.i18n.viewExplorer || 'Explorateur' )
				)
		);
		$toggle.append(
			$( '<button type="button" class="gi-media-folders-view-btn" data-view="tree"/>' )
				.html(
					'<span class="dashicons dashicons-list-view" aria-hidden="true"></span> ' +
						( cfg.i18n.viewTree || 'Liste' )
				)
		);
		$header.append( $toggle );
		$sidebar.prepend( $header );
		$sidebar.find( '#gi-media-folders-tree' ).before(
			$( '<nav class="gi-explorer-breadcrumb" id="gi-media-folders-breadcrumb" aria-label="' +
				( cfg.i18n.breadcrumbLabel || 'Emplacement' ) +
				'"></nav>' )
		);
		$sidebar.find( '#gi-media-folders-tree' ).addClass( 'gi-media-folders-tree-panel' );
		$( '.gi-media-folders-view-btn[data-view="' + currentView + '"]' ).addClass( 'is-active' );
	}

	$( document ).on( 'click', '.gi-media-folders-view-btn', function () {
		setView( $( this ).data( 'view' ) );
	} );

	$( document ).on( 'click', '.gi-explorer-toggle:not(.gi-explorer-toggle--spacer)', function ( e ) {
		e.preventDefault();
		e.stopPropagation();
		var $toggle = $( this );
		var $children = $toggle.closest( '.gi-media-folder-node' ).children( '.gi-explorer-children' );
		var expanded = $toggle.attr( 'aria-expanded' ) === 'true';
		$children.prop( 'hidden', expanded );
		$toggle.attr( 'aria-expanded', expanded ? 'false' : 'true' ).toggleClass( 'is-expanded', ! expanded );
	} );

	$( document ).on( 'click', '#gi-media-folders-tree .gi-explorer-item, #gi-media-folders-tree .gi-media-folder-btn', function () {
		setActiveFolder( $( this ).data( 'id' ) );
	} );

	$( document ).on( 'click', '.gi-explorer-breadcrumb .gi-explorer-crumb:not(:disabled)', function () {
		setActiveFolder( $( this ).data( 'id' ) );
	} );

	$( '#gi-media-folder-create' ).on( 'click', function () {
		var name = $( '#gi-media-folder-new-name' ).val();
		if ( ! name ) {
			return;
		}
		post( 'gi_toolkit_media_folders_create', { name: name, parent: state.currentFolder } ).done( function () {
			$( '#gi-media-folder-new-name' ).val( '' );
			loadTree().then( function () {
				setActiveFolder( state.currentFolder );
			} );
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
		post( 'gi_toolkit_media_folders_rename', { term_id: state.currentFolder, name: name } ).done( loadTree );
	} );

	$( '#gi-media-folder-delete-btn' ).on( 'click', function () {
		if ( ! state.currentFolder || ! window.confirm( cfg.i18n.confirmDelete ) ) {
			return;
		}
		post( 'gi_toolkit_media_folders_delete', { term_id: state.currentFolder } ).done( function () {
			state.currentFolder = 0;
			loadTree().then( function () {
				setActiveFolder( 0 );
			} );
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
					loadTree().then( function () {
						setActiveFolder( state.currentFolder );
					} );
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
		injectExplorerChrome();
		loadTree().then( function () {
			setActiveFolder( 0 );
		} );
	} );
} )( jQuery );
