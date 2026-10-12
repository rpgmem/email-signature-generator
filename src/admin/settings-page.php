<?php
/**
 * Settings → Email Signature.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Admin;

use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Regions;
use EmailSignatureGenerator\Settings;
use EmailSignatureGenerator\Shortcode;

/**
 * The settings screen. It saves through the Settings API (options.php), which
 * checks the nonce `settings_fields()` prints and the `manage_options`
 * capability before Settings::sanitize() runs.
 */
final class SettingsPage {

	public const SLUG = 'email-signature-generator';

	public const GROUP = 'email_signature_generator';

	/**
	 * Hook the screen.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'registerSetting' ) );
		add_action( 'admin_menu', array( self::class, 'addPage' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( Plugin::file() ), array( self::class, 'actionLinks' ) );
	}

	/**
	 * Register the option with its sanitizer.
	 */
	public static function registerSetting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'save' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Add the page under Settings.
	 */
	public static function addPage(): void {
		add_options_page(
			__( 'Email Signature', 'email-signature-generator' ),
			__( 'Email Signature', 'email-signature-generator' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * A Settings link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Existing links.
	 * @return array<int|string, string>
	 */
	public static function actionLinks( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'email-signature-generator' ) )
		);
		return $links;
	}

	/**
	 * The media picker, on this screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		// The theme class must be on <html> before the page paints, or a dark
		// screen flashes light first: an inline script in the head, with no
		// file behind it (as in Free Form Certificate and Total Mail Queue).
		wp_register_script( 'email-signature-generator-theme', false, array(), Plugin::VERSION, false );
		wp_enqueue_script( 'email-signature-generator-theme' );
		wp_add_inline_script( 'email-signature-generator-theme', self::darkModeScript( (string) Settings::get()['dark_mode'] ) );
		wp_enqueue_style( 'email-signature-generator-admin', Plugin::url() . 'assets/css/admin-settings.css', array(), Plugin::assetVersion( 'assets/css/admin-settings.css' ) );
		wp_enqueue_script( 'email-signature-generator-admin', Plugin::url() . 'assets/js/admin-settings.js', array( 'media-editor' ), Plugin::assetVersion( 'assets/js/admin-settings.js' ), array( 'in_footer' => true ) );
		wp_localize_script(
			'email-signature-generator-admin',
			'esgAdmin',
			array(
				'bundledLogo' => Config::bundledLogoUrl(),
				'choose'      => __( 'Choose the logo', 'email-signature-generator' ),
				'use'         => __( 'Use this image', 'email-signature-generator' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => Autosave::ACTION,
				'nonce'       => wp_create_nonce( Autosave::ACTION ),
				'saving'      => __( 'Saving…', 'email-signature-generator' ),
				'saved'       => __( 'Saved', 'email-signature-generator' ),
				'failed'      => __( 'Not saved', 'email-signature-generator' ),
				'copied'      => __( 'Copied', 'email-signature-generator' ),
			)
		);
	}

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get();
		$config   = Settings::apply( Config::defaults( Config::bundledLogoUrl() ), array_merge( $settings, array( 'models' => Settings::modelIds() ) ) );
		$name     = Settings::OPTION;
		$logo_url = $settings['logo_id'] > 0 ? (string) wp_get_attachment_image_url( $settings['logo_id'], 'medium' ) : '';
		$regions  = array() !== $settings['regions'] ? $settings['regions'] : Regions::defaults();
		$unit     = self::defaultUnit( $config );

		echo '<div class="wrap esg-settings">';
		echo '<h1>' . esc_html__( 'Email Signature', 'email-signature-generator' ) . '</h1>';
		echo '<p>' . esc_html__( 'These settings apply to every form on the site.', 'email-signature-generator' ) . '</p>';

		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );

		$active = self::activeTab();
		echo '<div class="esg-tabs">';
		self::nav( $active );
		echo '<div class="esg-tabs__panels">';

		// General.
		self::openPanel( 'general', $active );
		self::openCard( __( 'Form', 'email-signature-generator' ), 'settings' );
		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row"><label for="esg-shortcode">%1$s</label></th><td><input type="text" id="esg-shortcode" class="regular-text code" value="%2$s" readonly> <button type="button" class="button esg-copy" data-esg-copy="esg-shortcode">%3$s</button><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Shortcode', 'email-signature-generator' ),
			esc_attr( '[' . Shortcode::TAG . ']' ),
			esc_html__( 'Copy', 'email-signature-generator' ),
			wp_kses(
				sprintf(
					/* translators: 1: an example shortcode, 2: the model ids, separated by commas. */
					__( 'Paste it into any page or post. To offer only some models, in the order given, list them: %1$s. Model ids: %2$s.', 'email-signature-generator' ),
					'<code>[' . esc_html( Shortcode::TAG ) . ' models="' . esc_html( implode( ',', array_slice( Settings::modelIds(), 1 ) ) ) . '"]</code>',
					'<code>' . esc_html( implode( ', ', Settings::modelIds() ) ) . '</code>'
				),
				array( 'code' => array() )
			)
		);

		echo '<tr><th scope="row">' . esc_html__( 'Models offered', 'email-signature-generator' ) . '</th><td><fieldset>';
		foreach ( $config['models'] as $model ) {
			echo '<p>';
			self::toggle(
				array(
					'name'    => $name . '[models][]',
					'value'   => $model['id'],
					'checked' => in_array( $model['id'], $settings['models'], true ),
					'label'   => $model['label'],
					'key'     => 'models',
				)
			);
			echo '</p>';
		}
		echo '<p class="description">' . esc_html__( 'With one model, the form does not ask which. With none ticked, every model is offered.', 'email-signature-generator' ) . '</p>';
		echo '</fieldset></td></tr>';

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s[remember_values]" value="0">',
			esc_html__( 'Remember what was typed', 'email-signature-generator' ),
			esc_attr( $name )
		);
		self::toggle(
			array(
				'name'    => $name . '[remember_values]',
				'checked' => $settings['remember_values'],
				'label'   => __( 'Each browser keeps what its user typed, to fill the form the next time', 'email-signature-generator' ),
				'key'     => 'remember_values',
			)
		);
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %d: minutes. */
				__( 'Kept only in that browser, never sent to the site, and forgotten %d minutes after the signature was last generated; the form also offers a link to forget it at once.', 'email-signature-generator' ),
				Config::REMEMBER_MINUTES
			)
		) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Who can use the form', 'email-signature-generator' ) . '</th><td><fieldset>';
		foreach ( array(
			Settings::ACCESS_EVERYONE  => __( 'Everyone who can see the page', 'email-signature-generator' ),
			Settings::ACCESS_LOGGED_IN => __( 'Logged-in users only', 'email-signature-generator' ),
		) as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[access]" value="%2$s"%3$s> %4$s</label><br>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $settings['access'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset></td></tr>';
		echo '</tbody></table></div>';

		self::openCard( __( 'Email', 'email-signature-generator' ), 'email' );
		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s[restrict_email_domain]" value="0">',
			esc_html__( 'Allowed email domain', 'email-signature-generator' ),
			esc_attr( $name )
		);
		self::toggle(
			array(
				'name'    => $name . '[restrict_email_domain]',
				'checked' => $settings['restrict_email_domain'],
				'label'   => __( 'Accept only addresses at the domains below', 'email-signature-generator' ),
				'key'     => 'restrict_email_domain',
			)
		);
		echo '<p class="description">' . esc_html__( 'Off, the form accepts any valid address outside the refused domains.', 'email-signature-generator' ) . '</p></td></tr>';
		printf(
			'<tr data-esg-shown-by="restrict_email_domain"%1$s><th scope="row"><label for="esg-email-domains">%2$s</label></th><td><textarea id="esg-email-domains" class="regular-text" rows="3" name="%3$s[email_domains]" placeholder="example.org">%4$s</textarea><p class="description">%5$s</p></td></tr>',
			$settings['restrict_email_domain'] ? '' : ' hidden',
			esc_html__( 'Domains', 'email-signature-generator' ),
			esc_attr( $name ),
			esc_textarea( implode( "\n", $settings['email_domains'] ) ),
			esc_html__( 'One per line. The form shows the domain beside the field, so a person types only the part before the "@"; with more than one, they pick it from a list, and the first is preselected. With none, any address is accepted.', 'email-signature-generator' )
		);
		printf(
			'<tr data-esg-hidden-by="restrict_email_domain"%1$s><th scope="row"><label for="esg-blocked-domains">%2$s</label></th><td><textarea id="esg-blocked-domains" class="regular-text" rows="6" name="%3$s[blocked_email_domains]">%4$s</textarea><p class="description">%5$s</p></td></tr>',
			$settings['restrict_email_domain'] ? ' hidden' : '',
			esc_html__( 'Refused domains', 'email-signature-generator' ),
			esc_attr( $name ),
			esc_textarea( implode( "\n", $settings['blocked_email_domains'] ) ),
			esc_html__( 'While any address is accepted, addresses at these domains are refused: placeholders and throwaway inboxes people type to get past the field. One per line; a subdomain of one is refused too.', 'email-signature-generator' )
		);
		echo '</tbody></table></div>';

		self::openCard( __( 'Google Analytics', 'email-signature-generator' ), 'chart' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s[analytics]" value="0">',
			esc_html__( 'Tracking', 'email-signature-generator' ),
			esc_attr( $name )
		);
		self::toggle(
			array(
				'name'    => $name . '[analytics]',
				'checked' => $settings['analytics'],
				'label'   => __( 'Load Google Analytics on the pages that show the form', 'email-signature-generator' ),
				'key'     => 'analytics',
			)
		);
		echo '<p class="description">' . esc_html__( 'Off, nothing of Google\'s reaches the page. On, the page loads Google\'s tag with the ID below and records when a signature is generated, copied or downloaded, with only the model (and the image format): never a name, an address or anything typed. Google then receives each visit, so say so in the site\'s privacy policy. Leave it off if the site already loads Google Analytics on every page.', 'email-signature-generator' ) . '</p></td></tr>';
		printf(
			'<tr><th scope="row"><label for="esg-analytics-id">%1$s</label></th><td><input type="text" id="esg-analytics-id" class="regular-text code" name="%2$s[analytics_id]" value="%3$s" placeholder="G-XXXXXXXXXX" autocomplete="off"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Measurement ID', 'email-signature-generator' ),
			esc_attr( $name ),
			esc_attr( $settings['analytics_id'] ),
			esc_html__( 'From Google Analytics: Admin, Data streams, the site\'s stream. Only the ID, not the code snippet. Without an ID nothing is loaded, even switched on.', 'email-signature-generator' )
		);
		echo '</tbody></table></div>';

		self::openCard( __( 'Dark Mode', 'email-signature-generator' ), 'theme' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row"><label for="esg-dark-mode">%1$s</label></th><td><select id="esg-dark-mode" name="%2$s[dark_mode]" data-esg-autosave="dark_mode">',
			esc_html__( 'Dark Mode', 'email-signature-generator' ),
			esc_attr( $name )
		);
		$modes = array(
			'off'  => __( 'Off', 'email-signature-generator' ),
			'on'   => __( 'On (always dark)', 'email-signature-generator' ),
			'auto' => __( 'Auto (follow OS)', 'email-signature-generator' ),
		);
		foreach ( $modes as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $settings['dark_mode'], $value, false ), esc_html( $label ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Applies to this settings screen only; the form on the site keeps its own look. "Auto" follows your operating system preference.', 'email-signature-generator' ) . '</p></td></tr>';
		echo '</tbody></table></div>';

		echo '</div>';

		// Layout: the logo and the space around the signature.
		self::openPanel( 'layout', $active );
		self::openCard( __( 'Form on the page', 'email-signature-generator' ), 'layout' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row"><label for="esg-form-width">%1$s</label></th><td><input type="number" id="esg-form-width" class="small-text" name="%2$s[form_width]" value="%3$d" min="%4$d" max="%5$d" required> px<p class="description">%6$s</p></td></tr>',
			esc_html__( 'Form width', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['form_width'],
			(int) Settings::FORM_WIDTH_MIN,
			(int) Settings::FORM_WIDTH_MAX,
			/* translators: %d: default width, in pixels. */
			esc_html( sprintf( __( 'The widest the form gets on the page; on a narrower screen it shrinks to fit. Default: %d px.', 'email-signature-generator' ), Config::FORM_WIDTH ) )
		);
		echo '</tbody></table></div>';

		self::openCard( __( 'Logo', 'email-signature-generator' ), 'image' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%1$s</th><td><img class="esg-logo-preview" src="%2$s" alt="" width="120"><input type="hidden" class="esg-logo-id" name="%3$s[logo_id]" value="%4$d"><p><button type="button" class="button esg-logo-choose">%5$s</button> <button type="button" class="button-link esg-logo-reset">%6$s</button></p><p class="description">%7$s</p></td></tr>',
			esc_html__( 'Image', 'email-signature-generator' ),
			esc_url( '' !== $logo_url ? $logo_url : Config::bundledLogoUrl() ),
			esc_attr( $name ),
			(int) $settings['logo_id'],
			esc_html__( 'Choose from the media library', 'email-signature-generator' ),
			esc_html__( 'Use the bundled logo', 'email-signature-generator' ),
			esc_html__( 'The signature links to this image, so it must stay in the media library. A square image works best.', 'email-signature-generator' )
		);
		printf(
			'<tr><th scope="row"><label for="esg-logo-alt">%1$s</label></th><td><input type="text" id="esg-logo-alt" class="large-text" name="%2$s[logo_alt]" value="%3$s" placeholder="%4$s" maxlength="%5$d"><p class="description">%6$s</p></td></tr>',
			esc_html__( 'Alternative text', 'email-signature-generator' ),
			esc_attr( $name ),
			esc_attr( $settings['logo_alt'] ),
			esc_attr( $settings['logo_id'] > 0 ? '' : Config::LOGO_ALT ),
			(int) Settings::LOGO_ALT_MAX,
			esc_html__( 'What the logo shows, in words: screen readers read it, and email programs that block images show it instead. Leave it empty to use the bundled logo\'s text, or the alternative text the chosen image has in the media library.', 'email-signature-generator' )
		);
		printf(
			'<tr><th scope="row"><label for="esg-logo-width">%1$s</label></th><td><input type="number" id="esg-logo-width" class="small-text" name="%2$s[logo_width]" value="%3$d" min="%4$d" max="%5$d" required> px</td></tr>',
			esc_html__( 'Width', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['logo_width'],
			(int) Settings::LOGO_WIDTH_MIN,
			(int) Settings::LOGO_WIDTH_MAX
		);
		echo '</tbody></table></div>';

		self::openCard( __( 'Spacing', 'email-signature-generator' ), 'layout' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row"><label for="esg-top-space">%1$s</label></th><td><input type="number" id="esg-top-space" class="small-text" name="%2$s[top_space]" value="%3$d" min="0" max="%4$d" required> px<p class="description">%5$s</p></td></tr>',
			esc_html__( 'Space above the signature', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['top_space'],
			(int) Settings::TOP_SPACE_MAX,
			esc_html__( 'Keeps the signature slightly apart from the text above it, in the copied HTML and in the image. It adds to the height, never to the width.', 'email-signature-generator' )
		);
		echo '</tbody></table></div></div>';

		// Downloaded image.
		self::openPanel( 'download', $active );
		self::openCard( __( 'Downloaded image', 'email-signature-generator' ), 'download' );
		echo '<p class="description">' . esc_html__( 'Copying the HTML is the recommended way to use a signature: the text stays sharp at any size. The image is for email programs that do not accept an HTML signature.', 'email-signature-generator' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s[offer_download]" value="0">',
			esc_html__( 'Offer the download', 'email-signature-generator' ),
			esc_attr( $name )
		);
		self::toggle(
			array(
				'name'    => $name . '[offer_download]',
				'checked' => $settings['offer_download'],
				'label'   => __( 'Show the "Download image" button on the form', 'email-signature-generator' ),
				'key'     => 'offer_download',
			)
		);
		echo '<p class="description">' . esc_html__( 'Turn it off to offer only Copy HTML. The options below apply while it is on.', 'email-signature-generator' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'How to offer it', 'email-signature-generator' ) . '</th><td><fieldset>';
		foreach ( array(
			'notice' => __( 'A button, and a note recommending Copy HTML after each download (recommended)', 'email-signature-generator' ),
			'more'   => __( 'Inside a collapsed "More options", with the same note', 'email-signature-generator' ),
			'plain'  => __( 'A button, with no note', 'email-signature-generator' ),
		) as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[download_style]" value="%2$s"%3$s> %4$s</label><br>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $settings['download_style'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset></td></tr>';
		echo '<tr><th scope="row"><label for="esg-image-format">' . esc_html__( 'Format', 'email-signature-generator' ) . '</label></th><td>';
		printf( '<select id="esg-image-format" name="%s[image_format]">', esc_attr( $name ) );
		foreach ( array(
			'png' => __( 'PNG (recommended)', 'email-signature-generator' ),
			'jpg' => __( 'JPG', 'email-signature-generator' ),
		) as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $settings['image_format'], $value, false ), esc_html( $label ) );
		}
		echo '</select><p class="description">' . esc_html__( 'PNG keeps small letters clean. JPG makes a smaller file but leaves compression noise around the text.', 'email-signature-generator' ) . '</p></td></tr>';
		printf(
			'<tr><th scope="row"><label for="esg-image-quality">%1$s</label></th><td><input type="number" id="esg-image-quality" class="small-text" name="%2$s[image_quality]" value="%3$d" min="%4$d" max="%5$d" required> %%<p class="description">%6$s</p></td></tr>',
			esc_html__( 'JPG quality', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['image_quality'],
			(int) Settings::IMAGE_QUALITY_MIN,
			(int) Settings::IMAGE_QUALITY_MAX,
			esc_html__( 'Only for JPG. Higher keeps the text sharper and makes the file larger.', 'email-signature-generator' )
		);
		echo '<tr><th scope="row"><label for="esg-image-scale">' . esc_html__( 'Resolution', 'email-signature-generator' ) . '</label></th><td>';
		printf( '<select id="esg-image-scale" name="%s[image_scale]">', esc_attr( $name ) );
		foreach ( Settings::IMAGE_SCALES as $scale ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $scale,
				selected( $settings['image_scale'], $scale, false ),
				esc_html(
					sprintf(
						/* translators: 1: pixel density, such as 2; 2: image width in pixels. */
						__( '%1$d× (%2$d px wide)', 'email-signature-generator' ),
						$scale,
						Config::SIGNATURE_WIDTH * $scale
					)
				)
			);
		}
		echo '</select><p class="description">' . esc_html__( '1× is the size of the signature in an email. 2× and 3× are sharper, but an email client may show them larger unless the image is resized.', 'email-signature-generator' ) . '</p></td></tr>';
		echo '</tbody></table></div></div>';

		// Regions.
		self::openPanel( 'regions', $active );
		self::openCard( __( 'Units', 'email-signature-generator' ), 'building' );
		echo '<p class="description">' . esc_html__( 'The units people choose from in the signature form. The signature prints a unit as "Name / ACRONYM", or adds its acronym to the department or the school. The acronym identifies the unit: capitals and digits, up to 12, each used once.', 'email-signature-generator' ) . '</p>';
		echo '<table class="widefat striped esg-regions-table"><thead><tr>';
		echo '<th scope="col" class="esg-regions-default">' . esc_html__( 'Default', 'email-signature-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Name', 'email-signature-generator' ) . '</th>';
		echo '<th scope="col" class="esg-regions-acronym">' . esc_html__( 'Acronym', 'email-signature-generator' ) . '</th>';
		echo '<td class="esg-regions-remove"></td>';
		echo '</tr></thead><tbody class="esg-regions-rows">';
		foreach ( array_values( $regions ) as $i => $region ) {
			self::regionRow( $name, (string) $i, $region['name'], $region['acronym'], $unit === $region['acronym'] );
		}
		echo '</tbody></table>';
		// The row the "Add" button copies; a template is inert, so its
		// required inputs never block the form.
		echo '<template class="esg-regions-template">';
		self::regionRow( $name, '__i__', '', '', false );
		echo '</template>';
		printf( '<p><button type="button" class="button esg-regions-add">%s</button></p>', esc_html__( 'Add a unit', 'email-signature-generator' ) );
		echo '</div></div>';

		// Fields.
		self::openPanel( 'fields', $active );
		self::openCard( __( 'Fields', 'email-signature-generator' ), 'list' );
		echo '<p class="description">' . esc_html__( 'A default pre-fills the field. A locked field shows its default and cannot be changed, for a value every signature must share, such as the address of the building. The name and the email cannot be locked. Capitals prints the field in capital letters, however it was typed. The label is what the form calls the field; leave it empty for the one shown.', 'email-signature-generator' ) . '</p>';
		// The built-in fields, for each one's generic name and default label.
		$builtins = array();
		foreach ( Config::defaults( '' )['models'] as $model ) {
			foreach ( $model['fields'] as $field ) {
				$builtins[ $model['id'] ][ $field['key'] ] = $field;
			}
		}
		foreach ( $config['models'] as $model ) {
			echo '<h3>' . esc_html( $model['label'] ) . '</h3>';
			echo '<div class="esg-table-scroll"><table class="widefat striped esg-fields-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Field', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Label in the form', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Default', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Required', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Locked', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Capitals', 'email-signature-generator' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $model['fields'] as $field ) {
				$base    = $name . '[fields][' . $model['id'] . '][' . $field['key'] . ']';
				$builtin = $builtins[ $model['id'] ][ $field['key'] ] ?? $field;
				$custom  = (string) ( $settings['fields'][ $model['id'] ][ $field['key'] ]['label'] ?? '' );
				printf(
					'<tr><td>%1$s</td><td><input type="text" class="regular-text" name="%2$s" value="%3$s" placeholder="%4$s" maxlength="%5$d" aria-label="%6$s"></td><td>',
					esc_html( (string) ( $builtin['title'] ?? $builtin['label'] ) ),
					esc_attr( $base . '[label]' ),
					esc_attr( $custom ),
					esc_attr( (string) $builtin['label'] ),
					(int) Settings::LABEL_MAX,
					/* translators: %s: a field's generic name. */
					esc_attr( sprintf( __( 'Label of %s in the form', 'email-signature-generator' ), (string) ( $builtin['title'] ?? $builtin['label'] ) ) )
				);
				if ( 'region' === ( $field['type'] ?? '' ) ) {
					printf( '<select name="%s">', esc_attr( $base . '[default]' ) );
					foreach ( Regions::labels( $regions ) as $region ) {
						printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $region ), selected( $field['default'] ?? '', $region, false ) );
					}
					echo '</select>';
				} elseif ( 'office' === ( $field['type'] ?? '' ) ) {
					// One default for every unit field, chosen on the Units tab.
					printf(
						'<span class="description">%1$s <a href="%2$s" data-esg-goto="regions">%3$s</a></span>',
						esc_html( $unit ),
						esc_url( add_query_arg( 'tab', 'regions' ) ),
						esc_html__( 'Set on the Units tab', 'email-signature-generator' )
					);
				} else {
					printf(
						'<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s">',
						esc_attr( $base . '[default]' ),
						esc_attr( (string) ( $field['default'] ?? '' ) ),
						esc_attr( (string) ( $field['placeholder'] ?? '' ) )
					);
				}
				echo '</td><td>';
				self::toggle(
					array(
						'name'     => $base . '[required]',
						'checked'  => ! empty( $field['required'] ),
						'disabled' => Settings::alwaysRequired( $field['key'] ),
						/* translators: %s: field label. */
						'aria'     => sprintf( __( '%s is required', 'email-signature-generator' ), $field['label'] ),
						'key'      => Settings::alwaysRequired( $field['key'] ) ? '' : 'fields.' . $model['id'] . '.' . $field['key'] . '.required',
					)
				);
				if ( Settings::alwaysRequired( $field['key'] ) ) {
					// A disabled checkbox posts nothing; the field stays required.
					printf( '<input type="hidden" name="%s" value="1">', esc_attr( $base . '[required]' ) );
				}
				echo '</td><td>';
				if ( Settings::lockable( $field['key'] ) ) {
					self::toggle(
						array(
							'name'    => $base . '[locked]',
							'checked' => ! empty( $field['locked'] ),
							/* translators: %s: field label. */
							'aria'    => sprintf( __( '%s is locked', 'email-signature-generator' ), $field['label'] ),
							'key'     => 'fields.' . $model['id'] . '.' . $field['key'] . '.locked',
						)
					);
				}
				echo '</td><td>';
				if ( Config::casable( $field ) ) {
					// Unticked posts nothing; the 0 says "no capitals".
					printf( '<input type="hidden" name="%s" value="0">', esc_attr( $base . '[uppercase]' ) );
					self::toggle(
						array(
							'name'    => $base . '[uppercase]',
							'checked' => ! empty( $field['uppercase'] ),
							/* translators: %s: field label. */
							'aria'    => sprintf( __( '%s in capital letters', 'email-signature-generator' ), $field['label'] ),
							'key'     => 'fields.' . $model['id'] . '.' . $field['key'] . '.uppercase',
						)
					);
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div></div>';

		// Design: the form's colours, corners and font.
		self::openPanel( 'design', $active );
		self::openCard( __( 'Design', 'email-signature-generator' ), 'palette' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s[custom_style]" value="0">',
			esc_html__( 'Customise', 'email-signature-generator' ),
			esc_attr( $name )
		);
		self::toggle(
			array(
				'name'    => $name . '[custom_style]',
				'checked' => $settings['custom_style'],
				'label'   => __( 'Use the colours, corners and font below', 'email-signature-generator' ),
				'key'     => 'custom_style',
			)
		);
		echo '<p class="description">' . esc_html__( 'Off, the form keeps its own look, and the values below go back to their defaults: switching it off clears the customisation. Only the form changes: the signature keeps its own look, since that is what email programs receive. The standalone HTML always keeps the form\'s own look.', 'email-signature-generator' ) . '</p></td></tr>';
		foreach ( self::colorLabels() as $key => $label ) {
			printf(
				'<tr data-esg-shown-by="custom_style"%7$s><th scope="row"><label for="esg-color-%1$s">%2$s</label></th><td><input type="color" id="esg-color-%1$s" name="%3$s[colors][%1$s]" value="%4$s" data-esg-default="%6$s"> <code>%5$s</code></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( $name ),
				esc_attr( $settings['colors'][ $key ] ),
				/* translators: %s: the default colour, #rrggbb. */
				esc_html( sprintf( __( 'Default: %s', 'email-signature-generator' ), Config::COLORS[ $key ] ) ),
				esc_attr( Config::COLORS[ $key ] ),
				$settings['custom_style'] ? '' : ' hidden'
			);
		}
		printf(
			'<tr data-esg-shown-by="custom_style"%7$s><th scope="row"><label for="esg-radius">%1$s</label></th><td><input type="number" id="esg-radius" class="small-text" name="%2$s[radius]" value="%3$d" data-esg-default="%6$d" min="0" max="%4$d" required> px<p class="description">%5$s</p></td></tr>',
			esc_html__( 'Rounded corners', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['radius'],
			(int) Settings::RADIUS_MAX,
			/* translators: %d: the default radius, in pixels. */
			esc_html( sprintf( __( 'Of the fields, the buttons and the lists; 0 makes them square. Default: %d px.', 'email-signature-generator' ), Config::RADIUS ) ),
			(int) Config::RADIUS,
			$settings['custom_style'] ? '' : ' hidden'
		);
		printf(
			'<tr data-esg-shown-by="custom_style"%1$s><th scope="row">%2$s</th><td><fieldset>',
			$settings['custom_style'] ? '' : ' hidden',
			esc_html__( 'Font', 'email-signature-generator' )
		);
		foreach ( array(
			'form'  => __( 'The form\'s own (Arial)', 'email-signature-generator' ),
			'theme' => __( 'The site theme\'s', 'email-signature-generator' ),
		) as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[font]" value="%2$s" data-esg-default="%5$s"%3$s> %4$s</label><br>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $settings['font'], $value, false ),
				esc_html( $label ),
				esc_attr( Config::FONTS[0] )
			);
		}
		echo '</fieldset><p class="description">' . esc_html__( 'Colours whose text would be hard to read are saved anyway, with a warning naming them.', 'email-signature-generator' ) . '</p></td></tr>';
		echo '</tbody></table></div></div>';

		// Documentation: nothing to save, so the panel hides the button.
		self::openPanel( 'documentation', $active );
		Documentation::render();
		echo '</div>';

		echo '</div></div>';
		echo '<div class="esg-submit"' . ( 'documentation' === $active ? ' hidden' : '' ) . '>';
		submit_button();
		echo '</div>';
		echo '</form></div>';
	}

	/**
	 * One row of the units table.
	 *
	 * @param string $name    Option name.
	 * @param string $index   Row index, or the template's placeholder.
	 * @param string $office  Unit name.
	 * @param string $acronym Unit acronym.
	 * @param bool   $is_default Whether it is the form's default unit.
	 */
	private static function regionRow( string $name, string $index, string $office, string $acronym, bool $is_default ): void {
		$base = $name . '[regions][' . $index . ']';
		printf(
			'<tr><td class="esg-regions-default"><input type="radio" name="%1$s" value="%2$s" aria-label="%3$s"%4$s></td><td><input type="text" class="large-text" name="%5$s" value="%6$s" aria-label="%7$s" required></td><td class="esg-regions-acronym"><input type="text" class="esg-acronym" name="%8$s" value="%9$s" aria-label="%10$s" pattern="[A-Za-z0-9]{1,12}" maxlength="12" required></td><td class="esg-regions-remove"><button type="button" class="button-link esg-regions-delete" aria-label="%11$s">%12$s</button></td></tr>',
			esc_attr( $name . '[default_unit_row]' ),
			esc_attr( $index ),
			esc_attr__( 'Default unit of the form', 'email-signature-generator' ),
			checked( $is_default, true, false ),
			esc_attr( $base . '[name]' ),
			esc_attr( $office ),
			esc_attr__( 'Name', 'email-signature-generator' ),
			esc_attr( $base . '[acronym]' ),
			esc_attr( $acronym ),
			esc_attr__( 'Acronym', 'email-signature-generator' ),
			esc_attr__( 'Remove this unit', 'email-signature-generator' ),
			esc_html__( 'Remove', 'email-signature-generator' )
		);
	}

	/**
	 * The unit the form starts on: the one every unit field defaults to.
	 *
	 * @param array<string, mixed> $config The applied configuration.
	 */
	private static function defaultUnit( array $config ): string {
		foreach ( $config['models'] as $model ) {
			foreach ( $model['fields'] as $field ) {
				if ( 'office' === ( $field['type'] ?? '' ) ) {
					return (string) ( $field['default'] ?? '' );
				}
			}
		}
		return '';
	}

	/**
	 * The colours the Design tab offers, key => label, in the order shown.
	 *
	 * @return array<string, string>
	 */
	private static function colorLabels(): array {
		return array(
			'accent'      => __( 'Accent (buttons and links)', 'email-signature-generator' ),
			'accent_text' => __( 'Text on the accent', 'email-signature-generator' ),
			'border'      => __( 'Field borders', 'email-signature-generator' ),
			'muted'       => __( 'Notes and hints', 'email-signature-generator' ),
			'error'       => __( 'Errors', 'email-signature-generator' ),
		);
	}

	/**
	 * The script that puts `esg-dark-mode` on <html>.
	 *
	 * "on" and "off" are decided here; only "auto" asks the operating system,
	 * and it keeps listening, so switching the system theme repaints the open
	 * page. A saved change arrives as `esg:setting-saved` and repaints too.
	 * An unknown value is treated as "off".
	 *
	 * @param string $mode The stored `dark_mode` setting.
	 */
	public static function darkModeScript( string $mode ): string {
		if ( ! in_array( $mode, Settings::DARK_MODES, true ) ) {
			$mode = 'off';
		}
		return '(function(mode){'
			. 'var root=document.documentElement;'
			. 'var query=window.matchMedia?window.matchMedia("(prefers-color-scheme: dark)"):null;'
			. 'function apply(){root.classList.toggle("esg-dark-mode",mode==="on"||(mode==="auto"&&!!query&&query.matches));}'
			. 'apply();'
			. 'if(query&&query.addEventListener){query.addEventListener("change",apply);}'
			. 'document.addEventListener("esg:setting-saved",function(e){if(e.detail&&e.detail.key==="dark_mode"){mode=String(e.detail.value);apply();}});'
			. '})(' . wp_json_encode( $mode ) . ');';
	}

	/**
	 * The tabs, in order: id => label and icon.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function tabs(): array {
		return array(
			'general'       => array(
				'label' => __( 'General', 'email-signature-generator' ),
				'icon'  => 'settings',
			),
			'layout'        => array(
				'label' => __( 'Layout', 'email-signature-generator' ),
				'icon'  => 'layout',
			),
			'download'      => array(
				'label' => __( 'Downloaded image', 'email-signature-generator' ),
				'icon'  => 'download',
			),
			'regions'       => array(
				'label' => __( 'Units', 'email-signature-generator' ),
				'icon'  => 'building',
			),
			'fields'        => array(
				'label' => __( 'Fields', 'email-signature-generator' ),
				'icon'  => 'list',
			),
			'design'        => array(
				'label' => __( 'Design', 'email-signature-generator' ),
				'icon'  => 'palette',
			),
			'documentation' => array(
				'label' => __( 'Documentation', 'email-signature-generator' ),
				'icon'  => 'book',
			),
		);
	}

	/**
	 * The tab to open: `?tab=` when it names one, else the first.
	 *
	 * Every tab is in the one form, so switching tabs never loses an edit;
	 * the parameter only chooses which panel shows first, for example after
	 * "Save Changes" returns to the screen.
	 */
	private static function activeTab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: chooses the panel shown first.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'general';
	}

	/**
	 * The vertical tab menu. Each tab is a link, so it also works without
	 * JavaScript, where every panel shows anyway.
	 *
	 * @param string $active Active tab id.
	 */
	private static function nav( string $active ): void {
		echo '<ul class="esg-tabs__nav" role="tablist">';
		foreach ( self::tabs() as $id => $tab ) {
			$is = $id === $active;
			printf(
				'<li role="presentation"><a class="esg-tabs__tab%1$s" id="esg-tab-%2$s" href="%3$s" role="tab" aria-controls="esg-panel-%2$s" aria-selected="%4$s" data-esg-tab="%2$s">%5$s<span class="esg-tabs__label">%6$s</span></a></li>',
				$is ? ' is-active' : '',
				esc_attr( $id ),
				esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=' . $id ) ),
				$is ? 'true' : 'false',
				Icons::svg( $tab['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup from the icon registry.
				esc_html( $tab['label'] )
			);
		}
		echo '</ul>';
	}

	/**
	 * Open a tab's panel; the caller closes the `div`.
	 *
	 * @param string $id     Tab id.
	 * @param string $active Active tab id.
	 */
	private static function openPanel( string $id, string $active ): void {
		printf(
			'<div class="esg-tabs__panel%1$s" id="esg-panel-%2$s" role="tabpanel" aria-labelledby="esg-tab-%2$s">',
			$id === $active ? ' is-active' : '',
			esc_attr( $id )
		);
	}

	/**
	 * Open a boxed section whose heading carries the icon of its subject;
	 * the caller closes the `div`.
	 *
	 * @param string $title Heading.
	 * @param string $icon  Icon name.
	 */
	private static function openCard( string $title, string $icon ): void {
		printf(
			'<div class="card"><h2>%1$s<span>%2$s</span></h2>',
			Icons::svg( $icon ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup from the icon registry.
			esc_html( $title )
		);
	}

	/**
	 * An on/off switch: a real checkbox, so the form still posts it, drawn as
	 * a track. With a `key` it also saves the moment it is flipped.
	 *
	 * @param array{name: string, value?: string, checked: bool, disabled?: bool, label?: string, aria?: string, key?: string} $args Toggle.
	 */
	private static function toggle( array $args ): void {
		$label = (string) ( $args['label'] ?? '' );
		$aria  = (string) ( $args['aria'] ?? '' );
		$key   = (string) ( $args['key'] ?? '' );
		printf(
			'<label class="esg-toggle"><input type="checkbox" name="%1$s" value="%2$s"%3$s%4$s%5$s%6$s><span class="esg-toggle-track" aria-hidden="true"></span>%7$s</label>',
			esc_attr( $args['name'] ),
			esc_attr( $args['value'] ?? '1' ),
			checked( $args['checked'], true, false ),
			disabled( ! empty( $args['disabled'] ), true, false ),
			'' !== $aria ? ' aria-label="' . esc_attr( $aria ) . '"' : '',
			'' !== $key ? ' data-esg-autosave="' . esc_attr( $key ) . '"' : '',
			'' !== $label ? '<span class="esg-toggle-label">' . esc_html( $label ) . '</span>' : ''
		);
	}
}
