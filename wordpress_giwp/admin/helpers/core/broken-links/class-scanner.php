<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan des liens dans le contenu et vérification HTTP.
 */
class Gi_Toolkit_Broken_Links_Scanner {

	const OPTION_QUEUE   = 'gi_toolkit_broken_links_queue';

	const BATCH_SIZE     = 8;

	const LAST_DOMAIN    = 'gi_toolkit_broken_links_last_domain_time';

	/**
	 * @param string $trigger manual|cron.
	 * @return int Scan ID.
	 */
	public static function start_scan( $trigger = 'manual' ) {
		$scan_id = Gi_Toolkit_Broken_Links_DB::create_scan( $trigger );
		$queue   = self::build_queue();
		update_option(
			self::OPTION_QUEUE,
			array(
				'scan_id'  => $scan_id,
				'queue'    => $queue,
				'checked'  => 0,
				'broken'   => 0,
			),
			false
		);
		return $scan_id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_queue() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$posts = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$seen  = array();
		$queue = array();

		foreach ( $posts as $post_id ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			$type    = get_post_type( $post_id );
			foreach ( self::extract_links( $content ) as $link ) {
				$url = $link['url'];
				$key = md5( $url . '|' . $post_id );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$queue[]      = array(
					'url'            => $url,
					'source_post_id' => (int) $post_id,
					'source_type'    => (string) $type,
					'link_text'      => $link['text'],
				);
			}
		}

		return $queue;
	}

	/**
	 * @param string $content HTML.
	 * @return array<int, array{url: string, text: string}>
	 */
	public static function extract_links( $content ) {
		$links = array();

		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$url = esc_url_raw( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
				if ( self::is_scannable_url( $url ) ) {
					$links[] = array(
						'url'  => $url,
						'text' => wp_strip_all_tags( $m[2] ),
					);
				}
			}
		}

		if ( preg_match_all( '#https?://[^\s"\'<>]+#i', $content, $raw ) ) {
			foreach ( $raw[0] as $url ) {
				$url = esc_url_raw( rtrim( $url, '.,);]>' ) );
				if ( self::is_scannable_url( $url ) ) {
					$links[] = array(
						'url'  => $url,
						'text' => '',
					);
				}
			}
		}

		return $links;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_scannable_url( $url ) {
		if ( '' === $url || '#' === $url[0] ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return ! empty( $parts['scheme'] ) && in_array( $parts['scheme'], array( 'http', 'https' ), true );
	}

	/**
	 * @return array{done: bool, percent: int, message: string, scan_id: int}
	 */
	public static function process_batch() {
		$state = get_option( self::OPTION_QUEUE, array() );
		if ( empty( $state['scan_id'] ) || ! is_array( $state['queue'] ?? null ) ) {
			return array(
				'done'    => true,
				'percent' => 100,
				'message' => __( 'Aucun scan en cours.', 'gi-toolkit' ),
				'scan_id' => 0,
			);
		}

		$scan_id = (int) $state['scan_id'];
		$queue   = $state['queue'];
		$total   = max( 1, (int) ( $state['total'] ?? 0 ) );
		if ( empty( $state['total'] ) ) {
			$total = count( $queue ) + (int) ( $state['checked'] ?? 0 );
			$state['total'] = $total;
		}

		$batch   = array_splice( $queue, 0, self::BATCH_SIZE );
		$checked = (int) ( $state['checked'] ?? 0 );
		$broken  = (int) ( $state['broken'] ?? 0 );

		foreach ( $batch as $item ) {
			self::throttle_domain( $item['url'] );
			$result = self::check_url( $item['url'] );
			++$checked;
			if ( $result['broken'] ) {
				++$broken;
				Gi_Toolkit_Broken_Links_DB::insert_broken_link(
					$scan_id,
					array_merge( $item, $result )
				);
			}
		}

		$state['queue']   = $queue;
		$state['checked'] = $checked;
		$state['broken']  = $broken;

		$done = empty( $queue );
		if ( $done ) {
			Gi_Toolkit_Broken_Links_DB::update_scan(
				$scan_id,
				array(
					'finished_at'  => current_time( 'mysql' ),
					'status'       => 'done',
					'urls_checked' => $checked,
					'broken_count' => $broken,
				)
			);
			delete_option( self::OPTION_QUEUE );
			$settings = get_option( Gi_Toolkit_Broken_Links::OPTION_SETTINGS, array() );
			$keep     = max( 3, (int) ( $settings['keep_scans'] ?? 10 ) );
			Gi_Toolkit_Broken_Links_DB::prune_old_scans( $keep );
		} else {
			update_option( self::OPTION_QUEUE, $state, false );
		}

		$pct = $done ? 100 : min( 99, (int) floor( ( $checked / $total ) * 100 ) );

		return array(
			'done'    => $done,
			'percent' => $pct,
			'message' => $done
				? sprintf(
					/* translators: 1: broken 2: checked */
					__( 'Scan terminé : %1$d liens cassés sur %2$d vérifiés.', 'gi-toolkit' ),
					$broken,
					$checked
				)
				: sprintf(
					/* translators: %d: checked count */
					__( 'Vérification… %d URLs traitées', 'gi-toolkit' ),
					$checked
				),
			'scan_id' => $scan_id,
		);
	}

	/**
	 * @param string $url URL.
	 * @return void
	 */
	private static function throttle_domain( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return;
		}
		$key  = 'gi_bl_' . md5( $host );
		$last = (float) get_transient( $key );
		$now  = microtime( true );
		if ( $last && ( $now - $last ) < 1.0 ) {
			usleep( (int) ( ( 1.0 - ( $now - $last ) ) * 1000000 ) );
		}
		set_transient( $key, microtime( true ), 60 );
	}

	/**
	 * @param string $url URL.
	 * @return array{broken: bool, http_status: int, error_message: string}
	 */
	public static function check_url( $url ) {
		$home = home_url();
		if ( 0 === strpos( $url, $home ) ) {
			return self::check_internal( $url );
		}

		return self::http_check( $url );
	}

	/**
	 * Vérification HTTP (HEAD puis GET si besoin).
	 *
	 * @param string $url URL.
	 * @return array{broken: bool, http_status: int, error_message: string}
	 */
	private static function http_check( $url ) {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 5,
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 8,
					'redirection' => 5,
					'sslverify'   => false,
					'body'        => '',
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'broken'        => true,
				'http_status'   => 0,
				'error_message' => $response->get_error_message(),
			);
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$broken = $code >= 400 || 0 === $code;

		return array(
			'broken'        => $broken,
			'http_status'   => $code,
			'error_message' => $broken ? wp_remote_retrieve_response_message( $response ) : '',
		);
	}

	/**
	 * @param string $url URL interne.
	 * @return array{broken: bool, http_status: int, error_message: string}
	 */
	private static function check_internal( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return array( 'broken' => false, 'http_status' => 200, 'error_message' => '' );
		}

		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			$status = get_post_status( $post_id );
			if ( false === $status || 'trash' === $status ) {
				return array(
					'broken'        => true,
					'http_status'   => 404,
					'error_message' => __( 'Contenu introuvable', 'gi-toolkit' ),
				);
			}
			return array( 'broken' => false, 'http_status' => 200, 'error_message' => '' );
		}

		return self::http_check( $url );
	}
}
