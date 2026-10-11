<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * The version and the two floors are declared in several files that
 * WordPress, Composer and PHPCS read before any PHP runs. Each must agree with
 * the constant the code uses, or one of them silently lies.
 *
 * @covers \EmailSignatureGenerator\Plugin
 */
final class PluginIdentityTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	public function test_the_version_agrees_everywhere(): void {
		self::assertSame( Plugin::VERSION, $this->header( 'Version' ), 'Plugin header Version.' );
		self::assertSame( 1, preg_match( "/^define\\( 'EMAIL_SIGNATURE_GENERATOR_VERSION', '([^']+)' \\);$/m", (string) file_get_contents( self::ROOT . '/' . Plugin::SLUG . '.php' ), $m ), 'The main file defines the version constant once.' );
		self::assertSame( $this->header( 'Version' ), $m[1], 'The constant beside the header.' );
		self::assertSame( Plugin::VERSION, $this->readme( 'Stable tag' ), 'readme.txt Stable tag.' );
	}

	public function test_the_php_floor_agrees_everywhere(): void {
		self::assertSame( Plugin::REQUIRES_PHP, $this->header( 'Requires PHP' ), 'Plugin header.' );
		self::assertSame( Plugin::REQUIRES_PHP, $this->readme( 'Requires PHP' ), 'readme.txt.' );

		$composer = json_decode( (string) file_get_contents( self::ROOT . '/composer.json' ), true );
		self::assertSame( '>=' . Plugin::REQUIRES_PHP, $composer['require']['php'], 'composer.json.' );

		$phpcs = (string) file_get_contents( self::ROOT . '/phpcs.xml.dist' );
		self::assertStringContainsString( '<config name="testVersion" value="' . Plugin::REQUIRES_PHP . '-"/>', $phpcs, 'PHPCompatibility testVersion.' );
	}

	public function test_the_wordpress_floor_agrees_everywhere(): void {
		self::assertSame( Plugin::REQUIRES_WP, $this->header( 'Requires at least' ), 'Plugin header.' );
		self::assertSame( Plugin::REQUIRES_WP, $this->readme( 'Requires at least' ), 'readme.txt.' );

		$phpcs = (string) file_get_contents( self::ROOT . '/phpcs.xml.dist' );
		self::assertStringContainsString( '<config name="minimum_wp_version" value="' . Plugin::REQUIRES_WP . '"/>', $phpcs, 'WPCS minimum_wp_version.' );
	}

	public function test_the_slug_names_the_main_file_and_the_text_domain(): void {
		self::assertFileExists( self::ROOT . '/' . Plugin::SLUG . '.php' );
		self::assertSame( Plugin::SLUG, $this->header( 'Text Domain' ) );
	}

	public function test_every_released_version_has_a_changelog_section(): void {
		$changelog = (string) file_get_contents( self::ROOT . '/CHANGELOG.md' );
		self::assertStringContainsString( '## [Unreleased]', $changelog );
	}

	private function header( string $field ): string {
		$main = (string) file_get_contents( self::ROOT . '/' . Plugin::SLUG . '.php' );
		self::assertSame( 1, preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(.+)$/m', $main, $m ), "Header field {$field} is missing." );
		return trim( $m[1] );
	}

	private function readme( string $field ): string {
		$readme = (string) file_get_contents( self::ROOT . '/readme.txt' );
		self::assertSame( 1, preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+)$/m', $readme, $m ), "readme.txt field {$field} is missing." );
		return trim( $m[1] );
	}
}
