<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Regions;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EmailSignatureGenerator\Regions
 */
final class RegionsTest extends TestCase {

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

	public function test_the_defaults_have_a_unique_valid_acronym_each(): void {
		$acronyms = array_column( Regions::defaults(), 'acronym' );
		self::assertCount( 14, $acronyms );
		self::assertSame( $acronyms, array_values( array_unique( $acronyms ) ) );
		foreach ( $acronyms as $acronym ) {
			self::assertMatchesRegularExpression( Regions::ACRONYM_PATTERN, $acronym );
		}
	}

	public function test_the_signature_still_prints_name_slash_acronym(): void {
		self::assertSame( 'Diretoria Regional de Educação São Miguel / DREMP', Regions::labels( Regions::defaults() )[12] );
		self::assertSame( Regions::labels( Regions::defaults() ), Config::defaults( '' )['regions'] );
	}

	public function test_the_old_labels_parse_back_to_the_same_rows(): void {
		self::assertSame( Regions::defaults(), Regions::parse( Regions::labels( Regions::defaults() ) )['regions'] );
	}

	public function test_rows_from_the_form_are_cleaned(): void {
		$parsed = Regions::parse(
			array(
				array( 'name' => ' Norte ', 'acronym' => ' dr e n ' ),
				array( 'name' => '', 'acronym' => '' ),
				array( 'name' => 'Sem sigla', 'acronym' => '' ),
				array( 'name' => '', 'acronym' => 'ONLY' ),
				array( 'name' => 'Repetida', 'acronym' => 'DREN' ),
				array( 'name' => 'Inválida', 'acronym' => 'DRE-X' ),
				array( 'name' => 'Longa', 'acronym' => 'ABCDEFGHIJKLM' ),
				'not a row' => new \stdClass(),
			)
		);
		self::assertSame( array( array( 'name' => 'Norte', 'acronym' => 'DREN' ) ), $parsed['regions'] );
		self::assertSame( array( 'Sem sigla', 'ONLY', 'Repetida', 'Inválida', 'Longa' ), $parsed['rejected'] );
	}

	public function test_a_name_with_a_slash_splits_at_the_last_one(): void {
		self::assertSame(
			array( array( 'name' => 'Escola A / Anexo', 'acronym' => 'EA' ) ),
			Regions::parse( array( 'Escola A / Anexo / EA' ) )['regions']
		);
	}

	public function test_garbage_gives_nothing(): void {
		self::assertSame( array( 'regions' => array(), 'rejected' => array() ), Regions::parse( 42 ) );
	}
}
