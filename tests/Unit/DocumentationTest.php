<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Admin\Documentation;
use EmailSignatureGenerator\Admin\Icons;
use EmailSignatureGenerator\Admin\SettingsPage;
use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The Documentation tab explains every settings tab, and its contents list
 * and its cards come from one registry, so they cannot disagree.
 *
 * @covers \EmailSignatureGenerator\Admin\Documentation
 */
final class DocumentationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'add_query_arg' )->alias( static fn( string $key, string $value ): string => '?page=x&' . $key . '=' . $value );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A tab added without a topic fails here, which is how the documentation
	 * keeps up with the screen.
	 */
	public function test_every_settings_tab_has_exactly_one_topic(): void {
		$tabs = array_diff( array_keys( SettingsPage::tabs() ), array( 'documentation' ) );
		self::assertCount( count( SettingsPage::tabs() ) - 1, $tabs, 'The tab list has no documentation tab.' );

		$documented = array_values( array_filter( array_column( Documentation::topics(), 'tab' ) ) );
		sort( $tabs );
		sort( $documented );
		self::assertSame( array_values( $tabs ), $documented );
	}

	public function test_every_topic_has_an_icon_text_and_items(): void {
		self::assertNotEmpty( Documentation::topics() );
		foreach ( Documentation::topics() as $id => $topic ) {
			self::assertTrue( Icons::has( $topic['icon'] ), "Topic {$id} uses the unknown icon {$topic['icon']}." );
			self::assertNotSame( '', $topic['intro'], $id );
			self::assertNotEmpty( $topic['items'], $id );
			foreach ( $topic['items'] as $item ) {
				self::assertNotSame( '', $item[0], $id );
				self::assertNotSame( '', $item[1], $id );
			}
		}
		self::assertTrue( Icons::has( 'book' ), 'The contents card uses the book icon.' );
	}

	/**
	 * The numbers in the text are read from the code that enforces them, so
	 * moving a limit cannot leave the documentation behind.
	 */
	public function test_the_limits_it_quotes_are_the_ones_the_settings_enforce(): void {
		$text = self::textOf( Documentation::topics() );
		self::assertStringContainsString( Settings::FORM_WIDTH_MIN . ' to ' . Settings::FORM_WIDTH_MAX . ' px (' . Config::FORM_WIDTH . ' px', $text );
		self::assertStringContainsString( 'up to ' . Settings::LABEL_MAX . ' characters', $text );
		self::assertStringContainsString( 'for ' . Config::REMEMBER_MINUTES . ' minutes', $text );
	}

	public function test_the_contents_list_links_to_every_card(): void {
		ob_start();
		Documentation::render();
		$html = (string) ob_get_clean();

		preg_match_all( '/<a href="#(esg-doc-[a-z]+)">/', $html, $links );
		preg_match_all( '/<h2 id="(esg-doc-[a-z]+)">/', $html, $cards );
		self::assertCount( count( Documentation::topics() ), $cards[1] );
		self::assertSame( $cards[1], $links[1] );
		self::assertStringContainsString( 'data-esg-goto="fields"', $html );
		self::assertStringContainsString( 'Open the Fields tab', $html );
	}

	/**
	 * Every string of the registry joined, so a test can search its text.
	 *
	 * @param mixed $value Registry or part of it.
	 */
	private static function textOf( $value ): string {
		if ( is_array( $value ) ) {
			return implode( "\n", array_map( array( self::class, 'textOf' ), $value ) );
		}
		return (string) $value;
	}
}
