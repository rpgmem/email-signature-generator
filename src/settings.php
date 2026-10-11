<?php
/**
 * The administrator's settings.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Stores what an administrator changed and applies it to the configuration.
 *
 * Only the differences from the defaults are meaningful: an install that has
 * never saved the settings page generates exactly the default configuration,
 * which is also what the standalone HTML carries.
 */
final class Settings {

	public const OPTION = 'email_signature_generator_settings';

	public const ACCESS_EVERYONE = 'everyone';

	public const ACCESS_LOGGED_IN = 'logged_in';

	public const LOGO_WIDTH_MIN = 60;

	public const LOGO_WIDTH_MAX = 240;

	public const TOP_SPACE_MAX = 20;

	public const UNIT_LABEL_MAX = 40;

	public const LOGO_ALT_MAX = 150;

	/**
	 * A domain name: dot-separated labels of letters, digits and inner
	 * hyphens, ending in a top-level label of two letters or more.
	 */
	public const DOMAIN_PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/';

	public const IMAGE_QUALITY_MIN = 50;

	public const IMAGE_QUALITY_MAX = 100;

	/**
	 * Pixel densities offered for the downloaded image. Above 3 the file
	 * grows without a visible gain in any email client.
	 */
	public const IMAGE_SCALES = array( 1, 2, 3 );

	public const IMAGE_FORMATS = array( 'png', 'jpg' );

	/**
	 * How the form offers the download: a button that recommends Copy HTML
	 * once used, the same inside a collapsed "More options", or a plain
	 * button with no recommendation.
	 */
	public const DOWNLOAD_STYLES = array( 'notice', 'more', 'plain' );

	/**
	 * Fields whose value cannot be locked: they identify the person, so a
	 * fixed value would give everyone the same signature.
	 */
	private const UNLOCKABLE = array( 'name', 'email' );

	/**
	 * The stored settings, completed with their defaults.
	 *
	 * @return array{models: list<string>, offer_download: bool, download_style: string, remember_values: bool, uppercase_name: bool, logo_id: int, logo_alt: string, logo_width: int, top_space: int, image_format: string, image_quality: int, image_scale: int, restrict_email_domain: bool, email_domains: list<string>, blocked_email_domains: list<string>, regions: list<array{name: string, acronym: string}>, unit_label: string, default_unit: string, access: string, fields: array<string, array<string, array{default?: string, required?: bool, locked?: bool}>>}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * The settings an install starts with.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'models'                => self::modelIds(),
			'offer_download'        => true,
			'download_style'        => 'notice',
			'remember_values'       => false,
			'uppercase_name'        => true,
			'logo_id'               => 0,
			'logo_alt'              => '',
			'logo_width'            => Config::LOGO_WIDTH,
			'top_space'             => Config::TOP_SPACE,
			'image_format'          => Config::IMAGE_FORMAT,
			'image_quality'         => Config::IMAGE_QUALITY,
			'image_scale'           => Config::IMAGE_SCALE,
			'restrict_email_domain' => false,
			'email_domains'         => array(),
			'blocked_email_domains' => Config::BLOCKED_EMAIL_DOMAINS,
			'regions'               => array(),
			'unit_label'            => '',
			'default_unit'          => '',
			'access'                => self::ACCESS_EVERYONE,
			'fields'                => array(),
		);
	}

	/**
	 * Clean settings from the form or from storage. Anything unknown is
	 * dropped and anything out of range is brought back into it, so a stored
	 * value written by an older version still yields a usable configuration.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array{models: list<string>, offer_download: bool, download_style: string, remember_values: bool, uppercase_name: bool, logo_id: int, logo_alt: string, logo_width: int, top_space: int, image_format: string, image_quality: int, image_scale: int, restrict_email_domain: bool, email_domains: list<string>, blocked_email_domains: list<string>, regions: list<array{name: string, acronym: string}>, unit_label: string, default_unit: string, access: string, fields: array<string, array<string, array{default?: string, required?: bool, locked?: bool}>>}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$ids   = self::modelIds();

		$models = array_values( array_intersect( $ids, array_map( 'strval', (array) ( $input['models'] ?? $ids ) ) ) );
		if ( array() === $models ) {
			// A form with no model is a broken page; offer them all instead.
			$models = $ids;
		}

		$width   = (int) ( $input['logo_width'] ?? Config::LOGO_WIDTH );
		$quality = (int) ( $input['image_quality'] ?? Config::IMAGE_QUALITY );
		$space   = (int) ( $input['top_space'] ?? Config::TOP_SPACE );
		$style   = is_string( $input['download_style'] ?? null ) ? $input['download_style'] : 'notice';
		$format  = is_string( $input['image_format'] ?? null ) ? $input['image_format'] : Config::IMAGE_FORMAT;
		$scale   = (int) ( $input['image_scale'] ?? Config::IMAGE_SCALE );

		// Before the list, one domain was stored as `email_domain`, and a
		// non-empty one meant the restriction was on.
		$legacy   = is_string( $input['email_domain'] ?? null ) ? $input['email_domain'] : '';
		$domains  = self::parseDomains( $input['email_domains'] ?? $legacy )['domains'];
		$restrict = array_key_exists( 'restrict_email_domain', $input ) ? ! empty( $input['restrict_email_domain'] ) : '' !== trim( $legacy );

		$regions = Regions::parse( $input['regions'] ?? array() )['regions'];

		// The form marks the default unit by its row, since an acronym can
		// change in the same save; storage keeps the acronym.
		$default_unit = is_string( $input['default_unit'] ?? null ) ? $input['default_unit'] : '';
		$row          = $input['default_unit_row'] ?? null;
		if ( is_scalar( $row ) && is_array( $input['regions'] ?? null ) && is_array( $input['regions'][ (string) $row ] ?? null ) ) {
			$default_unit = (string) ( $input['regions'][ (string) $row ]['acronym'] ?? '' );
		}
		$default_unit = strtoupper( (string) preg_replace( '/\s+/', '', $default_unit ) );
		$available    = array_column( array() !== $regions ? $regions : Regions::defaults(), 'acronym' );
		if ( ! in_array( $default_unit, $available, true ) ) {
			$default_unit = '';
		}

		$access = (string) ( $input['access'] ?? self::ACCESS_EVERYONE );

		return array(
			'models'                => $models,
			// Absent means "never saved": keep the default. The form posts a
			// hidden 0 before the checkbox, so unticking it is never absent.
			'offer_download'        => array_key_exists( 'offer_download', $input ) ? ! empty( $input['offer_download'] ) : true,
			'download_style'        => in_array( $style, self::DOWNLOAD_STYLES, true ) ? $style : 'notice',
			'remember_values'       => ! empty( $input['remember_values'] ),
			'uppercase_name'        => array_key_exists( 'uppercase_name', $input ) ? ! empty( $input['uppercase_name'] ) : true,
			'logo_id'               => max( 0, (int) ( $input['logo_id'] ?? 0 ) ),
			'logo_alt'              => mb_substr( sanitize_text_field( is_string( $input['logo_alt'] ?? null ) ? $input['logo_alt'] : '' ), 0, self::LOGO_ALT_MAX ),
			'logo_width'            => min( self::LOGO_WIDTH_MAX, max( self::LOGO_WIDTH_MIN, $width ) ),
			'top_space'             => min( self::TOP_SPACE_MAX, max( 0, $space ) ),
			'image_format'          => in_array( $format, self::IMAGE_FORMATS, true ) ? $format : Config::IMAGE_FORMAT,
			'image_quality'         => min( self::IMAGE_QUALITY_MAX, max( self::IMAGE_QUALITY_MIN, $quality ) ),
			'image_scale'           => in_array( $scale, self::IMAGE_SCALES, true ) ? $scale : Config::IMAGE_SCALE,
			'restrict_email_domain' => $restrict,
			'email_domains'         => $domains,
			// Absent means "never saved": the built-in list. An emptied box
			// is a choice and stays empty.
			'blocked_email_domains' => array_key_exists( 'blocked_email_domains', $input ) ? self::parseDomains( $input['blocked_email_domains'] )['domains'] : Config::BLOCKED_EMAIL_DOMAINS,
			'regions'               => $regions,
			'unit_label'            => mb_substr( sanitize_text_field( is_string( $input['unit_label'] ?? null ) ? $input['unit_label'] : '' ), 0, self::UNIT_LABEL_MAX ),
			'default_unit'          => $default_unit,
			'access'                => self::ACCESS_LOGGED_IN === $access ? self::ACCESS_LOGGED_IN : self::ACCESS_EVERYONE,
			'fields'                => self::sanitizeFields( is_array( $input['fields'] ?? null ) ? $input['fields'] : array() ),
		);
	}

	/**
	 * The Settings API callback: sanitize, and tell the administrator about
	 * any regional office that was left out.
	 *
	 * WordPress runs the callback twice on the first save of an option, so
	 * the notice is added once per request.
	 *
	 * @param mixed $input Posted settings.
	 * @return array<string, mixed>
	 */
	public static function save( $input ): array {
		static $reported         = false;
		static $reported_domains = false;
		$input                   = is_array( $input ) ? $input : array();
		$rejected                = Regions::parse( $input['regions'] ?? array() )['rejected'];
		if ( ! $reported && array() !== $rejected && function_exists( 'add_settings_error' ) ) {
			$reported = true;
			add_settings_error(
				self::OPTION,
				'regions_rejected',
				sprintf(
					/* translators: %s: the offices left out, separated by commas. */
					__( 'These units were not saved, because each needs a name and a unique acronym of capitals and digits: %s', 'email-signature-generator' ),
					implode( ', ', $rejected )
				)
			);
		}
		$bad = array_merge(
			self::parseDomains( $input['email_domains'] ?? array() )['rejected'],
			self::parseDomains( $input['blocked_email_domains'] ?? array() )['rejected']
		);
		if ( ! $reported_domains && array() !== $bad && function_exists( 'add_settings_error' ) ) {
			$reported_domains = true;
			add_settings_error(
				self::OPTION,
				'domains_rejected',
				sprintf(
					/* translators: %s: the domains left out, separated by commas. */
					__( 'These email domains were not saved, because they are not domain names: %s', 'email-signature-generator' ),
					implode( ', ', $bad )
				)
			);
		}
		return self::sanitize( $input );
	}

	/**
	 * Clean a list of email domains: one per line (or separated by commas or
	 * spaces) as the form posts it, or a list. Each is lower-cased and loses
	 * a leading "@"; repeats are dropped, and anything that is not a domain
	 * name is left out and reported.
	 *
	 * @param mixed $input Raw domains.
	 * @return array{domains: list<string>, rejected: list<string>}
	 */
	public static function parseDomains( $input ): array {
		if ( is_string( $input ) ) {
			$input = preg_split( '/[\s,;]+/', $input );
		}
		$domains  = array();
		$rejected = array();
		foreach ( is_array( $input ) ? $input : array() as $raw ) {
			if ( ! is_scalar( $raw ) ) {
				continue;
			}
			$domain = ltrim( strtolower( trim( (string) $raw ) ), '@' );
			if ( '' === $domain ) {
				continue;
			}
			if ( ! preg_match( self::DOMAIN_PATTERN, $domain ) ) {
				$rejected[] = sanitize_text_field( (string) $raw );
				continue;
			}
			if ( ! in_array( $domain, $domains, true ) ) {
				$domains[] = $domain;
			}
		}
		return array(
			'domains'  => $domains,
			'rejected' => $rejected,
		);
	}

	/**
	 * Apply settings to a configuration.
	 *
	 * @param array<string, mixed> $config   The default configuration.
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param string               $logo_url URL of the chosen logo, or '' for the given one.
	 * @return array<string, mixed> The configuration the form uses.
	 */
	public static function apply( array $config, array $settings, string $logo_url = '' ): array {
		$enabled          = $settings['models'];
		$config['models'] = array_values(
			array_filter( $config['models'], static fn( array $m ): bool => in_array( $m['id'], $enabled, true ) )
		);

		$config['offerDownload']       = (bool) $settings['offer_download'];
		$config['downloadStyle']       = (string) $settings['download_style'];
		$config['rememberValues']      = (bool) $settings['remember_values'];
		$config['uppercaseName']       = (bool) $settings['uppercase_name'];
		$config['emailDomains']        = $settings['restrict_email_domain'] ? array_values( $settings['email_domains'] ) : array();
		$config['blockedEmailDomains'] = array_values( $settings['blocked_email_domains'] );
		$config['logo']['width']       = (int) $settings['logo_width'];
		$config['topSpace']            = (int) $settings['top_space'];
		$config['image']               = array(
			'format'  => (string) $settings['image_format'],
			'quality' => (int) $settings['image_quality'],
			'scale'   => (int) $settings['image_scale'],
		);
		if ( '' !== $logo_url ) {
			$config['logo']['url'] = $logo_url;
			// The bundled logo's description does not fit another image.
			$config['logo']['alt'] = '';
		}
		if ( '' !== $settings['logo_alt'] ) {
			$config['logo']['alt'] = (string) $settings['logo_alt'];
		}
		if ( array() !== $settings['regions'] ) {
			$config['regions'] = Regions::labels( $settings['regions'] );
			$config['offices'] = $settings['regions'];
		}
		$acronyms = array_column( $config['offices'], 'acronym' );

		foreach ( $config['models'] as $m => $model ) {
			foreach ( $model['fields'] as $f => $field ) {
				$override = $settings['fields'][ $model['id'] ][ $field['key'] ] ?? array();
				if ( isset( $override['default'] ) ) {
					$field['default'] = $override['default'];
				}
				if ( isset( $override['required'] ) ) {
					$field['required'] = $override['required'];
				}
				$field['locked'] = ! empty( $override['locked'] ) && '' !== (string) ( $field['default'] ?? '' );

				// A select must start on one of its options.
				if ( 'region' === ( $field['type'] ?? '' ) && ! in_array( $field['default'] ?? '', $config['regions'], true ) ) {
					$field['default'] = $config['regions'][0] ?? '';
				}
				// The unit marked default on the Units tab is every unit
				// field's default.
				if ( 'office' === ( $field['type'] ?? '' ) && '' !== $settings['default_unit'] ) {
					$field['default'] = $settings['default_unit'];
				}
				if ( 'office' === ( $field['type'] ?? '' ) && ! in_array( $field['default'] ?? '', $acronyms, true ) ) {
					$field['default'] = $acronyms[0] ?? '';
				}
				if ( 'office' === ( $field['type'] ?? '' ) && '' !== $settings['unit_label'] ) {
					$field['label'] = $settings['unit_label'];
				}

				$config['models'][ $m ]['fields'][ $f ] = $field;
			}
		}

		return $config;
	}

	/**
	 * The configuration this site's form uses: the defaults with the stored
	 * settings applied.
	 *
	 * @return array<string, mixed>
	 */
	public static function siteConfig(): array {
		$settings = self::get();
		$logo_url = '';
		if ( $settings['logo_id'] > 0 ) {
			$logo_url = (string) wp_get_attachment_image_url( $settings['logo_id'], 'medium' );
			if ( '' !== $logo_url && '' === $settings['logo_alt'] ) {
				// Without its own text, the image's from the media library.
				$alt                  = trim( (string) get_post_meta( $settings['logo_id'], '_wp_attachment_image_alt', true ) );
				$settings['logo_alt'] = '' !== $alt ? $alt : trim( (string) get_the_title( $settings['logo_id'] ) );
			}
		}
		return self::apply( Config::defaults( Config::bundledLogoUrl() ), $settings, $logo_url );
	}

	/**
	 * Clean the per-field overrides: known models and fields only, text
	 * values, and locks only where a lock makes sense.
	 *
	 * @param array<mixed> $input Raw overrides, model id => field key => override.
	 * @return array<string, array<string, array{default?: string, required?: bool, locked?: bool}>>
	 */
	private static function sanitizeFields( array $input ): array {
		$input = self::splitLegacyFields( $input );
		$out   = array();
		foreach ( Config::defaults( '' )['models'] as $model ) {
			foreach ( $model['fields'] as $field ) {
				$raw = $input[ $model['id'] ][ $field['key'] ] ?? null;
				if ( ! is_array( $raw ) ) {
					continue;
				}

				$override = array(
					'default'  => sanitize_text_field( (string) ( $raw['default'] ?? '' ) ),
					'required' => ! empty( $raw['required'] ),
				);
				if ( self::alwaysRequired( $field['key'] ) ) {
					$override['required'] = true;
				}
				if ( ! in_array( $field['key'], self::UNLOCKABLE, true ) && ! empty( $raw['locked'] ) && '' !== $override['default'] ) {
					$override['locked'] = true;
				}

				$out[ $model['id'] ][ $field['key'] ] = $override;
			}
		}
		return $out;
	}

	/**
	 * Carry overrides saved before the office became its own select.
	 *
	 * "Role / Department" (staff at a regional office) and "School / Regional
	 * office" (staff at a school) were one text each, "NTIC / DREMP". They
	 * are now the department or the school plus the office's acronym, so a
	 * stored default is split at its last slash; required and locked stay
	 * with the text part. Runs only while the old key is there and the new
	 * one is not, so a saved form is never rewritten.
	 *
	 * @param array<mixed> $input Raw overrides.
	 * @return array<mixed>
	 */
	private static function splitLegacyFields( array $input ): array {
		foreach ( array(
			'staff_regional' => array( 'role', 'sector' ),
			'staff_school'   => array( 'unit', 'school' ),
		) as $model => $keys ) {
			list( $old, $new ) = $keys;
			$legacy            = $input[ $model ][ $old ] ?? null;
			if ( ! is_array( $legacy ) || isset( $input[ $model ][ $new ] ) ) {
				continue;
			}

			$default = (string) ( $legacy['default'] ?? '' );
			$at      = strrpos( $default, '/' );
			$acronym = false === $at ? '' : strtoupper( trim( substr( $default, $at + 1 ) ) );
			$text    = false === $at ? $default : trim( substr( $default, 0, $at ) );

			$input[ $model ][ $new ] = array(
				'default'  => $text,
				'required' => ! empty( $legacy['required'] ),
				'locked'   => ! empty( $legacy['locked'] ),
			);
			if ( preg_match( Regions::ACRONYM_PATTERN, $acronym ) && ! isset( $input[ $model ]['office'] ) ) {
				$input[ $model ]['office'] = array(
					'default'  => $acronym,
					'required' => true,
				);
			}
			unset( $input[ $model ][ $old ] );
		}
		return $input;
	}

	/**
	 * Ids of every model the generator knows, in display order.
	 *
	 * @return list<string>
	 */
	public static function modelIds(): array {
		return array_column( Config::defaults( '' )['models'], 'id' );
	}

	/**
	 * Whether a field is required whatever the settings say: the name makes a
	 * signature someone's own, and the office is a select that always has a
	 * value.
	 *
	 * @param string $key Field key.
	 */
	public static function alwaysRequired( string $key ): bool {
		return in_array( $key, array( 'name', 'office' ), true );
	}

	/**
	 * Whether a field may be locked.
	 *
	 * @param string $key Field key.
	 */
	public static function lockable( string $key ): bool {
		return ! in_array( $key, self::UNLOCKABLE, true );
	}
}
