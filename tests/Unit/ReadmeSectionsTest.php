<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use PHPUnit\Framework\TestCase;
use EmailSignatureGenerator\Update\ReadmeSections;

/**
 * The readme and Markdown rendering behind the "View details" tabs.
 *
 * @covers \EmailSignatureGenerator\Update\ReadmeSections
 */
final class ReadmeSectionsTest extends TestCase {

	public function test_markdown_blocks_become_headings_lists_and_paragraphs(): void {
		$md = "### Changed\n\n- one\n- two\n  continued\n\n1. first\n2. second\n\nA paragraph\nover two lines.";

		self::assertSame(
			'<h4>Changed</h4><ul><li>one</li><li>two continued</li></ul><ol><li>first</li><li>second</li></ol><p>A paragraph over two lines.</p>',
			ReadmeSections::toHtml( $md )
		);
	}

	public function test_inline_code_links_bold_and_issue_references(): void {
		self::assertSame(
			'<p><strong>Bold</strong> <code>a **b** &lt;c&gt;</code> <a href="https://example.org/x">link</a> (<a href="https://github.com/o/r/issues/12">#12</a>)</p>',
			ReadmeSections::toHtml( '**Bold** `a **b** <c>` [link](https://example.org/x) (#12)', 'o/r' )
		);
	}

	public function test_markup_in_the_source_is_escaped_and_unsafe_links_stay_text(): void {
		$html = ReadmeSections::toHtml( '<script>alert(1)</script> [x](javascript:alert(1)) "q"' );

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( 'href="javascript', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_without_a_repo_issue_references_stay_text(): void {
		self::assertSame( '<p>Fixed (#5).</p>', ReadmeSections::toHtml( 'Fixed (#5).' ) );
	}

	public function test_the_title_line_is_not_a_section_and_unknown_sections_become_other_notes(): void {
		$readme = "=== Plugin ===\nTested up to: 6.9\n\n== Description ==\n\nHello.\n\n== Privacy ==\n\nNothing.\n\n== Upgrade Notice ==\n\n= 1.0 =\nSkip me.\n";

		self::assertSame(
			array(
				'description' => '<p>Hello.</p>',
				'other_notes' => '<h3>Privacy</h3><p>Nothing.</p>',
			),
			ReadmeSections::fromReadme( $readme, '', array() )
		);
	}

	public function test_screenshots_pair_each_caption_with_its_image_and_keep_a_caption_without_one(): void {
		$html = ReadmeSections::screenshots( "Intro prose.\n\n1. First \"screen\"\n2. Second", array( 'https://img/1.png' ) );

		self::assertSame(
			'<ol><li><a href="https://img/1.png"><img src="https://img/1.png" alt="First &quot;screen&quot;" /></a><p>First &quot;screen&quot;</p></li><li><p>Second</p></li></ol>',
			$html
		);
		self::assertSame( '', ReadmeSections::screenshots( 'No list here.', array() ) );
	}
}
