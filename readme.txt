=== Email Signature Generator ===
Contributors: rpgmem
Tags: email signature, signature generator, outlook, shortcode
Requires at least: 6.8
Tested up to: 7.1.3
Stable tag: 1.0.0
Requires PHP: 8.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A form, placed with a shortcode, that builds a consistent HTML email signature for your organisation.

== Description ==

Everyone in an organisation fills in the same form and gets a signature with the same layout, ready to paste into Outlook or to download as an image.

The plugin ships with the São Paulo Municipal Department of Education (SME-SP) layout as its default, and an administrator can adapt it to another organisation.

A standalone HTML version, which needs no WordPress, is published next to the plugin on each GitHub release, and the latest one is served at https://rpgmem.github.io/email-signature-generator/.

== Usage ==

Put the shortcode on any page or post:

`[email_signature]`

To offer only some of the models, list them in the order to show them:

`[email_signature models="staff_school,department"]`

The models are `staff_regional` (staff at a regional office), `staff_school` (staff at a school) and `department` (a department or a school building). With one model, the form does not ask which.

The signature is built in the visitor's browser: nothing they type is sent to the site. Phone numbers are formatted as they are typed and become links that dial them, the preview can be seen in dark mode, and a collapsible section explains how to add the signature in Outlook, Gmail and on a phone.

== Settings ==

Settings → Email Signature sets, for every form on the site:

* which models are offered, and whether only logged-in users may use the form;
* whether each browser may remember what its user typed, for 60 minutes after the signature was last generated (off by default);
* which email domains are accepted: only the ones listed, shown beside the field, or any address except placeholder domains such as test.com;
* how wide the form gets on the page (640 px by default);
* the logo, from the media library (the bundled one is the default), its width and its alternative text;
* the space above the signature (4 px by default);
* whether the image can be downloaded at all, and how it is offered: a button that recommends Copy HTML after each download, the same inside a collapsed "More options", or a plain button;
* the downloaded image's format (PNG by default, or JPG with its quality) and resolution (1×, 2× or 3×);
* the units, each with a name and a unique acronym, and the one the form starts on;
* per field: the label the form shows, a default value, whether it is required, whether it is locked to that default, and whether it is printed in capitals.
* optional Google Analytics, with a GA4 measurement ID (off by default);
* the form's colours, rounded corners and font, to match the site (off by default; the signature keeps its own look).

A Documentation tab on the same screen explains every setting, how the form behaves, privacy and updates.

On/off settings are switches that save as soon as they are flipped. Text fields, the logo and the lists save with "Save Changes".

== Privacy ==

Nothing typed in the form is sent to the site, and the plugin stores no personal data. With "Remember what was typed" on, the values stay in the visitor's own browser and are forgotten 60 minutes after the signature was last generated, or at once with the form's "Forget it" link. Leave it off where computers are shared.

By default the form loads nothing from third parties. With Google Analytics switched on (off by default), the pages with the form load Google's tag, which sets Google's cookies, and record when a signature is generated, copied or downloaded, with only the model and the image format.

== Installation ==

1. Download `email-signature-generator-X.Y.Z.zip` from the latest GitHub release.
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip and activate it.

Updates then arrive through the normal WordPress update screen, straight from this plugin's GitHub Releases. Each package is checked against its published SHA-256 before it is installed, and WordPress's own per-plugin toggle turns on automatic updates.

== Screenshots ==

1. The form, with a signature generated from fictitious details.
2. The signature previewed in dark mode.
3. Settings, General tab: models, access, remembered values and email domains.
4. Settings, Layout tab: form width, logo and spacing.
5. Settings, Downloaded image tab.
6. Settings, Units tab, with the unit the form starts on.
7. Settings, Fields tab: label, default, required, locked and capitals per field.
8. Settings, Documentation tab.

== Changelog ==

The full history of every release is in [CHANGELOG.md](https://github.com/rpgmem/email-signature-generator/blob/main/CHANGELOG.md).

= 1.0.0 =
* New: the email signature generator as a WordPress plugin, placed with the [email_signature] shortcode.
* New: Settings → Email Signature for the models offered, access, remembered values, the allowed or refused email domains, the form width, the logo and its alternative text, the image format and resolution, the units and the default one, and per field the label, default, required, locked and capitals, with a Documentation tab explaining each.
* New: phone numbers formatted and clickable, a dark-mode preview, and instructions for adding the signature in Outlook, Gmail and on a phone.
* New: updates from GitHub Releases, each package checked against its SHA-256.
* New: a standalone HTML version on every release, for use without WordPress.
* Security: typed values are escaped in the signature, and the logo and html2canvas are bundled instead of loaded from other sites.

== Upgrade Notice ==

= 1.0.0 =
First release as a WordPress plugin: the [email_signature] shortcode, settings for models, access, email domains, layout, units and fields, and updates from GitHub. Details in CHANGELOG.md.
