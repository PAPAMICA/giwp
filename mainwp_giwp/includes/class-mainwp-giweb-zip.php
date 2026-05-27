<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Construction et publication du ZIP GI-Toolkit pour installation distante.
 */
class MainWP_GIWeb_Zip {

	const PLUGIN_SLUG     = 'gi-toolkit';
	const PLUGIN_BASENAME = 'gi-toolkit/gi-toolkit.php';
	const ZIP_FILENAME    = 'gi-toolkit.zip';

	/**
	 * @return string
	 */
	public static function source_path() {
		return trailingslashit( MAINWP_GIWEB_GI_TOOLKIT_PATH );
	}

	/**
	 * @return string|false
	 */
	public static function get_zip_path() {
		$built = self::build_if_needed();
		return $built ? self::zip_storage_path() : false;
	}

	/**
	 * URL publique du ZIP (accessible par le site enfant).
	 *
	 * @return string|false
	 */
	public static function get_public_url() {
		$path = self::get_zip_path();
		if ( ! $path ) {
			return false;
		}
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}
		return trailingslashit( $upload['baseurl'] ) . 'mainwp-giweb/' . self::ZIP_FILENAME;
	}

	/**
	 * URL utilisée pour installer GI-Toolkit sur les sites enfants (personnalisable).
	 *
	 * @return string|false
	 */
	public static function get_install_url() {
		$settings = MainWP_GIWeb_Settings::get();
		$custom   = trim( (string) ( $settings['client_zip_url'] ?? '' ) );
		if ( '' !== $custom && filter_var( $custom, FILTER_VALIDATE_URL ) ) {
			return $custom;
		}

		return self::get_public_url();
	}

	/**
	 * @return string
	 */
	private static function zip_storage_path() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . 'mainwp-giweb/' . self::ZIP_FILENAME;
	}

	/**
	 * @return bool
	 */
	public static function build_if_needed() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$source = self::source_path();
		if ( ! is_file( $source . 'gi-toolkit.php' ) ) {
			return false;
		}

		$zip_path = self::zip_storage_path();
		$dir      = dirname( $zip_path );
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$source_mtime = self::latest_source_mtime( $source );
		if ( is_file( $zip_path ) && filemtime( $zip_path ) >= $source_mtime ) {
			return true;
		}

		return self::build_zip( $source, $zip_path );
	}

	/**
	 * @param string $source Répertoire source.
	 * @param string $zip_path Fichier ZIP cible.
	 * @return bool
	 */
	private static function build_zip( $source, $zip_path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$source = wp_normalize_path( $source );
		$files  = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		$exclude = array( '.git', '.github', '.DS_Store', 'node_modules', '.cursor' );

		foreach ( $files as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$file_path = wp_normalize_path( $file->getRealPath() );
			$relative  = ltrim( str_replace( $source, '', $file_path ), '/' );

			foreach ( $exclude as $skip ) {
				if ( 0 === strpos( $relative, $skip . '/' ) || basename( $relative ) === $skip ) {
					continue 2;
				}
			}

			$zip->addFile( $file_path, self::PLUGIN_SLUG . '/' . $relative );
		}

		$zip->close();
		return is_file( $zip_path );
	}

	/**
	 * @param string $source Répertoire source.
	 * @return int
	 */
	private static function latest_source_mtime( $source ) {
		$max = (int) filemtime( $source . 'gi-toolkit.php' );
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $files as $file ) {
			if ( $file->isFile() ) {
				$max = max( $max, (int) $file->getMTime() );
			}
		}
		return $max;
	}
}
