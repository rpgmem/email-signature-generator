<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Build\PoCatalogue;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bin/po-catalogue.php';

/**
 * The shapes the plugin's own catalogue does not carry yet: a literal over
 * several lines, a context, a plural, a fuzzy entry and an escape. Without
 * this, the reader's handling of them would be untested until the first
 * string that needs one.
 *
 * @covers \EmailSignatureGenerator\Build\PoCatalogue
 */
final class PoCatalogueTest extends TestCase {

	private string $file = '';

	protected function setUp(): void {
		$this->file = (string) tempnam( sys_get_temp_dir(), 'esg-po' );
		file_put_contents(
			$this->file,
			<<<'PO'
msgid ""
msgstr ""
"Language: pt_BR\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"

msgid ""
"A long "
"string"
msgstr ""
"Uma frase "
"longa"

msgctxt "menu"
msgid "Open"
msgstr "Abrir"

msgid "%d file"
msgid_plural "%d files"
msgstr[0] "%d arquivo"
msgstr[1] "%d arquivos"

#, fuzzy
msgid "Guess"
msgstr "Palpite"

msgid "Untranslated"
msgstr ""

msgid "Say \"hi\""
msgstr "Diga \"oi\""

PO
		);
	}

	protected function tearDown(): void {
		unlink( $this->file );
	}

	public function test_it_reads_every_shape(): void {
		$entries = array();
		foreach ( PoCatalogue::read( $this->file ) as $entry ) {
			$entries[ $entry['key'] ] = $entry;
		}

		self::assertSame( array( 'A long string', "menu\x04Open", '%d file', 'Guess', 'Untranslated', 'Say "hi"' ), array_keys( $entries ) );
		self::assertSame( array( 'Uma frase longa' ), $entries['A long string']['forms'] );
		self::assertSame( 'menu', $entries["menu\x04Open"]['msgctxt'] );
		self::assertSame( '%d files', $entries['%d file']['msgid_plural'] );
		self::assertSame( array( '%d arquivo', '%d arquivos' ), $entries['%d file']['forms'] );
		self::assertTrue( $entries['Guess']['fuzzy'] );
		self::assertSame( array( 'Diga "oi"' ), $entries['Say "hi"']['forms'] );
	}

	public function test_only_a_non_fuzzy_non_empty_entry_counts_as_translated(): void {
		$translated = array();
		foreach ( PoCatalogue::read( $this->file ) as $entry ) {
			$translated[ $entry['key'] ] = PoCatalogue::is_translated( $entry );
		}

		self::assertSame( array( 'A long string' => true, "menu\x04Open" => true, '%d file' => true, 'Guess' => false, 'Untranslated' => false, 'Say "hi"' => true ), $translated );
	}

	public function test_it_reads_the_header(): void {
		self::assertSame( array( 'language' => 'pt_BR', 'plural-forms' => 'nplurals=2; plural=(n > 1);' ), PoCatalogue::headers( $this->file ) );
	}

	public function test_the_compiled_l10n_matches_what_wordpress_expects(): void {
		$path = (string) tempnam( sys_get_temp_dir(), 'esg-l10n' );
		file_put_contents( $path, PoCatalogue::l10n( $this->file, 'demo' ) );
		$data = require $path;
		unlink( $path );

		self::assertSame( 'demo', $data['domain'] );
		self::assertSame( 'pt_BR', $data['language'] );
		self::assertSame(
			array(
				'A long string' => 'Uma frase longa',
				"menu\x04Open"  => 'Abrir',
				'%d file'       => "%d arquivo\0%d arquivos",
				'Say "hi"'      => 'Diga "oi"',
			),
			$data['messages']
		);
	}
}
