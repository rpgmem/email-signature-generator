<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Button;
use EmailSignatureGenerator\Icons;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The button family (#106): its markup, its stylesheet in both themes, the
 * icon classes generated from the registry, and that no button of the
 * plugin's own falls back to an emoji, a dashicon or core's `.button`.
 *
 * @covers \EmailSignatureGenerator\Button
 * @covers \EmailSignatureGenerator\Icons
 */
final class ButtonsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'esc_attr' )->alias( static fn( $s ): string => htmlspecialchars( (string) $s, ENT_QUOTES ) );
		Functions\when( 'esc_html' )->alias( static fn( $s ): string => htmlspecialchars( (string) $s, ENT_QUOTES ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_a_button_names_its_category_and_carries_its_icon(): void {
		self::assertSame(
			'<button type="submit" class="esg-btn esg-btn-primary esg-icon-save" id="submit" name="submit">Save &amp; go</button>',
			Button::html(
				array(
					'label'    => 'Save & go',
					'category' => 'primary',
					'icon'     => 'save',
					'type'     => 'submit',
					'id'       => 'submit',
					'name'     => 'submit',
				)
			)
		);
	}

	public function test_unknown_values_fall_back_rather_than_break(): void {
		$html = Button::html(
			array(
				'label'    => 'Go',
				'category' => 'loud',
				'icon'     => 'nope',
				'type'     => 'image',
				'class'    => ' esg-hook  other ',
				'attrs'    => array(
					'data-esg-copy' => 'x"y',
					'onclick'       => 'alert(1)',
					'hidden'        => true,
					'bad key'       => '1',
					'skip'          => false,
				),
			)
		);
		self::assertSame( '<button type="button" class="esg-btn esg-btn-neutral esg-hook other" data-esg-copy="x&quot;y" hidden>Go</button>', $html );
		self::assertSame( '', Button::html( array( 'label' => '' ) ), 'A button needs a label.' );
	}

	public function test_an_icon_only_button_keeps_its_name(): void {
		self::assertSame(
			'<button type="button" class="esg-btn esg-btn-danger esg-icon-trash esg-btn-icon" aria-label="Remove" title="Remove"></button>',
			Button::html(
				array(
					'label'     => 'Remove',
					'category'  => 'danger',
					'icon'      => 'trash',
					'icon_only' => true,
				)
			)
		);
		self::assertSame( '', Button::html( array( 'label' => 'Remove', 'icon_only' => true ) ), 'Without an icon there is nothing to show.' );
	}

	/**
	 * The icon classes in the sheet are what the registry generates. Set
	 * ESG_UPDATE_ICON_CSS=1 to write them after changing the registry.
	 */
	public function test_the_icon_css_is_generated_from_the_registry(): void {
		$path  = dirname( __DIR__, 2 ) . '/assets/css/buttons.css';
		$css   = (string) file_get_contents( $path );
		$begin = strpos( $css, '/* esg-icons:begin' );
		$end   = strpos( $css, Icons::CSS_END );
		self::assertNotFalse( $begin, 'The begin marker is missing.' );
		self::assertNotFalse( $end, 'The end marker is missing.' );
		$block = substr( $css, $begin, $end + strlen( Icons::CSS_END ) - $begin );

		if ( '1' === getenv( 'ESG_UPDATE_ICON_CSS' ) && $block !== Icons::stylesheet() ) {
			file_put_contents( $path, substr( $css, 0, $begin ) . Icons::stylesheet() . substr( $css, $end + strlen( Icons::CSS_END ) ) );
			$css   = (string) file_get_contents( $path );
			$block = substr( $css, $begin, (int) strpos( $css, Icons::CSS_END ) + strlen( Icons::CSS_END ) - $begin );
		}
		self::assertSame( Icons::stylesheet(), $block, 'Regenerate with ESG_UPDATE_ICON_CSS=1 vendor/bin/phpunit --filter ButtonsTest.' );

		foreach ( Icons::CLASSES as $name ) {
			self::assertTrue( Icons::isClass( $name ), "{$name} is in CLASSES but not in the registry." );
			self::assertStringContainsString( '.esg-icon-' . $name . '::before {', $block );
		}
		self::assertSame( count( Icons::CLASSES ), substr_count( $block, 'mask-image: url(' ) / 2, 'One mask per class, with its -webkit- twin.' );
	}

	/**
	 * Every `.esg-icon-*` class the code names is one the sheet draws, so no
	 * button shows an empty square. Recounted by a second reading, so a
	 * scan that reads nothing fails.
	 */
	public function test_every_icon_class_the_code_names_is_drawn(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge( glob( $root . '/src/*.php' ) ?: array(), glob( $root . '/src/*/*.php' ) ?: array(), array( $root . '/assets/js/signature-generator.js' ) );
		$names = array();
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			preg_match_all( "/'icon'\s*=>\s*'([a-z-]+)'|icon:\s*'([a-z-]+)'/", $source, $m );
			$names = array_merge( $names, array_filter( $m[1] ), array_filter( $m[2] ) );
		}
		$button_calls = 0;
		foreach ( $files as $file ) {
			$button_calls += substr_count( (string) file_get_contents( $file ), 'icon: \'' );
		}
		self::assertGreaterThan( 0, $button_calls, 'The form script names no button icon; the scan is broken.' );
		foreach ( array_unique( $names ) as $name ) {
			self::assertTrue( Icons::has( $name ), "Unknown icon {$name}." );
		}
		$js = (string) file_get_contents( $root . '/assets/js/signature-generator.js' );
		preg_match_all( "/icon:\s*'([a-z-]+)'/", $js, $m );
		foreach ( $m[1] as $name ) {
			self::assertTrue( Icons::isClass( $name ), "The form's button icon {$name} has no class in buttons.css." );
		}
	}

	/**
	 * The plugin's own buttons draw no emoji and no dashicon, and none uses
	 * core's `.button` classes any more.
	 */
	public function test_no_emoji_dashicon_or_core_button_class(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge(
			glob( $root . '/src/*.php' ) ?: array(),
			glob( $root . '/src/admin/*.php' ) ?: array(),
			array( $root . '/assets/js/signature-generator.js', $root . '/assets/js/admin-settings.js' )
		);
		self::assertGreaterThan( 5, count( $files ) );
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			self::assertSame( 0, preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $source ), basename( $file ) . ' carries an emoji.' );
			self::assertStringNotContainsString( 'dashicons', $source, basename( $file ) . ' draws a dashicon.' );
			self::assertSame( 0, preg_match( '/class="button[ "]|class="button-link|submit_button\(/', $source ), basename( $file ) . ' prints a core button.' );
		}
	}

	/**
	 * The dark block redefines tokens the light block declares, and nothing
	 * the family reads is undeclared.
	 */
	public function test_the_dark_tokens_redefine_declared_ones_only(): void {
		$css   = self::css();
		$light = array_keys( self::values( $css, ":root {\n" ) );
		$dark  = array_keys( self::values( $css, ':root.esg-dark-mode {' ) );
		self::assertNotEmpty( $dark );
		self::assertSame( array(), array_values( array_diff( $dark, $light ) ) );
		preg_match_all( '/var\(--(esg-btn-[a-z0-9-]+)\)/', $css, $used );
		self::assertSame( array(), array_values( array_diff( array_unique( $used[1] ), self::declared( $css ) ) ) );
		self::assertStringNotContainsString( 'prefers-color-scheme', $css );
		self::assertSame( 0, preg_match( '/\bopacity\s*:/', $css ), 'A state is said with colour, never by fading.' );
	}

	/**
	 * Each category's label reads at 4.5:1 or more on its ground, in both
	 * themes, hover included; the disabled grey too, though WCAG exempts it.
	 *
	 * @dataProvider looks
	 *
	 * @param bool $dark Whether to measure the dark look.
	 */
	public function test_every_category_reads_well( bool $dark ): void {
		$css    = self::css();
		$tokens = self::values( $css, ":root {\n" );
		if ( $dark ) {
			$tokens = array_merge( $tokens, self::values( $css, ':root.esg-dark-mode {' ) );
		}
		$pairs = array(
			array( 'on-primary', 'primary' ),
			array( 'on-primary', 'primary-hover' ),
			array( 'neutral-text', 'neutral-bg' ),
			array( 'create-text', 'create-bg' ),
			array( 'caution-text', 'caution-bg' ),
			array( 'danger-text', 'danger-bg' ),
			array( 'disabled-text', 'disabled-bg' ),
		);
		foreach ( $pairs as $pair ) {
			$ratio = Settings::contrast( $tokens[ 'esg-btn-' . $pair[0] ], $tokens[ 'esg-btn-' . $pair[1] ] );
			self::assertGreaterThanOrEqual( 4.5, $ratio, sprintf( '%s on %s: %.2f:1', $pair[0], $pair[1], $ratio ) );
		}
	}

	/**
	 * The two looks.
	 *
	 * @return array<string, array{bool}>
	 */
	public static function looks(): array {
		return array(
			'light' => array( false ),
			'dark'  => array( true ),
		);
	}

	/**
	 * The sheet.
	 */
	private static function css(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/buttons.css' );
	}

	/**
	 * Every `--esg-btn-*` token the sheet declares, wherever.
	 *
	 * @param string $css Stylesheet.
	 * @return list<string>
	 */
	private static function declared( string $css ): array {
		preg_match_all( '/--(esg-btn-[a-z0-9-]+):/', $css, $m );
		return array_values( array_unique( $m[1] ) );
	}

	/**
	 * Token => value in the block that opens with $selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Opening of the block.
	 * @return array<string, string>
	 */
	private static function values( string $css, string $selector ): array {
		$start = strpos( $css, $selector );
		self::assertNotFalse( $start, $selector );
		$block = substr( $css, $start, (int) strpos( $css, "\n}", $start ) - $start );
		preg_match_all( '/--(esg-btn-[a-z0-9-]+):\s*([^;]+);/', $block, $m );
		return array_combine( $m[1], $m[2] );
	}
}
