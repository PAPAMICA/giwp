<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Détecte les médias utilisés sur le site (critère standard).
 */
class Gi_Toolkit_Unused_Media_Scanner {

	const OPTION_SCAN   = 'gi_toolkit_unused_media_scan';

	const OPTION_PROGRESS = 'gi_toolkit_unused_media_scan_progress';

	const BATCH_POSTS = 40;

	/**
	 * @return void
	 */
	public static function reset_scan() {
		delete_option( self::OPTION_PROGRESS );
		update_option(
			self::OPTION_PROGRESS,
			array(
				'used_ids'      => array(),
				'post_offset'   => 0,
				'phase'         => 'posts',
				'total_posts'   => 0,
				'finished'      => false,
			),
			false
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_progress() {
		$progress = get_option( self::OPTION_PROGRESS, array() );
		return is_array( $progress ) ? $progress : array();
	}

	/**
	 * Traite un lot de scan.
	 *
	 * @return array{done: bool, percent: int, message: string}
	 */
	public static function process_batch() {
		$progress = self::get_progress();
		if ( empty( $progress ) ) {
			self::reset_scan();
			$progress = self::get_progress();
		}

		$used = isset( $progress['used_ids'] ) && is_array( $progress['used_ids'] )
			? array_map( 'intval', $progress['used_ids'] )
			: array();
		$used = array_fill_keys( $used, true );

		$phase = $progress['phase'] ?? 'posts';

		if ( 'posts' === $phase ) {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			unset( $post_types['attachment'] );

			$statuses = array( 'publish', 'draft', 'pending', 'future', 'private' );

			if ( empty( $progress['total_posts'] ) ) {
				$counts = 0;
				foreach ( $post_types as $pt ) {
					$c = wp_count_posts( $pt );
					foreach ( $statuses as $st ) {
						$counts += isset( $c->$st ) ? (int) $c->$st : 0;
					}
				}
				$progress['total_posts'] = $counts;
			}

			$offset = (int) ( $progress['post_offset'] ?? 0 );
			$posts  = get_posts(
				array(
					'post_type'      => array_values( $post_types ),
					'post_status'    => $statuses,
					'posts_per_page' => self::BATCH_POSTS,
					'offset'         => $offset,
					'fields'         => 'ids',
				)
			);

			foreach ( $posts as $post_id ) {
				$thumb = (int) get_post_thumbnail_id( $post_id );
				if ( $thumb > 0 ) {
					$used[ $thumb ] = true;
				}
				$content = (string) get_post_field( 'post_content', $post_id );
				foreach ( self::extract_attachment_ids_from_content( $content ) as $aid ) {
					$used[ $aid ] = true;
				}
			}

			$progress['post_offset'] = $offset + count( $posts );
			$progress['used_ids']      = array_keys( $used );

			if ( count( $posts ) < self::BATCH_POSTS ) {
				$progress['phase'] = 'attachments';
			}

			update_option( self::OPTION_PROGRESS, $progress, false );

			$total = max( 1, (int) $progress['total_posts'] );
			$pct   = min( 90, (int) floor( ( $progress['post_offset'] / $total ) * 80 ) );

			return array(
				'done'    => false,
				'percent' => $pct,
				'message' => sprintf(
					/* translators: %d: number of posts processed */
					__( 'Analyse du contenu… %d publications traitées', 'gi-toolkit' ),
					(int) $progress['post_offset']
				),
			);
		}

		if ( 'attachments' === $phase ) {
			global $wpdb;

			$attached = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'attachment' AND post_parent > 0"
			);
			foreach ( $attached as $aid ) {
				$used[ (int) $aid ] = true;
			}

			$thumb_rows = $wpdb->get_col(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
				WHERE meta_key = '_thumbnail_id' AND meta_value > 0"
			);
			foreach ( $thumb_rows as $aid ) {
				$used[ (int) $aid ] = true;
			}

			$all_attachments = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
			);

			$unused = array();
			foreach ( $all_attachments as $aid ) {
				$aid = (int) $aid;
				if ( empty( $used[ $aid ] ) ) {
					$unused[] = $aid;
				}
			}

			update_option(
				self::OPTION_SCAN,
				array(
					'scanned_at'          => gmdate( 'c' ),
					'unused_ids'          => $unused,
					'total_attachments'   => count( $all_attachments ),
					'used_count'          => count( $all_attachments ) - count( $unused ),
				),
				false
			);

			$progress['finished'] = true;
			$progress['phase']    = 'done';
			update_option( self::OPTION_PROGRESS, $progress, false );

			return array(
				'done'    => true,
				'percent' => 100,
				'message' => sprintf(
					/* translators: 1: unused count 2: total count */
					__( 'Analyse terminée : %1$d médias non utilisés sur %2$d.', 'gi-toolkit' ),
					count( $unused ),
					count( $all_attachments )
				),
			);
		}

		return array(
			'done'    => true,
			'percent' => 100,
			'message' => __( 'Analyse déjà terminée.', 'gi-toolkit' ),
		);
	}

	/**
	 * @param string $content Contenu HTML.
	 * @return array<int>
	 */
	public static function extract_attachment_ids_from_content( $content ) {
		$ids = array();

		if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) {
			foreach ( $m[1] as $id ) {
				$ids[] = (int) $id;
			}
		}

		if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $content, $m ) ) {
			foreach ( $m[1] as $id ) {
				$ids[] = (int) $id;
			}
		}

		if ( preg_match_all( '/\[gallery[^\]]*ids=["\']([^"\']+)["\']/', $content, $m ) ) {
			foreach ( $m[1] as $list ) {
				foreach ( array_map( 'intval', explode( ',', $list ) ) as $id ) {
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
			}
		}

		if ( preg_match_all( '#https?://[^"\')\s]+#i', $content, $m ) ) {
			foreach ( $m[0] as $url ) {
				$aid = attachment_url_to_postid( $url );
				if ( $aid > 0 ) {
					$ids[] = $aid;
				}
			}
		}

		return array_unique( array_filter( $ids ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_scan_results() {
		$scan = get_option( self::OPTION_SCAN, null );
		return is_array( $scan ) ? $scan : null;
	}
}
