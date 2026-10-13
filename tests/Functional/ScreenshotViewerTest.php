<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Update\ScreenshotViewer;

/**
 * The screenshot viewer loads on this plugin's "View details" modal and
 * nowhere else, with its labels.
 *
 * @covers \EmailSignatureGenerator\Update\ScreenshotViewer
 */
final class ScreenshotViewerTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_scripts'] = new \WP_Scripts();
		$GLOBALS['wp_styles']  = new \WP_Styles();
	}

	protected function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	private function loaded(): bool {
		$script = wp_script_is( ScreenshotViewer::HANDLE, 'enqueued' );
		self::assertSame( $script, wp_style_is( ScreenshotViewer::HANDLE, 'enqueued' ), 'The script and its stylesheet load together.' );
		return $script;
	}

	public function test_it_loads_on_this_plugins_modal_with_its_labels(): void {
		$_GET = array(
			'tab'    => 'plugin-information',
			'plugin' => 'email-signature-generator',
		);

		ScreenshotViewer::enqueue( 'plugin-install.php' );

		self::assertTrue( $this->loaded() );
		$config = (string) wp_scripts()->get_data( ScreenshotViewer::HANDLE, 'data' );
		self::assertStringContainsString( 'var esgScreenshots', $config );
		self::assertStringContainsString( '"counter":"%1$s of %2$s"', $config );
	}

	public function test_another_plugins_modal_does_not_get_it(): void {
		$_GET = array(
			'tab'    => 'plugin-information',
			'plugin' => 'akismet',
		);

		ScreenshotViewer::enqueue( 'plugin-install.php' );

		self::assertFalse( $this->loaded() );
	}

	public function test_the_plugin_installer_itself_does_not_get_it(): void {
		$_GET = array( 'tab' => 'search' );

		ScreenshotViewer::enqueue( 'plugin-install.php' );

		self::assertFalse( $this->loaded() );
	}

	public function test_another_screen_with_the_same_query_does_not_get_it(): void {
		$_GET = array(
			'tab'    => 'plugin-information',
			'plugin' => 'email-signature-generator',
		);

		ScreenshotViewer::enqueue( 'plugins.php' );

		self::assertFalse( $this->loaded() );
	}

	public function test_the_hook_is_registered(): void {
		self::assertSame( 10, has_action( 'admin_enqueue_scripts', array( ScreenshotViewer::class, 'enqueue' ) ) );
	}
}
