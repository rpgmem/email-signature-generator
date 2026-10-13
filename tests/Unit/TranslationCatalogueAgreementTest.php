<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Build\PoCatalogue;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bin/po-catalogue.php';

/**
 * The .po, the .mo and the .l10n.php of each locale say the same thing (#88).
 *
 * WordPress prefers the .l10n.php and falls back to the .mo, and translation
 * tools read the .mo, so a fix that reaches one and not the other depends on
 * which file loaded. That shipped in Free Form Certificate for two releases
 * (rpgmem/ffcertificate#1266). Here the .l10n.php was compiled by a script
 * outside the repository, in a format the committed compiler did not write.
 *
 * Every translated .po entry must reach both compiled files with the same
 * value, and neither may carry an entry the .po does not. The .l10n.php is
 * read by including it, because PHP is the authority on how PHP escapes a
 * string; the .mo is read from its two string tables, which is all WordPress
 * reads; the .po through `bin/po-catalogue.php`, the reader the compiler
 * uses.
 *
 * @coversNothing
 */
final class TranslationCatalogueAgreementTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	/**
	 * @return list<array{0: string}>
	 */
	public static function catalogues(): array {
		$out = array();
		foreach ( glob( self::ROOT . '/languages/*.po' ) ?: array() as $po ) {
			$out[ basename( $po ) ] = array( $po );
		}
		return $out;
	}

	public function test_every_locale_ships_its_three_files(): void {
		$po = glob( self::ROOT . '/languages/*.po' ) ?: array();
		self::assertNotEmpty( $po, 'No .po was found under languages/.' );

		foreach ( $po as $file ) {
			$base = substr( $file, 0, -3 );
			self::assertFileExists( $base . '.mo' );
			self::assertFileExists( $base . '.l10n.php' );
		}
	}

	/**
	 * @dataProvider catalogues
	 */
	public function test_the_l10n_php_is_what_the_compiler_writes( string $po ): void {
		self::assertSame(
			PoCatalogue::l10n( $po, 'email-signature-generator' ),
			(string) file_get_contents( substr( $po, 0, -3 ) . '.l10n.php' ),
			basename( $po ) . ': the .l10n.php is not what bin/po-to-l10n.php writes from the .po. Run bin/i18n.sh.'
		);
	}

	/**
	 * @dataProvider catalogues
	 */
	public function test_every_translated_entry_reaches_both_compiled_files( string $po ): void {
		$mo    = $this->mo( substr( $po, 0, -3 ) . '.mo' );
		$l10n  = $this->l10n( substr( $po, 0, -3 ) . '.l10n.php' );
		$drift = array();

		foreach ( PoCatalogue::read( $po ) as $entry ) {
			if ( ! PoCatalogue::is_translated( $entry ) ) {
				continue;
			}
			$key = $entry['key'];
			if ( ( $mo[ $key ] ?? null ) !== $entry['forms'] ) {
				$drift[] = '.mo: ' . $key;
			}
			if ( ( $l10n[ $key ] ?? null ) !== $entry['forms'] ) {
				$drift[] = '.l10n.php: ' . $key;
			}
		}

		self::assertSame( array(), $drift, basename( $po ) . ": a compiled file is missing or disagrees on these entries. Run bin/i18n.sh.\n" . implode( "\n", $drift ) );
	}

	/**
	 * @dataProvider catalogues
	 */
	public function test_no_compiled_file_carries_an_entry_the_po_does_not( string $po ): void {
		$keys = array();
		foreach ( PoCatalogue::read( $po ) as $entry ) {
			$keys[ $entry['key'] ] = true;
		}

		$orphans = array();
		foreach ( array_keys( $this->mo( substr( $po, 0, -3 ) . '.mo' ) ) as $key ) {
			if ( ! isset( $keys[ $key ] ) ) {
				$orphans[] = '.mo: ' . $key;
			}
		}
		foreach ( array_keys( $this->l10n( substr( $po, 0, -3 ) . '.l10n.php' ) ) as $key ) {
			if ( ! isset( $keys[ $key ] ) ) {
				$orphans[] = '.l10n.php: ' . $key;
			}
		}

		self::assertSame( array(), $orphans, basename( $po ) . ": a compiled file holds a string the .po no longer has. Run bin/i18n.sh.\n" . implode( "\n", $orphans ) );
	}

	/**
	 * The reader saw every entry: recounted by a different route, msgid lines.
	 *
	 * An empty read would pass both comparisons above, so the count is checked
	 * exactly rather than against a floor.
	 *
	 * @dataProvider catalogues
	 */
	public function test_the_reader_sees_every_entry( string $po ): void {
		$lines = preg_match_all( '/^msgid "/m', (string) file_get_contents( $po ) );
		self::assertSame( $lines - 1, count( PoCatalogue::read( $po ) ), basename( $po ) . ': the reader and a count of msgid lines (less the header) disagree.' );
		self::assertGreaterThan( 0, count( $this->mo( substr( $po, 0, -3 ) . '.mo' ) ), 'The .mo reader returned nothing.' );
	}

	/**
	 * The .mo's entries, key => plural forms, header excluded.
	 *
	 * @return array<string, list<string>>
	 */
	private function mo( string $file ): array {
		$blob  = (string) file_get_contents( $file );
		$magic = unpack( 'V', substr( $blob, 0, 4 ) );
		self::assertSame( 0x950412de, is_array( $magic ) ? $magic[1] : null, basename( $file ) . ' is not a little-endian .mo.' );

		$head = unpack( 'Vrev/Vcount/Vorig/Vtrans', substr( $blob, 4, 16 ) );
		self::assertIsArray( $head );

		$out = array();
		for ( $i = 0; $i < $head['count']; $i++ ) {
			$k = unpack( 'Vlen/Voff', substr( $blob, $head['orig'] + $i * 8, 8 ) );
			$v = unpack( 'Vlen/Voff', substr( $blob, $head['trans'] + $i * 8, 8 ) );
			self::assertIsArray( $k );
			self::assertIsArray( $v );
			$key = substr( $blob, $k['off'], $k['len'] );
			if ( '' === $key ) {
				continue;
			}
			// A plural is keyed `msgid \0 msgid_plural`.
			$key         = explode( "\0", $key, 2 )[0];
			$out[ $key ] = explode( "\0", substr( $blob, $v['off'], $v['len'] ) );
		}
		return $out;
	}

	/**
	 * The .l10n.php's entries, key => plural forms.
	 *
	 * @return array<string, list<string>>
	 */
	private function l10n( string $file ): array {
		$data = require $file;
		self::assertIsArray( $data['messages'] ?? null, basename( $file ) . ' has no messages array.' );

		$out = array();
		foreach ( $data['messages'] as $key => $value ) {
			// PHP turns a numeric-string key into an int.
			$out[ (string) $key ] = explode( "\0", (string) $value );
		}
		return $out;
	}
}
