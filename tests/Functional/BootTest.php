<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Plugin;
use WP_UnitTestCase;

/**
 * The plugin loads inside a real WordPress and wires its hooks.
 *
 * @covers \EmailSignatureGenerator\Plugin
 */
final class BootTest extends WP_UnitTestCase {

	public function test_the_plugin_boots_from_its_main_file(): void {
		self::assertStringEndsWith( '/' . Plugin::SLUG . '.php', Plugin::file() );
		self::assertStringEndsWith( '/', Plugin::dir() );
	}

	public function test_translations_load_on_init(): void {
		self::assertSame( 10, has_action( 'init', array( Plugin::class, 'loadTextdomain' ) ) );
	}
}
