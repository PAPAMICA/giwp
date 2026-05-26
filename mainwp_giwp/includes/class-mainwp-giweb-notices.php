<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Messages flash après actions admin (une lecture par requête).
 */
class MainWP_GIWeb_Notices {

	const TRANSIENT = 'mainwp_giweb_flash_notice';

	/**
	 * @param string $type    success|error|warning|info
	 * @param string $message Message HTML-échappé côté affichage.
	 * @return void
	 */
	public static function add( $type, $message ) {
		$user_id = get_current_user_id();
		if ( ! $user_id || '' === $message ) {
			return;
		}
		set_transient(
			self::TRANSIENT . '_' . $user_id,
			array(
				'type'    => sanitize_key( $type ),
				'message' => $message,
			),
			60
		);
	}

	/**
	 * @return void
	 */
	public static function render() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$key    = self::TRANSIENT . '_' . $user_id;
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );

		$type = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true )
			? $notice['type']
			: 'info';

		$wp_class = 'notice-success';
		if ( 'error' === $type ) {
			$wp_class = 'notice-error';
		} elseif ( 'warning' === $type ) {
			$wp_class = 'notice-warning';
		} elseif ( 'info' === $type ) {
			$wp_class = 'notice-info';
		}

		printf(
			'<div class="notice %1$s is-dismissible mainwp-giweb-flash-notice"><p>%2$s</p></div>',
			esc_attr( $wp_class ),
			esc_html( $notice['message'] )
		);
	}
}
