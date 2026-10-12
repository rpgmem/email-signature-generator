<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Admin\Autosave;
use EmailSignatureGenerator\Admin\SettingsPage;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Dark mode for the settings screen: the setting, its autosave, the head
 * script that applies it, and the palette that makes it dark.
 *
 * @covers \EmailSignatureGenerator\Settings
 * @covers \EmailSignatureGenerator\Admin\Autosave
 * @covers \EmailSignatureGenerator\Admin\SettingsPage
 */
final class DarkModeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ): string => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $v ): string => (string) json_encode( $v ) );
		if ( ! class_exists( WP_Error::class ) ) {
			require __DIR__ . '/../Stubs/wp-error.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_the_setting_starts_off_and_keeps_only_known_values(): void {
		self::assertSame( 'off', Settings::defaults()['dark_mode'] );
		foreach ( Settings::DARK_MODES as $mode ) {
			self::assertSame( $mode, Settings::sanitize( array( 'dark_mode' => $mode ) )['dark_mode'] );
		}
		self::assertSame( 'off', Settings::sanitize( array( 'dark_mode' => 'dim' ) )['dark_mode'] );
		self::assertSame( 'off', Settings::sanitize( array( 'dark_mode' => array( 'on' ) ) )['dark_mode'] );
		self::assertSame( 'off', Settings::sanitize( array() )['dark_mode'] );
	}

	public function test_it_saves_on_its_own_and_refuses_an_unknown_value(): void {
		$on = Autosave::apply( Settings::defaults(), 'dark_mode', 'auto' );
		self::assertIsArray( $on );
		self::assertSame( 'auto', Autosave::read( $on, 'dark_mode' ) );

		$refused = Autosave::apply( Settings::defaults(), 'dark_mode', 'dim' );
		self::assertInstanceOf( WP_Error::class, $refused );
		self::assertSame( 'unknown_value', $refused->get_error_code() );
	}

	public function test_the_head_script_applies_the_mode_and_follows_a_save(): void {
		$script = SettingsPage::darkModeScript( 'auto' );
		self::assertStringEndsWith( '})("auto");', $script );
		self::assertStringContainsString( 'classList.toggle("esg-dark-mode",', $script );
		self::assertStringContainsString( 'prefers-color-scheme: dark', $script );
		self::assertStringContainsString( 'addEventListener("esg:setting-saved"', $script );

		self::assertStringEndsWith( '})("off");', SettingsPage::darkModeScript( '"); alert(1); ("' ), 'An unknown value is never printed.' );
	}

	/**
	 * The dark block only redefines tokens the light palette declares, and
	 * every token the sheet reads is declared: an undeclared custom property
	 * drops the whole declaration, so the element would lose its colour.
	 */
	public function test_the_dark_palette_redefines_declared_tokens_only(): void {
		$css   = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin-settings.css' );
		$light = self::tokens( $css, ':root {' );
		$dark  = self::tokens( $css, ':root.esg-dark-mode {' );

		self::assertNotEmpty( $dark );
		self::assertSame( array(), array_values( array_diff( $dark, $light ) ), 'A dark token with no light value.' );

		preg_match_all( '/var\(--(esg-[a-z0-9-]+)\)/', $css, $used );
		self::assertNotEmpty( $used[1] );
		self::assertSame( array(), array_values( array_diff( array_unique( $used[1] ), $light ) ), 'A token read but never declared.' );
	}

	/**
	 * Token names declared in the block that opens with $selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Opening of the block.
	 * @return list<string>
	 */
	private static function tokens( string $css, string $selector ): array {
		$start = strpos( $css, $selector );
		self::assertNotFalse( $start, $selector );
		$block = substr( $css, $start, (int) strpos( $css, "\n}", $start ) - $start );
		preg_match_all( '/--(esg-[a-z0-9-]+):/', $block, $m );
		return $m[1];
	}
}
