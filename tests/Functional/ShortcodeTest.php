<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Settings;
use EmailSignatureGenerator\Shortcode;
use WP_UnitTestCase;

/**
 * The shortcode inside a real WordPress.
 *
 * @covers \EmailSignatureGenerator\Shortcode
 */
final class ShortcodeTest extends WP_UnitTestCase {

	public function test_the_shortcode_is_registered(): void {
		self::assertTrue( shortcode_exists( Shortcode::TAG ) );
	}

	public function test_it_prints_a_container_with_the_configuration(): void {
		$html = do_shortcode( '[email_signature]' );

		self::assertSame( 1, preg_match( '/<div class="esg-generator" style="max-width:640px" data-esg-config="([^"]+)">/', $html, $m ) );
		$config = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
		self::assertIsArray( $config );
		self::assertSame( Plugin::url() . 'assets/img/default-logo.png', $config['logo']['url'] );
		self::assertCount( 3, $config['models'] );
	}

	public function test_the_form_width_comes_from_the_settings(): void {
		update_option( Settings::OPTION, array( 'form_width' => 900 ) );

		self::assertStringContainsString( 'style="max-width:900px"', do_shortcode( '[email_signature]' ) );
	}

	public function test_the_models_attribute_limits_the_form(): void {
		$html = do_shortcode( '[email_signature models="staff_school"]' );
		preg_match( '/data-esg-config="([^"]+)"/', $html, $m );
		$config = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );

		self::assertSame( array( 'staff_school' ), array_column( $config['models'], 'id' ) );
	}

	public function test_assets_load_only_when_the_shortcode_renders(): void {
		self::assertFalse( wp_script_is( Shortcode::SCRIPT_HANDLE, 'enqueued' ) );

		do_shortcode( '[email_signature]' );

		self::assertTrue( wp_script_is( Shortcode::SCRIPT_HANDLE, 'enqueued' ) );
		self::assertTrue( wp_script_is( Shortcode::HTML2CANVAS_HANDLE, 'enqueued' ) );
		self::assertTrue( wp_style_is( Shortcode::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_its_own_assets_are_versioned_by_file_time(): void {
		do_shortcode( '[email_signature]' );

		$js  = wp_scripts()->registered[ Shortcode::SCRIPT_HANDLE ]->ver;
		$css = wp_styles()->registered[ Shortcode::STYLE_HANDLE ]->ver;
		self::assertSame( Plugin::VERSION . '.' . filemtime( Plugin::dir() . 'assets/js/signature-generator.js' ), $js, 'A deploy changes the file time, so a cached script is replaced.' );
		self::assertSame( Plugin::VERSION . '.' . filemtime( Plugin::dir() . 'assets/css/signature-generator.css' ), $css );
	}

	public function test_a_missing_asset_falls_back_to_the_version(): void {
		self::assertSame( Plugin::VERSION, Plugin::assetVersion( 'assets/js/does-not-exist.js' ) );
	}

	public function test_every_enqueued_file_exists(): void {
		do_shortcode( '[email_signature]' );
		$dir = Plugin::dir();
		$url = Plugin::url();

		foreach ( array( wp_scripts()->registered[ Shortcode::SCRIPT_HANDLE ], wp_scripts()->registered[ Shortcode::HTML2CANVAS_HANDLE ], wp_styles()->registered[ Shortcode::STYLE_HANDLE ] ) as $dep ) {
			self::assertFileExists( $dir . substr( $dep->src, strlen( $url ) ) );
		}
	}

	protected function tearDown(): void {
		wp_dequeue_script( Shortcode::SCRIPT_HANDLE );
		wp_dequeue_script( Shortcode::HTML2CANVAS_HANDLE );
		wp_dequeue_style( Shortcode::STYLE_HANDLE );
		parent::tearDown();
	}
}
