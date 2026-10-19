<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Settings;
use EmailSignatureGenerator\Shortcode;
use EmailSignatureGenerator\Theme;
use WP_UnitTestCase;

/**
 * The shortcode inside a real WordPress.
 *
 * @covers \EmailSignatureGenerator\Shortcode
 * @covers \EmailSignatureGenerator\Theme
 */
final class ShortcodeTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Each test reads what it enqueued, not what an earlier one left.
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

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

	public function test_the_install_instructions_follow_the_settings(): void {
		$config = $this->config( do_shortcode( '[email_signature]' ) );
		self::assertSame(
			array(
				'sme.prefeitura.sp.gov.br'     => 'outlook',
				'edu.sme.prefeitura.sp.gov.br' => 'gmail',
			),
			$config['mailClients']
		);
		self::assertNotSame( '', $config['strings']['installOutlook'] );

		update_option(
			Settings::OPTION,
			array(
				'mail_clients' => array(
					array( 'domain' => 'sme.prefeitura.sp.gov.br', 'client' => 'none' ),
					array( 'domain' => 'example.org', 'client' => 'gmail' ),
				),
			)
		);
		self::assertSame( array( 'example.org' => 'gmail' ), $this->config( do_shortcode( '[email_signature]' ) )['mailClients'] );
	}

	/**
	 * The configuration a rendered shortcode carries.
	 *
	 * @param string $html The shortcode's output.
	 * @return array<string, mixed>
	 */
	private function config( string $html ): array {
		self::assertSame( 1, preg_match( '/data-esg-config="([^"]+)"/', $html, $m ) );
		return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
	}

	public function test_the_form_width_comes_from_the_settings(): void {
		update_option( Settings::OPTION, array( 'form_width' => 900 ) );

		self::assertStringContainsString( 'style="max-width:900px"', do_shortcode( '[email_signature]' ) );
	}

	public function test_a_custom_design_reaches_the_stylesheet_once_and_in_the_light_look(): void {
		update_option( Settings::OPTION, array( 'custom_style' => true, 'colors' => array( 'accent' => '#aa0000' ), 'radius' => 0, 'font' => 'theme' ) );

		self::assertStringContainsString( 'style="max-width:640px"', do_shortcode( '[email_signature]' ), 'The colours left the style attribute, where the dark block could not override them.' );
		do_shortcode( '[email_signature]' );

		$after = wp_styles()->get_data( Shortcode::STYLE_HANDLE, 'after' );
		self::assertIsArray( $after );
		self::assertCount( 1, $after, 'Two generators on a page add the rule once.' );
		self::assertStringContainsString( ':root:not(.esg-dark-mode) .esg-generator { --esg-accent: #aa0000;', $after[0] );
	}

	public function test_the_dark_mode_switch_loads_with_the_form_and_runs_before_it(): void {
		update_option( Settings::OPTION, array( 'dark_mode' => 'on' ) );

		do_shortcode( '[email_signature]' );

		self::assertTrue( wp_script_is( Theme::HANDLE, 'enqueued' ) );
		self::assertContains( Theme::HANDLE, wp_scripts()->registered[ Shortcode::SCRIPT_HANDLE ]->deps );
		self::assertStringEndsWith( '})("on");', implode( '', (array) wp_scripts()->get_data( Theme::HANDLE, 'after' ) ) );
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

	public function test_analytics_off_loads_nothing_of_googles(): void {
		update_option( Settings::OPTION, array( 'analytics' => false, 'analytics_id' => 'G-ABC1234' ) );

		$html = do_shortcode( '[email_signature]' );

		self::assertFalse( wp_script_is( Shortcode::GTAG_HANDLE, 'enqueued' ) );
		self::assertSame( 1, preg_match( '/data-esg-config="([^"]+)"/', $html, $m ) );
		self::assertFalse( json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true )['analytics'] );
	}

	public function test_analytics_on_loads_googles_tag_with_the_id(): void {
		update_option( Settings::OPTION, array( 'analytics' => true, 'analytics_id' => 'G-ABC1234' ) );

		$html = do_shortcode( '[email_signature]' );

		self::assertTrue( wp_script_is( Shortcode::GTAG_HANDLE, 'enqueued' ) );
		self::assertSame( 'https://www.googletagmanager.com/gtag/js?id=G-ABC1234', wp_scripts()->registered[ Shortcode::GTAG_HANDLE ]->src );
		self::assertStringContainsString( 'gtag("config","G-ABC1234")', implode( '', wp_scripts()->get_data( Shortcode::GTAG_HANDLE, 'after' ) ) );
		self::assertSame( 1, preg_match( '/data-esg-config="([^"]+)"/', $html, $m ) );
		self::assertTrue( json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true )['analytics'] );
	}

	public function test_its_own_assets_are_versioned_by_file_time(): void {
		do_shortcode( '[email_signature]' );

		$js  = wp_scripts()->registered[ Shortcode::SCRIPT_HANDLE ]->ver;
		$css = wp_styles()->registered[ Shortcode::STYLE_HANDLE ]->ver;
		// The time of the file that loads: the `.min` build, unless SCRIPT_DEBUG.
		self::assertSame( Plugin::VERSION . '.' . filemtime( Plugin::dir() . Plugin::asset( 'assets/js/signature-generator.js' ) ), $js, 'A deploy changes the file time, so a cached script is replaced.' );
		self::assertSame( Plugin::VERSION . '.' . filemtime( Plugin::dir() . Plugin::asset( 'assets/css/signature-generator.css' ) ), $css );
		self::assertStringEndsWith( 'assets/js/signature-generator.min.js', wp_scripts()->registered[ Shortcode::SCRIPT_HANDLE ]->src );
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
		wp_deregister_script( Shortcode::GTAG_HANDLE );
		parent::tearDown();
	}
}
