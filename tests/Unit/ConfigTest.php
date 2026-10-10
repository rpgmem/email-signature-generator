<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Config;
use PHPUnit\Framework\TestCase;

/**
 * The configuration the browser reads. The generator trusts its shape, so
 * the shape is pinned here.
 *
 * @covers \EmailSignatureGenerator\Config
 */
final class ConfigTest extends TestCase {

	/** Checks the generator implements (`validate()` in signature-generator.js). */
	private const RULES = array( 'email', 'phone', 'url', 'roleSector', 'singleRole' );

	/** Field keys the generator prints (`render()` in signature-generator.js). */
	private const KEYS = array( 'name', 'role', 'email', 'phone', 'unit', 'region', 'address', 'postal', 'link1', 'link2' );

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_models_have_unique_ids_and_known_fields(): void {
		$config = Config::defaults( 'https://example.org/logo.png' );
		$ids    = array_column( $config['models'], 'id' );

		self::assertSame( array( 'staff_regional', 'staff_school', 'department' ), $ids );
		foreach ( $config['models'] as $model ) {
			$keys = array_column( $model['fields'], 'key' );
			self::assertSame( array_unique( $keys ), $keys, "{$model['id']} repeats a field." );
			foreach ( $model['fields'] as $field ) {
				self::assertContains( $field['key'], self::KEYS, "{$model['id']}.{$field['key']} is a field the generator does not print." );
				self::assertNotSame( '', $field['label'] );
				if ( isset( $field['rule'] ) ) {
					self::assertContains( $field['rule'], self::RULES, "{$model['id']}.{$field['key']} names an unknown rule." );
				}
			}
		}
	}

	public function test_a_default_never_fails_its_own_rule(): void {
		$config = Config::defaults( 'https://example.org/logo.png' );
		foreach ( $config['models'] as $model ) {
			foreach ( $model['fields'] as $field ) {
				if ( ! isset( $field['default'] ) ) {
					continue;
				}
				if ( 'region' === ( $field['type'] ?? '' ) ) {
					self::assertContains( $field['default'], $config['regions'] );
				}
				if ( 'phone' === ( $field['rule'] ?? '' ) ) {
					self::assertMatchesRegularExpression( '/' . $config['phonePattern'] . '/', $field['default'] );
				}
			}
		}
	}

	/**
	 * @dataProvider phones
	 */
	public function test_the_phone_pattern( string $phone, bool $valid ): void {
		self::assertSame( $valid, 1 === preg_match( '/' . Config::PHONE_PATTERN . '/', $phone ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function phones(): array {
		return array(
			'landline'          => array( '55 11 3397-1234', true ),
			'mobile'            => array( '55 11 93397-1234', true ),
			'two numbers'       => array( '55 11 3397-1234 | 55 11 93397-5678', true ),
			'no country code'   => array( '11 3397-1234', false ),
			'placeholder'       => array( '55 11 3397-XXXX', false ),
			'missing separator' => array( '55 11 3397-1234 55 11 3397-1234', false ),
		);
	}

	public function test_every_string_the_script_reads_is_configured(): void {
		$js      = (string) file_get_contents( __DIR__ . '/../../assets/js/signature-generator.js' );
		$strings = Config::defaults( '' )['strings'];

		preg_match_all( '/strings\.(\w+)/', $js, $m );
		$used = array_unique( $m[1] );

		self::assertNotEmpty( $used, 'The scan found no string the script reads.' );
		foreach ( $used as $key ) {
			self::assertArrayHasKey( $key, $strings, "The script reads strings.{$key}, which the configuration lacks." );
		}
		foreach ( array_keys( $strings ) as $key ) {
			self::assertContains( $key, $used, "strings.{$key} is configured but never read." );
		}
	}

	public function test_the_logo_url_is_the_one_given(): void {
		$config = Config::defaults( 'https://example.org/logo.png' );
		self::assertSame( 'https://example.org/logo.png', $config['logo']['url'] );
		self::assertSame( Config::LOGO_WIDTH, $config['logo']['width'] );
	}

	public function test_the_image_defaults_are_quality_90_at_1x(): void {
		$config = Config::defaults( '' );
		self::assertSame( array( 'format' => 'png', 'quality' => Config::IMAGE_QUALITY, 'scale' => Config::IMAGE_SCALE ), $config['image'] );
		self::assertSame( 90, Config::IMAGE_QUALITY );
		// The original page's size: the signature's own 450 px.
		self::assertSame( 1, Config::IMAGE_SCALE );
	}

	public function test_the_signature_width_mirrors_the_script(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/signature-generator.js' );
		self::assertSame( 1, preg_match( '/var SIGNATURE_WIDTH = (\d+);/', $js, $m ), 'The script declares no SIGNATURE_WIDTH.' );
		self::assertSame( Config::SIGNATURE_WIDTH, (int) $m[1] );
	}
}
