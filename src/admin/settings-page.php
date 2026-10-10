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
		wp_enqueue_style( 'email-signature-generator-admin', Plugin::url() . 'assets/css/admin-settings.css', array(), Plugin::VERSION );
		wp_enqueue_script( 'email-signature-generator-admin', Plugin::url() . 'assets/js/admin-settings.js', array( 'media-editor' ), Plugin::VERSION, array( 'in_footer' => true ) );
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

		echo '<div class="wrap esg-settings">';
		echo '<h1>' . esc_html__( 'Email Signature', 'email-signature-generator' ) . '</h1>';
		echo '<p>' . wp_kses(
			sprintf(
				/* translators: %s: the shortcode. */
				__( 'Place the form on any page with %s. These settings apply to every form on the site.', 'email-signature-generator' ),
				'<code>[email_signature]</code>'
			),
			array( 'code' => array() )
		) . '</p>';

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

		self::openCard( __( 'Name and email', 'email-signature-generator' ), 'email' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s[uppercase_name]" value="0">',
			esc_html__( 'Name in capitals', 'email-signature-generator' ),
			esc_attr( $name )
		);
		self::toggle(
			array(
				'name'    => $name . '[uppercase_name]',
				'checked' => $settings['uppercase_name'],
				'label'   => __( 'Print the name in capital letters, whatever was typed', 'email-signature-generator' ),
				'key'     => 'uppercase_name',
			)
		);
		echo '</td></tr>';

		printf(
			'<tr><th scope="row"><label for="esg-email-domain">%1$s</label></th><td><input type="text" id="esg-email-domain" class="regular-text" name="%2$s[email_domain]" value="%3$s" placeholder="example.org"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Allowed email domain', 'email-signature-generator' ),
			esc_attr( $name ),
			esc_attr( $settings['email_domain'] ),
			esc_html__( 'Only addresses at this domain are accepted. Leave it empty to accept any address.', 'email-signature-generator' )
		);
		echo '</tbody></table></div>';

		self::openCard( __( 'Layout', 'email-signature-generator' ), 'layout' );
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

		// Logo.
		self::openPanel( 'logo', $active );
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
			'<tr><th scope="row"><label for="esg-logo-width">%1$s</label></th><td><input type="number" id="esg-logo-width" class="small-text" name="%2$s[logo_width]" value="%3$d" min="%4$d" max="%5$d" required> px</td></tr>',
			esc_html__( 'Width', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['logo_width'],
			(int) Settings::LOGO_WIDTH_MIN,
			(int) Settings::LOGO_WIDTH_MAX
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
		self::openCard( __( 'Regional offices', 'email-signature-generator' ), 'building' );
		echo '<p class="description">' . esc_html__( 'The choices of the "Regional office or central" field. The signature prints each as "Name / ACRONYM". The acronym identifies the office: capitals and digits, up to 12, each used once.', 'email-signature-generator' ) . '</p>';
		echo '<table class="widefat striped esg-regions-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Name', 'email-signature-generator' ) . '</th>';
		echo '<th scope="col" class="esg-regions-acronym">' . esc_html__( 'Acronym', 'email-signature-generator' ) . '</th>';
		echo '<td class="esg-regions-remove"></td>';
		echo '</tr></thead><tbody class="esg-regions-rows">';
		foreach ( array_values( $regions ) as $i => $region ) {
			self::regionRow( $name, (string) $i, $region['name'], $region['acronym'] );
		}
		echo '</tbody></table>';
		// The row the "Add" button copies; a template is inert, so its
		// required inputs never block the form.
		echo '<template class="esg-regions-template">';
		self::regionRow( $name, '__i__', '', '' );
		echo '</template>';
		printf( '<p><button type="button" class="button esg-regions-add">%s</button></p>', esc_html__( 'Add an office', 'email-signature-generator' ) );
		echo '</div></div>';

		// Fields.
		self::openPanel( 'fields', $active );
		self::openCard( __( 'Fields', 'email-signature-generator' ), 'list' );
		echo '<p class="description">' . esc_html__( 'A default pre-fills the field. A locked field shows its default and cannot be changed, for a value every signature must share, such as the address of the building. The name and the email cannot be locked.', 'email-signature-generator' ) . '</p>';
		foreach ( $config['models'] as $model ) {
			echo '<h3>' . esc_html( $model['label'] ) . '</h3>';
			echo '<table class="widefat striped esg-fields-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Field', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Default', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Required', 'email-signature-generator' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Locked', 'email-signature-generator' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $model['fields'] as $field ) {
				$base = $name . '[fields][' . $model['id'] . '][' . $field['key'] . ']';
				echo '<tr><td>' . esc_html( $field['label'] ) . '</td><td>';
				if ( 'region' === ( $field['type'] ?? '' ) ) {
					printf( '<select name="%s">', esc_attr( $base . '[default]' ) );
					foreach ( Regions::labels( $regions ) as $region ) {
						printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $region ), selected( $field['default'] ?? '', $region, false ) );
					}
					echo '</select>';
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
						'disabled' => 'name' === $field['key'],
						/* translators: %s: field label. */
						'aria'     => sprintf( __( '%s is required', 'email-signature-generator' ), $field['label'] ),
						'key'      => 'name' === $field['key'] ? '' : 'fields.' . $model['id'] . '.' . $field['key'] . '.required',
					)
				);
				if ( 'name' === $field['key'] ) {
					// A disabled checkbox posts nothing; the name stays required.
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
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';

		echo '</div></div>';
		submit_button();
		echo '</form></div>';
	}

	/**
	 * One row of the offices table.
	 *
	 * @param string $name    Option name.
	 * @param string $index   Row index, or the template's placeholder.
	 * @param string $office  Office name.
	 * @param string $acronym Office acronym.
	 */
	private static function regionRow( string $name, string $index, string $office, string $acronym ): void {
		$base = $name . '[regions][' . $index . ']';
		printf(
			'<tr><td><input type="text" class="large-text" name="%1$s" value="%2$s" aria-label="%3$s" required></td><td class="esg-regions-acronym"><input type="text" class="small-text esg-acronym" name="%4$s" value="%5$s" aria-label="%6$s" pattern="[A-Za-z0-9]{1,12}" maxlength="12" required></td><td class="esg-regions-remove"><button type="button" class="button-link esg-regions-delete" aria-label="%7$s">%8$s</button></td></tr>',
			esc_attr( $base . '[name]' ),
			esc_attr( $office ),
			esc_attr__( 'Name', 'email-signature-generator' ),
			esc_attr( $base . '[acronym]' ),
			esc_attr( $acronym ),
			esc_attr__( 'Acronym', 'email-signature-generator' ),
			esc_attr__( 'Remove this office', 'email-signature-generator' ),
			esc_html__( 'Remove', 'email-signature-generator' )
		);
	}

	/**
	 * The tabs, in order: id => label and icon.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function tabs(): array {
		return array(
			'general'  => array(
				'label' => __( 'General', 'email-signature-generator' ),
				'icon'  => 'settings',
			),
			'logo'     => array(
				'label' => __( 'Logo', 'email-signature-generator' ),
				'icon'  => 'image',
			),
			'download' => array(
				'label' => __( 'Downloaded image', 'email-signature-generator' ),
				'icon'  => 'download',
			),
			'regions'  => array(
				'label' => __( 'Regional offices', 'email-signature-generator' ),
				'icon'  => 'building',
			),
			'fields'   => array(
				'label' => __( 'Fields', 'email-signature-generator' ),
				'icon'  => 'list',
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
