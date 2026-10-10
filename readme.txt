=== Email Signature Generator ===
Contributors: rpgmem
Tags: email signature, signature generator, outlook, shortcode
Requires at least: 6.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A form, placed with a shortcode, that builds a consistent HTML email signature for your organisation.

== Description ==

Everyone in an organisation fills in the same form and gets a signature with the same layout, ready to paste into Outlook or to download as an image.

The plugin ships with the São Paulo Municipal Department of Education (SME-SP) layout as its default, and an administrator can adapt it to another organisation.

A standalone HTML version, which needs no WordPress, is published next to the plugin on each GitHub release.

== Usage ==

Put the shortcode on any page or post:

`[email_signature]`

To offer only some of the models, list them in the order to show them:

`[email_signature models="staff_school,department"]`

The models are `staff_regional` (staff at a regional office), `staff_school` (staff at a school) and `department` (a department or a school building). With one model, the form does not ask which.

The signature is built in the visitor's browser: nothing they type is sent to the site.

== Settings ==

Settings → Email Signature sets, for every form on the site:

* which models are offered, and whether only logged-in users may use the form;
* whether the name is printed in capitals, and an email domain every address must use;
* the logo, from the media library (the bundled one is the default), and its width;
* the JPG download's quality (50–100%) and resolution (1×, 2× or 3×);
* the list of regional offices;
* per field: a default value, whether it is required, and whether it is locked to that default.

== Installation ==

1. Download `email-signature-generator-X.Y.Z.zip` from the latest GitHub release.
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip and activate it.

Updates then arrive through the normal WordPress update screen, straight from this plugin's GitHub Releases. Each package is checked against its published SHA-256 before it is installed, and WordPress's own per-plugin toggle turns on automatic updates.

== Changelog ==

The full history of every release is in [CHANGELOG.md](https://github.com/rpgmem/email-signature-generator/blob/main/CHANGELOG.md).

= 1.0.0 =
* New: the email signature generator as a WordPress plugin, placed with the [email_signature] shortcode.
* New: Settings → Email Signature for the models offered, access, an allowed email domain, the logo, the JPG quality and resolution, the regional offices and per-field defaults, required and locked fields.
* New: updates from GitHub Releases, each package checked against its SHA-256.
* New: a standalone HTML version on every release, for use without WordPress.
* Security: typed values are escaped in the signature, and the logo and html2canvas are bundled instead of loaded from other sites.

== Upgrade Notice ==

= 1.0.0 =
First release as a WordPress plugin: the [email_signature] shortcode, settings for models, access, email domain, logo and fields, and updates from GitHub. Details in CHANGELOG.md.
