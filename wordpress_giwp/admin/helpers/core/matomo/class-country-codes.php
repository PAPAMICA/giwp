<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conversion libellés / codes pays Matomo → ISO 3166-1 alpha-2 (carte mondiale).
 */
class Gi_Toolkit_Matomo_Country_Codes {

	/**
	 * @param string $code Code Matomo (fr, FR, …).
	 * @param string $label Libellé pays.
	 * @return string Code ISO majuscule ou chaîne vide.
	 */
	public static function to_iso2( $code, $label = '' ) {
		$code = strtoupper( trim( (string) $code ) );
		if ( 2 === strlen( $code ) && ctype_alpha( $code ) ) {
			return $code;
		}

		$label = trim( (string) $label );
		if ( '' === $label ) {
			return '';
		}

		$map = self::label_map();
		$key = strtolower( $label );
		return $map[ $key ] ?? '';
	}

	/**
	 * @return array<string, string> label lowercase => ISO2
	 */
	private static function label_map() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}

		$map = array(
			'france'                          => 'FR',
			'united states'                   => 'US',
			'united states of america'        => 'US',
			'usa'                             => 'US',
			'germany'                         => 'DE',
			'deutschland'                     => 'DE',
			'switzerland'                     => 'CH',
			'suisse'                          => 'CH',
			'schweiz'                         => 'CH',
			'belgium'                         => 'BE',
			'belgique'                        => 'BE',
			'belgië'                          => 'BE',
			'canada'                          => 'CA',
			'united kingdom'                  => 'GB',
			'great britain'                   => 'GB',
			'royaume-uni'                     => 'GB',
			'italy'                           => 'IT',
			'italie'                          => 'IT',
			'spain'                           => 'ES',
			'espagne'                         => 'ES',
			'netherlands'                     => 'NL',
			'pays-bas'                        => 'NL',
			'portugal'                        => 'PT',
			'austria'                         => 'AT',
			'österreich'                      => 'AT',
			'poland'                          => 'PL',
			'pologne'                         => 'PL',
			'brazil'                          => 'BR',
			'brésil'                          => 'BR',
			'india'                           => 'IN',
			'inde'                            => 'IN',
			'china'                           => 'CN',
			'chine'                           => 'CN',
			'japan'                           => 'JP',
			'japon'                           => 'JP',
			'australia'                       => 'AU',
			'australie'                       => 'AU',
			'mexico'                          => 'MX',
			'mexique'                         => 'MX',
			'russia'                          => 'RU',
			'russie'                          => 'RU',
			'sweden'                          => 'SE',
			'suède'                           => 'SE',
			'norway'                          => 'NO',
			'norvège'                         => 'NO',
			'denmark'                         => 'DK',
			'danemark'                        => 'DK',
			'finland'                         => 'FI',
			'finlande'                        => 'FI',
			'ireland'                         => 'IE',
			'irlande'                         => 'IE',
			'luxembourg'                      => 'LU',
			'czech republic'                  => 'CZ',
			'république tchèque'              => 'CZ',
			'hungary'                         => 'HU',
			'hongrie'                         => 'HU',
			'romania'                         => 'RO',
			'roumanie'                        => 'RO',
			'greece'                          => 'GR',
			'grèce'                           => 'GR',
			'turkey'                          => 'TR',
			'turquie'                         => 'TR',
			'south africa'                    => 'ZA',
			'afrique du sud'                  => 'ZA',
			'argentina'                       => 'AR',
			'argentine'                       => 'AR',
			'colombia'                        => 'CO',
			'colombie'                        => 'CO',
			'chile'                           => 'CL',
			'chili'                           => 'CL',
			'south korea'                     => 'KR',
			'corée du sud'                    => 'KR',
			'indonesia'                       => 'ID',
			'indonésie'                       => 'ID',
			'thailand'                        => 'TH',
			'thaïlande'                       => 'TH',
			'vietnam'                         => 'VN',
			'viêt nam'                        => 'VN',
			'malaysia'                        => 'MY',
			'malaisie'                        => 'MY',
			'singapore'                       => 'SG',
			'singapour'                       => 'SG',
			'new zealand'                     => 'NZ',
			'nouvelle-zélande'                => 'NZ',
			'israel'                          => 'IL',
			'israël'                          => 'IL',
			'saudi arabia'                    => 'SA',
			'arabie saoudite'                 => 'SA',
			'united arab emirates'            => 'AE',
			'émirats arabes unis'             => 'AE',
			'ukraine'                         => 'UA',
			'egypt'                           => 'EG',
			'égypte'                          => 'EG',
			'morocco'                         => 'MA',
			'maroc'                           => 'MA',
			'algeria'                         => 'DZ',
			'algérie'                         => 'DZ',
			'tunisia'                         => 'TN',
			'tunisie'                         => 'TN',
			'unknown'                         => '',
			'inconnu'                         => '',
		);

		return $map;
	}
}
