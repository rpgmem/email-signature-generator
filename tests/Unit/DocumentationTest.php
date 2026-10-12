<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Admin\Documentation;
use EmailSignatureGenerator\Admin\Icons;
use EmailSignatureGenerator\Admin\SettingsPage;
use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The Documentation tab explains every settings tab, and its contents list
 * and its cards come from one registry, so they cannot disagree.
 *
 * @covers \EmailSignatureGenerator\Admin\Documentation
 */
final class DocumentationTest extends TestCase {

	/**
	 * Plugin::$file before the test, put back after it.
	 *
	 * @var string
	 */
	private string $plugin_file = '';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// The main file is never included in the unit suite, so the path
		// Plugin::boot() would record is set here.
		$property          = new \ReflectionProperty( Plugin::class, 'file' );
		$this->plugin_file = (string) $property->getValue();
		$property->setValue( null, dirname( __DIR__, 2 ) . '/' . Plugin::SLUG . '.php' );
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		// The real header, read the way WordPress reads it.
		Functions\when( 'get_file_data' )->alias(
			static function ( string $file, array $headers ): array {
				$text = (string) file_get_contents( $file );
				return array_map(
					static fn( string $name ): string => preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $text, $m ) ? trim( $m[1] ) : '',
					$headers
				);
			}
		);
		Functions\when( 'add_query_arg' )->alias( static fn( string $key, string $value ): string => '?page=x&' . $key . '=' . $value );
	}

	protected function tearDown(): void {
		( new \ReflectionProperty( Plugin::class, 'file' ) )->setValue( null, $this->plugin_file );
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
		self::assertTrue( Icons::has( 'search' ), 'The quick navigation uses the search icon.' );
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
		self::assertStringContainsString( '<input type="search" id="esg-doc-search"', $html );
		self::assertStringContainsString( 'data-esg-goto="fields"', $html );
		self::assertStringContainsString( 'Open the Fields tab', $html );
	}

	/**
	 * The contents and the topics sit in one layout element, the two columns
	 * on a wide screen; the credits follow it, outside the sticky column.
	 */
	public function test_the_contents_and_the_topics_share_one_layout(): void {
		ob_start();
		Documentation::render();
		$html = (string) ob_get_clean();

		self::assertStringStartsWith( '<div class="esg-doc-layout"><nav class="card esg-doc-contents" aria-label="Quick Navigation">', $html );
		self::assertMatchesRegularExpression( '#</ul><p class="esg-doc-search-empty" hidden>No topic matches this search\.</p></nav><div class="esg-doc-topics"><div class="card esg-doc">#', $html );
		self::assertMatchesRegularExpression( '#</div></div></div><p class="esg-doc-credits">.*</p>$#s', $html );
	}

	/**
	 * The author and the repository come from the plugin header, so they
	 * say what the Plugins screen says.
	 */
	public function test_it_credits_the_author_and_links_the_repository(): void {
		ob_start();
		Documentation::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Developed by <a href="https://github.com/rpgmem" target="_blank" rel="noopener noreferrer">Alex Meusburger</a>.', $html );
		self::assertStringContainsString( '<a href="https://github.com/rpgmem/email-signature-generator" target="_blank" rel="noopener noreferrer">github.com/rpgmem/email-signature-generator</a>', $html );
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
