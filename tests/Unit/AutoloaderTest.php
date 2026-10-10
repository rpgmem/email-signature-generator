<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The shipped plugin loads its classes through the inline autoloader in the
 * main file, not through Composer. Every class under src/ must sit in the
 * file that autoloader computes from its name, or it loads in the tests and
 * fatals in production.
 *
 * @coversNothing
 */
final class AutoloaderTest extends TestCase {

	public function test_every_class_lives_where_the_inline_autoloader_looks(): void {
		$src   = realpath( __DIR__ . '/../../src' );
		$found = 0;

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( (string) $src, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			if ( ! preg_match( '/^namespace\s+([^;]+);/m', $code, $ns ) || ! preg_match( '/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $code, $cls ) ) {
				continue;
			}
			++$found;

			$relative = substr( $ns[1] . '\\' . $cls[1], strlen( 'EmailSignatureGenerator\\' ) );
			$parts    = explode( '\\', $relative );
			$basename = array_pop( $parts );
			$expected = ( $parts ? implode( '/', array_map( 'strtolower', $parts ) ) . '/' : '' )
				. strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $basename ) ) . '.php';

			self::assertSame( $src . '/' . $expected, $file->getPathname(), "{$ns[1]}\\{$cls[1]} is not where the autoloader looks." );
		}

		// An empty scan must not read as clean.
		self::assertGreaterThan( 0, $found, 'No class was found under src/.' );
	}
}
