<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Admin\SettingsPage;
use EmailSignatureGenerator\Settings;
use EmailSignatureGenerator\Shortcode;
use WP_UnitTestCase;

/**
 * The settings inside a real WordPress: saving through the Settings API,
 * applying to the form, the logo from the media library, access, and the
 * screen itself.
 *
 * @covers \EmailSignatureGenerator\Settings
 * @covers \EmailSignatureGenerator\Admin\SettingsPage
 * @covers \EmailSignatureGenerator\Shortcode::render
 */
final class SettingsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		SettingsPage::registerSetting();
		// Enqueues from earlier tests outlive them.
		wp_dequeue_script( Shortcode::SCRIPT_HANDLE );
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_saving_goes_through_the_sanitizer(): void {
		update_option( Settings::OPTION, array( 'logo_width' => '9999', 'email_domain' => '<b>x', 'access' => 'nope' ) );
		$stored = get_option( Settings::OPTION );

		self::assertSame( Settings::LOGO_WIDTH_MAX, $stored['logo_width'] );
		self::assertSame( '', $stored['email_domain'] );
		self::assertSame( Settings::ACCESS_EVERYONE, $stored['access'] );
	}

	public function test_an_install_that_never_saved_gets_the_defaults(): void {
		self::assertSame( Settings::defaults(), Settings::get() );
		self::assertTrue( Settings::siteConfig()['uppercaseName'] );
	}

	public function test_the_form_uses_the_stored_settings(): void {
		update_option(
			Settings::OPTION,
			array(
				'models'       => array( 'department' ),
				'email_domain' => 'example.org',
				'fields'       => array( 'department' => array( 'address' => array( 'default' => 'Rua A, 1', 'locked' => '1' ) ) ),
			)
		);

		$config = $this->renderedConfig( '[email_signature]' );
		self::assertSame( array( 'department' ), array_column( $config['models'], 'id' ) );
		self::assertSame( 'example.org', $config['emailDomain'] );

		$address = array_values( array_filter( $config['models'][0]['fields'], static fn( $f ) => 'address' === $f['key'] ) )[0];
		self::assertSame( 'Rua A, 1', $address['default'] );
		self::assertTrue( $address['locked'] );
	}

	public function test_the_shortcode_cannot_offer_a_disabled_model(): void {
		update_option( Settings::OPTION, array( 'models' => array( 'department' ) ) );
		self::assertSame( array( 'department' ), array_column( $this->renderedConfig( '[email_signature models="staff_school"]' )['models'], 'id' ) );
	}

	public function test_a_logo_from_the_media_library(): void {
		$attachment = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/assets/img/default-logo.png' );
		update_option( Settings::OPTION, array( 'logo_id' => $attachment, 'logo_width' => '90' ) );

		$logo = $this->renderedConfig( '[email_signature]' )['logo'];
		self::assertSame( wp_get_attachment_image_url( $attachment, 'medium' ), $logo['url'] );
		self::assertSame( 90, $logo['width'] );
	}

	public function test_a_deleted_logo_falls_back_to_the_bundled_one(): void {
		update_option( Settings::OPTION, array( 'logo_id' => 999999 ) );
		self::assertStringEndsWith( '/assets/img/default-logo.png', $this->renderedConfig( '[email_signature]' )['logo']['url'] );
	}

	public function test_logged_in_only_hides_the_form_from_visitors(): void {
		update_option( Settings::OPTION, array( 'access' => Settings::ACCESS_LOGGED_IN ) );

		$html = do_shortcode( '[email_signature]' );
		self::assertStringNotContainsString( 'data-esg-config', $html );
		self::assertStringContainsString( 'esg-login-required', $html );
		self::assertFalse( wp_script_is( Shortcode::SCRIPT_HANDLE, 'enqueued' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertStringContainsString( 'data-esg-config', do_shortcode( '[email_signature]' ) );
	}

	public function test_the_screen_renders_for_an_administrator_only(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		SettingsPage::render();
		self::assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		SettingsPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'action="options.php"', $html );
		self::assertMatchesRegularExpression( '/name=[\'"]option_page[\'"] value=[\'"]' . SettingsPage::GROUP . '[\'"]/', $html );
		self::assertStringContainsString( 'name="_wpnonce"', $html );
		self::assertStringContainsString( 'name="' . Settings::OPTION . '[fields][staff_school][address][locked]"', $html );
		self::assertStringNotContainsString( '[fields][staff_school][email][locked]', $html, 'The email is never lockable.' );
		self::assertStringContainsString( 'name="' . Settings::OPTION . '[image_quality]" value="90"', $html );
		self::assertStringContainsString( 'name="' . Settings::OPTION . '[image_scale]"', $html );
		self::assertStringContainsString( 'data-esg-autosave="offer_download"', $html );
		self::assertStringContainsString( 'name="' . Settings::OPTION . '[top_space]" value="4"', $html );
		self::assertMatchesRegularExpression( '/name="' . preg_quote( Settings::OPTION, '/' ) . '\[download_style\]" value="notice" checked/', $html );
		self::assertStringContainsString( '<option value="png" selected=\'selected\'>PNG (recommended)</option>', $html );
		self::assertStringContainsString( '<option value="1" selected=\'selected\'>1× (450 px wide)</option>', $html );
	}

	public function test_uninstall_removes_the_option(): void {
		update_option( Settings::OPTION, array( 'email_domain' => 'example.org' ) );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'email-signature-generator/email-signature-generator.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';
		// The explicit default bypasses the one register_setting() declares.
		self::assertFalse( get_option( Settings::OPTION, false ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function renderedConfig( string $shortcode ): array {
		self::assertSame( 1, preg_match( '/data-esg-config="([^"]+)"/', do_shortcode( $shortcode ), $m ) );
		return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
	}

	public function test_the_screen_is_vertical_tabs_of_boxed_sections(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = $this->renderScreen();

		foreach ( array_keys( SettingsPage::tabs() ) as $id ) {
			self::assertStringContainsString( 'id="esg-tab-' . $id . '"', $html );
			self::assertStringContainsString( 'id="esg-panel-' . $id . '"', $html );
		}
		self::assertSame( 1, substr_count( $html, 'esg-tabs__tab is-active' ) );
		self::assertStringContainsString( 'class="esg-tabs__tab is-active" id="esg-tab-general"', $html );

		// Every section heading is in a box and opens with an icon.
		preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/s', $html, $headings );
		self::assertGreaterThanOrEqual( count( SettingsPage::tabs() ), count( $headings[1] ) );
		foreach ( $headings[1] as $heading ) {
			self::assertStringStartsWith( '<svg class="esg-icon"', $heading );
		}
		self::assertSame( count( $headings[1] ), substr_count( $html, '<div class="card"><h2>' ) );

		// Every tab carries an icon.
		self::assertSame( count( SettingsPage::tabs() ), preg_match_all( '/data-esg-tab="[a-z]+"><svg class="esg-icon"/', $html ) );
	}

	public function test_the_tab_parameter_chooses_the_first_panel(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_GET['tab'] = 'fields';
		self::assertStringContainsString( 'class="esg-tabs__panel is-active" id="esg-panel-fields"', $this->renderScreen() );

		$_GET['tab'] = '<script>';
		self::assertStringContainsString( 'class="esg-tabs__panel is-active" id="esg-panel-general"', $this->renderScreen() );
		unset( $_GET['tab'] );
	}

	private function renderScreen(): string {
		ob_start();
		SettingsPage::render();
		return (string) ob_get_clean();
	}

	public function test_offices_are_saved_as_name_and_acronym_and_a_bad_row_is_reported(): void {
		global $wp_settings_errors;
		$wp_settings_errors = array();

		update_option(
			Settings::OPTION,
			array(
				'regions' => array(
					array( 'name' => 'Norte', 'acronym' => 'dren' ),
					array( 'name' => 'Sem sigla', 'acronym' => '' ),
				),
			)
		);

		self::assertSame( array( array( 'name' => 'Norte', 'acronym' => 'DREN' ) ), Settings::get()['regions'] );
		$errors = get_settings_errors( Settings::OPTION );
		self::assertSame( 'regions_rejected', $errors[0]['code'] ?? '' );
		self::assertStringContainsString( 'Sem sigla', $errors[0]['message'] );
	}

	public function test_an_install_with_the_old_text_list_reads_it_as_offices(): void {
		// Written straight to the database, as a version before acronyms did.
		global $wpdb;
		$wpdb->insert( $wpdb->options, array( 'option_name' => Settings::OPTION, 'option_value' => maybe_serialize( array( 'regions' => array( 'Norte / DREN', 'Sul / DRES' ) ) ), 'autoload' => 'yes' ) );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( Settings::OPTION, 'options' );

		self::assertSame(
			array( array( 'name' => 'Norte', 'acronym' => 'DREN' ), array( 'name' => 'Sul', 'acronym' => 'DRES' ) ),
			Settings::get()['regions']
		);
		self::assertSame( array( 'Norte / DREN', 'Sul / DRES' ), Settings::siteConfig()['regions'] );
	}

	public function test_the_offices_table_renders_a_row_per_office(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = $this->renderScreen();

		self::assertSame( 14, substr_count( $html, '[regions][' ) / 2 - 1, 'Fourteen rows plus the template, two inputs each.' );
		self::assertStringContainsString( 'name="' . Settings::OPTION . '[regions][12][acronym]" value="DREMP"', $html );
		self::assertStringContainsString( '<template class="esg-regions-template">', $html );
		self::assertStringContainsString( 'name="' . Settings::OPTION . '[unit_label]" value=""', $html );
	}
}
