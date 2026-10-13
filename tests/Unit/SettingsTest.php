<?php

declare(strict_types=1);

namespace ESG\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EmailSignatureGenerator\Settings
 */
final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ): string => trim( strip_tags( (string) $s ) ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_empty_input_gives_the_defaults(): void {
		self::assertSame( Settings::defaults(), Settings::sanitize( array() ) );
	}

	public function test_analytics_is_off_by_default_and_takes_only_a_measurement_id(): void {
		self::assertFalse( Settings::defaults()['analytics'] );
		self::assertSame( '', Settings::defaults()['analytics_id'] );
		self::assertSame( 'G-ABC1234', Settings::sanitize( array( 'analytics_id' => ' g-abc1234 ' ) )['analytics_id'] );
		self::assertSame( '', Settings::sanitize( array( 'analytics_id' => '<script>' ) )['analytics_id'] );
		self::assertSame( '', Settings::sanitize( array( 'analytics_id' => 'UA-12345-1' ) )['analytics_id'], 'Universal Analytics IDs are gone.' );
	}

	public function test_analytics_is_on_only_with_an_id(): void {
		self::assertFalse( Settings::analyticsOn( Settings::sanitize( array( 'analytics' => '1' ) ) ) );
		self::assertFalse( Settings::analyticsOn( Settings::sanitize( array( 'analytics' => '0', 'analytics_id' => 'G-ABC1234' ) ) ) );
		self::assertTrue( Settings::analyticsOn( Settings::sanitize( array( 'analytics' => '1', 'analytics_id' => 'G-ABC1234' ) ) ) );
	}

	public function test_the_design_is_off_by_default_and_clears_when_switched_off(): void {
		$defaults = Settings::defaults();
		self::assertFalse( $defaults['custom_style'] );
		self::assertSame( Config::COLORS, $defaults['colors'] );
		self::assertSame( '', Settings::styleRule( $defaults ) );

		$off = Settings::sanitize( array( 'custom_style' => '0', 'colors' => array( 'accent' => '#aa0000' ), 'radius' => 0, 'font' => 'theme' ) );
		self::assertSame( Config::COLORS, $off['colors'] );
		self::assertSame( Config::RADIUS, $off['radius'] );
		self::assertSame( 'theme', $off['font'], 'Off, the font goes back to the theme\'s.' );
	}

	public function test_a_custom_design_keeps_only_valid_values(): void {
		$on = Settings::sanitize(
			array(
				'custom_style' => '1',
				'colors'       => array( 'accent' => ' #AA0000 ', 'border' => 'red', 'muted' => 'javascript:x' ),
				'radius'       => 99,
				'font'         => 'comic',
			)
		);
		self::assertSame( '#aa0000', $on['colors']['accent'] );
		self::assertSame( Config::COLORS['border'], $on['colors']['border'] );
		self::assertSame( Config::COLORS['muted'], $on['colors']['muted'] );
		self::assertSame( Settings::RADIUS_MAX, $on['radius'] );
		self::assertSame( 'theme', $on['font'], 'An unknown font falls back to the theme\'s.' );
	}

	public function test_the_style_rule_keeps_the_colours_to_the_light_look(): void {
		$rule = Settings::styleRule( Settings::sanitize( array( 'custom_style' => '1', 'colors' => array( 'accent' => '#aa0000' ), 'radius' => 0, 'font' => 'form' ) ) );
		$lines = explode( "\n", $rule );

		self::assertSame( '.esg-generator { --esg-radius: 0px; --esg-font: arial, sans-serif; }', $lines[0], 'The corners and the font hold in both looks.' );
		self::assertStringStartsWith( ':root:not(.esg-dark-mode) .esg-generator { --esg-accent: #aa0000; --esg-accent-text: #ffffff; ', $lines[1] );
		self::assertStringNotContainsString( '<', $rule, 'Nothing can close the style element.' );
	}

	public function test_the_three_fonts_and_the_legacy_arial_value(): void {
		$with = static fn( string $font ): array => Settings::sanitize( array( 'custom_style' => '1', 'font' => $font ) );

		self::assertSame( 'arial', $with( 'form' )['font'], 'Arial was stored as `form` before the three options.' );
		self::assertStringNotContainsString( '--esg-font', Settings::styleRule( $with( 'theme' ) ), 'The theme\'s font is the stylesheet\'s default.' );
		self::assertStringContainsString( '--esg-font: ' . Config::PLUGIN_FONT, Settings::styleRule( $with( 'plugin' ) ) );
		self::assertStringContainsString( '--esg-font: arial, sans-serif', Settings::styleRule( $with( 'arial' ) ) );
		self::assertSame( 'theme', $with( 'comic' )['font'] );
	}

	public function test_contrast_follows_wcag(): void {
		self::assertEqualsWithDelta( 21.0, Settings::contrast( '#000000', '#ffffff' ), 0.01 );
		self::assertEqualsWithDelta( 1.0, Settings::contrast( '#777777', '#777777' ), 0.01 );
		self::assertSame( array(), Settings::weakContrasts( Config::COLORS ), 'The built-in colours read well.' );
	}

	public function test_weak_pairs_are_named(): void {
		Functions\when( 'number_format_i18n' )->alias( static fn( $n, $d = 0 ) => number_format( (float) $n, (int) $d ) );
		$weak = Settings::weakContrasts( array_merge( Config::COLORS, array( 'accent' => '#ffff00' ) ) );
		self::assertCount( 2, $weak, 'White on yellow, and yellow on white.' );
	}

	public function test_garbage_input_gives_the_defaults(): void {
		self::assertSame( Settings::defaults(), Settings::sanitize( 'not an array' ) );
	}

	public function test_capitals_are_a_choice_of_each_free_text_field(): void {
		$config = Settings::apply( Config::defaults( '' ), Settings::defaults() );
		self::assertTrue( $this->field( $config, 'staff_regional', 'name' )['uppercase'] );
		self::assertTrue( $this->field( $config, 'staff_regional', 'sector' )['uppercase'] );
		self::assertArrayNotHasKey( 'uppercase', $this->field( $config, 'staff_regional', 'address' ) );

		$settings = Settings::sanitize(
			array(
				'fields' => array(
					'staff_regional' => array(
						'sector'  => array( 'uppercase' => '0' ),
						'address' => array( 'uppercase' => '1' ),
						'email'   => array( 'uppercase' => '1' ),
					),
				),
			)
		);
		self::assertFalse( $settings['fields']['staff_regional']['sector']['uppercase'] );
		self::assertTrue( $settings['fields']['staff_regional']['address']['uppercase'] );
		self::assertArrayNotHasKey( 'uppercase', $settings['fields']['staff_regional']['email'], 'An email is never put in capitals.' );
	}

	public function test_the_old_general_capitals_switch_becomes_each_name_fields_choice(): void {
		$settings = Settings::sanitize( array( 'uppercase_name' => '0' ) );
		self::assertArrayNotHasKey( 'uppercase_name', $settings );
		foreach ( Settings::modelIds() as $model ) {
			self::assertFalse( $settings['fields'][ $model ]['name']['uppercase'] );
		}

		$kept = Settings::sanitize( array( 'uppercase_name' => '0', 'fields' => array( 'staff_school' => array( 'name' => array( 'uppercase' => '1' ) ) ) ) );
		self::assertTrue( $kept['fields']['staff_school']['name']['uppercase'], 'A field\'s own choice wins.' );
	}

	public function test_unknown_models_are_dropped_and_none_means_all(): void {
		self::assertSame( array( 'staff_school' ), Settings::sanitize( array( 'models' => array( 'nope', 'staff_school' ) ) )['models'] );
		self::assertSame( Settings::modelIds(), Settings::sanitize( array( 'models' => array( 'nope' ) ) )['models'] );
		self::assertSame( Settings::modelIds(), Settings::sanitize( array( 'models' => array() ) )['models'] );
	}

	public function test_the_logo_width_is_clamped(): void {
		self::assertSame( Settings::LOGO_WIDTH_MIN, Settings::sanitize( array( 'logo_width' => '5' ) )['logo_width'] );
		self::assertSame( Settings::LOGO_WIDTH_MAX, Settings::sanitize( array( 'logo_width' => '9999' ) )['logo_width'] );
		self::assertSame( 150, Settings::sanitize( array( 'logo_width' => '150' ) )['logo_width'] );
	}

	/**
	 * @dataProvider domains
	 *
	 * @param string|list<string> $input  Posted domains.
	 * @param list<string>        $stored Kept domains.
	 */
	public function test_the_email_domains( $input, array $stored ): void {
		self::assertSame( $stored, Settings::sanitize( array( 'email_domains' => $input ) )['email_domains'] );
	}

	/**
	 * @return array<string, array{string|list<string>, list<string>}>
	 */
	public static function domains(): array {
		return array(
			'one'           => array( 'sme.prefeitura.sp.gov.br', array( 'sme.prefeitura.sp.gov.br' ) ),
			'one per line'  => array( "sme.prefeitura.sp.gov.br\r\n\n@Prefeitura.SP.gov.br \n", array( 'sme.prefeitura.sp.gov.br', 'prefeitura.sp.gov.br' ) ),
			'commas'        => array( 'a.org, b.org;a.org', array( 'a.org', 'b.org' ) ),
			'a list'        => array( array( 'a.org', 'B.ORG' ), array( 'a.org', 'b.org' ) ),
			'with at'       => array( '@Example.ORG ', array( 'example.org' ) ),
			'empty'         => array( '', array() ),
			'not a domain'  => array( 'not a domain', array() ),
			'markup'        => array( '"><script>', array() ),
			'single label'  => array( 'localhost', array() ),
		);
	}

	public function test_bad_domains_are_reported_and_good_ones_kept(): void {
		self::assertSame(
			array(
				'domains'  => array( 'example.org' ),
				'rejected' => array( 'localhost', 'x_y.org' ),
			),
			Settings::parseDomains( "example.org\nlocalhost\nx_y.org" )
		);
	}

	public function test_the_restriction_is_off_by_default_and_follows_its_toggle(): void {
		self::assertFalse( Settings::sanitize( array() )['restrict_email_domain'] );
		self::assertTrue( Settings::sanitize( array( 'restrict_email_domain' => '1' ) )['restrict_email_domain'] );
		self::assertFalse( Settings::sanitize( array( 'restrict_email_domain' => '0', 'email_domains' => 'a.org' ) )['restrict_email_domain'] );
	}

	public function test_a_domain_stored_before_the_list_turns_the_restriction_on(): void {
		$settings = Settings::sanitize( array( 'email_domain' => 'Example.org' ) );
		self::assertTrue( $settings['restrict_email_domain'] );
		self::assertSame( array( 'example.org' ), $settings['email_domains'] );
		self::assertArrayNotHasKey( 'email_domain', $settings );

		self::assertFalse( Settings::sanitize( array( 'email_domain' => '' ) )['restrict_email_domain'] );
	}

	public function test_refused_domains_start_from_the_built_in_list_and_can_be_emptied(): void {
		self::assertSame( Config::BLOCKED_EMAIL_DOMAINS, Settings::sanitize( array() )['blocked_email_domains'] );
		self::assertSame( array( 'spam.org' ), Settings::sanitize( array( 'blocked_email_domains' => "@Spam.org\nnot a domain" ) )['blocked_email_domains'] );
		self::assertSame( array(), Settings::sanitize( array( 'blocked_email_domains' => '' ) )['blocked_email_domains'] );
		self::assertSame( array( 'spam.org' ), Settings::apply( Config::defaults( '' ), Settings::sanitize( array( 'blocked_email_domains' => 'spam.org' ) ) )['blockedEmailDomains'] );
	}

	public function test_the_built_in_refused_domains_are_valid_and_unique(): void {
		self::assertSame( Config::BLOCKED_EMAIL_DOMAINS, Settings::parseDomains( Config::BLOCKED_EMAIL_DOMAINS )['domains'] );
	}

	public function test_the_form_gets_the_domains_only_while_the_restriction_is_on(): void {
		$on  = Settings::sanitize( array( 'restrict_email_domain' => '1', 'email_domains' => "a.org\nb.org" ) );
		$off = Settings::sanitize( array( 'restrict_email_domain' => '0', 'email_domains' => "a.org\nb.org" ) );

		self::assertSame( array( 'a.org', 'b.org' ), Settings::apply( Config::defaults( '' ), $on )['emailDomains'] );
		self::assertSame( array(), Settings::apply( Config::defaults( '' ), $off )['emailDomains'] );
		self::assertSame( array( 'a.org', 'b.org' ), $off['email_domains'], 'Turning it off keeps the list for later.' );
	}

	public function test_regions_from_the_old_text_become_name_and_acronym(): void {
		$regions = Settings::sanitize( array( 'regions' => " A / x1 \r\n\nB / Y\nA / X1\n<b>C</b>" ) )['regions'];
		self::assertSame(
			array(
				array( 'name' => 'A', 'acronym' => 'X1' ),
				array( 'name' => 'B', 'acronym' => 'Y' ),
			),
			$regions,
			'A repeated acronym and a line without one are left out.'
		);
	}

	public function test_old_department_and_school_defaults_split_into_text_and_office(): void {
		$fields = Settings::sanitize(
			array(
				'fields' => array(
					'staff_regional' => array( 'role' => array( 'default' => 'NTIC / dremp', 'required' => '1', 'locked' => '1' ) ),
					'staff_school'   => array(
						'role' => array( 'default' => 'Professora' ),
						'unit' => array( 'default' => 'EMEF A / DREPE' ),
					),
				),
			)
		)['fields'];

		self::assertSame( array( 'default' => 'NTIC', 'required' => true, 'locked' => true ), $fields['staff_regional']['sector'] );
		self::assertSame( array( 'default' => 'DREMP', 'required' => true ), $fields['staff_regional']['office'] );
		self::assertArrayNotHasKey( 'role', $fields['staff_regional'] );
		self::assertSame( 'EMEF A', $fields['staff_school']['school']['default'] );
		self::assertSame( 'DREPE', $fields['staff_school']['office']['default'] );
		self::assertSame( 'Professora', $fields['staff_school']['role']['default'], 'The school role is not the old combined field.' );
	}

	public function test_a_saved_new_form_is_never_rewritten_by_the_split(): void {
		$fields = Settings::sanitize(
			array(
				'fields' => array(
					'staff_regional' => array(
						'role'   => array( 'default' => 'OLD / DREPE' ),
						'sector' => array( 'default' => 'NTIC' ),
						'office' => array( 'default' => 'DREMP' ),
					),
				),
			)
		)['fields'];
		self::assertSame( 'NTIC', $fields['staff_regional']['sector']['default'] );
		self::assertSame( 'DREMP', $fields['staff_regional']['office']['default'] );
	}

	public function test_an_unknown_office_default_falls_back_to_the_first_office(): void {
		$settings = Settings::sanitize( array( 'fields' => array( 'staff_school' => array( 'office' => array( 'default' => 'NOPE' ) ) ) ) );
		$config   = Settings::apply( Config::defaults( '' ), $settings );
		self::assertSame( 'DREBT', $this->field( $config, 'staff_school', 'office' )['default'] );
	}

	public function test_remembering_is_off_unless_turned_on(): void {
		self::assertFalse( Settings::sanitize( array() )['remember_values'] );
		self::assertTrue( Settings::sanitize( array( 'remember_values' => '1' ) )['remember_values'] );
		self::assertFalse( Settings::apply( Config::defaults( '' ), Settings::defaults() )['rememberValues'] );
		self::assertTrue( Settings::apply( Config::defaults( '' ), Settings::sanitize( array( 'remember_values' => '1' ) ) )['rememberValues'] );
	}

	public function test_the_logo_text_describes_the_image(): void {
		self::assertSame( Config::LOGO_ALT, Settings::apply( Config::defaults( '' ), Settings::defaults() )['logo']['alt'] );

		$settings = Settings::sanitize( array( 'logo_alt' => ' <b>Escola X</b> ' ) );
		self::assertSame( 'Escola X', $settings['logo_alt'] );
		self::assertSame( 'Escola X', Settings::apply( Config::defaults( '' ), $settings, 'https://example.org/x.png' )['logo']['alt'] );
		self::assertSame( Settings::LOGO_ALT_MAX, mb_strlen( Settings::sanitize( array( 'logo_alt' => str_repeat( 'á', 200 ) ) )['logo_alt'] ) );
	}

	public function test_the_default_unit_is_every_unit_fields_default(): void {
		self::assertSame( '', Settings::sanitize( array() )['default_unit'] );

		$settings = Settings::sanitize( array( 'default_unit' => 'drepe' ) );
		self::assertSame( 'DREPE', $settings['default_unit'] );
		$config = Settings::apply( Config::defaults( '' ), $settings );
		self::assertSame( 'DREPE', $this->field( $config, 'staff_regional', 'office' )['default'] );
		self::assertSame( 'DREPE', $this->field( $config, 'staff_school', 'office' )['default'] );

		self::assertSame( '', Settings::sanitize( array( 'default_unit' => 'NOPE' ) )['default_unit'], 'Only a unit on the list.' );
	}

	public function test_the_form_marks_the_default_unit_by_its_row_even_when_renamed(): void {
		$settings = Settings::sanitize(
			array(
				'default_unit'     => 'A',
				'default_unit_row' => '7',
				'regions'          => array(
					'0' => array( 'name' => 'North', 'acronym' => 'A' ),
					'7' => array( 'name' => 'South', 'acronym' => ' b2 ' ),
				),
			)
		);
		self::assertSame( 'B2', $settings['default_unit'] );
		self::assertSame( '', Settings::sanitize( array( 'default_unit_row' => '9', 'regions' => array( array( 'name' => 'N', 'acronym' => 'A' ) ) ) )['default_unit'] );
	}

	public function test_every_field_takes_its_own_label(): void {
		$settings = Settings::sanitize(
			array(
				'fields' => array(
					'staff_regional' => array(
						'sector' => array( 'label' => ' <b>Área</b> ' ),
						'phone'  => array( 'label' => '' ),
						'email'  => array( 'label' => str_repeat( 'á', 60 ) ),
					),
				),
			)
		);
		self::assertSame( 'Área', $settings['fields']['staff_regional']['sector']['label'] );
		self::assertArrayNotHasKey( 'label', $settings['fields']['staff_regional']['phone'], 'Empty keeps the built-in label.' );
		self::assertSame( Settings::LABEL_MAX, mb_strlen( $settings['fields']['staff_regional']['email']['label'] ) );

		$config = Settings::apply( Config::defaults( '' ), $settings );
		self::assertSame( 'Área', $this->field( $config, 'staff_regional', 'sector' )['label'] );
		self::assertSame( Config::unitLabel(), $this->field( $config, 'staff_school', 'office' )['label'] );
	}

	public function test_the_old_unit_name_becomes_each_unit_fields_label(): void {
		$settings = Settings::sanitize( array( 'unit_label' => 'Órgão' ) );
		self::assertArrayNotHasKey( 'unit_label', $settings );
		$config = Settings::apply( Config::defaults( '' ), $settings );
		self::assertSame( 'Órgão', $this->field( $config, 'staff_regional', 'office' )['label'] );
		self::assertSame( 'Órgão', $this->field( $config, 'staff_school', 'office' )['label'] );
		self::assertNotSame( 'Órgão', $this->field( $config, 'department', 'region' )['label'], 'The department model names its own field.' );

		$own = Settings::sanitize( array( 'unit_label' => 'Órgão', 'fields' => array( 'staff_school' => array( 'office' => array( 'label' => 'Lotação' ) ) ) ) );
		self::assertSame( 'Lotação', $own['fields']['staff_school']['office']['label'], 'A field\'s own label wins.' );
	}

	public function test_access_accepts_only_the_two_modes(): void {
		self::assertSame( Settings::ACCESS_LOGGED_IN, Settings::sanitize( array( 'access' => 'logged_in' ) )['access'] );
		self::assertSame( Settings::ACCESS_EVERYONE, Settings::sanitize( array( 'access' => 'admins' ) )['access'] );
	}

	public function test_field_overrides_keep_known_fields_only(): void {
		$fields = Settings::sanitize(
			array(
				'fields' => array(
					'staff_school' => array(
						'address' => array( 'default' => ' Rua A, 1 ', 'required' => '1', 'locked' => '1' ),
						'bogus'   => array( 'default' => 'x' ),
					),
					'bogus'        => array( 'name' => array( 'default' => 'x' ) ),
				),
			)
		)['fields'];

		self::assertSame(
			array( 'staff_school' => array( 'address' => array( 'default' => 'Rua A, 1', 'required' => true, 'locked' => true ) ) ),
			$fields
		);
	}

	public function test_the_name_stays_required_and_name_and_email_never_lock(): void {
		$fields = Settings::sanitize(
			array(
				'fields' => array(
					'staff_school' => array(
						'name'  => array( 'default' => 'Maria', 'locked' => '1' ),
						'email' => array( 'default' => 'a@b.org', 'locked' => '1' ),
					),
				),
			)
		)['fields']['staff_school'];

		self::assertTrue( $fields['name']['required'] );
		self::assertArrayNotHasKey( 'locked', $fields['name'] );
		self::assertArrayNotHasKey( 'locked', $fields['email'] );
	}

	public function test_the_image_quality_is_clamped(): void {
		self::assertSame( Settings::IMAGE_QUALITY_MIN, Settings::sanitize( array( 'image_quality' => '5' ) )['image_quality'] );
		self::assertSame( Settings::IMAGE_QUALITY_MAX, Settings::sanitize( array( 'image_quality' => '250' ) )['image_quality'] );
		self::assertSame( 75, Settings::sanitize( array( 'image_quality' => '75' ) )['image_quality'] );
		self::assertSame( Config::IMAGE_QUALITY, Settings::sanitize( array() )['image_quality'] );
	}

	public function test_the_download_is_offered_unless_turned_off(): void {
		self::assertTrue( Settings::sanitize( array() )['offer_download'] );
		self::assertFalse( Settings::sanitize( array( 'offer_download' => '0' ) )['offer_download'] );
		self::assertFalse( Settings::apply( Config::defaults( '' ), Settings::sanitize( array( 'offer_download' => '0' ) ) )['offerDownload'] );
	}

	public function test_the_form_width_is_clamped_and_defaults_to_640(): void {
		self::assertSame( 640, Settings::sanitize( array() )['form_width'] );
		self::assertSame( Settings::FORM_WIDTH_MIN, Settings::sanitize( array( 'form_width' => '100' ) )['form_width'] );
		self::assertSame( Settings::FORM_WIDTH_MAX, Settings::sanitize( array( 'form_width' => '5000' ) )['form_width'] );
		self::assertSame( 640, Settings::sanitize( array( 'form_width' => 'wide' ) )['form_width'], 'Garbage gives the default instead of failing.' );
		self::assertSame( 800, Settings::sanitize( array( 'form_width' => '800' ) )['form_width'] );
		self::assertSame( Config::SIGNATURE_WIDTH, Settings::FORM_WIDTH_MIN, 'The form never gets narrower than the signature.' );
	}

	public function test_a_cleared_number_keeps_its_default(): void {
		$saved = Settings::sanitize(
			array(
				'custom_style'  => '1',
				'logo_width'    => '',
				'top_space'     => '',
				'form_width'    => '',
				'image_quality' => '',
				'image_scale'   => '',
				'radius'        => '',
			)
		);
		self::assertSame( Config::LOGO_WIDTH, $saved['logo_width'], 'A cleared field is not its lower bound.' );
		self::assertSame( Config::TOP_SPACE, $saved['top_space'] );
		self::assertSame( Config::FORM_WIDTH, $saved['form_width'] );
		self::assertSame( Config::IMAGE_QUALITY, $saved['image_quality'] );
		self::assertSame( Config::IMAGE_SCALE, $saved['image_scale'] );
		self::assertSame( Config::RADIUS, $saved['radius'], 'A cleared field is not square corners.' );
		self::assertSame( 0, Settings::sanitize( array( 'top_space' => '0' ) )['top_space'], 'A typed 0 is a choice.' );
	}

	public function test_the_standalone_width_matches_the_default_form_width(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/signature-generator.css' );
		self::assertSame( 1, preg_match( '/^\.esg-generator \{[^}]*max-width: (\d+)px;/m', $css, $m ) );
		self::assertSame( (string) Config::FORM_WIDTH, $m[1] );
	}

	public function test_the_top_space_is_clamped_and_defaults_to_4(): void {
		self::assertSame( 4, Settings::sanitize( array() )['top_space'] );
		self::assertSame( 0, Settings::sanitize( array( 'top_space' => '-3' ) )['top_space'] );
		self::assertSame( Settings::TOP_SPACE_MAX, Settings::sanitize( array( 'top_space' => '99' ) )['top_space'] );
		self::assertSame( 10, Settings::sanitize( array( 'top_space' => '10' ) )['top_space'] );
		self::assertSame( 10, Settings::apply( Config::defaults( '' ), Settings::sanitize( array( 'top_space' => '10' ) ) )['topSpace'] );
	}

	public function test_the_download_style_is_one_of_three(): void {
		self::assertSame( 'notice', Settings::sanitize( array() )['download_style'] );
		self::assertSame( 'more', Settings::sanitize( array( 'download_style' => 'more' ) )['download_style'] );
		self::assertSame( 'plain', Settings::sanitize( array( 'download_style' => 'plain' ) )['download_style'] );
		self::assertSame( 'notice', Settings::sanitize( array( 'download_style' => 'modal' ) )['download_style'] );
		self::assertSame( 'notice', Settings::sanitize( array( 'download_style' => array( 'more' ) ) )['download_style'] );
		self::assertSame( 'more', Settings::apply( Config::defaults( '' ), Settings::sanitize( array( 'download_style' => 'more' ) ) )['downloadStyle'] );
	}

	public function test_the_image_format_is_png_or_jpg(): void {
		self::assertSame( 'png', Settings::sanitize( array() )['image_format'] );
		self::assertSame( 'jpg', Settings::sanitize( array( 'image_format' => 'jpg' ) )['image_format'] );
		self::assertSame( 'png', Settings::sanitize( array( 'image_format' => 'gif' ) )['image_format'] );
		self::assertSame( 'png', Settings::sanitize( array( 'image_format' => array( 'jpg' ) ) )['image_format'] );
	}

	public function test_an_unknown_image_scale_falls_back_to_the_default(): void {
		self::assertSame( 3, Settings::sanitize( array( 'image_scale' => '3' ) )['image_scale'] );
		self::assertSame( Config::IMAGE_SCALE, Settings::sanitize( array( 'image_scale' => '7' ) )['image_scale'] );
		self::assertSame( Config::IMAGE_SCALE, Settings::sanitize( array( 'image_scale' => 'x' ) )['image_scale'] );
		self::assertSame( Config::IMAGE_SCALE, Settings::sanitize( array() )['image_scale'] );
	}

	public function test_a_field_cannot_be_locked_empty(): void {
		$fields = Settings::sanitize( array( 'fields' => array( 'staff_school' => array( 'address' => array( 'default' => '', 'locked' => '1' ) ) ) ) )['fields'];
		self::assertArrayNotHasKey( 'locked', $fields['staff_school']['address'] );
	}

	public function test_apply_with_defaults_changes_nothing_but_the_new_keys(): void {
		$config  = Config::defaults( 'https://example.org/logo.png' );
		$applied = Settings::apply( $config, Settings::defaults() );

		foreach ( $applied['models'] as $m => $model ) {
			foreach ( $model['fields'] as $f => $field ) {
				self::assertFalse( $field['locked'] );
				unset( $applied['models'][ $m ]['fields'][ $f ]['locked'] );
			}
		}
		self::assertSame( $config, $applied );
	}

	public function test_apply_overrides_models_logo_domain_regions_and_fields(): void {
		$settings = Settings::sanitize(
			array(
				'models'         => array( 'staff_school', 'department' ),
				'logo_width'     => '90',
				'image_format'   => 'jpg',
				'image_quality'  => '80',
				'image_scale'    => '3',
				'restrict_email_domain' => '1',
				'email_domains'  => 'example.org',
				'regions'        => array(
					array( 'name' => 'North', 'acronym' => 'N' ),
					array( 'name' => 'South', 'acronym' => 'S' ),
				),
				'fields'         => array(
					'staff_school' => array( 'address' => array( 'default' => 'Rua A, 1', 'locked' => '1' ) ),
				),
			)
		);
		$config = Settings::apply( Config::defaults( 'https://example.org/default.png' ), $settings, 'https://example.org/chosen.png' );

		self::assertSame( array( 'staff_school', 'department' ), array_column( $config['models'], 'id' ) );
		self::assertSame( array( 'format' => 'jpg', 'quality' => 80, 'scale' => 3 ), $config['image'] );
		self::assertSame( array( 'example.org' ), $config['emailDomains'] );
		self::assertSame( array( 'url' => 'https://example.org/chosen.png', 'width' => 90, 'alt' => '' ), $config['logo'], 'Another image does not carry the bundled logo\'s text.' );
		self::assertSame( array( 'North / N', 'South / S' ), $config['regions'] );

		$address = $this->field( $config, 'staff_school', 'address' );
		self::assertSame( 'Rua A, 1', $address['default'] );
		self::assertTrue( $address['locked'] );

		// The region default left the list, so the select starts on its first option.
		self::assertSame( 'North / N', $this->field( $config, 'department', 'region' )['default'] );
	}

	/**
	 * @param array<string, mixed> $config Configuration.
	 * @return array<string, mixed>
	 */
	private function field( array $config, string $model, string $key ): array {
		foreach ( $config['models'] as $m ) {
			if ( $m['id'] !== $model ) {
				continue;
			}
			foreach ( $m['fields'] as $f ) {
				if ( $f['key'] === $key ) {
					return $f;
				}
			}
		}
		self::fail( "{$model}.{$key} not found." );
	}
}
