<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * An annotation that switches a gate off says which rule and why. A bare
 * `phpcs:ignore` silences every sniff on the line, and an unexplained one
 * cannot be judged by the next reader; `@phpstan-ignore-next-line` silences
 * every error on the line whatever identifier follows it.
 *
 * The scan reads the files PHPCS and PHPStan check, and only comments that
 * open with the token, so prose mentioning it is not a directive.
 *
 * @coversNothing
 */
final class SuppressionTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	/**
	 * A floor against "ok" or "see above", not a quality bar.
	 */
	private const MIN_REASON = 15;

	public function test_every_phpcs_annotation_names_a_sniff_and_gives_a_reason(): void {
		$bad = array();
		foreach ( $this->annotations( '/^(?:\/\/|#|\/\*+|\*)\s*phpcs:(ignore|disable)\b(.*)$/' ) as $where => $match ) {
			$rest = (string) preg_replace( '/\s*(?:\*\/|\?>).*$/', '', $match[2] );
			if ( 1 !== preg_match( '/^\s+[A-Za-z][\w.]*\.[\w.]+/', $rest ) ) {
				$bad[] = $where . ': names no sniff';
				continue;
			}
			if ( 1 !== preg_match( '/\s--\s*(.+)$/', $rest, $reason ) || mb_strlen( trim( $reason[1] ) ) < self::MIN_REASON ) {
				$bad[] = $where . ': gives no reason after "--"';
			}
		}
		self::assertSame( array(), $bad );
	}

	public function test_every_phpstan_annotation_names_an_identifier_and_gives_a_reason(): void {
		$bad = array();
		foreach ( $this->annotations( '/^(?:\/\/|#|\/\*+|\*)\s*@phpstan-ignore(-next-line|-line)?\b(.*)$/' ) as $where => $match ) {
			if ( '' !== $match[1] ) {
				$bad[] = $where . ': the -line forms silence every error; use @phpstan-ignore <identifier>';
				continue;
			}
			if ( 1 !== preg_match( '/^\s+[a-z][\w.]*\b.*\(\s*(.{' . self::MIN_REASON . ',})\)/', $match[2] ) ) {
				$bad[] = $where . ': needs an identifier and a reason in parentheses';
			}
		}
		self::assertSame( array(), $bad );
	}

	public function test_the_scan_reads_the_checked_files(): void {
		$scanned = array_map( static fn( string $path ): string => substr( $path, strlen( self::ROOT ) + 1 ), $this->files() );
		self::assertContains( 'email-signature-generator.php', $scanned );
		self::assertContains( 'uninstall.php', $scanned );

		// Recounted by a different traversal, so a scan that stopped reading
		// part of src/ fails here instead of passing as clean.
		$recount = 2;
		foreach ( array( '/src/*.php', '/src/*/*.php', '/src/*/*/*.php' ) as $level ) {
			$recount += count( glob( self::ROOT . $level ) ?: array() );
		}
		self::assertCount( $recount, $scanned );
	}

	/**
	 * Comments carrying a matching annotation, "file:line" => regex match.
	 *
	 * @param string $pattern Regex applied to the trimmed comment text.
	 * @return array<string, list<string>>
	 */
	private function annotations( string $pattern ): array {
		$found = array();
		foreach ( $this->files() as $file ) {
			foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
				if ( ! is_array( $token ) || ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}
				foreach ( explode( "\n", $token[1] ) as $offset => $line ) {
					if ( 1 === preg_match( $pattern, trim( $line ), $match ) ) {
						$found[ basename( $file ) . ':' . ( $token[2] + $offset ) ] = $match;
					}
				}
			}
		}
		return $found;
	}

	/**
	 * The files PHPCS reads (phpcs.xml.dist): the main file, uninstall.php
	 * and src/.
	 *
	 * @return list<string>
	 */
	private function files(): array {
		$files    = array( self::ROOT . '/email-signature-generator.php', self::ROOT . '/uninstall.php' );
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::ROOT . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
		sort( $files );
		return $files;
	}
}
