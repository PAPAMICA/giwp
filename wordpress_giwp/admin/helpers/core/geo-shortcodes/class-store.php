<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stockage des variables géo-localisées et résolution des valeurs.
 */
class Gi_Toolkit_Geo_Shortcodes_Store {

	const OPTION_KEY = 'gi_toolkit_geo_shortcodes_settings';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'default_country' => 'FR',
			'geoip_db_path'   => '',
			'variables'       => array(
				'phone' => array(
					'label'     => __( 'Téléphone', 'gi-toolkit' ),
					'default'   => '',
					'countries' => array(),
				),
				'currency' => array(
					'label'     => __( 'Devise', 'gi-toolkit' ),
					'default'   => 'EUR',
					'countries' => array(
						'FR' => 'EUR',
						'CH' => 'CHF',
						'GB' => 'GBP',
						'US' => 'USD',
					),
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args( $stored, self::defaults() );
		if ( ! is_array( $settings['variables'] ?? null ) ) {
			$settings['variables'] = array();
		}

		return self::sanitize_settings( $settings, false );
	}

	/**
	 * @param array<string, mixed> $settings Données brutes.
	 * @return array<string, mixed>
	 */
	public static function save( array $settings ) {
		$clean = self::sanitize_settings( $settings, true );
		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * @return string
	 */
	public static function get_default_country() {
		$settings = self::get();
		$code     = Gi_Toolkit_Geo_Shortcodes_Country_Resolver::sanitize_country( $settings['default_country'] ?? 'FR' );
		return '' !== $code ? $code : 'FR';
	}

	/**
	 * @return string
	 */
	public static function get_geoip_db_path() {
		$settings = self::get();
		$path     = trim( (string) ( $settings['geoip_db_path'] ?? '' ) );
		if ( '' === $path ) {
			return '';
		}
		if ( ! is_file( $path ) ) {
			return '';
		}
		return wp_normalize_path( $path );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_variables() {
		$settings = self::get();
		return is_array( $settings['variables'] ?? null ) ? $settings['variables'] : array();
	}

	/**
	 * @param string $slug Identifiant variable.
	 * @return array<string, mixed>|null
	 */
	public static function get_variable( $slug ) {
		$slug = self::sanitize_slug( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$variables = self::get_variables();
		return $variables[ $slug ] ?? null;
	}

	/**
	 * @param string               $slug    Variable.
	 * @param string               $country Code ISO2.
	 * @param array<string, mixed> $var     Définition variable.
	 * @return string
	 */
	public static function resolve_value( $slug, $country, array $var = null ) {
		if ( null === $var ) {
			$var = self::get_variable( $slug );
		}
		if ( ! is_array( $var ) ) {
			return '';
		}

		$country = Gi_Toolkit_Geo_Shortcodes_Country_Resolver::sanitize_country( $country );
		if ( '' === $country ) {
			$country = self::get_default_country();
		}

		$countries = is_array( $var['countries'] ?? null ) ? $var['countries'] : array();
		if ( isset( $countries[ $country ] ) ) {
			return (string) $countries[ $country ];
		}

		return (string) ( $var['default'] ?? '' );
	}

	/**
	 * @param string   $country Code ISO2.
	 * @param string[] $slugs   Variables demandées.
	 * @return array<string, string>
	 */
	public static function resolve_many( $country, array $slugs ) {
		$out       = array();
		$variables = self::get_variables();

		foreach ( $slugs as $slug ) {
			$slug = self::sanitize_slug( $slug );
			if ( '' === $slug || ! isset( $variables[ $slug ] ) ) {
				continue;
			}
			$out[ $slug ] = self::resolve_value( $slug, $country, $variables[ $slug ] );
		}

		return $out;
	}

	/**
	 * @param mixed $raw Slug brut.
	 * @return string
	 */
	public static function sanitize_slug( $raw ) {
		$slug = sanitize_key( (string) $raw );
		$slug = str_replace( '-', '_', $slug );
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $slug ) ) {
			return '';
		}
		return $slug;
	}

	/**
	 * @param array<string, mixed> $settings Données.
	 * @param bool                 $strict   Supprimer variables invalides.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $settings, $strict = true ) {
		$defaults = self::defaults();
		$clean    = array(
			'default_country' => Gi_Toolkit_Geo_Shortcodes_Country_Resolver::sanitize_country( $settings['default_country'] ?? $defaults['default_country'] ),
			'geoip_db_path'   => sanitize_text_field( (string) ( $settings['geoip_db_path'] ?? '' ) ),
			'variables'       => array(),
		);

		if ( '' === $clean['default_country'] ) {
			$clean['default_country'] = 'FR';
		}

		$variables = is_array( $settings['variables'] ?? null ) ? $settings['variables'] : array();
		foreach ( $variables as $slug => $var ) {
			$slug = self::sanitize_slug( $slug );
			if ( '' === $slug || ! is_array( $var ) ) {
				continue;
			}

			$entry = array(
				'label'     => sanitize_text_field( (string) ( $var['label'] ?? $slug ) ),
				'default'   => sanitize_text_field( (string) ( $var['default'] ?? '' ) ),
				'countries' => array(),
			);

			$countries = is_array( $var['countries'] ?? null ) ? $var['countries'] : array();
			foreach ( $countries as $code => $value ) {
				$code = Gi_Toolkit_Geo_Shortcodes_Country_Resolver::sanitize_country( $code );
				if ( '' === $code ) {
					continue;
				}
				$entry['countries'][ $code ] = sanitize_text_field( (string) $value );
			}

			$clean['variables'][ $slug ] = $entry;
		}

		if ( ! $strict && empty( $clean['variables'] ) ) {
			$clean['variables'] = $defaults['variables'];
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $payload Données JSON admin.
	 * @return array<string, mixed>
	 */
	public static function from_editor_payload( array $payload ) {
		return self::sanitize_settings(
			array(
				'default_country' => $payload['default_country'] ?? 'FR',
				'geoip_db_path'   => $payload['geoip_db_path'] ?? '',
				'variables'       => $payload['variables'] ?? array(),
			),
			true
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function common_country_codes() {
		return array(
			'FR', 'CH', 'BE', 'LU', 'DE', 'IT', 'ES', 'PT', 'NL', 'GB', 'IE',
			'US', 'CA', 'AU', 'NZ', 'MA', 'TN', 'DZ', 'SN', 'CI', 'MC', 'AD',
		);
	}
}
