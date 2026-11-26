<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Settings;
use EmailSignatureGenerator\Theme;
use PHPUnit\Framework\TestCase;

/**
 * Inside Site PWA's app the form takes the app's accent and corners, as Free
 * Form Certificate's screens do (#147): the light theme's colours and, in
 * both themes, the radius read the custom properties the app publishes, and
 * they win over the Design tab's rule.
 *
 * @covers \EmailSignatureGenerator\Settings
 */
final class AppLookTest extends TestCase {

	/**
	 * What each of the form's tokens reads inside the app, light theme.
	 */
	private const LIGHT = array(
		'--esg-accent'       => 'var(--site-pwa-accent)',
		'--esg-accent-hover' => 'var(--site-pwa-accent-text)',
		'--esg-accent-text'  => 'var(--site-pwa-on-accent)',
		'--esg-focus-ring'   => 'color-mix(in srgb, var(--site-pwa-accent-text) 75%, transparent)',
	);

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

	public function test_the_light_theme_reads_the_apps_colours(): void {
		$selector = ':root:not(.' . Theme::CSS_CLASS . ') .site-pwa-app .esg-generator';

		self::assertSame( self::LIGHT, self::declarations( $selector ) );
	}

	public function test_the_corners_read_the_apps_control_radius_in_both_themes(): void {
		self::assertSame( array( '--esg-radius' => 'var(--site-pwa-control-radius)' ), self::declarations( '.site-pwa-app .esg-generator' ) );
	}

	public function test_inside_the_app_it_outranks_the_design_tab(): void {
		$rule = Settings::styleRule( Settings::sanitize( array( 'custom_style' => '1', 'colors' => array( 'accent' => '#aa0000' ), 'radius' => 0 ) ) );
		preg_match_all( '/([^{}]+)\{/', $rule, $m );
		$design = array_map( 'trim', $m[1] );
		self::assertCount( 2, $design, 'The Design tab writes one shape rule and one colour rule.' );

		self::assertGreaterThan( self::specificity( $design[1] ), self::specificity( ':root:not(.' . Theme::CSS_CLASS . ') .site-pwa-app .esg-generator' ), 'colours' );
		self::assertGreaterThan( self::specificity( $design[0] ), self::specificity( '.site-pwa-app .esg-generator' ), 'corners' );
	}

	/**
	 * The declarations of the one rule with that exact selector.
	 *
	 * @param string $selector Selector.
	 * @return array<string, string>
	 */
	private static function declarations( string $selector ): array {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/signature-generator.css' );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		preg_match_all( '/(^|})\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $css, $m );
		self::assertCount( 1, $m[2], 'Exactly one rule for ' . $selector );

		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ';', $m[2][0] ) ) ) as $declaration ) {
			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
			$out[ $property ]         = $value;
		}
		return $out;
	}

	/**
	 * Classes and pseudo-classes in a selector, the only kinds these rules
	 * use; `:not()` counts its argument, as the cascade does.
	 *
	 * @param string $selector Selector.
	 */
	private static function specificity( string $selector ): int {
		$selector = str_replace( ':not(', '(', $selector );
		return preg_match_all( '/\.[a-z0-9_-]+|:[a-z-]+/i', $selector );
	}
}
