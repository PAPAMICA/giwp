<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomie gi_media_folder pour les attachments.
 */
class Gi_Toolkit_Media_Folders_Taxonomy {

	const TAXONOMY = 'gi_media_folder';

	const ROOT_HEADER = 'header';

	const ROOT_FOOTER = 'footer';

	/**
	 * @return void
	 */
	public static function register() {
		register_taxonomy(
			self::TAXONOMY,
			'attachment',
			array(
				'labels'            => array(
					'name'          => __( 'Dossiers média', 'gi-toolkit' ),
					'singular_name' => __( 'Dossier', 'gi-toolkit' ),
				),
				'public'            => false,
				'show_ui'           => false,
				'hierarchical'      => true,
				'rewrite'           => false,
				'query_var'         => false,
				'show_admin_column' => false,
			)
		);
	}

	/**
	 * Crée les dossiers racine header / footer.
	 *
	 * @return void
	 */
	public static function ensure_root_folders() {
		foreach ( array( self::ROOT_HEADER, self::ROOT_FOOTER ) as $slug ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term(
					ucfirst( $slug ),
					self::TAXONOMY,
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_folder_tree() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			$parent = (int) $term->parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => $parent,
				'count'  => (int) $term->count,
			);
		}

		return self::build_tree_branch( $by_parent, 0 );
	}

	/**
	 * @param array<int, array<int, array<string, mixed>>> $by_parent Index parent.
	 * @param int                                          $parent_id Parent ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_tree_branch( $by_parent, $parent_id ) {
		$branch = array();
		foreach ( $by_parent[ $parent_id ] ?? array() as $term ) {
			$term['children'] = self::build_tree_branch( $by_parent, (int) $term['id'] );
			$branch[]         = $term;
		}
		return $branch;
	}

	/**
	 * @param string $name Nom du dossier.
	 * @param int    $parent ID parent.
	 * @return int|WP_Error
	 */
	public static function create_folder( $name, $parent = 0 ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Nom de dossier invalide.', 'gi-toolkit' ) );
		}

		return wp_insert_term(
			$name,
			self::TAXONOMY,
			array(
				'parent' => max( 0, (int) $parent ),
				'slug'   => sanitize_title( $name ),
			)
		);
	}

	/**
	 * @param int    $term_id ID terme.
	 * @param string $name Nouveau nom.
	 * @return int|WP_Error
	 */
	public static function rename_folder( $term_id, $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Nom de dossier invalide.', 'gi-toolkit' ) );
		}

		return wp_update_term(
			(int) $term_id,
			self::TAXONOMY,
			array(
				'name' => $name,
				'slug' => sanitize_title( $name ),
			)
		);
	}

	/**
	 * @param int $term_id ID terme.
	 * @return bool|WP_Error
	 */
	public static function delete_folder( $term_id ) {
		return wp_delete_term( (int) $term_id, self::TAXONOMY );
	}

	/**
	 * @param array<int> $attachment_ids IDs attachments.
	 * @param int        $folder_id ID dossier (0 = retirer).
	 * @param bool       $manual Marquer comme classement manuel.
	 * @return void
	 */
	public static function move_attachments( $attachment_ids, $folder_id, $manual = true ) {
		$folder_id = (int) $folder_id;
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			if ( $folder_id > 0 ) {
				wp_set_object_terms( $attachment_id, array( $folder_id ), self::TAXONOMY, false );
			} else {
				wp_set_object_terms( $attachment_id, array(), self::TAXONOMY, false );
			}

			if ( $manual ) {
				update_post_meta( $attachment_id, '_gi_media_folder_manual', '1' );
			}
		}
	}

	/**
	 * @param int $attachment_id ID attachment.
	 * @return bool
	 */
	public static function is_manual( $attachment_id ) {
		return '1' === (string) get_post_meta( (int) $attachment_id, '_gi_media_folder_manual', true );
	}
}
