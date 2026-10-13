<?php
/**
 * One reader for the gettext catalogues, shared by the compiler and the tests.
 *
 * @package EmailSignatureGenerator\Build
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Build;

/**
 * Reads a `.po` and writes the `.l10n.php` WordPress prefers.
 *
 * `bin/po-to-l10n.php` compiles with it and `TranslationCatalogueAgreementTest`
 * checks with it, so the two cannot disagree about what an entry is. Before
 * this class they were separate parsers, and the committed `.l10n.php` came
 * from neither (#88).
 *
 * The reader is a state machine rather than a line regex because:
 *
 * - a literal continues over the following quoted lines, so `msgstr ""` can
 *   open a long translation rather than declare an empty one;
 * - a plural's value is the indexed set `msgstr[0]` … `msgstr[n]`;
 * - `#, fuzzy` marks a suggestion, which `msgfmt` leaves out of the `.mo`;
 * - the context separator is `\x04`, which has to be built, not read.
 *
 * Ported from Free Form Certificate's `tests/Support/PoCatalogue.php`.
 */
final class PoCatalogue {

	/**
	 * The entry key: `msgctxt \x04 msgid`, or the msgid alone.
	 *
	 * @param string|null $msgctxt Context, or null.
	 * @param string      $msgid   Source string.
	 */
	public static function key( ?string $msgctxt, string $msgid ): string {
		return null === $msgctxt ? $msgid : $msgctxt . "\x04" . $msgid;
	}

	/**
	 * Every entry, in file order, header excluded.
	 *
	 * A list rather than a map, so a duplicate msgid stays visible.
	 *
	 * @param string $path Path to a `.po` or `.pot`.
	 * @return list<array{key: string, msgctxt: string|null, msgid: string, msgid_plural: string|null, forms: list<string>, fuzzy: bool}>
	 */
	public static function read( string $path ): array {
		$entries = array();
		$cur     = array();
		$field   = null;

		$flush = static function () use ( &$cur, &$entries, &$field ): void {
			if ( isset( $cur['msgid'] ) && '' !== $cur['msgid'] ) {
				$forms = array();
				if ( isset( $cur['msgid_plural'] ) ) {
					for ( $i = 0; isset( $cur[ 'msgstr[' . $i . ']' ] ); $i++ ) {
						$forms[] = $cur[ 'msgstr[' . $i . ']' ];
					}
				} else {
					$forms[] = $cur['msgstr'] ?? '';
				}
				$entries[] = array(
					'key'          => self::key( $cur['msgctxt'] ?? null, $cur['msgid'] ),
					'msgctxt'      => $cur['msgctxt'] ?? null,
					'msgid'        => $cur['msgid'],
					'msgid_plural' => $cur['msgid_plural'] ?? null,
					'forms'        => $forms,
					'fuzzy'        => (bool) ( $cur['fuzzy'] ?? false ),
				);
			}
			$cur   = array();
			$field = null;
		};

		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				$flush();
				continue;
			}
			if ( str_starts_with( $trimmed, '#' ) ) {
				if ( str_starts_with( $trimmed, '#,' ) && str_contains( $trimmed, 'fuzzy' ) ) {
					$cur['fuzzy'] = true;
				}
				continue;
			}
			if ( preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?)\s+"(.*)"$/s', $trimmed, $m ) ) {
				$field         = $m[1];
				$cur[ $field ] = ( $cur[ $field ] ?? '' ) . stripcslashes( $m[2] );
				continue;
			}
			if ( null !== $field && preg_match( '/^"(.*)"$/s', $trimmed, $m ) ) {
				$cur[ $field ] .= stripcslashes( $m[1] );
			}
		}
		$flush();

		return $entries;
	}

	/**
	 * Whether an entry carries a translation `msgfmt` would compile.
	 *
	 * @param array{forms: list<string>, fuzzy: bool} $entry Entry.
	 */
	public static function is_translated( array $entry ): bool {
		if ( $entry['fuzzy'] ) {
			return false;
		}
		foreach ( $entry['forms'] as $form ) {
			if ( '' !== $form ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The header entry's fields, names lower-cased as WordPress keys them.
	 *
	 * @param string $path Path to a `.po`.
	 * @return array<string, string>
	 */
	public static function headers( string $path ): array {
		$text = (string) file_get_contents( $path );
		if ( 1 !== preg_match( '/^msgid ""\s*\nmsgstr ((?:".*"\s*\n?)+)/m', $text, $m ) ) {
			return array();
		}
		preg_match_all( '/"(.*)"/', $m[1], $lines );
		$headers = array();
		foreach ( explode( "\n", stripcslashes( implode( '', $lines[1] ) ) ) as $line ) {
			if ( str_contains( $line, ':' ) ) {
				[ $name, $value ]                       = explode( ':', $line, 2 );
				$headers[ strtolower( trim( $name ) ) ] = trim( $value );
			}
		}
		return $headers;
	}

	/**
	 * The `.l10n.php` a `.po` compiles to, as file contents.
	 *
	 * Only translated, non-fuzzy entries are written, as `msgfmt` does for the
	 * `.mo`, and a plural's forms travel as one value separated by NUL, which
	 * is how WordPress reads them.
	 *
	 * @param string $path   Path to a `.po`.
	 * @param string $domain Text domain.
	 */
	public static function l10n( string $path, string $domain ): string {
		$headers = self::headers( $path );
		$pairs   = array(
			"'domain'=>" . var_export( $domain, true ),
			"'plural-forms'=>" . var_export( $headers['plural-forms'] ?? 'nplurals=2; plural=(n > 1);', true ),
			"'language'=>" . var_export( $headers['language'] ?? '', true ),
		);
		$items   = array();
		foreach ( self::read( $path ) as $entry ) {
			if ( self::is_translated( $entry ) ) {
				$items[] = var_export( $entry['key'], true ) . '=>' . var_export( implode( "\0", $entry['forms'] ), true );
			}
		}
		$pairs[] = "'messages'=>[" . implode( ',', $items ) . ']';

		return "<?php\nreturn [" . implode( ',', $pairs ) . '];';
	}
}
