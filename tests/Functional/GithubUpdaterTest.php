<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Update\GithubUpdater;
use WP_UnitTestCase;

/**
 * The updater inside a real WordPress, with GitHub answered by
 * `pre_http_request`, so no test reaches the network.
 *
 * @covers \EmailSignatureGenerator\Update\GithubUpdater
 */
final class GithubUpdaterTest extends WP_UnitTestCase {

	private const ZIP = 'https://github.com/rpgmem/email-signature-generator/releases/download/v9.0.0/email-signature-generator-9.0.0.zip';

	/** @var list<string> URLs requested. */
	private array $requested = array();

	/** @var array<string, array{code: int, body: string, etag?: string}|null> Response per URL fragment; null = WP_Error. */
	private array $responses = array();

	protected function setUp(): void {
		parent::setUp();
		delete_site_transient( GithubUpdater::CACHE_KEY );
		$this->requested = array();
		$this->responses = array(
			'api.github.com'            => array(
				'code' => 200,
				'body' => (string) wp_json_encode( $this->release() ),
				'etag' => '"abc"',
			),
			'raw.githubusercontent.com' => array(
				'code' => 200,
				'body' => "=== Email Signature Generator ===\nRequires at least: 6.8\nTested up to: 6.9\nRequires PHP: 8.3\n\n== Upgrade Notice ==\n\n= 9.0.0 =\nA <b>big</b> release.\n\nMore prose.\n",
			),
		);
		add_filter( 'pre_http_request', array( $this, 'fakeHttp' ), 10, 3 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'fakeHttp' ), 10 );
		delete_site_transient( GithubUpdater::CACHE_KEY );
		parent::tearDown();
	}

	/**
	 * @param mixed                $pre  Short-circuit value.
	 * @param array<string, mixed> $args Request args.
	 * @param string               $url  Requested URL.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function fakeHttp( $pre, $args, $url ) {
		$this->requested[] = $url;
		foreach ( $this->responses as $fragment => $response ) {
			if ( false !== strpos( $url, $fragment ) ) {
				if ( null === $response ) {
					return new \WP_Error( 'http', 'down' );
				}
				return array(
					'headers'  => array( 'etag' => $response['etag'] ?? '' ),
					'body'     => $response['body'],
					'response' => array( 'code' => $response['code'], 'message' => '' ),
					'cookies'  => array(),
					'filename' => null,
				);
			}
		}
		return new \WP_Error( 'http', 'unexpected ' . $url );
	}

	public function test_a_newer_release_is_offered_with_its_own_compatibility(): void {
		$result = GithubUpdater::checkForUpdate( $this->transient( Plugin::VERSION ) );
		$entry  = $result->response[ plugin_basename( Plugin::file() ) ];

		self::assertSame( '9.0.0', $entry->new_version );
		self::assertSame( self::ZIP, $entry->package );
		self::assertSame( '6.9', $entry->tested );
		self::assertSame( '8.3', $entry->requires_php );
		self::assertSame( 'A big release.', $entry->upgrade_notice );
	}

	public function test_a_current_install_goes_to_no_update(): void {
		$result = GithubUpdater::checkForUpdate( $this->transient( '9.0.0' ) );
		self::assertArrayNotHasKey( plugin_basename( Plugin::file() ), $result->response );
		self::assertSame( '9.0.0', $result->no_update[ plugin_basename( Plugin::file() ) ]->new_version );
	}

	public function test_the_zip_is_chosen_never_the_standalone_html(): void {
		$parsed = GithubUpdater::parseRelease( $this->release() );
		self::assertSame( self::ZIP, $parsed['package'] );
		self::assertSame( str_repeat( 'a', 64 ), $parsed['sha256'] );
	}

	public function test_a_release_without_the_zip_offers_nothing(): void {
		$release           = $this->release();
		$release['assets'] = array_slice( $release['assets'], 0, 1 );
		self::assertNull( GithubUpdater::parseRelease( $release ) );
	}

	public function test_a_scan_that_is_not_a_real_one_is_left_alone(): void {
		$transient = new \stdClass();
		self::assertSame( $transient, GithubUpdater::checkForUpdate( $transient ) );
		self::assertSame( array(), $this->requested );
	}

	public function test_the_release_is_cached(): void {
		GithubUpdater::checkForUpdate( $this->transient( Plugin::VERSION ) );
		$count = count( $this->requested );
		GithubUpdater::checkForUpdate( $this->transient( Plugin::VERSION ) );
		self::assertCount( $count, $this->requested, 'A second scan within the TTL fetched again.' );
	}

	public function test_github_down_breaks_nothing(): void {
		$this->responses['api.github.com'] = null;
		$transient                         = $this->transient( Plugin::VERSION );
		$result                            = GithubUpdater::checkForUpdate( $transient );
		self::assertObjectNotHasProperty( "response", $result, "Nothing was offered while GitHub was down." );
	}

	public function test_the_update_and_the_details_modal_carry_the_installed_icon(): void {
		$base     = plugin_dir_url( Plugin::file() ) . 'assets/img/brand/';
		$expected = array(
			'1x'  => $base . 'icon-128.png',
			'2x'  => $base . 'icon-256.png',
			'svg' => $base . 'icon.svg',
		);

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );
		self::assertSame( $expected, $info->icons );

		$older = GithubUpdater::checkForUpdate( $this->transient( '0.0.1' ) );
		self::assertSame( $expected, current( $older->response )->icons );
	}

	public function test_the_details_modal(): void {
		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );
		self::assertSame( 'Email Signature Generator', $info->name );
		self::assertSame( '9.0.0', $info->version );
		self::assertSame( self::ZIP, $info->download_link );

		self::assertFalse( GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => 'other' ) ) );
	}

	public function test_the_details_modal_has_the_banner_and_the_readme_tabs_in_order(): void {
		$this->responses['raw.githubusercontent.com']['body'] = "=== Email Signature Generator ===\nRequires PHP: 8.3\n\n== Description ==\n\nBuilds **one** signature.\n\n== Frequently Asked Questions ==\n\n= Is it free? =\n\nYes.\n\n== Screenshots ==\n\nThe images live in the repository.\n\n1. The form\n2. Dark mode\n\n== Installation ==\n\nActivate it.\n\n== Privacy ==\n\nNothing leaves.\n\n== Changelog ==\n\nSee CHANGELOG.md.\n";
		$release         = $this->release();
		$release['body'] = "### Changed\n\n- **The logo** moved (#73).";
		$this->responses['api.github.com']['body'] = (string) wp_json_encode( $release );

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );

		$base = plugin_dir_url( Plugin::file() ) . 'assets/img/brand/';
		self::assertSame(
			array(
				'low'  => $base . 'banner-772x250.png',
				'high' => $base . 'banner-1544x500.png',
			),
			$info->banners
		);
		self::assertSame( array( 'description', 'installation', 'faq', 'changelog', 'screenshots', 'other_notes' ), array_keys( $info->sections ) );
		self::assertSame( '<p>Builds <strong>one</strong> signature.</p>', $info->sections['description'] );
		self::assertSame( '<h4>Is it free?</h4><p>Yes.</p>', $info->sections['faq'] );
		self::assertSame( '<h3>Privacy</h3><p>Nothing leaves.</p>', $info->sections['other_notes'] );
		self::assertStringContainsString( '<h4>Changed</h4><ul><li><strong>The logo</strong> moved (<a href="https://github.com/rpgmem/email-signature-generator/issues/73">#73</a>).</li></ul>', $info->sections['changelog'] );
		self::assertStringContainsString( 'https://raw.githubusercontent.com/rpgmem/email-signature-generator/v9.0.0/docs/screenshots/1-form.png', $info->sections['screenshots'] );
		self::assertStringNotContainsString( 'live in the repository', $info->sections['screenshots'], 'Prose before the caption list is not a caption.' );
	}

	public function test_a_release_readme_that_cannot_be_fetched_falls_back_to_the_installed_one(): void {
		$this->responses['raw.githubusercontent.com'] = null;

		$info = GithubUpdater::pluginInfo( false, 'plugin_information', (object) array( 'slug' => GithubUpdater::SLUG ) );

		self::assertArrayHasKey( 'description', $info->sections );
		self::assertArrayHasKey( 'screenshots', $info->sections );
	}

	public function test_a_foreign_package_is_not_intercepted(): void {
		self::assertFalse( GithubUpdater::verifyDownload( false, 'https://example.org/other.zip' ) );
	}

	public function test_network_readme_values_that_are_not_versions_are_dropped(): void {
		$meta = GithubUpdater::readmeMetadata( "Requires at least: 6.8; DROP\nTested up to: 6.9\nRequires PHP: <script>\n" );
		self::assertSame( '', $meta['requires'] );
		self::assertSame( '6.9', $meta['tested'] );
		self::assertSame( '', $meta['requires_php'] );
	}

	public function test_the_hooks_are_registered(): void {
		self::assertNotFalse( has_filter( 'pre_set_site_transient_update_plugins', array( GithubUpdater::class, 'checkForUpdate' ) ) );
		self::assertNotFalse( has_filter( 'upgrader_pre_download', array( GithubUpdater::class, 'verifyDownload' ) ) );
	}

	private function transient( string $installed ): \stdClass {
		$t          = new \stdClass();
		$t->checked = array( plugin_basename( Plugin::file() ) => $installed );
		return $t;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function release(): array {
		return array(
			'tag_name'     => 'v9.0.0',
			'html_url'     => 'https://github.com/rpgmem/email-signature-generator/releases/tag/v9.0.0',
			'body'         => 'Notes.',
			'published_at' => '2026-10-10T00:00:00Z',
			'assets'       => array(
				array(
					'name'                 => 'email-signature-generator-9.0.0.html',
					'browser_download_url' => 'https://github.com/rpgmem/email-signature-generator/releases/download/v9.0.0/email-signature-generator-9.0.0.html',
					'digest'               => 'sha256:' . str_repeat( 'b', 64 ),
				),
				array(
					'name'                 => 'email-signature-generator-9.0.0.zip',
					'browser_download_url' => self::ZIP,
					'digest'               => 'sha256:' . str_repeat( 'a', 64 ),
				),
			),
		);
	}
}
