<?php

declare(strict_types=1);

namespace ESG\Tests\Functional;

use EmailSignatureGenerator\Admin\Autosave;
use EmailSignatureGenerator\Admin\SettingsPage;
use EmailSignatureGenerator\Settings;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;
use WP_Ajax_UnitTestCase;

/**
 * The toggles' AJAX endpoint inside a real WordPress: nonce, capability,
 * storage, and the refusals that must reach the screen.
 *
 * @covers \EmailSignatureGenerator\Admin\Autosave
 * @group ajax
 */
final class AutosaveTest extends WP_Ajax_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		SettingsPage::registerSetting();
		Autosave::register();
		$_POST = array();
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		$_POST = array();
		parent::tearDown();
	}

	public function test_an_administrator_turns_capitals_off(): void {
		$this->_setRole( 'administrator' );
		$response = $this->post( 'uppercase_name', '0' );

		self::assertTrue( $response['success'] );
		self::assertFalse( $response['data']['value'] );
		self::assertFalse( Settings::get()['uppercase_name'] );
	}

	public function test_models_answer_with_the_stored_list(): void {
		$this->_setRole( 'administrator' );
		$response = $this->post( 'models', array() );

		// Nothing ticked offers every model, and the screen shows it so.
		self::assertSame( Settings::modelIds(), $response['data']['value'] );
	}

	public function test_a_toggle_keeps_the_other_settings(): void {
		update_option( Settings::OPTION, array( 'restrict_email_domain' => true, 'email_domains' => array( 'example.org' ), 'logo_width' => 90 ) );
		$this->_setRole( 'administrator' );
		$this->post( 'fields.staff_school.phone.required', '0' );

		$stored = Settings::get();
		self::assertSame( array( 'example.org' ), $stored['email_domains'] );
		self::assertTrue( $stored['restrict_email_domain'] );
		self::assertSame( 90, $stored['logo_width'] );
		self::assertFalse( $stored['fields']['staff_school']['phone']['required'] );
	}

	public function test_locking_an_empty_field_is_refused_with_a_message(): void {
		$this->_setRole( 'administrator' );
		$response = $this->post( 'fields.staff_school.address.locked', '1');

		self::assertFalse( $response['success'] );
		self::assertNotEmpty( $response['data']['message'] );
		self::assertSame( array(), Settings::get()['fields'] );
	}

	public function test_an_editor_is_refused(): void {
		$this->_setRole( 'editor' );
		$response = $this->post( 'uppercase_name', '0' );

		self::assertFalse( $response['success'] );
		self::assertTrue( Settings::get()['uppercase_name'] );
	}

	public function test_a_wrong_nonce_is_refused(): void {
		$this->_setRole( 'administrator' );
		$response = $this->post( 'uppercase_name', '0', 'wrong' );

		self::assertFalse( $response['success'] );
		self::assertTrue( Settings::get()['uppercase_name'] );
	}

	/**
	 * Post one toggle and decode the JSON answer.
	 *
	 * @param string          $key    Toggle key.
	 * @param string|string[] $value  Posted value.
	 * @param string|null     $nonce  Nonce; a valid one when null.
	 * @return array<string, mixed>
	 */
	private function post( string $key, $value, ?string $nonce = null ): array {
		$_POST = array(
			'nonce' => $nonce ?? wp_create_nonce( Autosave::ACTION ),
			'key'   => $key,
			'value' => $value,
		);
		try {
			$this->_handleAjax( Autosave::ACTION );
		} catch ( WPAjaxDieContinueException | WPAjaxDieStopException $e ) {
			unset( $e );
		}
		$response             = json_decode( $this->_last_response, true );
		$this->_last_response = '';
		self::assertIsArray( $response, 'The endpoint answered no JSON.' );
		return $response;
	}
}
