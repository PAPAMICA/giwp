<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Détection / création du site Matomo pour ce WordPress.
 */
class Gi_Toolkit_Matomo_Site {

	const TRANSIENT_TRACKING = 'gi_toolkit_matomo_tracking_code';

	/**
	 * @return string
	 */
	public static function get_wordpress_site_url() {
		return untrailingslashit( home_url() );
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @param bool                 $force    Ignorer site_id stocké.
	 * @return array{success:bool, site_id?:int, message?:string, created?:bool}
	 */
	public static function ensure_site_id( array $settings, $force = false ) {
		$api = new Gi_Toolkit_Matomo_API( $settings );
		if ( ! $api->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Configurez l’URL Matomo et le token API.', 'gi-toolkit' ),
			);
		}

		$stored_id = absint( $settings['site_id'] ?? 0 );

		if ( '1' !== (string) ( $settings['auto_site'] ?? '1' ) ) {
			if ( $stored_id > 0 ) {
				return array(
					'success' => true,
					'site_id' => $stored_id,
				);
			}
			return array(
				'success' => false,
				'message' => __( 'ID de site Matomo manquant. Activez la détection automatique ou saisissez un ID.', 'gi-toolkit' ),
			);
		}

		if ( $stored_id > 0 && ! $force ) {
			return array(
				'success' => true,
				'site_id' => $stored_id,
			);
		}

		$site_url = self::get_wordpress_site_url();
		$found    = self::find_site_id_by_url( $api, $site_url );

		if ( $found > 0 ) {
			return array(
				'success' => true,
				'site_id' => $found,
				'created' => false,
			);
		}

		$created = self::create_site( $api, $site_url );
		if ( $created > 0 ) {
			/**
			 * Site Matomo créé pour ce WordPress.
			 *
			 * @param int $created ID site Matomo.
			 */
			do_action( 'gi_toolkit_matomo_site_created', $created );
			return array(
				'success' => true,
				'site_id' => $created,
				'created' => true,
			);
		}

		return array(
			'success' => false,
			'message' => $api->get_last_error() ?: __( 'Impossible de créer le site Matomo.', 'gi-toolkit' ),
		);
	}

	/**
	 * @param Gi_Toolkit_Matomo_API $api      Client API.
	 * @param string                $site_url URL WordPress.
	 * @return int
	 */
	private static function find_site_id_by_url( Gi_Toolkit_Matomo_API $api, $site_url ) {
		$result = $api->request(
			'SitesManager.getSitesIdFromSiteUrl',
			array( 'url' => $site_url )
		);

		if ( ! is_array( $result ) || empty( $result ) ) {
			// Variante sans slash final.
			$alt = trailingslashit( $site_url );
			if ( $alt !== $site_url ) {
				$result = $api->request(
					'SitesManager.getSitesIdFromSiteUrl',
					array( 'url' => $alt )
				);
			}
		}

		if ( ! is_array( $result ) || empty( $result ) ) {
			return 0;
		}

		$first = reset( $result );
		if ( is_array( $first ) && isset( $first['idsite'] ) ) {
			return absint( $first['idsite'] );
		}
		if ( is_numeric( $first ) ) {
			return absint( $first );
		}

		return 0;
	}

	/**
	 * @param Gi_Toolkit_Matomo_API $api      Client.
	 * @param string                $site_url URL.
	 * @return int
	 */
	private static function create_site( Gi_Toolkit_Matomo_API $api, $site_url ) {
		$result = $api->request(
			'SitesManager.addSite',
			array(
				'siteName' => rawurlencode( get_bloginfo( 'name' ) ),
				'urls'     => $site_url,
			)
		);

		if ( is_array( $result ) && isset( $result['value'] ) ) {
			return absint( $result['value'] );
		}
		if ( is_numeric( $result ) ) {
			return absint( $result );
		}

		return 0;
	}

	/**
	 * @param array<string, mixed> $settings Réglages.
	 * @return void
	 */
	public static function clear_tracking_cache( array $settings = array() ) {
		$site_id = absint( $settings['site_id'] ?? 0 );
		delete_transient( self::TRANSIENT_TRACKING . '_' . $site_id );
		delete_transient( self::TRANSIENT_TRACKING . '_v2_' . $site_id );
		delete_transient( self::TRANSIENT_TRACKING );
	}
}
