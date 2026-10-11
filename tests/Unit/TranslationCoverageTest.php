<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every source string has a translation in each compiled catalogue, and the
 * compiled catalogues agree with their .po. Run bin/i18n.sh after changing a
 * string, translate it in the .po, then run it again.
 *
 * @coversNothing
 */
final class TranslationCoverageTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	public function test_every_source_string_is_translated_in_every_catalogue(): void {
		$strings = $this->sourceStrings();
		self::assertNotEmpty( $strings, 'No source string was found.' );

		$catalogues = glob( self::ROOT . '/languages/*.l10n.php' ) ?: array();
		self::assertNotEmpty( $catalogues, 'No compiled catalogue was found.' );

		foreach ( $catalogues as $file ) {
			$messages = ( require $file )['messages'];
			foreach ( $strings as $string ) {
				self::assertArrayHasKey( $string, $messages, basename( $file ) . " has no translation for: {$string}" );
			}
		}
	}

	public function test_each_compiled_catalogue_is_newer_than_nothing_it_was_built_from(): void {
		foreach ( glob( self::ROOT . '/languages/*.po' ) ?: array() as $po ) {
			$l10n = ( require preg_replace( '/\.po$/', '.l10n.php', $po ) )['messages'];
			self::assertFileExists( preg_replace( '/\.po$/', '.mo', $po ) );

			preg_match_all( '/^msgid "(.+)"\nmsgstr "(.+)"$/m', (string) file_get_contents( $po ), $m, PREG_SET_ORDER );
			foreach ( $m as $entry ) {
				$id = stripcslashes( $entry[1] );
				self::assertSame( stripcslashes( $entry[2] ), $l10n[ $id ] ?? null, basename( $po ) . " and its .l10n.php disagree on: {$id}" );
			}
		}
	}

	/**
	 * Literal strings passed to the translation functions in the sources.
	 *
	 * @return list<string>
	 */
	private function sourceStrings(): array {
		$files = array( self::ROOT . '/email-signature-generator.php', self::ROOT . '/bin/build-standalone.php' );
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::ROOT . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$files[] = $file->getPathname();
		}
		$strings = array();
		foreach ( $files as $file ) {
			preg_match_all( "/\\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'email-signature-generator'/", (string) file_get_contents( $file ), $m );
			foreach ( $m[1] as $s ) {
				$strings[] = stripslashes( $s );
			}
		}
		return array_values( array_unique( $strings ) );
	}
}
