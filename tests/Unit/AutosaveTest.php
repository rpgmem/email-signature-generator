<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Admin\Autosave;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \EmailSignatureGenerator\Admin\Autosave
 */
final class AutosaveTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ): string => trim( strip_tags( (string) $s ) ) );
		if ( ! class_exists( WP_Error::class ) ) {
			require __DIR__ . '/../Stubs/wp-error.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_capitals_turn_off_and_on_per_field(): void {
		$off = Autosave::apply( Settings::defaults(), 'fields.staff_regional.sector.uppercase', '0' );
		self::assertIsArray( $off );
		self::assertFalse( $off['fields']['staff_regional']['sector']['uppercase'] );
		self::assertFalse( Autosave::read( $off, 'fields.staff_regional.sector.uppercase' ) );
		self::assertTrue( Autosave::apply( $off, 'fields.staff_regional.sector.uppercase', '1' )['fields']['staff_regional']['sector']['uppercase'] );

		$refused = Autosave::apply( Settings::defaults(), 'fields.staff_regional.email.uppercase', '1' );
		self::assertInstanceOf( WP_Error::class, $refused );
		self::assertSame( 'not_casable', $refused->get_error_code() );
	}

	public function test_remembering_turns_on_and_off(): void {
		$on = Autosave::apply( Settings::defaults(), 'remember_values', '1' );
		self::assertIsArray( $on );
		self::assertTrue( Autosave::read( $on, 'remember_values' ) );
		self::assertFalse( Autosave::apply( $on, 'remember_values', '0' )['remember_values'] );
	}

	public function test_the_analytics_switch_saves_on_its_own_and_keeps_the_id(): void {
		$on = Autosave::apply( Settings::sanitize( array( 'analytics_id' => 'G-ABC1234' ) ), 'analytics', '1' );
		self::assertIsArray( $on );
		self::assertTrue( Autosave::read( $on, 'analytics' ) );
		self::assertSame( 'G-ABC1234', $on['analytics_id'] );
		self::assertFalse( Autosave::apply( $on, 'analytics', '0' )['analytics'] );
	}

	public function test_the_domain_restriction_turns_on_and_off_and_keeps_the_list(): void {
		$on = Autosave::apply( Settings::sanitize( array( 'email_domains' => 'a.org' ) ), 'restrict_email_domain', '1' );
		self::assertIsArray( $on );
		self::assertTrue( Autosave::read( $on, 'restrict_email_domain' ) );
		$off = Autosave::apply( $on, 'restrict_email_domain', '0' );
		self::assertFalse( $off['restrict_email_domain'] );
		self::assertSame( array( 'a.org' ), $off['email_domains'] );
	}

	public function test_the_download_turns_off_and_on(): void {
		$off = Autosave::apply( Settings::defaults(), 'offer_download', '0' );
		self::assertFalse( $off['offer_download'] );
		self::assertFalse( Autosave::read( $off, 'offer_download' ) );
		self::assertTrue( Autosave::apply( $off, 'offer_download', '1' )['offer_download'] );
	}

	public function test_models_take_the_ticked_list_and_never_end_empty(): void {
		self::assertSame( array( 'department' ), Autosave::apply( Settings::defaults(), 'models', array( 'department' ) )['models'] );
		self::assertSame( Settings::modelIds(), Autosave::apply( Settings::defaults(), 'models', array() )['models'] );
		self::assertSame( array( 'department' ), Autosave::apply( Settings::defaults(), 'models', array( 'department', 'nope' ) )['models'] );
	}

	public function test_required_toggles_a_field_and_keeps_its_default(): void {
		$settings                                    = Settings::defaults();
		$settings['fields']['staff_school']['phone'] = array(
			'default'  => '55 11 1234-5678',
			'required' => true,
		);

		$off = Autosave::apply( $settings, 'fields.staff_school.phone.required', '0' );
		self::assertSame( array( 'default' => '55 11 1234-5678', 'required' => false ), $off['fields']['staff_school']['phone'] );
	}

	public function test_locking_a_never_saved_field_keeps_its_built_in_default(): void {
		$locked = Autosave::apply( Settings::defaults(), 'fields.staff_regional.link1.locked', '1' );
		self::assertSame(
			array( 'default' => 'educacao.sme.prefeitura.sp.gov.br', 'required' => false, 'locked' => true ),
			$locked['fields']['staff_regional']['link1']
		);
		self::assertTrue( Autosave::read( $locked, 'fields.staff_regional.link1.locked' ) );
	}

	public function test_a_field_without_a_default_cannot_be_locked(): void {
		$result = Autosave::apply( Settings::defaults(), 'fields.staff_school.address.locked', '1' );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'locked_empty', $result->get_error_code() );
	}

	public function test_unlocking_a_field_without_a_default_is_allowed(): void {
		$result = Autosave::apply( Settings::defaults(), 'fields.staff_school.address.locked', '0' );
		self::assertIsArray( $result );
		self::assertFalse( Autosave::read( $result, 'fields.staff_school.address.locked' ) );
	}

	/**
	 * @dataProvider refused
	 */
	public function test_refused_keys( string $key, string $code ): void {
		$result = Autosave::apply( Settings::defaults(), $key, '1' );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( $code, $result->get_error_code() );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function refused(): array {
		return array(
			'name required'      => array( 'fields.staff_school.name.required', 'always_required' ),
			'office required'    => array( 'fields.staff_school.office.required', 'always_required' ),
			'name locked'        => array( 'fields.staff_school.name.locked', 'not_lockable' ),
			'email locked'       => array( 'fields.staff_school.email.locked', 'not_lockable' ),
			'unknown model'      => array( 'fields.nope.phone.required', 'unknown_key' ),
			'unknown field'      => array( 'fields.staff_school.nope.required', 'unknown_key' ),
			'unknown flag'       => array( 'fields.staff_school.phone.default', 'unknown_key' ),
			'text setting'       => array( 'email_domain', 'unknown_key' ),
			'empty key'          => array( '', 'unknown_key' ),
			'too many segments'  => array( 'fields.staff_school.phone.required.x', 'unknown_key' ),
		);
	}

	public function test_only_a_literal_on_value_turns_a_toggle_on(): void {
		foreach ( array( '0', '', 'yes', array( '1' ) ) as $value ) {
			self::assertFalse( Autosave::apply( Settings::defaults(), 'offer_download', $value )['offer_download'] );
		}
	}
}
