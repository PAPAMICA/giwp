<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-tri des médias dans des dossiers nommés comme le slug de la page source.
 */
class Gi_Toolkit_Media_Folders_Auto_Sort {

	/**
	 * @param bool $overwrite Écraser un classement existant.
	 * @return array{moved: int, skipped: int}
	 */
	public static function sort_all( $overwrite = false ) {
		$moved   = 0;
		$skipped = 0;

		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $attachment_ids as $attachment_id ) {
			$result = self::sort_attachment( (int) $attachment_id, $overwrite );
			if ( 'moved' === $result ) {
				++$moved;
			} else {
				++$skipped;
			}
		}

		return array(
			'moved'   => $moved,
			'skipped' => $skipped,
		);
	}

	/**
	 * @param int  $attachment_id ID attachment.
	 * @param bool $overwrite Écraser dossier existant.
	 * @return string moved|skipped
	 */
	public static function sort_attachment( $attachment_id, $overwrite = false ) {
		$attachment_id = (int) $attachment_id;

		if ( ! $overwrite && Gi_Toolkit_Media_Folders_Taxonomy::is_manual( $attachment_id ) ) {
			return 'skipped';
		}

		$terms = wp_get_object_terms( $attachment_id, Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY );
		if ( ! $overwrite && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return 'skipped';
		}

		$source_post = self::find_source_post( $attachment_id );
		if ( ! $source_post ) {
			return 'skipped';
		}

		$folder_id = self::get_or_create_folder_for_post( $source_post );
		if ( $folder_id < 1 ) {
			return 'skipped';
		}

		Gi_Toolkit_Media_Folders_Taxonomy::move_attachments( array( $attachment_id ), $folder_id, false );
		return 'moved';
	}

	/**
	 * @param int $attachment_id ID attachment.
	 * @return WP_Post|null
	 */
	private static function find_source_post( $attachment_id ) {
		$parent = (int) wp_get_post_parent_id( $attachment_id );
		if ( $parent > 0 ) {
			$post = get_post( $parent );
			if ( $post && in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
				return $post;
			}
		}

		global $wpdb;

		$thumb_post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
				$attachment_id
			)
		);
		if ( $thumb_post_id > 0 ) {
			$post = get_post( $thumb_post_id );
			if ( $post ) {
				return $post;
			}
		}

		$patterns = self::get_content_patterns( $attachment_id );
		if ( empty( $patterns ) ) {
			return null;
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );

		foreach ( get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $post_id ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			foreach ( $patterns as $pattern ) {
				if ( false !== strpos( $content, $pattern ) ) {
					return get_post( $post_id );
				}
			}
		}

		return null;
	}

	/**
	 * @param int $attachment_id ID attachment.
	 * @return array<int, string>
	 */
	private static function get_content_patterns( $attachment_id ) {
		$patterns = array(
			'wp-image-' . $attachment_id,
			'"id":' . $attachment_id,
			'wp:' . $attachment_id,
		);

		$url = wp_get_attachment_url( $attachment_id );
		if ( $url ) {
			$patterns[] = $url;
			$path       = wp_parse_url( $url, PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$patterns[] = $path;
			}
		}

		return array_unique( array_filter( $patterns ) );
	}

	/**
	 * @param WP_Post $post Post source.
	 * @return int Term ID ou 0.
	 */
	private static function get_or_create_folder_for_post( $post ) {
		$slug = sanitize_title( $post->post_name );
		if ( '' === $slug ) {
			$slug = sanitize_title( $post->post_title );
		}
		if ( '' === $slug ) {
			return 0;
		}

		$term = get_term_by( 'slug', $slug, Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term(
			$post->post_title ? $post->post_title : $slug,
			Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY,
			array( 'slug' => $slug )
		);

		if ( is_wp_error( $created ) ) {
			return 0;
		}

		return (int) ( $created['term_id'] ?? 0 );
	}

	/**
	 * Déplace le logo personnalisé vers le dossier header.
	 *
	 * @return bool
	 */
	public static function move_custom_logo_to_header() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id < 1 ) {
			return false;
		}

		$term = get_term_by( 'slug', Gi_Toolkit_Media_Folders_Taxonomy::ROOT_HEADER, Gi_Toolkit_Media_Folders_Taxonomy::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		Gi_Toolkit_Media_Folders_Taxonomy::move_attachments( array( $logo_id ), (int) $term->term_id, false );
		return true;
	}
}
