<?php
/**
 * The generator's configuration.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Builds the configuration object the browser-side generator reads.
 *
 * It is the one source of the form and the signature: the shortcode passes it
 * to the page, and `bin/build-standalone.php` embeds it in the standalone
 * HTML. Both therefore offer the same models, fields, rules and strings.
 *
 * The defaults are the São Paulo Municipal Department of Education's (SME-SP)
 * layout. The regional office names are data: they are what a signature
 * prints, so they stay in Portuguese and are not translated.
 */
final class Config {

	/**
	 * A phone number in the international format the layout prints:
	 * `55 11 3397-1234` or `55 11 93397-1234`, optionally two of them joined
	 * by ` | `.
	 */
	public const PHONE_PATTERN = '^55\s\d{2}\s\d{4,5}-\d{4}(?:\s\|\s55\s\d{2}\s\d{4,5}-\d{4})?$';

	/**
	 * Width, in pixels, at which the logo is drawn in the signature.
	 */
	public const LOGO_WIDTH = 120;

	/**
	 * The default configuration.
	 *
	 * @param string $logo_url Absolute URL of the logo the signature links to.
	 * @return array<string, mixed>
	 */
	public static function defaults( string $logo_url ): array {
		return array(
			'logo'          => array(
				'url'   => $logo_url,
				'width' => self::LOGO_WIDTH,
				'alt'   => __( 'Logo', 'email-signature-generator' ),
			),
			'uppercaseName' => true,
			'emailDomain'   => '',
			'phonePattern'  => self::PHONE_PATTERN,
			'regions'       => self::regions(),
			'models'        => self::models(),
			'strings'       => self::strings(),
		);
	}

	/**
	 * URL of the logo bundled with the plugin.
	 */
	public static function bundledLogoUrl(): string {
		return Plugin::url() . 'assets/img/default-logo.png';
	}

	/**
	 * The signature models offered in the form, in display order.
	 *
	 * `placeholder` shows an example; `default` pre-fills a value that most
	 * people keep. A field's `rule` names a check the generator knows (`roleSector`,
	 * `singleRole`, `email`, `phone`, `url`). `region` is a select over the
	 * configured regions, and `hostnameOnly` links print their host name.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function models(): array {
		$name    = array(
			'key'         => 'name',
			'label'       => __( 'Full name', 'email-signature-generator' ),
			'required'    => true,
			'placeholder' => __( 'Type your full name', 'email-signature-generator' ),
		);
		$email   = array(
			'key'         => 'email',
			'label'       => __( 'Email', 'email-signature-generator' ),
			'type'        => 'email',
			'required'    => true,
			'rule'        => 'email',
			'placeholder' => 'TESTE@sme.prefeitura.sp.gov.br',
		);
		$phone   = array(
			'key'         => 'phone',
			'label'       => __( 'Phone number(s)', 'email-signature-generator' ),
			'rule'        => 'phone',
			'placeholder' => '55 11 3397-XXXX | 55 11 3397-YYYY',
		);
		$address = array(
			'key'         => 'address',
			'label'       => __( 'Address | Floor | Room', 'email-signature-generator' ),
			'placeholder' => 'Av. Nordestina, 747 | 2º Andar | 226',
		);
		$postal  = array(
			'key'         => 'postal',
			'label'       => __( 'Postal code | City | State', 'email-signature-generator' ),
			'placeholder' => '08021-000 | São Paulo | SP',
		);
		$link1   = array(
			'key'     => 'link1',
			'label'   => __( 'Link 1', 'email-signature-generator' ),
			'type'    => 'url',
			'rule'    => 'url',
			'default' => 'educacao.sme.prefeitura.sp.gov.br',
		);
		$link2   = array(
			'key'         => 'link2',
			'label'       => __( 'Link 2', 'email-signature-generator' ),
			'type'        => 'url',
			'rule'        => 'url',
			'placeholder' => __( 'Type a second link, if needed', 'email-signature-generator' ),
		);

		return array(
			array(
				'id'     => 'staff_regional',
				'label'  => __( 'Staff - Regional office', 'email-signature-generator' ),
				'fields' => array(
					$name,
					array(
						'key'         => 'role',
						'label'       => __( 'Role / Department', 'email-signature-generator' ),
						'required'    => true,
						'rule'        => 'roleSector',
						'placeholder' => 'NTIC / DREMP',
					),
					$email,
					$phone,
					$address,
					$postal,
					$link1,
					$link2,
				),
			),
			array(
				'id'     => 'staff_school',
				'label'  => __( 'Staff - School', 'email-signature-generator' ),
				'fields' => array(
					$name,
					array(
						'key'         => 'role',
						'label'       => __( 'Role', 'email-signature-generator' ),
						'required'    => true,
						'rule'        => 'singleRole',
						'placeholder' => 'Secretário Escolar',
					),
					$email,
					$phone,
					array(
						'key'         => 'unit',
						'label'       => __( 'School / Regional office', 'email-signature-generator' ),
						'placeholder' => 'EMEF Joaquim José da Silva Xavier / DREMP',
					),
					$address,
					$postal,
					$link1,
					$link2,
				),
			),
			array(
				'id'     => 'department',
				'label'  => __( 'Department or school building', 'email-signature-generator' ),
				'fields' => array(
					array(
						'key'         => 'name',
						'label'       => __( 'Department or school', 'email-signature-generator' ),
						'required'    => true,
						'placeholder' => 'EMEF Joaquim José da Silva Xavier',
					),
					$email,
					$phone,
					array(
						'key'      => 'region',
						'label'    => __( 'Regional office or central', 'email-signature-generator' ),
						'type'     => 'region',
						'required' => true,
						'default'  => 'Diretoria Regional de Educação São Miguel / DREMP',
					),
					$address,
					$postal,
					$link1,
					$link2,
				),
			),
		);
	}

	/**
	 * The regional offices and the central department, as a signature prints
	 * them.
	 *
	 * @return list<string>
	 */
	private static function regions(): array {
		return array(
			'Diretoria Regional de Educação Butantã / DREBT',
			'Diretoria Regional de Educação Campo Limpo / DRECL',
			'Diretoria Regional de Educação Capela do Socorro / DRECS',
			'Diretoria Regional de Educação Freguesia Brasilândia / DREFB',
			'Diretoria Regional de Educação Guaianases / DREG',
			'Diretoria Regional de Educação Ipiranga / DREIP',
			'Diretoria Regional de Educação Itaquera / DREIQ',
			'Diretoria Regional de Educação Jaçanã Tremembé / DREJT',
			'Diretoria Regional de Educação Penha / DREPE',
			'Diretoria Regional de Educação Pirituba Jaraguá / DREPJ',
			'Diretoria Regional de Educação Santo Amaro / DRESA',
			'Diretoria Regional de Educação São Mateus / DRESM',
			'Diretoria Regional de Educação São Miguel / DREMP',
			'Secretaria Municipal de Educação de São Paulo / SMESP',
		);
	}

	/**
	 * Every string the generator shows in the browser.
	 *
	 * @return array<string, string>
	 */
	private static function strings(): array {
		return array(
			'model'              => __( 'Signature model', 'email-signature-generator' ),
			'generate'           => __( 'Generate signature', 'email-signature-generator' ),
			'copy'               => __( 'Copy HTML', 'email-signature-generator' ),
			'download'           => __( 'Download image', 'email-signature-generator' ),
			'preview'            => __( 'Preview', 'email-signature-generator' ),
			'phonePrefix'        => __( 'Tel.:', 'email-signature-generator' ),
			'copied'             => __( 'Signature copied. Paste it into your email signature settings.', 'email-signature-generator' ),
			'copyFailed'         => __( 'The signature could not be copied. Select it in the preview and copy it by hand.', 'email-signature-generator' ),
			'downloadFailed'     => __( 'The image could not be created. Try again.', 'email-signature-generator' ),
			/* translators: %s: a field's label. */
			'required'           => __( '%s is required.', 'email-signature-generator' ),
			'invalidEmail'       => __( 'Enter a valid email address.', 'email-signature-generator' ),
			/* translators: %s: an email domain, such as example.org. */
			'invalidEmailDomain' => __( 'Use an address at %s.', 'email-signature-generator' ),
			'invalidPhone'       => __( 'Enter the phone number as 55 XX XXXX-XXXX or 55 XX XXXXX-XXXX. For two numbers, separate them with " | ".', 'email-signature-generator' ),
			/* translators: %s: a field's label. */
			'invalidUrl'         => __( '%s is not a valid address.', 'email-signature-generator' ),
			'invalidRoleSector'  => __( 'Role / Department needs text on both sides of the slash, or a single word without spaces.', 'email-signature-generator' ),
			'invalidSingleRole'  => __( 'Role takes a single role, without slashes.', 'email-signature-generator' ),
		);
	}
}
