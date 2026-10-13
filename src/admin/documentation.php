<?php
/**
 * Settings → Email Signature → Documentation.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Admin;

use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Plugin;
use EmailSignatureGenerator\Settings;

/**
 * The Documentation tab: a contents list and one card per topic, read from
 * one ordered registry, so the list and the cards cannot disagree. A topic
 * that explains a settings tab names it in `tab`, which is how the guard in
 * tests/Unit/DocumentationTest.php knows every tab is documented.
 */
final class Documentation {

	/**
	 * The topics, in order: id => title, icon, the settings tab it explains
	 * (if any), an introduction, term/text pairs and an optional note.
	 *
	 * @return array<string, array{title: string, icon: string, tab?: string, intro: string, items: list<array{0: string, 1: string}>, note?: string}>
	 */
	public static function topics(): array {
		return array(
			'overview' => array(
				'title' => __( 'Overview', 'email-signature-generator' ),
				'icon'  => 'info',
				'intro' => __( 'A form that builds the same HTML email signature for everyone in the organisation. The signature is built in the visitor\'s browser, so nothing typed reaches the site.', 'email-signature-generator' ),
				'items' => array(
					array( '[email_signature]', __( 'Place the form on any page or post with this shortcode. The Copy button on the General tab copies it.', 'email-signature-generator' ) ),
					array( 'models="…"', __( 'Offers only the models listed, in that order: staff_regional, staff_school and department. With one model, the form does not ask which.', 'email-signature-generator' ) ),
					array( __( 'Standalone HTML', 'email-signature-generator' ), __( 'Every release also publishes a single HTML file with the same form, for use without WordPress. It uses the built-in defaults, not these settings.', 'email-signature-generator' ) ),
				),
			),
			'form'     => array(
				'title' => __( 'Using the form', 'email-signature-generator' ),
				'icon'  => 'email',
				'intro' => __( 'What a person sees on the page.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'Generate signature', 'email-signature-generator' ), __( 'Checks the fields and draws the preview. Changing a field afterwards hides the result until it is generated again.', 'email-signature-generator' ) ),
					array( __( 'Copy HTML', 'email-signature-generator' ), __( 'The recommended way to use the signature: the text stays sharp at any size and the links work. Paste it into the email program\'s signature settings.', 'email-signature-generator' ) ),
					array( __( 'Download image', 'email-signature-generator' ), __( 'For email programs that do not accept an HTML signature, when the Downloaded image tab offers it.', 'email-signature-generator' ) ),
					array( __( 'Phone numbers', 'email-signature-generator' ), __( 'Formatted as the person leaves the field, and each number becomes a link that dials it.', 'email-signature-generator' ) ),
					array( __( 'See it in dark mode', 'email-signature-generator' ), __( 'Shows the preview as an email program\'s dark theme would. It changes only the preview, never what is copied.', 'email-signature-generator' ) ),
					array( __( 'How to add the signature to your email', 'email-signature-generator' ), __( 'A collapsed section with the steps for Outlook on the web, classic Outlook, Gmail and phones.', 'email-signature-generator' ) ),
				),
			),
			'general'  => array(
				'title' => __( 'General', 'email-signature-generator' ),
				'icon'  => 'settings',
				'tab'   => 'general',
				'intro' => __( 'Who sees the form, what it offers, and which addresses it accepts.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'Models offered', 'email-signature-generator' ), __( 'The models every form on the site may offer. A shortcode\'s models="…" can narrow this list, never widen it.', 'email-signature-generator' ) ),
					array(
						__( 'Remember what was typed', 'email-signature-generator' ),
						sprintf(
							/* translators: %d: minutes. */
							__( 'Off by default. When on, each browser keeps what its user typed and fills the form again the next time, for %d minutes after the signature was last generated. The form has a "Forget it" link that clears it at once.', 'email-signature-generator' ),
							Config::REMEMBER_MINUTES
						),
					),
					array( __( 'Who can use the form', 'email-signature-generator' ), __( 'Everyone who can see the page, or logged-in users only; the others see a link to log in.', 'email-signature-generator' ) ),
					array( __( 'Google Analytics', 'email-signature-generator' ), __( 'Off by default, and then nothing of Google\'s is loaded. Switched on with a measurement ID (G-XXXXXXXXXX), the pages that show the form load Google\'s tag and record when a signature is generated, copied or downloaded, with only the model and the image format.', 'email-signature-generator' ) ),
					array( __( 'Allowed email domain', 'email-signature-generator' ), __( 'On: the person types only the part before the "@" and the domain is shown beside the field, as a list when there is more than one. Off: any valid address is accepted, except the refused domains, a list of placeholder and disposable domains such as test.com that you can edit.', 'email-signature-generator' ) ),
					array( __( 'Dark Mode', 'email-signature-generator' ), __( 'Off by default. On, or Auto when the operating system is set to dark, this settings screen and the form on the site turn dark. The signature preview keeps its white ground, as email programs show it, and the Design tab\'s colours apply to the light look only.', 'email-signature-generator' ) ),
				),
				'note'  => __( 'The on/off switches and Dark Mode save as soon as they change. Everything else saves with "Save Changes".', 'email-signature-generator' ),
			),
			'layout'   => array(
				'title' => __( 'Layout', 'email-signature-generator' ),
				'icon'  => 'layout',
				'tab'   => 'layout',
				'intro' => __( 'How the form sits on the page, and how the signature looks.', 'email-signature-generator' ),
				'items' => array(
					array(
						__( 'Form width', 'email-signature-generator' ),
						sprintf(
							/* translators: 1: minimum, 2: maximum, 3: default, all in pixels. */
							__( 'The widest the form gets, from %1$d to %2$d px (%3$d px by default). On a narrower screen it shrinks to fit.', 'email-signature-generator' ),
							Settings::FORM_WIDTH_MIN,
							Settings::FORM_WIDTH_MAX,
							Config::FORM_WIDTH
						),
					),
					array( __( 'Logo', 'email-signature-generator' ), __( 'Chosen from the media library; "Use the bundled logo" goes back to the default. The signature links to the image, so it must stay in the library. A square image works best.', 'email-signature-generator' ) ),
					array( __( 'Alternative text', 'email-signature-generator' ), __( 'What the logo shows, in words, for screen readers and for email programs that block images. Empty uses the bundled logo\'s text, or the chosen image\'s own alternative text.', 'email-signature-generator' ) ),
					array( __( 'Space above the signature', 'email-signature-generator' ), __( 'Keeps the signature slightly apart from the text above it. It adds to the height, never to the width.', 'email-signature-generator' ) ),
				),
			),
			'download' => array(
				'title' => __( 'Downloaded image', 'email-signature-generator' ),
				'icon'  => 'download',
				'tab'   => 'download',
				'intro' => __( 'The image is the fallback for email programs that do not accept an HTML signature.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'Offer the download', 'email-signature-generator' ), __( 'Off leaves only Copy HTML.', 'email-signature-generator' ) ),
					array( __( 'How to offer it', 'email-signature-generator' ), __( 'A button with a note recommending Copy HTML after each download, the same inside a collapsed "More options", or a plain button.', 'email-signature-generator' ) ),
					array( __( 'Format and quality', 'email-signature-generator' ), __( 'PNG keeps small letters clean. JPG makes a smaller file, at a quality you choose, but leaves compression noise around the text.', 'email-signature-generator' ) ),
					array( __( 'Resolution', 'email-signature-generator' ), __( '1× is the signature\'s size in an email. 2× and 3× are sharper, but an email program may show them larger unless the image is resized.', 'email-signature-generator' ) ),
				),
			),
			'regions'  => array(
				'title' => __( 'Units', 'email-signature-generator' ),
				'icon'  => 'building',
				'tab'   => 'regions',
				'intro' => __( 'The units people choose from in the form, such as the regional education offices.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'Name and acronym', 'email-signature-generator' ), __( 'The signature prints a unit as "Name / ACRONYM", or adds its acronym to the department or the school. An acronym is capitals and digits, up to 12, and each is used once.', 'email-signature-generator' ) ),
					array( __( 'Default', 'email-signature-generator' ), __( 'The unit the form starts on, in every model.', 'email-signature-generator' ) ),
				),
			),
			'fields'   => array(
				'title' => __( 'Fields', 'email-signature-generator' ),
				'icon'  => 'list',
				'tab'   => 'fields',
				'intro' => __( 'Per model and per field, what the form asks and how.', 'email-signature-generator' ),
				'items' => array(
					array(
						__( 'Label in the form', 'email-signature-generator' ),
						sprintf(
							/* translators: %d: maximum number of characters. */
							__( 'What the form calls the field, up to %d characters. Empty shows the built-in label.', 'email-signature-generator' ),
							Settings::LABEL_MAX
						),
					),
					array( __( 'Default', 'email-signature-generator' ), __( 'Pre-fills the field. The unit fields take their default from the Units tab.', 'email-signature-generator' ) ),
					array( __( 'Required', 'email-signature-generator' ), __( 'The form will not generate a signature without it. The name and the unit are always required.', 'email-signature-generator' ) ),
					array( __( 'Locked', 'email-signature-generator' ), __( 'Shows the default and cannot be changed, for a value every signature must share, such as the address of the building. The name and the email cannot be locked.', 'email-signature-generator' ) ),
					array( __( 'Capitals', 'email-signature-generator' ), __( 'Prints the field in capital letters, however it was typed. The email is always printed in lower case.', 'email-signature-generator' ) ),
				),
			),
			'design'   => array(
				'title' => __( 'Design', 'email-signature-generator' ),
				'icon'  => 'palette',
				'tab'   => 'design',
				'intro' => __( 'The form\'s colours, corners and font, to match the site. The signature keeps its own look, since that is what email programs receive.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'Customise', 'email-signature-generator' ), __( 'Off by default, and the form keeps its own look. Switching it off again puts every value back to its default.', 'email-signature-generator' ) ),
					array( __( 'Colours', 'email-signature-generator' ), __( 'The accent of the buttons and links, the text on it, the field borders, the notes and the errors, in the light look; in dark mode the form keeps its own dark colours. A pair hard to read (below 4.5:1, as WCAG asks) is saved with a warning naming it.', 'email-signature-generator' ) ),
					array(
						__( 'Rounded corners', 'email-signature-generator' ),
						sprintf(
							/* translators: 1: default, 2: maximum, in pixels. */
							__( 'Of the fields, the buttons and the lists, from 0 (square) to %2$d px; %1$d px by default.', 'email-signature-generator' ),
							Config::RADIUS,
							Settings::RADIUS_MAX
						),
					),
					array( __( 'Font', 'email-signature-generator' ), __( 'The form\'s own (Arial) or the site theme\'s.', 'email-signature-generator' ) ),
					array( __( 'Standalone HTML', 'email-signature-generator' ), __( 'Always keeps the form\'s own look: it never reads these settings.', 'email-signature-generator' ) ),
				),
			),
			'privacy'  => array(
				'title' => __( 'Privacy', 'email-signature-generator' ),
				'icon'  => 'shield',
				'intro' => __( 'Nothing typed in the form is sent to the site, and the plugin stores no personal data.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'In the browser', 'email-signature-generator' ), __( 'With "Remember what was typed" on, the values stay in the visitor\'s own browser until they expire or the visitor forgets them. Leave it off where computers are shared.', 'email-signature-generator' ) ),
					array( __( 'On the site', 'email-signature-generator' ), __( 'Only these settings are stored, in one option that is deleted when the plugin is.', 'email-signature-generator' ) ),
					array( __( 'With Google Analytics', 'email-signature-generator' ), __( 'If an administrator switches it on, Google receives each visit to a page with the form, as on any site that uses it, and an event when a signature is generated, copied or downloaded. What was typed and the signature are never sent.', 'email-signature-generator' ) ),
				),
			),
			'updates'  => array(
				'title' => __( 'Updates', 'email-signature-generator' ),
				'icon'  => 'sync',
				'intro' => __( 'New versions arrive through the normal WordPress update screen, from the plugin\'s GitHub releases.', 'email-signature-generator' ),
				'items' => array(
					array( __( 'Checked package', 'email-signature-generator' ), __( 'Each package is checked against its published SHA-256 before it is installed.', 'email-signature-generator' ) ),
					array( __( 'Automatic updates', 'email-signature-generator' ), __( 'Turned on or off with WordPress\'s own link on the Plugins screen.', 'email-signature-generator' ) ),
					array( __( 'View details', 'email-signature-generator' ), __( 'Shows the description, installation steps and screenshots, in your language when the plugin ships a translation of them. A screenshot opens larger without leaving the window: the arrows move between them, and the wheel, a pinch or a click zooms in.', 'email-signature-generator' ) ),
				),
			),
		);
	}

	/**
	 * Print the contents list and the cards.
	 */
	public static function render(): void {
		$topics = self::topics();

		// On a wide screen the contents is a sticky column beside the topics;
		// on a narrow one it stays above them (assets/css/admin-settings.css).
		echo '<div class="esg-doc-layout">';
		// Quick navigation, as in Free Form Certificate's documentation: one
		// row per topic with its icon, and a search box that filters both the
		// rows and the cards (assets/js/admin-settings.js).
		printf( '<nav class="card esg-doc-contents" aria-label="%1$s"><h2>%2$s<span>%1$s</span></h2>', esc_attr__( 'Quick Navigation', 'email-signature-generator' ), Icons::svg( 'search' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup from the icon registry.
		printf( '<p class="esg-doc-search"><input type="search" id="esg-doc-search" placeholder="%1$s" aria-label="%2$s" /></p><ul>', esc_attr__( 'Search documentation…', 'email-signature-generator' ), esc_attr__( 'Search documentation', 'email-signature-generator' ) );
		foreach ( $topics as $id => $topic ) {
			printf( '<li><a href="#esg-doc-%1$s">%2$s%3$s</a></li>', esc_attr( $id ), Icons::svg( $topic['icon'], 16 ), esc_html( $topic['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup from the icon registry.
		}
		printf( '</ul><p class="esg-doc-search-empty" hidden>%s</p></nav>', esc_html__( 'No topic matches this search.', 'email-signature-generator' ) );

		echo '<div class="esg-doc-topics">';
		foreach ( $topics as $id => $topic ) {
			printf(
				'<div class="card esg-doc"><h2 id="esg-doc-%1$s">%2$s<span>%3$s</span></h2><p>%4$s</p>',
				esc_attr( $id ),
				Icons::svg( $topic['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup from the icon registry.
				esc_html( $topic['title'] ),
				esc_html( $topic['intro'] )
			);
			echo '<dl>';
			foreach ( $topic['items'] as $item ) {
				printf( '<dt>%1$s</dt><dd>%2$s</dd>', esc_html( $item[0] ), esc_html( $item[1] ) );
			}
			echo '</dl>';
			if ( isset( $topic['tab'] ) ) {
				printf(
					'<p class="esg-doc-goto"><a href="%1$s" data-esg-goto="%2$s">%3$s</a></p>',
					esc_url( add_query_arg( 'tab', $topic['tab'] ) ),
					esc_attr( $topic['tab'] ),
					/* translators: %s: settings tab name. */
					esc_html( sprintf( __( 'Open the %s tab', 'email-signature-generator' ), SettingsPage::tabs()[ $topic['tab'] ]['label'] ?? $topic['title'] ) )
				);
			}
			if ( isset( $topic['note'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $topic['note'] ) );
			}
			echo '</div>';
		}
		echo '</div></div>';

		self::credits();
	}

	/**
	 * The author and the repository, read from the plugin header: the lines
	 * WordPress shows on the Plugins screen, so the two cannot disagree.
	 */
	private static function credits(): void {
		$meta = get_file_data(
			Plugin::file(),
			array(
				'author'     => 'Author',
				'author_uri' => 'Author URI',
				'plugin_uri' => 'Plugin URI',
			)
		);
		echo '<p class="esg-doc-credits">';
		printf(
			/* translators: %s: plugin author's name, linked to the author's page. */
			esc_html__( 'Developed by %s.', 'email-signature-generator' ),
			'<a href="' . esc_url( $meta['author_uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $meta['author'] ) . '</a>'
		);
		echo ' ';
		printf(
			/* translators: %s: link to the plugin's source repository. */
			esc_html__( 'Source code and issue tracker: %s', 'email-signature-generator' ),
			'<a href="' . esc_url( $meta['plugin_uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) preg_replace( '#^https?://#', '', $meta['plugin_uri'] ) ) . '</a>'
		);
		echo '</p>';
	}
}
