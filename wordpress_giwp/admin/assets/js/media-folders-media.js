( function ( $ ) {
	'use strict';

	var cfg = window.giToolkitMediaFolders || {};
	var currentFolder = 0;
	var mediaReadyBound = false;
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

	function setStoredView( view ) {
		try {
			localStorage.setItem( STORAGE_KEY, view );
		} catch ( err ) {
			// ignore
		}
	}

	/**
	 * Index plat des dossiers (fil d’Ariane, expansion).
	 *
	 * @param {Array} nodes    Nœuds.
	 * @param {number} parentId Parent.
	 */
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

	/**
	 * @param {number} folderId ID dossier.
	 * @return {Array}
	 */
	function getBreadcrumbPath( folderId ) {
		var path = [];
		var id = folderId;
		while ( id && folderIndex[ id ] ) {
			path.unshift( folderIndex[ id ] );
			id = folderIndex[ id ].parent;
		}
		return path;
	}

	function renderClassicTree( nodes, $ul, depth ) {
		depth = depth || 0;
		nodes.forEach( function ( node ) {
			var $li = $( '<li class="gi-media-folder-node"/>' ).attr( 'data-id', node.id );
			var label = node.name + ( node.count ? ' (' + node.count + ')' : '' );
			var $btn = $( '<button type="button" class="gi-media-folder-btn"/>' )
				.text( label )
				.attr( 'data-id', node.id )
				.attr( 'data-search', label.toLowerCase() )
				.css( 'padding-left', 8 + depth * 12 + 'px' );
			$li.append( $btn );
			if ( node.children && node.children.length ) {
				var $child = $( '<ul class="gi-media-folder-children"/>' );
				renderClassicTree( node.children, $child, depth + 1 );
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
			var searchText = ( node.name + ' ' + ( node.count || '' ) ).toLowerCase();

			var $toggle = $( '<button type="button" class="gi-explorer-toggle"/>' )
				.attr( 'aria-expanded', 'false' )
				.attr(
					'aria-label',
					hasChildren ? cfg.i18n.expandFolder || 'Développer' : ''
				);
			if ( hasChildren ) {
				$toggle.append( '<span class="gi-explorer-chevron" aria-hidden="true"></span>' );
			} else {
				$toggle.addClass( 'gi-explorer-toggle--spacer' ).prop( 'disabled', true );
			}

			var $item = $( '<button type="button" class="gi-explorer-item"/>' )
				.attr( 'data-id', node.id )
				.attr( 'data-search', searchText );
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
				$( '<button type="button" class="gi-media-folder-btn gi-media-folder-btn--all"/>' )
					.text( allLabel )
					.attr( 'data-id', '0' )
					.attr( 'data-search', allLabel.toLowerCase() )
			);
		}
		$root.append( $all );
	}

	function rebuildTree() {
		var $tree = $( '#gi-media-folders-native-tree' );
		$tree.empty();
		var $root = $( '<ul class="gi-media-folders-tree-root"/>' );
		appendAllMediaNode( $root );
		if ( currentView === 'explorer' ) {
			renderExplorerTree( treeData, $root );
		} else {
			renderClassicTree( treeData, $root, 0 );
		}
		$tree.append( $root );
		setActiveFolderButton( currentFolder );
		if ( currentView === 'explorer' ) {
			expandToFolder( currentFolder );
		}
		updateBreadcrumb( currentFolder );
	}

	function setView( view ) {
		currentView = view === 'tree' ? 'tree' : 'explorer';
		setStoredView( currentView );
		$( 'body' )
			.removeClass( 'gi-media-folders-view-tree gi-media-folders-view-explorer' )
			.addClass( 'gi-media-folders-view-' + currentView );
		$( '.gi-media-folders-view-btn' ).removeClass( 'is-active' );
		$( '.gi-media-folders-view-btn[data-view="' + currentView + '"]' ).addClass( 'is-active' );
		rebuildTree();
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
					( cfg.i18n.breadcrumbHome || cfg.i18n.allMedia || 'Accueil' )
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
			var $node = $( '.gi-media-folder-node[data-id="' + id + '"]' );
			$node.children( '.gi-explorer-children' ).prop( 'hidden', false );
			$node
				.find( '> .gi-explorer-row .gi-explorer-toggle:not(.gi-explorer-toggle--spacer)' )
				.attr( 'aria-expanded', 'true' )
				.addClass( 'is-expanded' );
			id = folderIndex[ id ].parent;
		}
	}

	function setActiveFolderButton( folderId ) {
		var $sidebar = $( '.gi-media-folders-native-sidebar' );
		$sidebar.find( '.gi-media-folder-node' ).removeClass( 'is-active' );
		$sidebar.find( '.gi-explorer-item, .gi-media-folder-btn' ).removeClass( 'is-selected' );

		var $target = $sidebar
			.find( '.gi-explorer-item[data-id="' + folderId + '"], .gi-media-folder-btn[data-id="' + folderId + '"]' )
			.first();
		$target.addClass( 'is-selected' );
		$target.closest( '.gi-media-folder-node' ).addClass( 'is-active' );

		if ( $target.length && currentView === 'explorer' ) {
			var el = $target.get( 0 );
			if ( el && el.scrollIntoView ) {
				el.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
			}
		}
	}

	function syncNativeFolderSelect( folderId ) {
		var $select = $( '#gi-media-folder-filter' );
		if ( $select.length ) {
			$select.val( folderId > 0 ? String( folderId ) : '' );
		}
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

	function refreshAttachmentsGrid() {
		if ( typeof wp === 'undefined' || ! wp.media || ! wp.media.frames || ! wp.media.frames.browse ) {
			return false;
		}

		var frame = wp.media.frames.browse;
		var library = frame.state().get( 'library' );
		if ( ! library || ! library.props ) {
			return false;
		}

		if ( currentFolder > 0 ) {
			library.props.unset( 'tax_query' );
			library.props.set( {
				gi_media_folder: currentFolder,
				paged: 1,
				ignore: +new Date(),
			} );
		} else {
			library.props.unset( 'gi_media_folder' );
			library.props.unset( 'tax_query' );
			library.props.set( {
				paged: 1,
				ignore: +new Date(),
			} );
		}

		return true;
	}

	function applyFolderFilter( folderId ) {
		currentFolder = parseInt( folderId, 10 ) || 0;
		setActiveFolderButton( currentFolder );
		syncNativeFolderSelect( currentFolder );
		updateUrl( currentFolder );
		updateBreadcrumb( currentFolder );
		if ( currentView === 'explorer' ) {
			expandToFolder( currentFolder );
		}

		if ( refreshAttachmentsGrid() ) {
			return;
		}

		var attempts = 0;
		var timer = window.setInterval( function () {
			attempts += 1;
			if ( refreshAttachmentsGrid() || attempts > 40 ) {
				window.clearInterval( timer );
			}
		}, 150 );
	}

	function stripFolderFromQueryString( data ) {
		return String( data )
			.replace( /&?query%5Bgi_media_folder%5D=[^&]*/gi, '' )
			.replace( /&?query\[gi_media_folder\]=[^&]*/gi, '' );
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
				var next = stripFolderFromQueryString( data );
				if ( currentFolder > 0 ) {
					next += '&query[gi_media_folder]=' + encodeURIComponent( currentFolder );
				}
				settings.data = next;
			}
		} );
	}

	function filterFolderTree( query ) {
		query = ( query || '' ).trim().toLowerCase();
		var $tree = $( '#gi-media-folders-native-tree' );
		var $nodes = $tree.find( '.gi-media-folder-node' );

		if ( ! query ) {
			$nodes.show();
			$tree.find( '.gi-media-folder-children, .gi-explorer-children' ).each( function () {
				var $ul = $( this );
				if ( currentView === 'explorer' && !$ul.closest( '.gi-media-folder-node.is-active' ).length ) {
					var hasExpanded = $ul.siblings( '.gi-explorer-row' ).find( '.gi-explorer-toggle.is-expanded' ).length;
					if ( !hasExpanded ) {
						$ul.prop( 'hidden', true );
					}
				}
			} );
			return;
		}

		$tree.find( '.gi-explorer-children, .gi-media-folder-children' ).prop( 'hidden', false );

		$nodes.each( function () {
			var $node = $( this );
			var searchText = String(
				$node.find( '> .gi-explorer-row .gi-explorer-item, > .gi-media-folder-btn' ).first().attr( 'data-search' ) ||
					''
			);
			var selfMatch = searchText.indexOf( query ) !== -1;

			var descendantMatch = false;
			$node.find( '.gi-explorer-item, .gi-media-folder-btn' ).each( function () {
				if ( $( this ).closest( '.gi-media-folder-node' ).get( 0 ) === $node.get( 0 ) ) {
					return;
				}
				var t = String( $( this ).attr( 'data-search' ) || '' );
				if ( t.indexOf( query ) !== -1 ) {
					descendantMatch = true;
				}
			} );

			var show = selfMatch || descendantMatch;
			$node.toggle( show );
			if ( descendantMatch ) {
				$node.children( '.gi-explorer-children, .gi-media-folder-children' ).prop( 'hidden', false );
				$node.find( '> .gi-explorer-row .gi-explorer-toggle' ).addClass( 'is-expanded' ).attr( 'aria-expanded', 'true' );
			}
		} );

		$nodes.filter( ':visible' ).parents( '.gi-media-folder-node' ).show();
	}

	function loadTreeData() {
		return post( 'gi_toolkit_media_folders_tree' ).done( function ( res ) {
			if ( ! res.success ) {
				return;
			}
			treeData = res.data.tree || [];
			folderIndex = {};
			indexTree( treeData, 0 );

			var $sel = $( '#gi-media-native-move-target' );
			$sel.find( 'option:not(:first)' ).remove();
			( res.data.terms || [] ).forEach( function ( t ) {
				$sel.append( $( '<option/>' ).val( t.id ).text( t.name ) );
			} );

			rebuildTree();

			var params = new URLSearchParams( window.location.search );
			var fromUrl = parseInt( params.get( 'gi_media_folder' ), 10 ) || 0;
			applyFolderFilter( fromUrl );
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

		currentView = getStoredView();
		$( 'body' ).addClass( 'gi-media-folders-active gi-media-folders-view-' + currentView );

		var $layout = $( '<div class="gi-media-folders-native-layout"/>' );
		var $sidebar = $( '<aside class="gi-media-folders-native-sidebar"/>' );

		var $header = $( '<div class="gi-media-folders-sidebar-header"/>' );
		$header.append(
			'<h2 class="gi-media-folders-native-title">' + ( cfg.i18n.foldersTitle || 'Dossiers' ) + '</h2>'
		);
		var $toggle = $( '<div class="gi-media-folders-view-toggle" role="group"/>' );
		$toggle.append(
			$( '<button type="button" class="gi-media-folders-view-btn" data-view="explorer"/>' )
				.attr( 'title', cfg.i18n.viewExplorer || 'Explorateur' )
				.html( '<span class="dashicons dashicons-category" aria-hidden="true"></span>' )
				.append( document.createTextNode( ' ' + ( cfg.i18n.viewExplorer || 'Explorateur' ) ) )
		);
		$toggle.append(
			$( '<button type="button" class="gi-media-folders-view-btn" data-view="tree"/>' )
				.attr( 'title', cfg.i18n.viewTree || 'Liste' )
				.html( '<span class="dashicons dashicons-list-view" aria-hidden="true"></span>' )
				.append( document.createTextNode( ' ' + ( cfg.i18n.viewTree || 'Liste' ) ) )
		);
		$header.append( $toggle );
		$sidebar.append( $header );

		$sidebar.append(
			$( '<nav class="gi-explorer-breadcrumb" id="gi-media-folders-breadcrumb" aria-label="' +
				( cfg.i18n.breadcrumbLabel || 'Emplacement' ) +
				'"></nav>' )
		);

		$sidebar.append(
			$( '<input type="search" class="gi-media-folders-search" id="gi-media-folders-search" autocomplete="off" />' )
				.attr( 'placeholder', cfg.i18n.searchFolders || 'Rechercher un dossier…' )
		);
		$sidebar.append( '<div class="gi-media-folders-tree gi-media-folders-tree-panel" id="gi-media-folders-native-tree"/>' );

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

		$( '.gi-media-folders-view-btn[data-view="' + currentView + '"]' ).addClass( 'is-active' );

		$( '#gi-media-folders-search' ).on( 'input', function () {
			filterFolderTree( $( this ).val() );
		} );

		$( document ).on( 'click', '.gi-media-folders-view-btn', function () {
			setView( $( this ).data( 'view' ) );
		} );

		loadTreeData();
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
			loadTreeData();
			applyFolderFilter( currentFolder );
		} );
	}

	function folderIdFromClick( $el ) {
		var folderId = parseInt( $el.attr( 'data-id' ), 10 );
		return isNaN( folderId ) ? 0 : folderId;
	}

	$( document ).on( 'click', '.gi-explorer-toggle:not(.gi-explorer-toggle--spacer)', function ( e ) {
		e.preventDefault();
		e.stopPropagation();
		var $toggle = $( this );
		var $node = $toggle.closest( '.gi-media-folder-node' );
		var $children = $node.children( '.gi-explorer-children' );
		var expanded = $toggle.attr( 'aria-expanded' ) === 'true';
		if ( expanded ) {
			$children.prop( 'hidden', true );
			$toggle.attr( 'aria-expanded', 'false' ).removeClass( 'is-expanded' );
		} else {
			$children.prop( 'hidden', false );
			$toggle.attr( 'aria-expanded', 'true' ).addClass( 'is-expanded' );
		}
	} );

	$( document ).on( 'click', '.gi-explorer-item, .gi-media-folders-native-sidebar .gi-media-folder-btn', function ( e ) {
		e.preventDefault();
		applyFolderFilter( folderIdFromClick( $( this ) ) );
	} );

	$( document ).on( 'click', '.gi-explorer-breadcrumb .gi-explorer-crumb:not(:disabled)', function ( e ) {
		e.preventDefault();
		applyFolderFilter( folderIdFromClick( $( this ) ) );
	} );

	$( document ).on( 'change', '#gi-media-folder-filter', function () {
		applyFolderFilter( parseInt( $( this ).val(), 10 ) || 0 );
	} );

	$( function () {
		if ( ! $( 'body' ).hasClass( 'upload-php' ) ) {
			return;
		}
		bindMediaAjaxFallback();
		injectSidebar();
	} );
} )( jQuery );
