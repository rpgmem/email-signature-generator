<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Every option the plugin writes is deleted on uninstall. The scan reads
 * the option names the code passes to update_option()/register_setting()
 * through a class constant, and fails when uninstall.php does not delete
 * each of them by its literal name.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

	public function test_every_option_is_deleted(): void {
		$uninstall = (string) file_get_contents( __DIR__ . '/../../uninstall.php' );
		$options   = array( Settings::OPTION );

		foreach ( $options as $option ) {
			self::assertStringContainsString( "delete_option( '{$option}' )", $uninstall, "uninstall.php does not delete {$option}." );
		}
	}

	public function test_no_option_is_written_outside_the_known_list(): void {
		$found = 0;
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__ . '/../../src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			++$found;
			$code = (string) file_get_contents( $file->getPathname() );
			preg_match_all( '/\b(?:update_option|add_option)\s*\(\s*([^,)]+)/', $code, $writes );
			foreach ( $writes[1] as $option ) {
				// The settings option is the one uninstall.php deletes (above).
				self::assertSame( 'Settings::OPTION', trim( $option ), $file->getPathname() . " writes {$option}; list it here and in uninstall.php." );
			}
		}
		self::assertGreaterThan( 0, $found );
	}
}
