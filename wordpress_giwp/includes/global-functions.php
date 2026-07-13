<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Get the options
 * 
 * @since   1.0.0
 * @return  array
 */
function gi_toolkit_options( $type = 'all' ) {

	if ( 'normal' == $type ) {
		$modules = Gi_Toolkit_Modules_Data::modules_normal_values();
	} elseif ( 'translation' == $type ) {
		$modules = Gi_Toolkit_Modules_Data::modules_translation_values();
	} else {
		$modules = Gi_Toolkit_Modules_Data::modules_values();
	}

    return $modules;
}

/**
 * Get the options groups
 * 
 * @since   1.0.0
 * @return  array
 */
function gi_toolkit_settings_groups() {
    $groups = Gi_Toolkit_Modules_Data::modules_groups();
    return $groups;
}

/**
 * Get the options groups
 * 
 * @since   2.8.0
 * @return  array
 */
function gi_toolkit_ai_modules() {
    return array(
        'openai'  => array(
            'name'    => 'OpenAI',
            'options' => array(
                'gpt-4.1' => array(
                    'name'    => 'GPT-4.1',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4.1-mini' => array(
                    'name'    => 'GPT-4.1 mini',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4.1-nano' => array(
                    'name'    => 'GPT-4.1 nano',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4' => array(
                    'name'    => 'GPT-4',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4-turbo' => array(
                    'name'    => 'GPT-4 Turbo',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4o' => array(
                    'name'    => 'GPT-4o',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-4o-mini' => array(
                    'name'    => 'GPT-4o mini',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'chatgpt-4o-latest' => array(
                    'name'    => 'ChatGPT-4o',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gpt-3.5-turbo' => array(
                    'name'    => 'GPT-3.5 Turbo',
                    'support' => array( 'texttotext' ),
                ),
                'o4-mini' => array(
                    'name'    => 'o4-mini',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'o3' => array(
                    'name'    => 'o3',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
				'o3-mini' => array(
                    'name'    => 'o3-mini',
                    'support' => array( 'texttotext' ),
                ),
                'o1' => array(
                    'name'    => 'o1',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'o1-pro' => array(
                    'name'    => 'o1-pro',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
            ),
        ),
        'gemini'  => array(
            'name'    => 'Gemini',
            'options' => array(
                'gemini-2.0-flash' => array(
                    'name'    => '2.0 Flash',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-2.0-flash-lite' => array(
                    'name'    => '2.0 Flash Lite',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-1.5-pro' => array(
                    'name'    => '1.5 Pro',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-1.5-flash' => array(
                    'name'    => '1.5 Flash',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
                'gemini-1.5-flash-8b' => array(
                    'name'    => '1.5 Flash-8b',
                    'support' => array( 'texttotext', 'imagetotext' ),
                ),
            ),
        ),
		'mistral' => array(
			'name'    => 'Mistral',
			'options' => array(
				'pixtral-12b-latest' => array(
					'name'    => 'Pixtral 12B',
					'support' => array( 'texttotext', 'imagetotext' ),
				),
				'pixtral-large-latest' => array(
					'name'    => 'Pixtral Large',
					'support' => array( 'texttotext', 'imagetotext' ),
				),
				'mistral-small-latest' => array(
					'name'    => 'Mistral Small',
					'support' => array( 'texttotext', 'imagetotext' ),
				),
			),
		),
        'claude' => array(
            'name'    => 'Claude',
            'options' => array(
                'claude-3-7-sonnet-latest' => array(
                    'name'    => '3.7 Sonnet',
                    'support' => array('texttotext', 'imagetotext'),
                ),
                'claude-3-5-sonnet-latest' => array(
                    'name'    => '3.5 Sonnet',
                    'support' => array('texttotext', 'imagetotext'),
                ),
                'claude-3-5-haiku-latest' => array(
                    'name'    => '3.5 Haiku',
                    'support' => array('texttotext', 'imagetotext'),
                ),
            ),
        ),
    );
}

/**
 * Allowed tags for SVG files
 * 
 * @since   1.0.0
 * @return  array
 */
function gi_toolkit_allowed_tags_for_svg_files() {
    $allowedtags = array(
        'svg' => array(
            'class'               => true,
            'xmlns'               => true,
            'width'               => true,
            'height'              => true,
            'viewbox'             => true,
            'preserveaspectratio' => true,
            'fill'                => true,
            'aria-hidden'         => true,
            'focusable'           => true,
            'role'                => true,
        ),
        'path' => array(
            'fill'      => true,
            'fill-rule' => true,
            'd'         => true,
            'transform' => true,
        ),
        'polygon' => array(
            'fill'      => true,
            'fill-rule' => true,
            'points'    => true,
            'transform' => true,
            'focusable' => true,
        ),
        'rect' => array(
            'fill'      => true,
            'fill-rule' => true,
            'height'    => true,
            'width'     => true,
            'x'         => true,
            'y'         => true,
        ),
        'line' => array(
            'fill'         => true,
            'fill-rule'    => true,
            'x1'           => true,
            'x2'           => true,
            'y1'           => true,
            'y2'           => true,
            'stroke'       => true,
            'stroke-width' => true,
            'transform'    => true,
        ),
        'defs' => array(
            'id' => true,
        ),
        'clipPath' => array(
            'id' => true,
        ),
        'g' => array(
            'clip-path' => true,
            'mask'      => true,
        ),
        'circle' => array(
            'cx'   => true,
            'cy'   => true,
            'r'    => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-dasharray' => true,
            'stroke-linecap' => true,
        ),
        'mask' => array(
            'id'        => true,
            'fill'      => true,
            'style'     => true,
            'maskUnits' => true,
            'x'         => true,
            'y'         => true,
            'width'     => true,
            'height'    => true,
        ),
        'image' => array(
            'id'      => true,
            'href'    => true,
            'x'       => true,
            'y'       => true,
            'width'   => true,
            'height'  => true,
            'clip'    => true,
            'mask'    => true,
            'opacity' => true,
            'xlink:href' => true,
        ),
        'defs' => array(
            'id' => true,
        ),
        'pattern' => array(
            'id'      => true,
            'x'       => true,
            'y'       => true,
            'width'   => true,
            'height'  => true,
            'patternUnits' => true,
            'patternContentUnits' => true,
        ),
        'use' => array(
            'id' => true,
            'x'  => true,
            'y'  => true,
            'xlink:href' => true,
            'transform' => true,
        ),
    );
    return $allowedtags;     
}

/**
 * gi_toolkit_kses_svg
 *
 * @param  mixed $svg
 * @return void
 */
function gi_toolkit_kses_svg( $svg ) {
    return wp_kses($svg, gi_toolkit_allowed_tags_for_svg_files());
}

/**
 * gi_toolkit_kses_svg_by_path
 *
 * @param  mixed $relative_path
 * @return void
 */
function gi_toolkit_kses_svg_by_path( $relative_path ) {
    $svg = file_get_contents( GI_TOOLKIT_PLUGIN_PATH . $relative_path );
    return wp_kses($svg, gi_toolkit_allowed_tags_for_svg_files());
}

/**
 * gi_toolkit_folders
 *
 * @return void
 */
function gi_toolkit_data_dir() {
	static $resolved = null;

	if ( null !== $resolved ) {
		return $resolved;
	}

	$canonical = WP_CONTENT_DIR . '/gi-toolkit';
	$legacy    = WP_CONTENT_DIR . '/gi_toolkit';

	if ( is_dir( $legacy ) && ! is_dir( $canonical ) ) {
		$resolved = $legacy;
		return $resolved;
	}

	$resolved = $canonical;
	return $resolved;
}

function gi_toolkit_folders(){
    $path    = WP_CONTENT_DIR;
    $folders = array(
        'gi-toolkit' => array(
			'wp-config-backup' => array(),
		),
    );

    /**
     * Filter the folders and subfolders to be created.
     *
     * @since 2.0.0
     *
     * @param array    $folders     Array of folders and subfolders.
     */
    $folders = apply_filters( 'gi_toolkit/folders', $folders );

    gi_toolkit_recursive_mkdir( $folders, $path );

    return gi_toolkit_data_dir();
}

/**
 * gi_toolkit_create_index_file
 *
 * @param  mixed $path
 * @return void
 */
function gi_toolkit_create_index_file($path){
    if( is_dir( $path ) ){
        $index_file = $path . '/index.php';
        if( !file_exists( $index_file ) ){
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }
    }
}

/**
 * gi_toolkit_get_folder_url
 *
 * @return void
 */
function gi_toolkit_get_folder_url(){
    return content_url( basename( gi_toolkit_data_dir() ) );
}

/**
 * gi_toolkit_recursive_mkdir
 *
 * @param  mixed $folders
 * @param  mixed $root_dir_path
 * @return void
 */
function gi_toolkit_recursive_mkdir( $folders, $root_dir_path ){
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	WP_Filesystem();

    foreach ($folders as $folder_name => $sub_folders) {
        $folder_path = $root_dir_path . '/' . $folder_name;
        if ( ! is_dir( $folder_path ) ) {
            $wp_filesystem->mkdir( $folder_path, FS_CHMOD_DIR );
            if ( ! is_dir( $folder_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
                wp_mkdir_p( $folder_path );
            }
        }

        gi_toolkit_create_index_file($folder_path);

        if( is_array( $sub_folders ) && !empty( $sub_folders ) ){
            gi_toolkit_recursive_mkdir( $sub_folders, $folder_path );
        }
    }
}

/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 */
function gi_toolkit_clean( $var ) {

    if ( is_array( $var ) ) {
		return array_map( 'gi_toolkit_clean', $var );
	} else {
		return sanitize_text_field( $var );
	}
}

/**
 * gi_toolkit_recursive_rmdir
 *
 * @param  mixed $folders
 * @param  mixed $root_dir_path
 * @return void
 */
function gi_toolkit_zip_file_or_folder( $paths, $output_path ){
    $zip = new ZipArchive();
    $zip->open( $output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    if( is_array( $paths ) ){
        foreach ($paths as $path) {
            if( is_dir( $path ) ){
                $folderName = basename($path);
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator( $path ),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath     = $file->getRealPath();
                        $relativePath = $folderName . '/' . substr($filePath, strlen($path) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            } else {
                $zip->addFile( $path, basename( $path ) );
            }
        }
    } else {
        $path = $paths;
        if( is_dir( $path ) ){
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $path ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath     = $file->getRealPath();
                    $realPath     = realpath($path);
                    $relativePath = str_replace($realPath, '', $filePath);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        } else {
            $zip->addFile( $path, basename( $path ) );
        }
    }
    
    $zip->close();
}

/**
 * Version interne : toutes les fonctionnalités du cœur du plugin sont déverrouillées (aucune édition payante séparée).
 */
function gi_toolkit_is_pro() {
	return true;
}

/**
 * Chemin absolu d’un fichier module sous admin/modules/ (core/ ou pro/).
 * Sur Linux, tolère une casse différente du dossier (ex. Pro/ au lieu de pro/).
 *
 * @param string $relative_path Chemin relatif, ex. pro/class-update-logs.php
 * @return string|false Chemin normalisé ou false
 */
function gi_toolkit_resolve_module_path( $relative_path ) {
	if ( ! is_string( $relative_path ) || '' === $relative_path ) {
		return false;
	}
	$relative_path = str_replace( '\\', '/', $relative_path );
	if ( false !== strpos( $relative_path, '..' ) ) {
		return false;
	}

	$base      = trailingslashit( wp_normalize_path( GI_TOOLKIT_PLUGIN_PATH ) ) . 'admin/modules/';
	$candidate = wp_normalize_path( $base . $relative_path );

	if ( is_file( $candidate ) ) {
		return apply_filters( 'gi_toolkit_resolved_module_path', $candidate, $relative_path );
	}

	$parts = array_values( array_filter( explode( '/', $relative_path ), 'strlen' ) );
	if ( empty( $parts ) ) {
		return apply_filters( 'gi_toolkit_resolved_module_path', false, $relative_path );
	}

	$current = wp_normalize_path( rtrim( $base, '/' ) );
	foreach ( $parts as $segment ) {
		if ( ! is_dir( $current ) ) {
			return apply_filters( 'gi_toolkit_resolved_module_path', false, $relative_path );
		}
		$items = @scandir( $current );
		if ( ! is_array( $items ) ) {
			return apply_filters( 'gi_toolkit_resolved_module_path', false, $relative_path );
		}
		$found = false;
		foreach ( $items as $node ) {
			if ( '.' === $node || '..' === $node ) {
				continue;
			}
			if ( 0 === strcasecmp( $node, $segment ) ) {
				$current = $current . '/' . $node;
				$found   = true;
				break;
			}
		}
		if ( ! $found ) {
			return apply_filters( 'gi_toolkit_resolved_module_path', false, $relative_path );
		}
	}

	$resolved = is_file( $current ) ? wp_normalize_path( $current ) : false;
	return apply_filters( 'gi_toolkit_resolved_module_path', $resolved, $relative_path );
}

/**
 * Slugs de sous-pages affichées en bas du menu GI-Toolkit (après le séparateur).
 *
 * @return string[]
 */
function gi_toolkit_settings_submenu_footer_slugs() {
	return apply_filters(
		'gi_toolkit_settings_submenu_footer_slugs',
		array( 'gi-toolkit-settings-mail-catcher' )
	);
}

/**
 * Compare les titres de sous-menu GI-Toolkit (tri alphabétique).
 *
 * @param array<int, string> $a Entrée sous-menu.
 * @param array<int, string> $b Entrée sous-menu.
 * @return int
 */
function gi_toolkit_compare_submenu_titles( $a, $b ) {
	$title_a = wp_strip_all_tags( $a[0] ?? '' );
	$title_b = wp_strip_all_tags( $b[0] ?? '' );
	return strcasecmp( $title_a, $title_b );
}

/**
 * Réordonne le sous-menu GI-Toolkit : page principale, puis modules (↳), puis entrées de pied.
 * Corrige les modules enregistrés trop tôt (ex. Connect Matomo au-dessus de « Modules »).
 *
 * @return void
 */
function gi_toolkit_normalize_settings_submenu() {
	global $submenu;

	$parent = 'gi-toolkit-settings';
	if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
		return;
	}

	$footer_slugs = gi_toolkit_settings_submenu_footer_slugs();
	if ( ! is_array( $footer_slugs ) ) {
		$footer_slugs = array();
	}
	$main         = array();
	$modules      = array();
	$footer       = array();
	$other        = array();

	foreach ( $submenu[ $parent ] as $item ) {
		if ( ! is_array( $item ) ) {
			$other[] = $item;
			continue;
		}

		if ( ! isset( $item[2] ) ) {
			$other[] = $item;
			continue;
		}

		$slug = (string) $item[2];

		if ( $slug === $parent ) {
			$main[] = $item;
		} elseif ( in_array( $slug, $footer_slugs, true ) ) {
			$footer[] = $item;
		} elseif ( 0 === strpos( $slug, 'gi-toolkit-settings-' ) ) {
			$modules[] = $item;
		} else {
			$other[] = $item;
		}
	}

	if ( empty( $main ) && ! empty( $submenu[ $parent ][0] ) ) {
		$main[] = $submenu[ $parent ][0];
	}

	if ( count( $modules ) > 1 ) {
		usort( $modules, 'gi_toolkit_compare_submenu_titles' );
	}
	if ( count( $footer ) > 1 ) {
		usort( $footer, 'gi_toolkit_compare_submenu_titles' );
	}

	$merged   = array_merge( $main, $modules, $footer, $other );
	$position = 0;
	$ordered  = array();

	foreach ( $merged as $item ) {
		$ordered[ $position ] = $item;
		$position++;
	}

	$submenu[ $parent ] = $ordered;
}

/**
 * Styles admin pour les sous-pages des modules (dossier pro/).
 */
function gi_toolkit_pro_module_admin_styles() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done       = true;
	$assets     = include GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/global-admin.asset.php';
	wp_enqueue_style(
		'gi-toolkit-pro-module-admin',
		GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.css',
		array(),
		$assets['version'],
		'all'
	);
}

/**
 * Redirection après enregistrement d’options sur une page module.
 *
 * @param string $page_slug Slug complet ex. gi-toolkit-settings-foo.
 */
function gi_toolkit_pro_module_redirect_saved( $page_slug ) {
	wp_safe_redirect(
		add_query_arg(
			'gi_toolkit_pro_saved',
			'1',
			admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) )
		)
	);
	exit;
}

/**
 * Vérifie une soumission de formulaire sur une page module pro (POST + nonce).
 *
 * @param string $page_slug   ex. gi-toolkit-settings-foo.
 * @param string $nonce_action ex. gi_toolkit_save_foo.
 * @return bool True si la sauvegarde doit être traitée.
 */
function gi_toolkit_pro_begin_save( $page_slug, $nonce_action ) {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
	$posted_page = '';
	if ( ! empty( $_POST['page'] ) ) {
		$posted_page = sanitize_key( wp_unslash( $_POST['page'] ) );
	} elseif ( ! empty( $_GET['page'] ) ) {
		$posted_page = sanitize_key( wp_unslash( $_GET['page'] ) );
	}
	if ( '' === $posted_page || $posted_page !== sanitize_key( $page_slug ) ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['gi_toolkit_pro_save'] ) ) {
		return false;
	}
	check_admin_referer( $nonce_action );
	return true;
}

/**
 * Get languages
 * 
 * @since   2.8.0
 */
function gi_toolkit_languages() {
	$languages = array(
		'en' => array(
			'display_name' => __( 'English', 'gi-toolkit' ),
			'english_name' => 'English',
			'iso'          => 'en',
		),
		'fr' => array(
			'display_name' => __( 'French', 'gi-toolkit' ),
			'english_name' => 'French',
			'iso'          => 'fr',
		),
		'es' => array(
			'display_name' => __( 'Spanish', 'gi-toolkit' ),
			'english_name' => 'Spanish',
			'iso'          => 'es',
		),
		'de' => array(
			'display_name' => __( 'German', 'gi-toolkit' ),
			'english_name' => 'German',
			'iso'          => 'de',
		),
		'it' => array(
			'display_name' => __( 'Italian', 'gi-toolkit' ),
			'english_name' => 'Italian',
			'iso'          => 'it',
		),
		'pt' => array(
			'display_name' => __( 'Portuguese', 'gi-toolkit' ),
			'english_name' => 'Portuguese',
			'iso'          => 'pt',
		),
		'nl' => array(
			'display_name' => __( 'Dutch', 'gi-toolkit' ),
			'english_name' => 'Dutch',
			'iso'          => 'nl',
		),
		'ru' => array(
			'display_name' => __( 'Russian', 'gi-toolkit' ),
			'english_name' => 'Russian',
			'iso'          => 'ru',
		),
		'ja' => array(
			'display_name' => __( 'Japanese', 'gi-toolkit' ),
			'english_name' => 'Japanese',
			'iso'          => 'ja',
		),
		'zh' => array(
			'display_name' => __( 'Chinese', 'gi-toolkit' ),
			'english_name' => 'Chinese',
			'iso'          => 'zh',
		),
		'ar' => array(
			'display_name' => __( 'Arabic', 'gi-toolkit' ),
			'english_name' => 'Arabic',
			'iso'          => 'ar',
		),
		'ko' => array(
			'display_name' => __( 'Korean', 'gi-toolkit' ),
			'english_name' => 'Korean',
			'iso'          => 'ko',
		),
		'hi' => array(
			'display_name' => __( 'Hindi', 'gi-toolkit' ),
			'english_name' => 'Hindi',
			'iso'          => 'hi',
		),
		'tr' => array(
			'display_name' => __( 'Turkish', 'gi-toolkit' ),
			'english_name' => 'Turkish',
			'iso'          => 'tr',
		),
		'sv' => array(
			'display_name' => __( 'Swedish', 'gi-toolkit' ),
			'english_name' => 'Swedish',
			'iso'          => 'sv',
		),
		'pl' => array(
			'display_name' => __( 'Polish', 'gi-toolkit' ),
			'english_name' => 'Polish',
			'iso'          => 'pl',
		),
		'cs' => array(
			'display_name' => __( 'Czech', 'gi-toolkit' ),
			'english_name' => 'Czech',
			'iso'          => 'cs',
		),
		'el' => array(
			'display_name' => __( 'Greek', 'gi-toolkit' ),
			'english_name' => 'Greek',
			'iso'          => 'el',
		),
		'he' => array(
			'display_name' => __( 'Hebrew', 'gi-toolkit' ),
			'english_name' => 'Hebrew',
			'iso'          => 'he',
		),
	);

	return $languages;	
}

/**
 * Get current IP
 * 
 * @since 2.12.0
 */
function gi_toolkit_get_current_ip() {
	if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
	} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
	} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED'] ) );
	} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED_FOR'] ) );
	} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED'] ) );
	} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	} else {
		return '';
	}
}