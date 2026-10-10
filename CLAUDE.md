# CLAUDE.md

Project conventions for Claude (Anthropic CLI / agent sessions) working on this repository. Sessions inherit nothing across cold starts, so the durable rules live here. The workflow mirrors `rpgmem/total-mail-queue` and `rpgmem/ffcertificate`; where this file is silent, theirs are the reference.

## What this repository ships

**Email Signature Generator** has two deliverables, and both are attached to every GitHub release:
- **The WordPress plugin** (`email-signature-generator-X.Y.Z.zip`). A shortcode places the form, and an administrator configures it.
- **The standalone HTML** (`email-signature-generator-X.Y.Z.html`). One self-contained file that needs no WordPress, with the default configuration embedded.

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
- **Refs:** an agent's credential can create and update refs but **cannot delete a ref** (HTTP 403). Deleting merged branches is the maintainer's job.
- Dependabot targets `develop`. A security update opens against `main`: retarget it to `develop`.

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

## Versioning and CHANGELOG

- **The version** lives in three places, all updated in the same commit:
  - the `Version:` header in `email-signature-generator.php`;
  - `Plugin::VERSION` in `src/plugin.php`;
  - `Stable tag` in `readme.txt`.
- **The floors** (PHP 8.3, WordPress 6.8, the same as Free Form Certificate's) live in the plugin header, `readme.txt`, `Plugin::REQUIRES_PHP` / `REQUIRES_WP` and `phpcs.xml.dist`. `composer.json` also declares the PHP floor.
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
   - add a fresh `[Unreleased]` above it.
2. **Open the `develop → main` PR.** Keep it draft until the maintainer confirms the production release.
3. **After it lands on `main`,** tag that commit (`vX.Y.Z`) and push the tag. `.github/workflows/release.yml` then:
   - checks the tag against the header version;
   - builds the zip through `bin/build-release-zip.sh` and `.distignore`;
   - publishes a GitHub Release whose notes are that CHANGELOG section, marked as the latest. The plugin's updater reads `releases/latest`.

   The Lint workflow runs the same build against the newest tag on every PR, so a broken `.distignore` or CHANGELOG section fails there first.

   **Pushing the tag publishes a production release, so an agent hands that command to the maintainer and does not run it.** Verify `HEAD` equals `origin/main` before tagging.
4. **Sync `develop` with `main`:**
   - after a squash merge, reset it (`git reset --hard origin/main`, then a force-with-lease push);
   - after a merge commit, fast-forward it.
5. **Backfill the sha** through a normal PR to `develop`: append `` — `<short-sha>` `` to the new heading. This must come after step 4, which would discard it.

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
- PHPUnit `unit` suite on PHP 8.3 and 8.4, and the functional suite against a real WordPress and MySQL (`phpunit.functional.xml.dist`).
- PHPStan level 8, and PHPCS with WPCS and PHPCompatibilityWP.
- ESLint over `assets/js` and Stylelint over `assets/css`.
- `composer validate --strict` and `composer audit --locked`.
- The release build against the newest tag.

- Vitest over the generator (`tests/js`), against the real configuration that `bin/build-standalone.php - ` prints.

Locally:

```bash
composer install && npm ci
vendor/bin/phpunit --testsuite unit
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
npm run lint
npm test
```

## Translations

`languages/` holds the `.pot`, and per locale the `.po`, `.mo` and `.l10n.php`. After changing a translatable string, run `bin/i18n.sh` (needs GNU gettext): it re-extracts the `.pot`, merges it into each `.po` and recompiles. Translate the new entries in the `.po`, then run it again. `TranslationCoverageTest` fails when a source string has no translation, or when a `.po` and its `.l10n.php` disagree.

Every string the browser shows travels in `Config::strings()`, so the JavaScript has no strings of its own. `ConfigTest` fails when the script reads a key the configuration lacks, or the configuration carries one the script never reads.

## The standalone HTML

`bin/build-standalone.php <version> <out-file> [locale] [logo-url]` writes one file with the stylesheet, html2canvas and the generator inlined, and the configuration from `Config::defaults()` translated into the locale (pt_BR by default). `bin/build-release-zip.sh` runs it from the tag's own files, so the release's `.html` and `.zip` come from the same commit.

The signature links its logo by URL, because email clients drop embedded images. The standalone build points at the bundled logo **at the release tag** on `raw.githubusercontent.com`, which stays valid as long as the tag exists; the plugin points at its own copy.

## Settings

`Settings` owns the one option, `email_signature_generator_settings`. Its rules:

- **It saves through the Settings API.** `options.php` checks the nonce and `manage_options` before `Settings::sanitize()` runs.
- **Sanitizing never fails a save, it repairs it.**
  - Unknown models and fields are dropped.
  - Widths are clamped, and an invalid domain becomes empty.
  - With no model ticked, all of them are offered.
  - The name stays required, and the name and the email never lock.
  - A field can only be locked with a default.
- **Absent is not off.** A key missing from the stored option means "never saved", so it keeps the default. That is why the capitals checkbox posts a hidden `0` before itself.
- **`Settings::apply()` is the only place a setting reaches the generator.** The standalone build never calls it, so the standalone HTML always carries the plain defaults.

## Code

- Namespace `EmailSignatureGenerator`; no global functions. A class maps to a kebab-case file under `src/`, and `AutoloaderTest` fails when one does not.
- Escape on output; check a nonce and a capability on every request entry point; sanitize every setting on save.
- **Every option the plugin writes is deleted in `uninstall.php`**, in the same change that adds it.
- **The signature is generated in the browser**, and the plugin never receives what a person types. Keep it that way: it is why the plugin stores no personal data.
