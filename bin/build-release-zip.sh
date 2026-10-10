#!/usr/bin/env bash
#
# Build the distributable zip and the release notes for one tag.
#
#   bin/build-release-zip.sh vX.Y.Z [out-dir]
#
# The plugin files come from the tag. The exclusion list (.distignore) and
# the notes (the tag's section of CHANGELOG.md) come from the working tree,
# so a tag cut before either file existed, or before its CHANGELOG entry was
# corrected, is still packaged and described the way a release is today.
# On a tag push the working tree is the tag itself, so nothing differs.
#
# Writes <out-dir>/email-signature-generator-X.Y.Z.zip, the standalone
# <out-dir>/email-signature-generator-X.Y.Z.html (built from the tag's own
# sources, when the tag has the build script) and <out-dir>/notes-X.Y.Z.md.
# Fails when the tag's Version header disagrees with the tag, or when the
# CHANGELOG has no section for the version.

set -euo pipefail

tag="${1:?usage: bin/build-release-zip.sh vX.Y.Z [out-dir]}"
out="${2:-build}"
slug="email-signature-generator"
version="${tag#v}"
root="$(git rev-parse --show-toplevel)"

mkdir -p "$out"
out="$(cd "$out" && pwd)"

work="$(mktemp -d)"
cleanup() {
	git -C "$root" worktree remove --force "$work/src" >/dev/null 2>&1 || true
	rm -rf "$work"
}
trap cleanup EXIT

if ! git -C "$root" rev-parse -q --verify "refs/tags/$tag^{commit}" >/dev/null; then
	echo "::error::Tag $tag does not exist." >&2
	exit 1
fi
git -C "$root" worktree add --detach "$work/src" "$tag" >/dev/null 2>&1

header=$(grep -m1 -E 'Version:' "$work/src/$slug.php" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')
if [ "$header" != "$version" ]; then
	echo "::error::Tag $tag does not match plugin header version $header." >&2
	exit 1
fi

mkdir -p "$work/stage/$slug"
rsync -a --exclude-from="$root/.distignore" "$work/src/" "$work/stage/$slug/"
rm -f "$out/$slug-$version.zip"
(cd "$work/stage" && zip -rqX "$out/$slug-$version.zip" "$slug")

# The standalone HTML, built from the tag's files so it matches the zip.
if [ -f "$work/src/bin/build-standalone.php" ]; then
	rm -f "$out/$slug-$version.html"
	php "$work/src/bin/build-standalone.php" "$version" "$out/$slug-$version.html" >/dev/null
fi

# The section is everything between "## [X.Y.Z]" and the next "## " heading.
awk -v head="## [$version]" '
	index($0, head) == 1 { found = 1; next }
	found && /^## / { exit }
	found { print }
' "$root/CHANGELOG.md" | sed -e '/./,$!d' >"$out/notes-$version.md"

if ! grep -q '[^[:space:]]' "$out/notes-$version.md"; then
	echo "::error::CHANGELOG.md has no section for $version." >&2
	exit 1
fi

echo "$out/$slug-$version.zip"
