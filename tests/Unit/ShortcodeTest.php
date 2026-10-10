<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Shortcode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EmailSignatureGenerator\Shortcode::restrictModels
 */
final class ShortcodeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_models_are_kept_in_the_requested_order(): void {
		$config = Shortcode::restrictModels( Config::defaults( '' ), 'department, staff_school' );
		self::assertSame( array( 'department', 'staff_school' ), array_column( $config['models'], 'id' ) );
	}

	public function test_unknown_ids_are_ignored(): void {
		$config = Shortcode::restrictModels( Config::defaults( '' ), 'nope,staff_school' );
		self::assertSame( array( 'staff_school' ), array_column( $config['models'], 'id' ) );
	}

	public function test_no_known_id_offers_every_model(): void {
		$all = Config::defaults( '' );
		self::assertSame( $all, Shortcode::restrictModels( $all, 'nope' ) );
		self::assertSame( $all, Shortcode::restrictModels( $all, '' ) );
	}
}
