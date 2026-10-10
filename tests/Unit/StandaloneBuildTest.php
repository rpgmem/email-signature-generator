<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The standalone HTML: one file, nothing fetched but the logo, the plugin's
 * own configuration translated into the requested locale.
 *
 * @coversNothing
 */
final class StandaloneBuildTest extends TestCase {

	private static string $html = '';

	public static function setUpBeforeClass(): void {
		$out = tempnam( sys_get_temp_dir(), 'esg' ) . '.html';
		exec( 'php ' . escapeshellarg( __DIR__ . '/../../bin/build-standalone.php' ) . ' 9.9.9 ' . escapeshellarg( $out ) . ' 2>&1', $output, $status );
		self::assertSame( 0, $status, implode( "\n", $output ) );
		self::$html = (string) file_get_contents( $out );
		unlink( $out );
	}

	public function test_it_is_one_self_contained_file(): void {
		self::assertStringStartsWith( '<!DOCTYPE html>', self::$html );
		self::assertDoesNotMatchRegularExpression( '/<script[^>]+src=/i', self::$html, 'A script is loaded instead of inlined.' );
		self::assertDoesNotMatchRegularExpression( '/<link[^>]+stylesheet/i', self::$html, 'A stylesheet is loaded instead of inlined.' );
		self::assertStringContainsString( 'html2canvas', self::$html );
		self::assertStringContainsString( 'mountAll', self::$html );
	}

	public function test_it_carries_the_translated_configuration(): void {
		self::assertSame( 1, preg_match( '/data-esg-config="([^"]+)"/', self::$html, $m ) );
		$config = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );

		self::assertIsArray( $config );
		self::assertSame( 'Gerar assinatura', $config['strings']['generate'] );
		self::assertStringContainsString( 'lang="pt-BR"', self::$html );
	}

	public function test_the_logo_is_pinned_to_the_release_tag(): void {
		self::assertStringContainsString( 'raw.githubusercontent.com/rpgmem/email-signature-generator/v9.9.9/assets/img/default-logo.png', self::$html );
	}

	public function test_an_unknown_locale_stops_the_build(): void {
		exec( 'php ' . escapeshellarg( __DIR__ . '/../../bin/build-standalone.php' ) . ' 9.9.9 - xx_XX 2>&1', $output, $status );
		self::assertSame( 1, $status );
	}
}
