<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Création / association d’un monitor Uptime Kuma pour ce site WordPress.
 */
class Gi_Toolkit_Uptime_Kuma_Monitor {

	/**
	 * @return string
	 */
	public static function get_wordpress_site_url() {
		return untrailingslashit( home_url() );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $force    Ignorer monitor_id stocké.
	 * @return array{success:bool, monitor_id?:int, message?:string, created?:bool}
	 */
	public static function ensure_monitor_id( array $settings, $force = false ) {
		$api = new Gi_Toolkit_Uptime_Kuma_API( $settings );
		if ( ! $api->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Configurez l’URL, l’utilisateur et le mot de passe Uptime Kuma.', 'gi-toolkit' ),
			);
		}

		$stored_id = absint( $settings['monitor_id'] ?? 0 );

		if ( '1' !== (string) ( $settings['auto_monitor'] ?? '1' ) ) {
			if ( $stored_id > 0 ) {
				return array(
					'success'    => true,
					'monitor_id' => $stored_id,
				);
			}
			return array(
				'success' => false,
				'message' => __( 'ID monitor manquant. Activez la détection automatique.', 'gi-toolkit' ),
			);
		}

		if ( $stored_id > 0 && ! $force ) {
			return array(
				'success'    => true,
				'monitor_id' => $stored_id,
			);
		}

		$site_url = self::get_wordpress_site_url();
		$site_name = get_bloginfo( 'name' ) ?: $site_url;
		$found     = self::find_monitor_id_by_url( $api, $site_url );

		if ( $found > 0 ) {
			return array(
				'success'    => true,
				'monitor_id' => $found,
				'created'    => false,
			);
		}

		$created = $api->add_http_monitor( $site_name, $site_url );
		if ( empty( $created['success'] ) || empty( $created['monitor_id'] ) ) {
			return array(
				'success' => false,
				'message' => $created['message'] ?? $api->get_last_error() ?: __( 'Impossible de créer le monitor.', 'gi-toolkit' ),
			);
		}

		return array(
			'success'    => true,
			'monitor_id' => (int) $created['monitor_id'],
			'created'    => true,
		);
	}

	/**
	 * @param Gi_Toolkit_Uptime_Kuma_API $api      API.
	 * @param string                     $site_url URL WordPress.
	 * @return int
	 */
	private static function find_monitor_id_by_url( Gi_Toolkit_Uptime_Kuma_API $api, $site_url ) {
		$list = $api->get_monitors();
		if ( ! is_array( $list ) ) {
			return 0;
		}

		$candidates = array_unique(
			array_filter(
				array(
					untrailingslashit( $site_url ),
					trailingslashit( $site_url ),
					str_replace( 'https://', 'http://', untrailingslashit( $site_url ) ),
				)
			)
		);

		foreach ( $list as $key => $monitor ) {
			if ( ! is_array( $monitor ) ) {
				continue;
			}
			$monitor_id = isset( $monitor['id'] ) ? absint( $monitor['id'] ) : absint( $key );
			$monitor_url = isset( $monitor['url'] ) ? untrailingslashit( (string) $monitor['url'] ) : '';
			if ( '' === $monitor_url || $monitor_id < 1 ) {
				continue;
			}
			foreach ( $candidates as $candidate ) {
				if ( $monitor_url === untrailingslashit( $candidate ) ) {
					return $monitor_id;
				}
			}
		}

		return 0;
	}
}
