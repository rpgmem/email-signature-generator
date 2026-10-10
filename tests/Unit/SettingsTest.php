<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EmailSignatureGenerator\Settings
 */
final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ): string => trim( strip_tags( (string) $s ) ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_empty_input_gives_the_defaults(): void {
		self::assertSame( Settings::defaults(), Settings::sanitize( array() ) );
	}

	public function test_garbage_input_gives_the_defaults(): void {
		self::assertSame( Settings::defaults(), Settings::sanitize( 'not an array' ) );
	}

	public function test_an_unticked_box_turns_capitals_off(): void {
		self::assertFalse( Settings::sanitize( array( 'uppercase_name' => '0' ) )['uppercase_name'] );
		self::assertTrue( Settings::sanitize( array( 'uppercase_name' => '1' ) )['uppercase_name'] );
	}

	public function test_unknown_models_are_dropped_and_none_means_all(): void {
		self::assertSame( array( 'staff_school' ), Settings::sanitize( array( 'models' => array( 'nope', 'staff_school' ) ) )['models'] );
		self::assertSame( Settings::modelIds(), Settings::sanitize( array( 'models' => array( 'nope' ) ) )['models'] );
		self::assertSame( Settings::modelIds(), Settings::sanitize( array( 'models' => array() ) )['models'] );
	}

	public function test_the_logo_width_is_clamped(): void {
		self::assertSame( Settings::LOGO_WIDTH_MIN, Settings::sanitize( array( 'logo_width' => '5' ) )['logo_width'] );
		self::assertSame( Settings::LOGO_WIDTH_MAX, Settings::sanitize( array( 'logo_width' => '9999' ) )['logo_width'] );
		self::assertSame( 150, Settings::sanitize( array( 'logo_width' => '150' ) )['logo_width'] );
	}

	/**
	 * @dataProvider domains
	 */
	public function test_the_email_domain( string $input, string $stored ): void {
		self::assertSame( $stored, Settings::sanitize( array( 'email_domain' => $input ) )['email_domain'] );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function domains(): array {
		return array(
			'plain'         => array( 'sme.prefeitura.sp.gov.br', 'sme.prefeitura.sp.gov.br' ),
			'with at'       => array( '@Example.ORG ', 'example.org' ),
			'empty'         => array( '', '' ),
			'not a domain'  => array( 'not a domain', '' ),
			'markup'        => array( '"><script>', '' ),
			'single label'  => array( 'localhost', '' ),
		);
	}

	public function test_regions_come_from_lines_trimmed_and_unique(): void {
		$regions = Settings::sanitize( array( 'regions' => " A / X \r\n\nB / Y\nA / X\n<b>C</b>" ) )['regions'];
		self::assertSame( array( 'A / X', 'B / Y', 'C' ), $regions );
	}

	public function test_access_accepts_only_the_two_modes(): void {
		self::assertSame( Settings::ACCESS_LOGGED_IN, Settings::sanitize( array( 'access' => 'logged_in' ) )['access'] );
		self::assertSame( Settings::ACCESS_EVERYONE, Settings::sanitize( array( 'access' => 'admins' ) )['access'] );
	}

	public function test_field_overrides_keep_known_fields_only(): void {
		$fields = Settings::sanitize(
			array(
				'fields' => array(
					'staff_school' => array(
						'address' => array( 'default' => ' Rua A, 1 ', 'required' => '1', 'locked' => '1' ),
						'bogus'   => array( 'default' => 'x' ),
					),
					'bogus'        => array( 'name' => array( 'default' => 'x' ) ),
				),
			)
		)['fields'];

		self::assertSame(
			array( 'staff_school' => array( 'address' => array( 'default' => 'Rua A, 1', 'required' => true, 'locked' => true ) ) ),
			$fields
		);
	}

	public function test_the_name_stays_required_and_name_and_email_never_lock(): void {
		$fields = Settings::sanitize(
			array(
				'fields' => array(
					'staff_school' => array(
						'name'  => array( 'default' => 'Maria', 'locked' => '1' ),
						'email' => array( 'default' => 'a@b.org', 'locked' => '1' ),
					),
				),
			)
		)['fields']['staff_school'];

		self::assertTrue( $fields['name']['required'] );
		self::assertArrayNotHasKey( 'locked', $fields['name'] );
		self::assertArrayNotHasKey( 'locked', $fields['email'] );
	}

	public function test_the_image_quality_is_clamped(): void {
		self::assertSame( Settings::IMAGE_QUALITY_MIN, Settings::sanitize( array( 'image_quality' => '5' ) )['image_quality'] );
		self::assertSame( Settings::IMAGE_QUALITY_MAX, Settings::sanitize( array( 'image_quality' => '250' ) )['image_quality'] );
		self::assertSame( 75, Settings::sanitize( array( 'image_quality' => '75' ) )['image_quality'] );
		self::assertSame( Config::IMAGE_QUALITY, Settings::sanitize( array() )['image_quality'] );
	}

	public function test_an_unknown_image_scale_falls_back_to_the_default(): void {
		self::assertSame( 3, Settings::sanitize( array( 'image_scale' => '3' ) )['image_scale'] );
		self::assertSame( Config::IMAGE_SCALE, Settings::sanitize( array( 'image_scale' => '7' ) )['image_scale'] );
		self::assertSame( Config::IMAGE_SCALE, Settings::sanitize( array( 'image_scale' => 'x' ) )['image_scale'] );
		self::assertSame( Config::IMAGE_SCALE, Settings::sanitize( array() )['image_scale'] );
	}

	public function test_a_field_cannot_be_locked_empty(): void {
		$fields = Settings::sanitize( array( 'fields' => array( 'staff_school' => array( 'address' => array( 'default' => '', 'locked' => '1' ) ) ) ) )['fields'];
		self::assertArrayNotHasKey( 'locked', $fields['staff_school']['address'] );
	}

	public function test_apply_with_defaults_changes_nothing_but_the_new_keys(): void {
		$config  = Config::defaults( 'https://example.org/logo.png' );
		$applied = Settings::apply( $config, Settings::defaults() );

		foreach ( $applied['models'] as $m => $model ) {
			foreach ( $model['fields'] as $f => $field ) {
				self::assertFalse( $field['locked'] );
				unset( $applied['models'][ $m ]['fields'][ $f ]['locked'] );
			}
		}
		self::assertSame( $config, $applied );
	}

	public function test_apply_overrides_models_logo_domain_regions_and_fields(): void {
		$settings = Settings::sanitize(
			array(
				'models'         => array( 'staff_school', 'department' ),
				'uppercase_name' => '0',
				'logo_width'     => '90',
				'image_quality'  => '80',
				'image_scale'    => '3',
				'email_domain'   => 'example.org',
				'regions'        => "North\nSouth",
				'fields'         => array(
					'staff_school' => array( 'address' => array( 'default' => 'Rua A, 1', 'locked' => '1' ) ),
				),
			)
		);
		$config = Settings::apply( Config::defaults( 'https://example.org/default.png' ), $settings, 'https://example.org/chosen.png' );

		self::assertSame( array( 'staff_school', 'department' ), array_column( $config['models'], 'id' ) );
		self::assertFalse( $config['uppercaseName'] );
		self::assertSame( array( 'quality' => 80, 'scale' => 3 ), $config['image'] );
		self::assertSame( 'example.org', $config['emailDomain'] );
		self::assertSame( array( 'url' => 'https://example.org/chosen.png', 'width' => 90, 'alt' => 'Logo' ), $config['logo'] );
		self::assertSame( array( 'North', 'South' ), $config['regions'] );

		$address = $this->field( $config, 'staff_school', 'address' );
		self::assertSame( 'Rua A, 1', $address['default'] );
		self::assertTrue( $address['locked'] );

		// The region default left the list, so the select starts on its first option.
		self::assertSame( 'North', $this->field( $config, 'department', 'region' )['default'] );
	}

	/**
	 * @param array<string, mixed> $config Configuration.
	 * @return array<string, mixed>
	 */
	private function field( array $config, string $model, string $key ): array {
		foreach ( $config['models'] as $m ) {
			if ( $m['id'] !== $model ) {
				continue;
			}
			foreach ( $m['fields'] as $f ) {
				if ( $f['key'] === $key ) {
					return $f;
				}
			}
		}
		self::fail( "{$model}.{$key} not found." );
	}
}
