# CLAUDE.md

Project conventions for Claude (Anthropic CLI / agent sessions) working on this repository. Sessions inherit nothing across cold starts, so the durable rules live here. The workflow mirrors `rpgmem/total-mail-queue` and `rpgmem/ffcertificate`; where this file is silent, theirs are the reference.

## What this repository ships

**Email Signature Generator** has two deliverables, and both are attached to every GitHub release:
- **The WordPress plugin** (`email-signature-generator-X.Y.Z.zip`). A shortcode places the form, and an administrator configures it.
- **The standalone HTML** (`email-signature-generator-X.Y.Z.html`). One self-contained file that needs no WordPress, with the default configuration embedded. GitHub Pages serves the latest release's copy at a stable URL.

They share one source. The generator is JavaScript driven by a configuration object: the plugin builds that object from its settings, and the standalone build embeds the defaults. Never fix a behaviour in only one of them.

The default layout and logo are SME-SP's (São Paulo Municipal Department of Education). The plugin's name and code stay organisation-neutral, so another organisation can adopt it by changing settings rather than code.

## Language

Every versioned artifact is in **English**:
- code, comments and docblocks;
- i18n source strings, whose translations live in `languages/` (pt_BR first);
- `CHANGELOG.md` and commit messages;
- issue and PR titles and bodies.

The conversation with the maintainer is in Brazilian Portuguese, and that does not decide the language of anything committed. A PR body is composed in the flow of the conversation, so the discipline belongs at the moment of writing it.

Domain values stay in Portuguese where they are data, not code: the names of the regional offices (`Diretoria Regional de Educação …`) are what the signature prints.

## Branches and pull requests

- **`main`** is production. It changes only through the release PR (`develop → main`) and through `hotfix/*` PRs.
- **`develop`** is the integration branch and the default base for every feature PR.
- Feature branches are `claude/<short-kebab-description>`, cut from `develop`. Always go through a PR, so the commit goes through CI.
- **Opening a PR:**
  - Open it as **draft**, flip it to ready, then enable **auto-merge (SQUASH)**.
  - When a PR completes a pre-agreed unit, flip it straight to ready.
  - When its scope emerged mid-session, confirm with the maintainer first.
- When `develop` moves under a PR, the PR is `behind`. Update the branch; don't wait it out.
- **Closing issues:** `Closes #NNN` does not auto-close an issue when the PR merges into `develop`, because the default branch is `main`. Close the issue by hand when the work lands, and tick its checkboxes or map each one to the PR that shipped it.
- **Refs:** an agent's credential can create and update refs but **cannot delete a ref** (HTTP 403). Deleting a branch that survives the merge is the maintainer's job (see "Issues, pull requests and the merge queue").
- Dependabot targets `develop`. A security update opens against `main`: retarget it to `develop`.
- `dependabot-automerge.yml` arms squash auto-merge on Dependabot's patch and minor updates, the same as Free Form Certificate; the required checks still gate the merge. Majors stay manual, because a major can change behaviour the checks do not see. A merge made with the workflow's token does not trigger other workflows, so `deploy-develop.yml` skips it, which only matters for testes and only until the next merge.

### Rulesets (set by the maintainer in Settings → Rules → Rulesets)

An agent cannot read or change these; the table is the intended configuration, so check the UI before relying on it.

| Ruleset | Rules | Bypass |
| --- | --- | --- |
| `develop-locked` | Restrict deletions | none |
| `develop-ci-gates` | Require a pull request (0 approvals) · Require status checks · branches up to date | Repository admin |
| `main-locked` | Restrict deletions · Block force pushes | none |
| `main-ci-gates` | Require a pull request (0 approvals) · Require status checks · branches up to date | Repository admin |

**Required status checks:** every job in `tests.yml` and `lint.yml`, by job name.

A required check must run on every PR to that branch: no `paths:` filter, and no job-level `if:` that can skip it. Otherwise every PR waits forever on a check that never reports.

Force push stays allowed on `develop`, because the post-release sync is a reset.

## Issues, pull requests and the merge queue

The same standard as Free Form Certificate (its `CLAUDE.md` → "Issues, pull requests and the merge queue"). The layout lives in the templates — `.github/ISSUE_TEMPLATE/` (bug, feature, task) and `.github/PULL_REQUEST_TEMPLATE.md` (plus `PULL_REQUEST_TEMPLATE/release.md`, reached with `?template=release.md`); the rules around them are here.

- **An issue before the PR.** Every PR names the issue it closes. Exempt, and only these: a trivial docs or chore change, a Dependabot PR, the `release: X.Y.Z` bump and the CHANGELOG SHA backfill. An agent opens the issue itself, in English, from the task template.
- **Titles.** Issue: `Area: the problem in one sentence`. PR: `type(scope): summary`, `type` one of `feat`, `fix`, `docs`, `test`, `refactor`, `chore` — it becomes the squash subject on `develop`. Release titles keep their own shape.
- **Labels: one type.** Type is `bug`, `enhancement`, `task`, `documentation` or `security`; this plugin is small enough that it carries no area labels. `duplicate`, `invalid` and `wontfix` say why something closed unmerged, and `dependencies` and the ecosystem labels belong to Dependabot, whose config applies them. An agent cannot create a label, only apply one that exists.
- **A PR carries the labels of the issue it closes.** The agent's tool opens a PR with no labels and cannot set them at creation, so the agent applies them as the next step, before marking it ready. Every issue and every PR carries its type; a PR that only names its issue in the body is unlabelled until someone labels it, which is how the alignment PRs of 2026-10-12 went out bare in three repositories.
- **One merge at a time.** Only one PR is ready with auto-merge armed; the others wait as drafts. After a merge the next PR takes `develop` by a merge, never a rebase; `CHANGELOG.md` conflicts keep both bullets, translation catalogues are recompiled rather than hand-edited, the touched tests run again, and only then is it flipped to ready and armed.
- **After each merge** the agent closes the issue by hand, ticking or naming each acceptance criterion.
- **Branches.** Settings → General → "Automatically delete head branches" is on, so GitHub deletes a merged PR's branch. Whatever survives, the agent lists at the end of the batch for the maintainer to delete; local worktrees the agent removes itself.

## Versioning and CHANGELOG

- **The version** lives in three places, all updated in the same commit:
  - the `Version:` header in `email-signature-generator.php`;
  - `EMAIL_SIGNATURE_GENERATOR_VERSION`, defined just below it in the same file (as Free Form Certificate does with `FFC_VERSION`). `Plugin::VERSION` reads it, and the unit bootstrap reads it from the file's text;
  - `Stable tag` in `readme.txt`.
- **The floors** (PHP 8.3, WordPress 6.8, the same as Free Form Certificate's) live in the plugin header, `readme.txt`, `Plugin::REQUIRES_PHP` / `REQUIRES_WP` and `phpcs.xml.dist`. `composer.json` declares the PHP floor twice: in `require.php`, and in `config.platform.php`, which makes Composer resolve dev dependencies as if it ran on the floor, so none can quietly require a newer PHP.
- **`PluginIdentityTest`** fails when any of those disagree.
- **Feature PRs never bump the version.**
  - Their CHANGELOG entry goes under `## [Unreleased]`.
  - Entries are grouped by `Added` / `Changed` / `Fixed` / `Removed` / `Security` / `Deprecated`, and no other heading.
  - Append the bullet at the end of its heading.
- **A bullet** is:
  - a bold lead sentence;
  - the PR or issue reference;
  - then one or two short sentences, aiming for 300 characters.

  A released heading reads `` ## [X.Y.Z] (YYYY-MM-DD) — `<short-sha>` ``, the sha being the release commit on `main`.

## Releases

1. **On `develop`,** through a dedicated `release: X.Y.Z` PR:
   - bump the three version sites;
   - rename `[Unreleased]` to `## [X.Y.Z] (YYYY-MM-DD)`;
   - add a fresh `[Unreleased]` above it;
   - read `git diff origin/main...origin/develop -- readme.txt languages/readme-pt_BR.txt`: a change on one side only is a translation left behind (see "Translations").
2. **Open the `develop → main` PR.** Keep it draft until the maintainer confirms the production release.
3. **After it lands on `main`,** tag that commit (`vX.Y.Z`) and push the tag. `.github/workflows/release.yml` then:
   - checks the tag against the header version;
   - builds the zip through `bin/build-release-zip.sh` and `.distignore`;
   - publishes a GitHub Release whose notes are that CHANGELOG section, marked as the latest. The plugin's updater reads `releases/latest`.
   - calls `.github/workflows/pages.yml`, which publishes that release's HTML on GitHub Pages.

   The Lint workflow runs the same build against the newest tag on every PR, so a broken `.distignore` or CHANGELOG section fails there first.

   **Pushing the tag publishes a production release, so an agent hands that command to the maintainer and does not run it.** Verify `HEAD` equals `origin/main` before tagging.
4. **Sync `develop` with `main`:**
   - after a squash merge, reset it (`git reset --hard origin/main`, then a force-with-lease push);
   - after a merge commit, fast-forward it.
5. **Backfill the sha** through a normal PR to `develop`: append `` — `<short-sha>` `` to the new heading. This must come after step 4, which would discard it.

**The 0.x tags are history, not releases of the plugin.** 0.1.0 to 0.3.0 were the single page `assinatura.html` (2025), versioned afterwards (#38) on the commits their CHANGELOG headings name. Pushing them starts no workflow, because those commits have none. To give one a GitHub Release, run `release.yml` by hand (`workflow_dispatch`, the tag as input) once the workflow is on `main`. Its release is that page as `email-signature-generator-X.Y.Z.html`, no zip, and it is never marked latest. `bin/build-release-zip.sh` tells such a tag apart by the missing plugin file.

The zip ships no Composer or npm dependency. The autoloader is inline in the main plugin file, and third-party browser libraries are vendored under `assets/vendor/` with their version in the filename. Anything new at the repository root that should not ship goes into `.distignore`.

## Deploy to testes

`.github/workflows/deploy-develop.yml` runs on every push to `develop`, and by hand through `workflow_dispatch`. It rsyncs the tree to the testes site, the same host Free Form Certificate and Total Mail Queue deploy to, and then runs a smoke check there.

- **What lands is what a release would ship.** The rsync excludes `.distignore`, and `--delete` keeps the directory an exact copy.
- **Secrets,** set in this repository:
  - `TESTES_SSH_HOST`, `TESTES_SSH_USER`, `TESTES_SSH_KEY` (no passphrase) and the optional `TESTES_SSH_PORT` carry the same values as in the other two repositories;
  - `TESTES_REMOTE_PATH` is this plugin's own folder: `…/wp-content/plugins/email-signature-generator`, absolute, no trailing slash.
- **The path is checked before anything is sent.** `--delete` empties whatever directory it is pointed at, so the workflow refuses a path that is not absolute or does not end in `/wp-content/plugins/email-signature-generator`. The check cannot tell the testes site from another site on the same account, so the secret must name the testes site.
- **The smoke** (`.github/scripts/testes-smoke.php`) boots the real WordPress. It checks that the plugin is active, that `Plugin::VERSION` on the host equals the deployed commit, and that the shortcode is registered. A boot that stops early fails; a timeout only warns.
- **It is an alarm, not a gate:** it runs after the merge. Anything that must block a merge belongs in CI.

## Quality gates

These run in CI on every PR and on pushes to `main` and `develop`:
- PHPUnit `unit` suite on PHP 8.3 and 8.4, and the functional suite against a real WordPress and MySQL (`phpunit.functional.xml.dist`). The functional suite runs on the latest WordPress and on **the declared floor**: the `floor` leg reads `Requires at least` from the plugin header, resolves it to that branch's newest patch release and swaps the WordPress test library to the same branch, so moving the floor moves the test. A floor nothing runs is a claim, not a requirement.
- PHPStan level 8, and PHPCS with WPCS and PHPCompatibilityWP.
- ESLint over `assets/js` and Stylelint over `assets/css`.
- **Verify minified assets are up to date**: CI runs `npm run build` and fails when an `assets/**/*.min.*` file changes or appears. Run `npm run build` after editing any file under `assets/css` or `assets/js`, and commit the `.min` and `.map` beside it.
- `composer validate --strict` and `composer audit --locked`.
- The release build against the newest tag.
- Vitest over the generator (`tests/js`), against the real configuration that `bin/build-standalone.php - ` prints.
- Coverage floors. The PHP floor is `COVERAGE_FLOOR_LINES` in `tests.yml`, checked against the merged line coverage of the unit and functional suites (`bin/coverage-merge.php`). The JS floor is `thresholds.lines` in `vitest.config.mjs`, which counts every file in `assets/js`. The rule is the same in the four plugins (Free Form Certificate, Total Mail Queue, Email Signature Generator, Institutional Email): **a floor is the highest of 90%, the measured figure minus 5 points, and its current value.** So it is never below 90%, never more than 5 points behind what is measured, and never lowered — the one exception is an honest re-measure after deleting covered product code, recorded beside the floor. Raise it in the PR that delivers the gain, and only once the measured figure is above it.

Two guards read the code rather than test it:
- `ReadmeUpgradeNoticeTest`: the Upgrade Notice holds one entry, for the Stable tag, at most 300 characters, pointing at `CHANGELOG.md`, and the updater's own parser returns it.
- `SuppressionTest`: every `phpcs:ignore` or `phpcs:disable` names a sniff and gives a reason after `--`; a `@phpstan-ignore` names an identifier with a reason, and the `-line` forms are refused.

A browser script under test runs through `runScript()` in `tests/js/helpers.js`, never `new Function()`, which V8 cannot attribute coverage to.

Locally:

```bash
composer install && npm ci
vendor/bin/phpunit --testsuite unit
# The functional suite needs MySQL. A cloud session's container ships MariaDB stopped;
# the environment's setup script starts it with the CI credentials (root / root).
service mariadb start
bash bin/install-wp-tests.sh wp_esg_tests root root 127.0.0.1 false latest
vendor/bin/phpunit -c phpunit.functional.xml.dist
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
npm run lint
npm run test:coverage
npm run build   # after editing assets/css or assets/js
```

## Translations

`languages/` holds the `.pot`, and per locale the `.po`, `.mo` and `.l10n.php`. After changing a translatable string, run `bin/i18n.sh` (needs GNU gettext): it re-extracts the `.pot`, merges it into each `.po` and recompiles. Translate the new entries in the `.po`, then run it again. `TranslationCoverageTest` fails when a source string has no translation.

- **Never edit a compiled catalogue by hand**, and never compile one with a tool outside the repository. `msgfmt` writes the `.mo` and `bin/po-to-l10n.php` the `.l10n.php`; a fix that reaches one and not the other depends on which file WordPress loaded. Before #88 the committed `.l10n.php` had come from a script outside the repository, in a format the committed compiler did not write.
- **`bin/po-catalogue.php` is the one `.po` reader.** The compiler and the tests both use it, so they cannot disagree about what an entry is. It handles the shapes this catalogue does not carry yet (a context, a plural, a fuzzy entry); `PoCatalogueTest` exercises them on a synthetic file.
- **The `.l10n.php` is in `wp i18n make-php`'s shape, byte for byte** — the same keys in the same order Loco and WP-CLI write, so a catalogue compiled here, saved from Loco or made by WP-CLI is the same file. WP-CLI reads the text domain from the `.po`'s `X-Domain` header, which is why the header is there; keep it. The reader sits inside PHPStan's and PHPCS's paths like the plugin's own code (#90).
- **`TranslationCatalogueAgreementTest`** fails when the three files of a locale disagree, in either direction, or when the `.l10n.php` is not byte for byte what the compiler writes. Its self-check recounts the `.po`'s entries by a different route, so a reader that read nothing fails instead of passing.
- **`languages/readme-pt_BR.txt` is the readme "View details" shows a pt_BR administrator.** A PR that changes `readme.txt` updates it in the same PR. `PluginDetailsAssetsTest` holds the two to the same structure (tab sections, the count of other sections, the screenshot captions) and cannot see the content: a changed sentence in English passes CI with the translation still saying the old thing (#87).

Every string the browser shows travels in `Config::strings()`, so the JavaScript has no strings of its own. `ConfigTest` fails when the script reads a key the configuration lacks, or the configuration carries one the script never reads.

## The standalone HTML

`bin/build-standalone.php <version> <out-file> [locale] [logo-url]` writes one file with the stylesheet, html2canvas and the generator inlined, and the configuration from `Config::defaults()` translated into the locale (pt_BR by default). `bin/build-release-zip.sh` runs it from the tag's own files, so the release's `.html` and `.zip` come from the same commit.

The signature links its logo by URL, because email clients drop embedded images. The standalone build points at the bundled logo **at the release tag** on `raw.githubusercontent.com`, which stays valid as long as the tag exists; the plugin points at its own copy.


**Pages serves the release asset, not a build from a branch,** so the page at https://rpgmem.github.io/email-signature-generator/ is always the file the release offers for download. `pages.yml` runs from `release.yml` after a tag push (a release created with the workflow token fires no `release` event) and by hand through `workflow_dispatch`; a historical release published by hand never replaces it. Pages must be enabled with **GitHub Actions** as its source.
## Settings

`Settings` owns the one option, `email_signature_generator_settings`. Its rules:

- **It saves through the Settings API.** `options.php` checks the nonce and `manage_options` before `Settings::sanitize()` runs.
- **Sanitizing never fails a save, it repairs it.**
  - Unknown models and fields are dropped.
  - Widths are clamped, and an entry in a domain list that is not a domain name is left out (and named in a notice).
  - With no model ticked, all of them are offered.
  - The name stays required, and the name and the email never lock.
  - A field can only be locked with a default.
- **Absent is not off.** A key missing from the stored option means "never saved", so it keeps the default. That is why a field's capitals toggle posts a hidden `0` before itself.
- **A row that only matters in one state of a toggle** carries `data-esg-shown-by="<key>"` or `data-esg-hidden-by="<key>"`; the script hides it at once, and again when a failed save puts the toggle back. The server renders it with `hidden` already set, so it never flashes. The hidden fields still post, so turning a toggle off never loses what is under it.
- **On/off settings save as they are flipped** (`Admin\Autosave`, one AJAX action, nonce plus `manage_options`). Every toggle also stays a named field of the form, so "Save Changes" posts its current state and cannot undo an autosave. A new toggle needs both: the `data-esg-autosave` key and its branch in `Autosave::apply()`. The handler reads the stored settings, changes one key and runs `Settings::sanitize()` over the whole, so it can never store what the form could not.
- **The screen is vertical tabs inside the one form** (`SettingsPage::tabs()`), Free Form Certificate's layout. Every panel is in the form, so switching tabs never loses an edit and "Save Changes" still posts everything; that is why the tabs switch in the browser instead of reloading as Free Form Certificate's do (a per-tab form would need a merge-based save, which `Settings::sanitize()` deliberately is not). `?tab=` only chooses the panel shown first, and the script carries it into `_wp_http_referer` so a save returns to the same tab. Without JavaScript every panel shows.
- **Every section is a `.card` whose `h2` opens with an icon** from `Icons`, which holds Free Form Certificate's drawings (24×24, stroke in `currentColor`, geometry only; `IconsTest` refuses anything else), only the ones this plugin draws. A new icon joins that registry, copied from Free Form Certificate's `Core\Icons`; never an inline one-off SVG, an emoji or a dashicon. The registry was `Admin\Icons` until the form's buttons needed it (#106).
- **Every button is the family in `assets/css/buttons.css`** (#106), a slim copy of Free Form Certificate's `.ffc-btn` under `esg-btn`: one category by what the action does (`primary`, `neutral`, `create`, `caution`, `danger`) and an `.esg-icon-*` class. PHP prints it with `Button::html()`, the form's script with its `button()` helper; never core's `.button` or `submit_button()`, whose `<input>` cannot carry the icon. The icon classes are generated from `Icons::CLASSES` between markers in that sheet (`ESG_UPDATE_ICON_CSS=1 vendor/bin/phpunit --filter ButtonsTest`), and `ButtonsTest` measures every category's contrast in both themes. The settings screen and the form both depend on that sheet, and the standalone build inlines it before the form's; the form maps the family's primary onto its accent, so the Design tab reaches the buttons.
- **Dark Mode follows Free Form Certificate when it is active** (#106). `Theme::mode()` reads `ffc_settings['dark_mode']` while `FFC_VERSION` is defined, and the local setting otherwise; nothing requires that plugin. The local field is then disabled with a note and out of the autosave, which refuses the key; a disabled field posts nothing, so `Settings::save()` keeps the stored value instead of resetting it. The standalone HTML has no WordPress and keeps following the operating system.
- **A field the autosave touches for the first time starts from its built-in values.** Otherwise locking the link would also blank its default.
- **Regional offices are `{name, acronym}` rows** (`Regions`). The acronym is required, unique and `[A-Z0-9]{1,12}`, because it identifies the office; the signature and the region select print `Regions::label()`, "Name / ACRONYM". `Regions::parse()` also reads the text lines stored before the split, so old installs need no migration step. `Settings::save()` is the Settings API callback: it reports rows it had to leave out, while `Settings::sanitize()` stays silent so reads never raise notices.
- **The admin says "unit", the form says what the site chooses.** The settings call the list Units (tab id and stored keys stay `regions` / `office`, so nothing migrates); `unit_label` renames the `office` fields in the form, falling back to `Config::unitLabel()`, "Regional office". The department model's own "Regional office or central" field keeps its label.
- **`office` fields keep the acronym, `region` fields the full label.** Staff models pick the office from `config.offices` and the signature appends the acronym to the department or the school; the department model prints the whole "Name / ACRONYM". The office is always required (`Settings::alwaysRequired()`), because a select always has a value. Overrides saved under the old combined keys (`staff_regional.role`, `staff_school.unit`) are split by `Settings::splitLegacyFields()`, only while the new key is absent.
- **`Settings::apply()` is the only place a setting reaches the generator.** The standalone build never calls it, so the standalone HTML always carries the plain defaults.

## Updates

`Update\GithubUpdater` (ported from Total Mail Queue) feeds WordPress's own update check from `releases/latest`:

- **The asset.** It picks the `email-signature-generator-*.zip` asset and never the standalone `.html` beside it.
- **The checksum.** It verifies the asset's SHA-256 digest before WordPress installs it.
- **The release's own metadata.** `Requires at least`, `Tested up to`, `Requires PHP` and the Upgrade Notice come from the offered release's own `readme.txt`.
- **Caching.** It caches for 12 hours in the `email_signature_generator_github_update` site transient, with ETag requests. `uninstall.php` deletes it.
- **Registration.** It is registered outside `is_admin()`, because WordPress also scans from cron.
- **The `releases/latest` consequence.** A release published by hand for an old tag must never be marked latest, which is why `release.yml` sets `make_latest: false` for `workflow_dispatch`.

## Code

- **CSS lives in `assets/css`, never inline.** No `style=""` and no `wp_add_inline_style()`. What the script needs off screen takes the `.esg-offscreen` class. Three things stay inline, each because it cannot be a file: the shortcode's `--esg-*` custom properties (the configured design), whatever the script sets from a runtime value (the screenshot viewer's zoom transform), and the signature itself (mail clients ignore a stylesheet).
- **Minified in production.** Every enqueue goes through `Plugin::asset()`: the `.min` build normally, the readable source when `SCRIPT_DEBUG` is on. The zip ships both and leaves the source maps out. The standalone HTML inlines the sources, not the builds, so its build depends on nothing but the repository.
- Namespace `EmailSignatureGenerator`; no global functions. A class maps to a kebab-case file under `src/`, and `AutoloaderTest` fails when one does not.
- Escape on output; check a nonce and a capability on every request entry point; sanitize every setting on save.
- **Every option the plugin writes is deleted in `uninstall.php`**, in the same change that adds it.
- **The signature is generated in the browser**, and the plugin never receives what a person types. Keep it that way: it is why the plugin stores no personal data.
