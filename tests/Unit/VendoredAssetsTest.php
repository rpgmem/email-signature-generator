<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Shortcode;
use PHPUnit\Framework\TestCase;

/**
 * Third-party browser code is vendored, never loaded from a CDN, and its
 * version is in the file name so the enqueue and the file cannot disagree.
 * A new bundle in assets/vendor/ fails here until it is listed.
 *
 * @coversNothing
 */
final class VendoredAssetsTest extends TestCase {

	public function test_the_vendor_directory_holds_exactly_the_known_bundles(): void {
		$files = array_values( array_diff( (array) scandir( __DIR__ . '/../../assets/vendor' ), array( '.', '..' ) ) );
		self::assertSame( array( 'html2canvas-' . Shortcode::HTML2CANVAS_VERSION . '.min.js' ), $files );
	}

	public function test_no_script_or_stylesheet_loads_from_another_host(): void {
		$roots = array( 'src', 'assets/js', 'assets/css', 'bin' );
		$found = 0;
		foreach ( $roots as $dir ) {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__ . '/../../' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				++$found;
				$code = (string) file_get_contents( $file->getPathname() );
				self::assertDoesNotMatchRegularExpression( '#(cdnjs|unpkg|jsdelivr)\.#i', $code, $file->getPathname() . ' loads from a CDN.' );
			}
		}
		self::assertGreaterThan( 0, $found, 'The scan read no file.' );
	}
}
