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
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
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
		$regions  = array() !== $settings['regions'] ? $settings['regions'] : Config::defaults( '' )['regions'];

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

		// General.
		echo '<h2>' . esc_html__( 'General', 'email-signature-generator' ) . '</h2>';
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
		echo '</tbody></table>';

		// Logo.
		echo '<h2>' . esc_html__( 'Logo', 'email-signature-generator' ) . '</h2>';
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
		echo '</tbody></table>';

		// Downloaded image.
		echo '<h2>' . esc_html__( 'Downloaded image', 'email-signature-generator' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row"><label for="esg-image-quality">%1$s</label></th><td><input type="number" id="esg-image-quality" class="small-text" name="%2$s[image_quality]" value="%3$d" min="%4$d" max="%5$d" required> %%<p class="description">%6$s</p></td></tr>',
			esc_html__( 'JPG quality', 'email-signature-generator' ),
			esc_attr( $name ),
			(int) $settings['image_quality'],
			(int) Settings::IMAGE_QUALITY_MIN,
			(int) Settings::IMAGE_QUALITY_MAX,
			esc_html__( 'Higher keeps the text sharper and makes the file larger. 90 suits most signatures.', 'email-signature-generator' )
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
		echo '</select><p class="description">' . esc_html__( 'Every visitor gets the same image, whatever their screen. 2× stays sharp on high-density screens.', 'email-signature-generator' ) . '</p></td></tr>';
		echo '</tbody></table>';

		// Regions.
		echo '<h2>' . esc_html__( 'Regional offices', 'email-signature-generator' ) . '</h2>';
		printf(
			'<p><label for="esg-regions">%1$s</label></p><textarea id="esg-regions" class="large-text code" rows="8" name="%2$s[regions]">%3$s</textarea>',
			esc_html__( 'The choices of the "Regional office or central" field, one per line, exactly as the signature prints them.', 'email-signature-generator' ),
			esc_attr( $name ),
			esc_textarea( implode( "\n", $regions ) )
		);

		// Fields.
		echo '<h2>' . esc_html__( 'Fields', 'email-signature-generator' ) . '</h2>';
		echo '<p>' . esc_html__( 'A default pre-fills the field. A locked field shows its default and cannot be changed, for a value every signature must share, such as the address of the building. The name and the email cannot be locked.', 'email-signature-generator' ) . '</p>';
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
					foreach ( $regions as $region ) {
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

		submit_button();
		echo '</form></div>';
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
