<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The form follows Free Form Certificate's public forms: the same type steps,
 * written the same way.
 *
 * Each step is `max(<px floor>, <rem>)`. The rem keeps the browser's font
 * size preference; the floor keeps a theme that shrinks the root (the
 * `html { font-size: 62.5% }` idiom) from shrinking the form with it. The two
 * halves must agree at a 16px root, or the step is a range nobody chose.
 *
 * @coversNothing
 */
final class FormStyleTest extends TestCase {

	/**
	 * Free Form Certificate's values for the steps the form uses, in pixels.
	 */
	private const STEPS = array(
		'sm'   => 13,
		'base' => 14,
		'xl'   => 18,
	);

	public function test_the_type_steps_are_free_form_certificates(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/signature-generator.css' );
		preg_match_all( '/--esg-font-size-([a-z0-9]+):\s*max\((\d+)px,\s*([\d.]+)rem\);/', $css, $m, PREG_SET_ORDER );

		$found = array();
		foreach ( $m as $step ) {
			$found[ $step[1] ] = (int) $step[2];
			self::assertSame( (float) $step[2], (float) $step[3] * 16, sprintf( 'The two halves of --esg-font-size-%s disagree at a 16px root.', $step[1] ) );
		}
		self::assertSame( self::STEPS, $found );
	}

	public function test_no_size_in_the_form_is_relative_to_the_theme(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/signature-generator.css' );
		self::assertDoesNotMatchRegularExpression( '/font-size:\s*[\d.]+em\b/', $css, 'An em size follows the theme\'s body size, which is what the scale replaced.' );
	}
}
