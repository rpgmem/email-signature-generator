# Changelog

All notable changes to **Email Signature Generator** are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- **The plugin has an icon on Dashboard → Updates and in the plugin list** (#80). An envelope over a signature stroke, white on purple, replaces WordPress's grey placeholder, read from the installed copy. `assets/img/brand/` also ships black, white, dark-screen and no-background variants.

## [1.3.0] (2026-10-12) — `dce0461`

The licence moves to AGPLv3 or later. The rest is the release process catching up with Free Form Certificate: CI tests the declared WordPress floor, both coverage floors rise to 90%, Dependabot's minor updates merge on their own, and every workflow runs with a read-only token. Nothing changes in what the plugin does.

### Changed

- ⚠ **The licence is now AGPLv3 or later** (#73). Reuse and forks stay free; a modified copy offered as a network service must now publish its source too. Releases already published stay GPLv3, and a test holds every declaration of the licence together.
- **CI tests the declared WordPress floor, and both coverage floors rise to 90%** (#67). The functional suite also runs on WordPress 6.8, and `composer.json` pins the PHP platform to 8.3.
- **The generator's copy and download paths are tested** (#66): the Clipboard API and its fallback, a refused copy, and the image download when drawing fails. JS line coverage rose from 86.3% to 94.1%.
- **Dependabot's patch and minor updates merge on their own** (#70). Required checks still gate the merge; majors stay manual.

### Security

- **CI jobs run with a read-only token, and form ids no longer use `Math.random()`** (#75). Both close CodeQL alerts.

## [1.2.0] (2026-10-12) — `d888d8f`

The settings screen takes Free Form Certificate's look: its colours, icons and documentation navigation, and a dark mode; a cleared number now keeps its default.

### Added

- **Dark mode for the settings screen** (#59). A Dark Mode setting on the General tab (Off, On, or Auto to follow the operating system) turns this screen dark with Free Form Certificate's dark palette, saves as soon as it changes and repaints at once. The form on the site keeps its own look.

### Changed

- **CI holds coverage floors and two new guards** (#55). PHP line coverage (unit and functional suites merged) and JS line coverage now have floors. Tests check the Upgrade Notice against the Stable tag, and require every PHPCS or PHPStan suppression to name its rule and give a reason. The admin script's tests now count toward coverage.
- **The documentation's contents stays in view on a wide screen** (#56). From 1100px wide, the Contents box is a column beside the topics that stays put while they scroll; narrower, it stays above them. The credits line moves below the topics, and a topic opened from the contents lands just below the admin bar.
- **The documentation's navigation matches Free Form Certificate's** (#57). The Contents box is now Quick Navigation: a search box that filters the topics and their cards, and one bordered row per topic with its icon, filled with the admin colour on hover.
- **The settings screen takes Free Form Certificate's colours and icons** (#58). Its colours come from one palette with Free Form Certificate's light values: the accent is its blue rather than the admin colour scheme's, and borders are darker (3:1 against white). The Design tab and its topic use its palette icon, and the chart icon is its drawing.

### Fixed

- **A cleared number field keeps its default** (#55). Emptying the form width, logo width, space above, image quality or rounded corners stored the lowest value; it now means "not set".

## [1.1.0] (2026-10-11) — `e540c1c`

The form gets clearer errors and a sturdier email field, and the plugin gains optional analytics and a design of its own; the standalone page gets a stable address.

### Added

- **The latest standalone generator has a stable address** (#48). Each release publishes its HTML on GitHub Pages at https://rpgmem.github.io/email-signature-generator/, for a link or an `<iframe>`, byte-for-byte the file the release offers.
- **Optional Google Analytics** (#50). Off by default; switched on with a GA4 measurement ID, pages with the form load Google's tag and record when a signature is generated, copied or downloaded, with only the model and the image format. Nothing typed is ever sent.
- **A Design tab for the form's colours, corners and font** (#51). Off by default; switched on, five colours, the corner radius and the theme's font reach the form, and colour pairs below WCAG's 4.5:1 are saved with a warning. The signature and the standalone HTML keep their own look.

### Changed

- **Each field shows its own error, and the form says what stays private** (#47). A message sits under its field and is read with it; the box above the buttons only says to check the marked fields, and sending the form focuses the first one. The form always says nothing typed is sent to the site.

### Fixed

- **The split email field keeps the domain whole and the typed part usable** (#46). The domain stays on one line at its own width, the part before the "@" never gets narrower than 14 characters, and the domain moves under the field when the form's column is narrow, not only on a small screen.
- **The form's lists no longer look disabled** (#47). Selects paint like the text fields; a locked one keeps the disabled look.

## [1.0.0] (2026-10-11) — `4797e9c`

The signature page becomes a WordPress plugin: a form placed with a shortcode, set up from the admin, and a standalone HTML file published beside it on every release.

### Added

- **A WordPress plugin with a shortcode** (#1, #3). `[email_signature]` places the form on any page, and `models="…"` narrows and orders the models it offers. The signature is built in the visitor's browser, so nothing typed reaches the site.
- **A standalone HTML file on every release** (#3). `email-signature-generator-X.Y.Z.html` is one self-contained page in Brazilian Portuguese, built from the plugin's own sources at the release tag, so the two cannot drift apart.
- **Settings → Email Signature** (#4, #9, #10, #26, #37, #39). Vertical tabs with icons and boxed sections, as in Free Form Certificate: General, Layout, Downloaded image, Units, Fields and Documentation. Switches save the moment they are flipped. General opens with the shortcode and a Copy button; Documentation explains every setting and credits the author.
- **Who can use the form and which addresses it takes** (#4, #24, #25). The models offered and whether only logged-in users see the form. Email domains on a switch: on, the person types the part before the "@" beside a fixed domain or a list of them; off, any valid address except an editable list of placeholder and throwaway domains.
- **Units** (#17, #18, #19, #20, #26). A table of names and unique acronyms, with the one the form starts on. The form offers them as a list, and the signature prints "Name / ACRONYM" or adds the acronym to the department or school.
- **Per-field control** (#4, #30, #32). For every field of every model: the label the form shows, a default, and whether it is required, locked to that default, or printed in capitals.
- **Layout** (#4, #16, #28, #34). A logo from the media library, with its width and alternative text (the bundled one by default, and as fallback); the space above the signature; and how wide the form gets on the page.
- **How the image is offered** (#8, #11, #12, #15). The download can be turned off, leaving only Copy HTML, or offered with a note recommending it. PNG by default, JPG with its quality, and 1× to 3× resolution.
- **Help while filling in the form** (#23, #28, #29, #31). Phone numbers format themselves and become `tel:` links, a preview shows the signature in dark mode, and a collapsed section explains how to add it in Outlook, Gmail and on a phone.
- **Remember what was typed** (#31, #33, #35). Off by default. When on, each browser keeps its person's last model and values for 60 minutes after the signature was last generated, with a link to forget them at once; a field left empty does not hide a default set later.
- **Updates from GitHub Releases** (#5). WordPress offers new versions on its normal update screen and checks each zip's SHA-256 before installing it.
- **Brazilian Portuguese** (#3, #4, #5). Every string of the form, the signature, the settings and the updater.
- **Release packaging, CI and a testes deploy** (#2, #21, #22, #38). Each tag publishes the zip, the standalone HTML and the notes; every PR runs PHPUnit (also against a real WordPress), Vitest, PHPStan, PHPCS, ESLint and Stylelint; `develop` deploys to the testes site. Asset URLs carry the file time, so a deploy reaches the browser at once, and the HTML-only history is versioned 0.1.0–0.3.0.
- **README and readme.txt cover every setting, with screenshots** (#36), and a privacy section on what a browser keeps and for how long.

### Changed

- **Example values are placeholders, and errors show beside the form** (#3). The page pre-filled examples its own validation refused, and reported errors one `alert()` at a time; now hints are hints, and every error is listed above the buttons and marks its field.
- **Copy HTML is the recommended way** (#3, #12, #14, #15). It copies rich text and HTML through the Clipboard API, its button is the filled one, and the form says why. Copy and download appear only once a signature is generated, and hide again when a field changes.
- **A cleaner signature** (#4, #13, #30). The name is bold inline, which every email client keeps; the divider is as tall as the logo; and capitals are written into the text instead of forced by CSS, so turning them off works.

### Removed

- **`assinatura.html` left the repository** (#3). The release build produces the standalone file from the plugin's sources.

### Security

- **Typed values are escaped in the signature** (#3). The page inserted them as they were, so a copied signature could carry markup or script someone typed. A link must be `http` or `https`.
- **The logo and html2canvas are bundled** (#3). The logo was hotlinked from a third-party site and html2canvas came from a CDN; both now ship with the plugin, html2canvas byte-identical to its 1.4.1 release.

## [0.3.0] (2025-06-24) — `de27b69`

The last version of the single page, `assinatura.html`, before it became a plugin.

### Changed

- **The regional office is picked from a list.** The department or school building model chose among the thirteen regional education offices and the Municipal Department of Education, labelled "Regional ou Central", instead of typing the office's name.
- **School staff print their role too.** The role line, until then only in the regional office model, is printed in the school model's signature as well.
- **"Full name" in both staff models.** The regional office model's name field and its message say "Nome Completo", as the school model's did.

## [0.2.0] (2025-06-24) — `b0788a4`

### Added

- **A third model, for a department or a school building.** It signs as the place rather than a person: the department or school, its email and phones, its regional office, address, postal code and links.

### Changed

- **The models are named for who uses them:** "Servidores - DRE" and "Servidores - Escola" instead of "Assinatura DRE" and "Assinatura Escola".

## [0.1.1] (2025-05-27) — `82f675e`

### Changed

- **Phone numbers are checked in one format for both models:** `55 XX XXXX-XXXX` or `55 XX XXXXX-XXXX`, optionally a second number after " | ". The school model accepted one number before.

## [0.1.0] (2025-05-21) — `ff0d5cb`

### Added

- **The first signature page,** one file, `assinatura.html`, with two models: a regional office (DRE) signature and a school signature. Each had its own fields with SME-SP defaults, checks on the name, role or department, email and phone, a preview, "Copiar HTML" for Outlook and "Baixar Imagem", a JPG drawn by html2canvas loaded from a CDN. The logo was linked from the DRE São Miguel site.

These four versions were never tagged at the time: their numbers, dates and commits were assigned afterwards from the repository's history (#38).
