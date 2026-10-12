<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Update\GithubUpdater;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The Upgrade Notice is the one sentence an administrator reads on
 * Dashboard → Updates before applying a release, so it must describe the
 * release being offered. The updater reads `releases/latest`, so an entry for
 * any older version is text nothing can show.
 *
 * @covers \EmailSignatureGenerator\Update\GithubUpdater
 */
final class ReadmeUpgradeNoticeTest extends TestCase {

	/**
	 * The WordPress.org norm for a notice, kept although the plugin is not
	 * published there, so publishing it later never means rewriting one.
	 */
	private const MAX_LENGTH = 300;

	public function test_the_section_holds_exactly_one_entry_naming_the_stable_tag(): void {
		$entries = $this->entries();
		self::assertCount( 1, $entries, 'One entry: the release being offered.' );
		self::assertSame( Plugin::VERSION, array_key_first( $entries ), 'The entry names the version the Stable tag declares.' );
	}

	public function test_the_entry_fits_the_length_norm_and_points_at_the_changelog(): void {
		$text = (string) current( $this->entries() );
		self::assertLessThanOrEqual( self::MAX_LENGTH, mb_strlen( $text ), 'At most 300 characters: ' . $text );
		self::assertStringContainsString( 'CHANGELOG.md', $text, 'The detail lives in the CHANGELOG.' );
	}

	public function test_the_updater_reads_the_same_entry(): void {
		$parser = new ReflectionMethod( GithubUpdater::class, 'noticeFrom' );
		$parser->setAccessible( true );
		self::assertSame( current( $this->entries() ), $parser->invoke( null, $this->readme() ), 'What the updater shows is what this test checked.' );
	}

	/**
	 * The entries of the section, version => text.
	 *
	 * @return array<string, string>
	 */
	private function entries(): array {
		self::assertSame( 1, preg_match( '/^==\s*Upgrade Notice\s*==\s*$(.*?)(?=^==\s|\z)/ms', $this->readme(), $section ), 'readme.txt has an Upgrade Notice section.' );
		preg_match_all( '/^=\s*(\S+)\s*=\s*$\R+(.+?)(?=\R\s*\R|\z)/ms', $section[1], $found, PREG_SET_ORDER );
		$entries = array();
		foreach ( $found as $entry ) {
			$entries[ $entry[1] ] = trim( $entry[2] );
		}
		self::assertNotSame( array(), $entries, 'The scan found the entries; an empty section must not pass.' );
		return $entries;
	}

	private function readme(): string {
		return (string) file_get_contents( __DIR__ . '/../../readme.txt' );
	}
}
