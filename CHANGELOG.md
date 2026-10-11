# Changelog

All notable changes to **Email Signature Generator** are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.0.0] (2026-10-10)

### Added

- **The generator is a WordPress plugin with a shortcode** (#1, #3). `[email_signature]` places the form on any page, and `models="…"` narrows and orders the models it offers. The signature is built in the visitor's browser, so nothing typed reaches the site and the plugin stores no personal data.
- **A standalone HTML download on every release** (#3). `email-signature-generator-X.Y.Z.html` is one self-contained file in Brazilian Portuguese, built from the plugin's own sources and defaults at the release tag, so the two cannot drift apart.
- **Settings → Email Signature** (#4). An administrator chooses the models offered, whether only logged-in users may use the form, capitals for the name, an allowed email domain, the regional offices, and the image's format (PNG by default, which keeps small letters clean; JPG optional, with its quality 50–100%) and resolution (1×–3×, default 1×: the 450 px the page always produced, still drawn at twice that and reduced, so the text stays smooth) (#8, #11, #12). Per field, they set a default and whether it is required or locked. On/off settings are switches that save the moment they are flipped (#9). The screen follows Free Form Certificate's layout: a vertical tab menu with icons, and each section in a box (#10).
- **Logo from the media library** (#4). The bundled SME-SP logo stays the default. Another image and its width (60–240 px) can be chosen, and a deleted image falls back to the bundled one.
- **Updates from GitHub Releases** (#5). WordPress offers new versions on its normal update screen and checks the zip's SHA-256 before installing it. The compatibility fields and the upgrade notice come from the release's own `readme.txt`. Ported from Total Mail Queue.
- **Brazilian Portuguese translation** (#3, #4, #5). Every string the form, the signature, the settings screen and the updater show.
- **Release packaging, CI and a testes deploy** (#2). Each tag publishes the zip, the standalone HTML and the notes. Every PR runs PHPUnit (also against a real WordPress), Vitest, PHPStan, PHPCS, ESLint, Stylelint and a composer audit. `develop` deploys to the testes site, followed by a smoke check.
- **The site decides how the image is offered** (#15). The download can be turned off, leaving only Copy HTML, or offered as a button that recommends Copy HTML after each download (the default), the same inside a collapsed "More options", or a plain button.
- **Space above the signature** (#16). A configurable gap, 4 px by default and up to 20, keeps the signature apart from the text above it in both the copied HTML and the image; it adds to the height, never the width.
- **Regional offices have a name and an acronym** (#17, #19). Settings → Regional offices is a table of both, with rows to add and remove; the signature still prints "Name / ACRONYM". The acronym is required and unique. A list saved as text before is read into the two parts, and a row that cannot be saved is named in a notice.
- **Units, with the name the form uses** (#20). The settings call the list Units, so it fits any structure, not only regional offices; a "Name in the form" setting chooses what the signature form calls the field, "Regional office" (pt_BR "Diretoria") by default.

### Changed

- **Example values are placeholders** (#3). The page pre-filled fields with examples such as `55 11 3397-XXXX`, which its own validation then refused. They now show as hints, and only real defaults are pre-filled.
- **Errors show beside the form** (#3). Validation messages appear in a list above the buttons and mark the field, instead of one `alert()` at a time.
- **Copying uses the Clipboard API** (#3). The signature is copied as rich text and as HTML, with the old `execCommand` copy kept as the fallback.
- **The name is bold in every email client** (#4). The weight is set inline, because a theme or an email client can reset `<strong>`.
- **Copying the HTML is the recommended way** (#12, #14). Its button is bold and the form says so under the buttons: pasted HTML keeps the text sharp at any size and the links working, while the image is for email programs that do not accept an HTML signature.
- **The divider is as tall as the logo** (#13). It was the border of the text's cell, so it ran the whole height of the signature; it is now the border of a cell that holds only the logo, in plain table markup email programs keep.
- **Copy and download appear once a signature is generated** (#15). They hide again when a field changes, so neither can deliver a signature that no longer matches the form. Copy HTML is the filled button and Download image a grey one.
- **The regional office is chosen, not typed** (#18). Staff at a regional office type only their department, and staff at a school only the school; both pick the office from a list, and the signature appends its acronym as before ("NTIC / DREMP"). Defaults saved in the old combined fields are split into the two.
- **A deploy reaches the browser at once** (#21). The plugin's own scripts and stylesheets carry the file's modification time in `?ver=`, beside the version. Hosts cache them for a year, and with the version alone a browser kept the previous script after an update.
- **The version is declared in the main plugin file** (#22). `EMAIL_SIGNATURE_GENERATOR_VERSION` sits beside the `Version:` header, as Free Form Certificate's `FFC_VERSION` does, so a bump edits the main file and `readme.txt` only.
- **Phone numbers dial on a tap** (#23). Each number in the signature is a `tel:` link ("55 11 3397-1234" → `tel:+551133971234`), in the same plain style as the email link, so it reads as text and opens the dialler on a phone or Teams.
- **Email domains, on a switch** (#24). Settings turn the restriction on or off. On, it takes one domain or several, one per line: the form shows the domain beside the field, or a list of them, so a person types only the part before the "@", a pasted address is split, and an "@" in that part is refused. Off, any valid address is accepted except at refused domains, a list that starts with placeholders and throwaway inboxes (`test.com`, `example.com`, `mailinator.com` …) and can be edited. A domain saved before keeps the restriction on.
- **The Layout tab, the shortcode to copy, and a default unit** (#26). The Logo tab is now Layout and also holds the space above the signature, which left General. General opens with the shortcode, a Copy button and how to limit the models. The Units table marks the unit the form starts on; the unit fields take it, so the Fields tab only points there.

### Removed

- **`assinatura.html` left the repository** (#3). The release build now produces the standalone file from the plugin's sources.

### Security

- **Typed values are escaped in the signature** (#3). The page inserted them into the signature's HTML as they were, so a copied signature could carry markup or script someone typed. A link must also be `http` or `https`.
- **The logo and html2canvas are bundled** (#3). The logo was hotlinked from a third-party site and html2canvas came from a CDN. Both now ship with the plugin, and html2canvas is byte-identical to its 1.4.1 release.

## Before 1.0.0

The project was one page, `assinatura.html`, written between 2025-05-21 and 2025-06-24 and never versioned or tagged. It offered three models for SME-SP staff: staff at a regional office (DRE), staff at a school, and a department or school building. Each model had its own fields, checks on the name, role, email and phone format, a preview, "Copy HTML" for Outlook and a JPG download through html2canvas. 1.0.0 carries all of it over.
