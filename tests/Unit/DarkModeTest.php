<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Admin\Autosave;
use EmailSignatureGenerator\Settings;
use EmailSignatureGenerator\Theme;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Dark mode for the settings screen and the form: the setting, its
 * autosave, the script that applies it, and the palettes that make each one
 * dark.
 *
 * @covers \EmailSignatureGenerator\Settings
 * @covers \EmailSignatureGenerator\Admin\Autosave
 * @covers \EmailSignatureGenerator\Theme
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
		$script = Theme::script( 'auto' );
		self::assertStringEndsWith( '})("auto");', $script );
		self::assertStringContainsString( 'classList.toggle("esg-dark-mode",', $script );
		self::assertStringContainsString( 'prefers-color-scheme: dark', $script );
		self::assertStringContainsString( 'addEventListener("esg:setting-saved"', $script );

		self::assertStringEndsWith( '})("off");', Theme::script( '"); alert(1); ("' ), 'An unknown value is never printed.' );
	}

	/**
	 * Without Free Form Certificate, this plugin's own setting decides, and a
	 * save that posts no Dark Mode is the default, as before (#106).
	 */
	public function test_without_ffc_the_local_setting_decides(): void {
		self::assertFalse( defined( 'FFC_VERSION' ), 'Another test leaked FFC_VERSION into this process.' );
		self::stubOptions( array( 'dark_mode' => 'on' ), array( 'dark_mode' => 'auto' ) );

		self::assertFalse( Theme::followsFfc() );
		self::assertSame( 'on', Theme::mode() );
		self::assertSame( 'off', Settings::save( array() )['dark_mode'] );
		self::assertSame( 'auto', Autosave::read( (array) Autosave::apply( Settings::get(), 'dark_mode', 'auto' ), 'dark_mode' ) );
	}

	/**
	 * With Free Form Certificate active, its Dark Mode decides; an unknown
	 * or missing value is its own default, "off".
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_with_ffc_its_setting_decides(): void {
		define( 'FFC_VERSION', '9.9.9' );
		self::assertTrue( Theme::followsFfc() );

		foreach ( array( 'off', 'on', 'auto' ) as $mode ) {
			self::stubOptions( array( 'dark_mode' => 'off' ), array( 'dark_mode' => $mode ) );
			self::assertSame( $mode, Theme::mode() );
		}
		foreach ( array( array( 'dark_mode' => 'dim' ), array( 'dark_mode' => array( 'on' ) ), array(), 'garbage' ) as $stored ) {
			self::stubOptions( array( 'dark_mode' => 'on' ), $stored );
			self::assertSame( 'off', Theme::mode(), 'An unknown value is Free Form Certificate\'s default, never the local one.' );
		}

		self::stubOptions( array( 'dark_mode' => 'off' ), array( 'dark_mode' => 'auto' ) );
		Functions\when( 'wp_script_is' )->justReturn( false );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\expect( 'wp_add_inline_script' )->once()->with( Theme::HANDLE, Theme::script( 'auto' ) );
		Theme::enqueue( false );
	}

	/**
	 * With Free Form Certificate active, the local field is disabled and
	 * posts nothing: saving keeps the stored value instead of resetting it,
	 * and the autosave refuses to change it.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_with_ffc_a_save_keeps_the_local_value(): void {
		define( 'FFC_VERSION', '9.9.9' );
		self::stubOptions( array( 'dark_mode' => 'on' ), array( 'dark_mode' => 'off' ) );

		self::assertSame( 'on', Settings::save( array( 'analytics' => '1' ) )['dark_mode'] );
		self::assertSame( 'auto', Settings::save( array( 'dark_mode' => 'auto' ) )['dark_mode'], 'A value that is posted is still taken.' );

		$refused = Autosave::apply( Settings::get(), 'dark_mode', 'auto' );
		self::assertInstanceOf( WP_Error::class, $refused );
		self::assertSame( 'controlled_by_ffc', $refused->get_error_code() );
	}

	/**
	 * Stub the two options: this plugin's settings and Free Form Certificate's.
	 *
	 * @param mixed $local This plugin's stored settings.
	 * @param mixed $ffc   Free Form Certificate's stored settings.
	 */
	private static function stubOptions( $local, $ffc ): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) use ( $local, $ffc ) {
				if ( Settings::OPTION === $name ) {
					return $local;
				}
				return Theme::FFC_OPTION === $name ? $ffc : $fallback;
			}
		);
	}

	/**
	 * Every button the form draws reads at 4.5:1 or more in the form's own
	 * palette, which the family's primary maps onto (#106): the label on the
	 * accent, at rest and on hover, in both looks.
	 *
	 * @dataProvider looks
	 *
	 * @param bool $dark Whether to measure the dark look.
	 */
	public function test_the_form_buttons_read_well( bool $dark ): void {
		$css    = self::formCss();
		$tokens = self::values( $css, ".esg-generator {\n\t--esg-border" );
		if ( $dark ) {
			$tokens = array_merge( $tokens, self::values( $css, ':root.esg-dark-mode .esg-generator {' ) );
		}
		foreach ( array( 'esg-accent', 'esg-accent-hover' ) as $ground ) {
			$ratio = Settings::contrast( $tokens['esg-accent-text'], $tokens[ $ground ] );
			self::assertGreaterThanOrEqual( 4.5, $ratio, sprintf( 'accent-text on %s: %.2f:1', $ground, $ratio ) );
		}
		self::assertStringContainsString( '--esg-btn-primary: var(--esg-accent);', $css );
		self::assertStringContainsString( '--esg-btn-primary-hover: var(--esg-accent-hover);', $css );
		self::assertStringContainsString( '--esg-btn-on-primary: var(--esg-accent-text);', $css );
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
	 * The form's dark block redefines colour tokens the light block declares,
	 * and nothing else. Nothing in the sheet keys on the operating system.
	 */
	public function test_the_form_dark_palette_redefines_declared_tokens_only(): void {
		$css   = self::formCss();
		$light = self::tokens( $css, '.esg-generator {' );
		$dark  = self::tokens( $css, ':root.esg-dark-mode .esg-generator {' );

		self::assertSame( array(), array_values( array_diff( $dark, $light ) ) );
		self::assertStringNotContainsString( 'prefers-color-scheme', $css, 'The setting decides, not the operating system.' );
	}

	/**
	 * Every pair the form draws text with reads at 4.5:1 or more, in both
	 * looks: the dark block merged over the light one, as the browser does.
	 *
	 * @dataProvider looks
	 *
	 * @param bool $dark Whether to measure the dark look.
	 */
	public function test_the_form_pairs_read_well( bool $dark ): void {
		$light = self::values( self::formCss(), '.esg-generator {' );
		$dark  = $dark ? array_merge( $light, self::values( self::formCss(), ':root.esg-dark-mode .esg-generator {' ) ) : $light;
		$pairs = array(
			array( 'esg-text', 'esg-surface' ),
			array( 'esg-muted', 'esg-surface' ),
			array( 'esg-accent', 'esg-surface' ),
			array( 'esg-error', 'esg-surface' ),
			array( 'esg-accent-text', 'esg-accent' ),
			array( 'esg-accent', 'esg-accent-text' ),
			array( 'esg-muted', 'esg-readonly-bg' ),
			array( 'esg-text', 'esg-field-bg' ),
			array( 'esg-placeholder', 'esg-field-bg' ),
		);
		foreach ( $pairs as $pair ) {
			$ratio = Settings::contrast( $dark[ $pair[0] ], $dark[ $pair[1] ] );
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

	public function test_the_script_names_the_class_the_stylesheets_read(): void {
		self::assertStringContainsString( '.' . Theme::CSS_CLASS . ' .esg-generator', self::formCss() );
		self::assertStringContainsString( '"' . Theme::CSS_CLASS . '"', Theme::script( 'on' ) );
	}

	/**
	 * The form's stylesheet.
	 */
	private static function formCss(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/signature-generator.css' );
	}

	/**
	 * Token => value declared in the block that opens with $selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Opening of the block.
	 * @return array<string, string>
	 */
	private static function values( string $css, string $selector ): array {
		$start = strpos( $css, $selector );
		self::assertNotFalse( $start, $selector );
		$block = substr( $css, $start, (int) strpos( $css, "\n}", $start ) - $start );
		preg_match_all( '/--(esg-[a-z0-9-]+):\s*(#[0-9a-f]{6});/', $block, $m );
		return array_combine( $m[1], $m[2] );
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
