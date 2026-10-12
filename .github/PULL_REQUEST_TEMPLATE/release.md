<!--
Title: `release: X.Y.Z — bump + finalize CHANGELOG` (to `develop`), or
`X.Y.Z — <summary of the batch>` (the `develop → main` PR, which stays draft
until the maintainer confirms). See CLAUDE.md → "Releases".
-->

## Release X.Y.Z

<!-- One paragraph: what the batch delivers. -->

## Checklist

- [ ] Version bumped in the three sites (`Version:` header, `EMAIL_SIGNATURE_GENERATOR_VERSION`, `readme.txt` `Stable tag`)
- [ ] `[Unreleased]` renamed to `[X.Y.Z] (YYYY-MM-DD)`, fresh empty `[Unreleased]` above
- [ ] `readme.txt` `== Upgrade Notice ==` rewritten for this version, ≤300 characters
- [ ] Documentation and README / screenshots reviewed against the batch

## After merge (maintainer / agent)

1. Maintainer pushes the tag.
2. Agent resets `develop` to `main` (`--force-with-lease`).
3. Agent opens the SHA-backfill PR for the CHANGELOG heading.
4. Agent lists any branch left behind; maintainer deletes it.
