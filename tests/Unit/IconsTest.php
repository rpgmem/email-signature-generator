<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use EmailSignatureGenerator\Admin\Icons;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EmailSignatureGenerator\Admin\Icons
 */
final class IconsTest extends TestCase {

	public function test_an_icon_is_a_line_drawing_in_the_text_colour(): void {
		$svg = Icons::svg( 'settings', 16 );
		self::assertStringStartsWith( '<svg class="esg-icon"', $svg );
		self::assertStringContainsString( 'width="16" height="16"', $svg );
		self::assertStringContainsString( 'stroke="currentColor"', $svg );
		self::assertStringContainsString( 'aria-hidden="true"', $svg );
	}

	public function test_an_unknown_icon_draws_nothing(): void {
		self::assertSame( '', Icons::svg( 'nope' ) );
		self::assertFalse( Icons::has( 'nope' ) );
	}

	/**
	 * A drawing may carry geometry only: no script, style, link or colour
	 * of its own, so a pasted SVG cannot bring one in.
	 */
	public function test_every_drawing_uses_the_closed_vocabulary(): void {
		self::assertNotEmpty( Icons::names() );
		foreach ( Icons::names() as $name ) {
			$svg = Icons::svg( $name );
			preg_match( '/^<svg[^>]*>(.*)<\/svg>$/s', $svg, $m );
			preg_match_all( '/<\/?([a-z]+)/', $m[1], $tags );
			self::assertNotEmpty( $tags[1], $name );
			foreach ( $tags[1] as $tag ) {
				self::assertContains( $tag, array( 'path', 'circle', 'rect', 'line', 'polyline' ), "{$name} uses <{$tag}>." );
			}
			preg_match_all( '/\s([a-z-]+)="([^"]*)"/', $m[1], $attrs, PREG_SET_ORDER );
			foreach ( $attrs as $attr ) {
				self::assertContains( $attr[1], array( 'd', 'cx', 'cy', 'r', 'x', 'y', 'width', 'height', 'rx', 'x1', 'y1', 'x2', 'y2', 'points', 'fill', 'stroke' ), "{$name} sets {$attr[1]}." );
				if ( in_array( $attr[1], array( 'fill', 'stroke' ), true ) ) {
					self::assertContains( $attr[2], array( 'none', 'currentColor' ), "{$name} paints its own colour." );
				}
			}
		}
	}

	public function test_every_tab_icon_exists(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/admin/settings-page.php' );
		preg_match_all( "/'icon'\s*=>\s*'([a-z-]+)'|openCard\(.*\),\s*'([a-z-]+)'\s*\);/", $source, $m );
		$names = array_filter( array_merge( $m[1], $m[2] ) );
		// Recounted by another reading, so a scan that misses a use fails.
		$uses = substr_count( $source, 'self::openCard(' ) + preg_match_all( "/'icon'\s*=>/", $source );
		self::assertGreaterThan( 0, $uses );
		self::assertSame( $uses, count( $names ), 'The scan missed an icon the screen uses.' );
		foreach ( $names as $name ) {
			self::assertTrue( Icons::has( $name ), "The settings screen uses the unknown icon {$name}." );
		}
	}
}
