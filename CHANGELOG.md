# Changelog

All notable changes to **Email Signature Generator** are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- **WordPress plugin scaffold** (#1). The standalone HTML generator becomes the `email-signature-generator` plugin, with CI, release packaging, a testes deploy and the repository conventions in `CLAUDE.md`.
- **The generator as a shortcode** (#1). `[email_signature]` places the form on any page; `models="…"` limits which signature models it offers. The form is drawn in the browser from one configuration, and nothing a person types reaches the server.
- **Standalone HTML download** (#1). Each release carries `email-signature-generator-X.Y.Z.html`, one self-contained file built from the plugin's own sources and defaults, translated into Brazilian Portuguese.
- **Brazilian Portuguese translation** (#1). Every string the form, the signature and the plugin show.

- **Settings → Email Signature** (#1). An administrator chooses the models offered, who may use the form (everyone or logged-in users), whether the name is upper-cased, an allowed email domain, the regional offices, and per field a default, whether it is required and whether it is locked. Unchanged settings keep the defaults.
- **Logo from the media library** (#1). The bundled SME-SP logo stays the default; another image and its width can be chosen on the settings screen.
- **Updates from GitHub Releases** (#1). WordPress offers new versions on its normal update screen, checks the package's SHA-256 before installing it, and reads the compatibility fields and upgrade notice from the release's own `readme.txt`. Ported from Total Mail Queue.

### Changed

- **Example values are placeholders** (#1). The old page pre-filled fields with examples such as `55 11 3397-XXXX`, which its own validation then refused; they now show as hints, and only real defaults are pre-filled.
- **Errors show beside the form** (#1). Validation messages appear in a list above the buttons and mark the field, instead of one `alert()` at a time.

### Security

- **Typed values are escaped in the signature** (#1). The old page inserted them into the signature's HTML as they were, so a copied signature could carry markup or script someone typed. A link must also be `http` or `https`.
- **The logo and html2canvas are bundled** (#1). The logo was hotlinked from a third-party site and html2canvas came from a CDN; both now ship with the plugin, and html2canvas is byte-identical to the 1.4.1 release.
