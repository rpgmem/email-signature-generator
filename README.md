# Email Signature Generator

A form that builds a consistent HTML email signature for everyone in an organisation. Each person picks a model, fills in their details and gets a signature with the same layout, ready to paste into Outlook or to download as an image.

It comes in two forms, both published on every [release](https://github.com/rpgmem/email-signature-generator/releases/latest):

- **WordPress plugin** (`email-signature-generator-X.Y.Z.zip`): place the form on any page with a shortcode. An administrator sets which models are offered, the defaults, the required and locked fields, the allowed email domain and the logo. Updates arrive through the normal WordPress update screen.
- **Standalone HTML** (`email-signature-generator-X.Y.Z.html`): one self-contained file that needs no WordPress. Open it in a browser or publish it on any web page.

The default layout and logo are those of the São Paulo Municipal Department of Education (SME-SP), and both can be adapted to another organisation.

**Requires** WordPress 6.8 or later and PHP 8.3 or later (plugin only) · **License** GPLv3 or later

## Models

- **Staff, regional office:** for people who work at a Regional Education Office (DRE).
- **Staff, school:** for people who work at a school.
- **Department or school building:** for a department of a DRE, or for the school as a whole.

## How to use it

**In WordPress**, put `[email_signature]` on any page. To offer only some models, list them: `[email_signature models="staff_school,department"]` (the ids are `staff_regional`, `staff_school` and `department`).

In **Settings → Email Signature** an administrator chooses the models offered, who may use the form, an allowed email domain, the logo (from the media library), the regional offices, and per field a default and whether it is required or locked.

**Standalone**, open the `.html` file in a browser, or publish it on any web page.

Then:

1. Choose a model and fill in the fields.
2. Click **Generate signature** to see the preview.
3. Click **Copy HTML** and paste it into your email client's signature settings, such as Outlook's, or click **Download image** to save it as a JPG.

## Documentation and contributing

- [CHANGELOG.md](CHANGELOG.md): what changed in each release.
- [CLAUDE.md](CLAUDE.md): how the repository is organised, the checks every change goes through and how releases are cut.
