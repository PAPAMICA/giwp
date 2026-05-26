<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalisation des sites MainWP (tableaux associatifs, pas des objets).
 */
class MainWP_GIWeb_Sites {

	/**
	 * @param object|null $activator Activator extension.
	 * @return array<int, object{id:int, name:string, url:string}>
	 */
	public static function fetch_all( $activator ) {
		if ( ! $activator || ! method_exists( $activator, 'getChildFile' ) || ! method_exists( $activator, 'getChildKey' ) ) {
			return array();
		}

		$raw = apply_filters( 'mainwp_getsites', $activator->getChildFile(), $activator->getChildKey(), null );

		return self::normalize_list( $raw );
	}

	/**
	 * @param mixed $websites Liste brute MainWP.
	 * @return array<int, object{id:int, name:string, url:string}>
	 */
	public static function normalize_list( $websites ) {
		if ( ! is_array( $websites ) ) {
			return array();
		}

		$list = array();
		foreach ( $websites as $key => $site ) {
			$row = self::normalize_one( $site, $key );
			if ( $row['id'] > 0 ) {
				$list[] = (object) $row;
			}
		}

		return $list;
	}

	/**
	 * @param mixed $site  Site MainWP (array ou objet).
	 * @param mixed $key   Clé du tableau parent.
	 * @return array{id:int, name:string, url:string}
	 */
	public static function normalize_one( $site, $key = 0 ) {
		if ( is_object( $site ) ) {
			$site = get_object_vars( $site );
		}
		if ( ! is_array( $site ) ) {
			return array(
				'id'   => 0,
				'name' => '',
				'url'  => '',
			);
		}

		$id = 0;
		if ( isset( $site['id'] ) ) {
			$id = (int) $site['id'];
		} elseif ( isset( $site['websiteid'] ) ) {
			$id = (int) $site['websiteid'];
		} elseif ( is_numeric( $key ) && (int) $key > 0 ) {
			$id = (int) $key;
		}

		return array(
			'id'   => $id,
			'name' => (string) ( $site['name'] ?? $site['siteName'] ?? '' ),
			'url'  => (string) ( $site['url'] ?? '' ),
		);
	}

	/**
	 * @param object|array<string, mixed> $site Site.
	 * @param string                      $prop Propriété.
	 * @param string                      $default Valeur par défaut.
	 * @return string
	 */
	public static function prop( $site, $prop, $default = '' ) {
		$row = self::normalize_one( $site, 0 );
		return isset( $row[ $prop ] ) ? (string) $row[ $prop ] : $default;
	}

	/**
	 * @param int         $site_id   ID MainWP.
	 * @param object|null $activator Activator.
	 * @return array{id:int, name:string, url:string}
	 */
	public static function find_by_id( $site_id, $activator ) {
		$site_id = absint( $site_id );
		foreach ( self::fetch_all( $activator ) as $site ) {
			$row = self::normalize_one( $site );
			if ( (int) $row['id'] === $site_id ) {
				return $row;
			}
		}
		return array(
			'id'   => $site_id,
			'name' => '',
			'url'  => '',
		);
	}
}
