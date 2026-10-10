#!/usr/bin/env bash
#
# Refresh the translation catalogues after changing a translatable string.
#
#   bin/i18n.sh
#
# 1. Extracts every string from the PHP sources into languages/*.pot.
# 2. Merges the .pot into each .po, keeping the translations already there.
# 3. Compiles each .po into the .mo and the .l10n.php WordPress reads.
#
# Translate the new or changed entries in the .po files, then run it again
# to recompile. Needs GNU gettext (xgettext, msgmerge, msgfmt).

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

pot="languages/email-signature-generator.pot"
version=$(grep -m1 -E 'Version:' email-signature-generator.php | sed -E 's/.*Version:[[:space:]]*//')

xgettext --language=PHP --from-code=UTF-8 --sort-by-file --no-wrap \
	--keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e \
	--keyword=esc_attr__ --keyword=esc_attr_e --keyword=_x:1,2c \
	--add-comments=translators: \
	--package-name="Email Signature Generator" --package-version="$version" \
	--msgid-bugs-address="https://github.com/rpgmem/email-signature-generator/issues" \
	-o "$pot" email-signature-generator.php src/*.php bin/build-standalone.php

# xgettext leaves the charset as a placeholder; the sources are UTF-8.
sed -i 's/charset=CHARSET/charset=UTF-8/' "$pot"

# The plugin header's description is translated by WordPress on the Plugins
# screen, but it is not a __() call, so xgettext cannot see it.
description=$(grep -m1 -E 'Description:' email-signature-generator.php | sed -E 's/.*Description:[[:space:]]*//')
printf '\n#. Plugin header: Description\nmsgid "%s"\nmsgstr ""\n' "$description" >>"$pot"

shopt -s nullglob
for po in languages/*.po; do
	msgmerge --quiet --update --backup=none --no-wrap --no-fuzzy-matching "$po" "$pot"
	msgfmt --check -o "${po%.po}.mo" "$po"
	php bin/po-to-l10n.php "$po" >/dev/null
done

echo "Catalogues refreshed."
