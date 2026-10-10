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
	 * @return array{models: list<string>, offer_download: bool, download_style: string, uppercase_name: bool, logo_id: int, logo_width: int, top_space: int, image_format: string, image_quality: int, image_scale: int, email_domain: string, regions: list<array{name: string, acronym: string}>, access: string, fields: array<string, array<string, array{default?: string, required?: bool, locked?: bool}>>}
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
			'models'         => self::modelIds(),
			'offer_download' => true,
			'download_style' => 'notice',
			'uppercase_name' => true,
			'logo_id'        => 0,
			'logo_width'     => Config::LOGO_WIDTH,
			'top_space'      => Config::TOP_SPACE,
			'image_format'   => Config::IMAGE_FORMAT,
			'image_quality'  => Config::IMAGE_QUALITY,
			'image_scale'    => Config::IMAGE_SCALE,
			'email_domain'   => '',
			'regions'        => array(),
			'access'         => self::ACCESS_EVERYONE,
			'fields'         => array(),
		);
	}

	/**
	 * Clean settings from the form or from storage. Anything unknown is
	 * dropped and anything out of range is brought back into it, so a stored
	 * value written by an older version still yields a usable configuration.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array{models: list<string>, offer_download: bool, download_style: string, uppercase_name: bool, logo_id: int, logo_width: int, top_space: int, image_format: string, image_quality: int, image_scale: int, email_domain: string, regions: list<array{name: string, acronym: string}>, access: string, fields: array<string, array<string, array{default?: string, required?: bool, locked?: bool}>>}
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

		$domain = strtolower( trim( (string) ( $input['email_domain'] ?? '' ) ) );
		$domain = ltrim( $domain, '@' );
		if ( '' !== $domain && ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $domain ) ) {
			$domain = '';
		}

		$regions = Regions::parse( $input['regions'] ?? array() )['regions'];

		$access = (string) ( $input['access'] ?? self::ACCESS_EVERYONE );

		return array(
			'models'         => $models,
			// Absent means "never saved": keep the default. The form posts a
			// hidden 0 before the checkbox, so unticking it is never absent.
			'offer_download' => array_key_exists( 'offer_download', $input ) ? ! empty( $input['offer_download'] ) : true,
			'download_style' => in_array( $style, self::DOWNLOAD_STYLES, true ) ? $style : 'notice',
			'uppercase_name' => array_key_exists( 'uppercase_name', $input ) ? ! empty( $input['uppercase_name'] ) : true,
			'logo_id'        => max( 0, (int) ( $input['logo_id'] ?? 0 ) ),
			'logo_width'     => min( self::LOGO_WIDTH_MAX, max( self::LOGO_WIDTH_MIN, $width ) ),
			'top_space'      => min( self::TOP_SPACE_MAX, max( 0, $space ) ),
			'image_format'   => in_array( $format, self::IMAGE_FORMATS, true ) ? $format : Config::IMAGE_FORMAT,
			'image_quality'  => min( self::IMAGE_QUALITY_MAX, max( self::IMAGE_QUALITY_MIN, $quality ) ),
			'image_scale'    => in_array( $scale, self::IMAGE_SCALES, true ) ? $scale : Config::IMAGE_SCALE,
			'email_domain'   => $domain,
			'regions'        => $regions,
			'access'         => self::ACCESS_LOGGED_IN === $access ? self::ACCESS_LOGGED_IN : self::ACCESS_EVERYONE,
			'fields'         => self::sanitizeFields( is_array( $input['fields'] ?? null ) ? $input['fields'] : array() ),
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
		static $reported = false;
		$input           = is_array( $input ) ? $input : array();
		$rejected        = Regions::parse( $input['regions'] ?? array() )['rejected'];
		if ( ! $reported && array() !== $rejected && function_exists( 'add_settings_error' ) ) {
			$reported = true;
			add_settings_error(
				self::OPTION,
				'regions_rejected',
				sprintf(
					/* translators: %s: the offices left out, separated by commas. */
					__( 'These regional offices were not saved, because each needs a name and a unique acronym of capitals and digits: %s', 'email-signature-generator' ),
					implode( ', ', $rejected )
				)
			);
		}
		return self::sanitize( $input );
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

		$config['offerDownload'] = (bool) $settings['offer_download'];
		$config['downloadStyle'] = (string) $settings['download_style'];
		$config['uppercaseName'] = (bool) $settings['uppercase_name'];
		$config['emailDomain']   = (string) $settings['email_domain'];
		$config['logo']['width'] = (int) $settings['logo_width'];
		$config['topSpace']      = (int) $settings['top_space'];
		$config['image']         = array(
			'format'  => (string) $settings['image_format'],
			'quality' => (int) $settings['image_quality'],
			'scale'   => (int) $settings['image_scale'],
		);
		if ( '' !== $logo_url ) {
			$config['logo']['url'] = $logo_url;
		}
		if ( array() !== $settings['regions'] ) {
			$config['regions'] = Regions::labels( $settings['regions'] );
		}

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
		$out = array();
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
				// The name is what makes a signature someone's own.
				if ( 'name' === $field['key'] ) {
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
	 * Ids of every model the generator knows, in display order.
	 *
	 * @return list<string>
	 */
	public static function modelIds(): array {
		return array_column( Config::defaults( '' )['models'], 'id' );
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
