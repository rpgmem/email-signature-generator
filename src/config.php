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
	 * Domains refused while any address is accepted: the placeholders and
	 * throwaway inboxes people type to get past an email field. A subdomain
	 * of one is refused too. An administrator edits the list.
	 */
	public const BLOCKED_EMAIL_DOMAINS = array(
		'test.com',
		'teste.com',
		'test.com.br',
		'teste.com.br',
		'example.com',
		'example.org',
		'example.net',
		'exemplo.com',
		'exemplo.com.br',
		'fake.com',
		'fakemail.com',
		'mailinator.com',
		'yopmail.com',
		'yopmail.fr',
		'guerrillamail.com',
		'guerrillamailblock.com',
		'sharklasers.com',
		'10minutemail.com',
		'tempmail.com',
		'temp-mail.org',
		'trashmail.com',
		'trashmail.de',
		'dispostable.com',
		'maildrop.cc',
	);

	/**
	 * What the bundled logo shows, read by screen readers and shown when an
	 * email program blocks images. Data, like the units' names: it describes
	 * the image, so it is not translated.
	 */
	public const LOGO_ALT = 'Prefeitura de São Paulo – Secretaria Municipal de Educação';

	/**
	 * Width, in pixels, at which the logo is drawn in the signature.
	 */
	public const LOGO_WIDTH = 120;

	/**
	 * Empty space above the signature, in pixels, so it does not touch what
	 * precedes it in an email. Part of the signature's height, never its
	 * width.
	 */
	public const TOP_SPACE = 4;

	/**
	 * How long a browser keeps what was typed, when the site allows it: the
	 * minutes since the signature was last generated there.
	 */
	public const REMEMBER_MINUTES = 60;

	/**
	 * Widest the form gets on the page, in CSS pixels. Mirrors the max-width
	 * of .esg-generator in assets/css/signature-generator.css, which is what
	 * the standalone HTML uses.
	 */
	public const FORM_WIDTH = 640;

	/**
	 * The form's colours, as the stylesheet declares them. Each key is a
	 * custom property of the form, `--esg-` plus the key with dashes.
	 */
	public const COLORS = array(
		'accent'      => '#2271b1',
		'accent_text' => '#ffffff',
		'border'      => '#8c8f94',
		'muted'       => '#50575e',
		'error'       => '#b32d2e',
	);

	/**
	 * The corners' radius, in pixels, as the stylesheet declares it.
	 */
	public const RADIUS = 4;

	/**
	 * The fonts the form can use, the same three as the other plugins of the
	 * family: the theme's (the default), the plugins' own system font stack,
	 * or Arial.
	 */
	public const FONTS = array( 'theme', 'plugin', 'arial' );

	/**
	 * The plugins' own font: the system stack Free Form Certificate's forms
	 * use, so the two read as one family whatever the theme.
	 */
	public const PLUGIN_FONT = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

	/**
	 * Width of the signature table, in CSS pixels. Mirrors SIGNATURE_WIDTH in
	 * assets/js/signature-generator.js, which draws it.
	 */
	public const SIGNATURE_WIDTH = 450;

	/**
	 * JPEG quality of the downloaded image, in percent.
	 */
	public const IMAGE_QUALITY = 90;

	/**
	 * Format of the downloaded image. PNG keeps small text clean at the
	 * signature's own size; JPG compression leaves noise around the letters
	 * (measured at 450 px: PNG ≈ 22 KB, JPG 90% ≈ 12 KB).
	 */
	public const IMAGE_FORMAT = 'png';

	/**
	 * Pixel density of the downloaded image. 1 gives the signature's own size
	 * (450 px wide), which is what the original page produced and what an
	 * email client shows when the image is pasted in; 2 and 3 are sharper but
	 * a client may show them that much larger.
	 */
	public const IMAGE_SCALE = 1;

	/**
	 * What the form calls a unit when the site sets no name of its own.
	 */
	public static function unitLabel(): string {
		return __( 'Regional office', 'email-signature-generator' );
	}

	/**
	 * The default configuration.
	 *
	 * @param string $logo_url Absolute URL of the logo the signature links to.
	 * @return array<string, mixed>
	 */
	public static function defaults( string $logo_url ): array {
		return array(
			'logo'                => array(
				'url'   => $logo_url,
				'width' => self::LOGO_WIDTH,
				'alt'   => self::LOGO_ALT,
			),
			'image'               => array(
				'format'  => self::IMAGE_FORMAT,
				'quality' => self::IMAGE_QUALITY,
				'scale'   => self::IMAGE_SCALE,
			),
			'topSpace'            => self::TOP_SPACE,
			'offerDownload'       => true,
			'rememberValues'      => false,
			'analytics'           => false,
			'rememberMinutes'     => self::REMEMBER_MINUTES,
			'downloadStyle'       => 'notice',
			'emailDomains'        => array(),
			'blockedEmailDomains' => self::BLOCKED_EMAIL_DOMAINS,
			'phonePattern'        => self::PHONE_PATTERN,
			'regions'             => self::regions(),
			'offices'             => Regions::defaults(),
			'models'              => self::models(),
			'strings'             => self::strings(),
		);
	}

	/**
	 * URL of the logo bundled with the plugin.
	 */
	public static function bundledLogoUrl(): string {
		return Plugin::url() . 'assets/img/default-logo.png';
	}

	/**
	 * Whether a field holds free text that may be printed in capitals: not
	 * an email, a link, a phone number or a choice from a list.
	 *
	 * @param array<string, mixed> $field A field.
	 */
	public static function casable( array $field ): bool {
		return ! isset( $field['type'] ) && 'phone' !== ( $field['rule'] ?? '' );
	}

	/**
	 * The signature models offered in the form, in display order.
	 *
	 * `label` is what the form shows, and an administrator can change it per
	 * field; `title`, when present, is the field's generic name on the
	 * settings screen, so the screen still says what the field is after the
	 * label is changed. `placeholder` shows an example; `default` pre-fills a value that most
	 * people keep. A field's `rule` names a check the generator knows (`noSlash`,
	 * `singleRole`, `email`, `phone`, `url`). `uppercase` prints the value in
	 * capitals, whatever was typed (see casable()). `region` is a select over the
	 * configured regions that prints the office's full label; `office` is a
	 * select over the same offices that keeps only the acronym, which the
	 * signature appends to the department or the school ("NTIC / DREMP").
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function models(): array {
		$name    = array(
			'key'         => 'name',
			'label'       => __( 'Full name', 'email-signature-generator' ),
			'required'    => true,
			'uppercase'   => true,
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
		$office  = array(
			'key'      => 'office',
			'title'    => __( 'Unit', 'email-signature-generator' ),
			'label'    => self::unitLabel(),
			'type'     => 'office',
			'required' => true,
			'default'  => 'DREMP',
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
						'key'         => 'sector',
						'label'       => __( 'Department', 'email-signature-generator' ),
						'required'    => true,
						'uppercase'   => true,
						'rule'        => 'noSlash',
						'placeholder' => 'NTIC',
					),
					$office,
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
						'uppercase'   => true,
						'rule'        => 'singleRole',
						'placeholder' => 'Secretário Escolar',
					),
					$email,
					$phone,
					array(
						'key'         => 'school',
						'label'       => __( 'School', 'email-signature-generator' ),
						'rule'        => 'noSlash',
						'placeholder' => 'EMEF Joaquim José da Silva Xavier',
					),
					$office,
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
						'title'       => __( 'Name', 'email-signature-generator' ),
						'label'       => __( 'Department or school', 'email-signature-generator' ),
						'required'    => true,
						'placeholder' => 'EMEF Joaquim José da Silva Xavier',
					),
					$email,
					$phone,
					array(
						'key'      => 'region',
						'title'    => __( 'Unit, full name', 'email-signature-generator' ),
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
		return Regions::labels( Regions::defaults() );
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
			'moreOptions'        => __( 'More options', 'email-signature-generator' ),
			'downloaded'         => __( 'Image downloaded. Links in an image cannot be clicked and small text loses sharpness: if your email program accepts it, prefer Copy HTML.', 'email-signature-generator' ),
			'copyHint'           => __( 'Recommended: copy the HTML and paste it into your email program\'s signature settings. The text stays sharp at any size and the links work. Use the image only where a signature cannot be HTML.', 'email-signature-generator' ),
			'remembered'         => sprintf(
				/* translators: %d: minutes. */
				__( 'This browser remembers what you type here for %d minutes after you generate the signature. Nothing is sent to the site.', 'email-signature-generator' ),
				self::REMEMBER_MINUTES
			),
			'forget'             => __( 'Forget it', 'email-signature-generator' ),
			'privacy'            => __( 'The signature is built in your browser: nothing you type is sent to the site.', 'email-signature-generator' ),
			'fixErrors'          => __( 'Check the marked fields.', 'email-signature-generator' ),
			'forgotten'          => __( 'This browser no longer keeps what you typed.', 'email-signature-generator' ),
			'darkPreview'        => __( 'See it in dark mode', 'email-signature-generator' ),
			'howTo'              => __( 'How to add the signature to your email', 'email-signature-generator' ),
			'howToOutlookWeb'    => __( 'Outlook on the web and the new Outlook for Windows: Settings (gear) → Account → Signatures. Create a signature, paste with Ctrl+V, choose it for new messages and replies, and save.', 'email-signature-generator' ),
			'howToOutlook'       => __( 'Classic Outlook for Windows: File → Options → Mail → Signatures → New. Paste with Ctrl+V, choose it for new messages and replies, and select OK.', 'email-signature-generator' ),
			'howToGmail'         => __( 'Gmail: Settings (gear) → See all settings → General → Signature → Create new. Paste with Ctrl+V, choose it in "Signature defaults", and select "Save changes" at the bottom of the page.', 'email-signature-generator' ),
			'howToPhone'         => __( 'Phone apps: most accept only plain text. Set the signature on the web or on the computer; messages sent from there carry it.', 'email-signature-generator' ),
			/* translators: %s: a field's label. */
			'required'           => __( '%s is required.', 'email-signature-generator' ),
			'invalidEmail'       => __( 'Enter a valid email address.', 'email-signature-generator' ),
			/* translators: %s: the allowed email domains, such as example.org, separated by commas. */
			'invalidEmailDomain' => __( 'Use an address at %s.', 'email-signature-generator' ),
			'emailDomain'        => __( 'Email domain', 'email-signature-generator' ),
			'emailLocalOnly'     => __( 'Type only the part before the "@": the domain is already beside the field.', 'email-signature-generator' ),
			/* translators: %s: an email domain, such as test.com. */
			'emailBlocked'       => __( 'Addresses at %s are not accepted. Use your real email address.', 'email-signature-generator' ),
			'invalidPhone'       => __( 'Enter the phone number as 55 XX XXXX-XXXX or 55 XX XXXXX-XXXX. For two numbers, separate them with " | ".', 'email-signature-generator' ),
			/* translators: %s: a field's label. */
			'invalidUrl'         => __( '%s is not a valid address.', 'email-signature-generator' ),
			/* translators: %s: a field's label. */
			'invalidNoSlash'     => __( '%s cannot contain a slash: the acronym is added on its own.', 'email-signature-generator' ),
			'invalidSingleRole'  => __( 'Role takes a single role, without slashes.', 'email-signature-generator' ),
		);
	}
}
