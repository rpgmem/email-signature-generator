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
	 * The headers `wp i18n make-php` writes after `domain`, in its order.
	 *
	 * Writing the same keys in the same order is what makes a catalogue
	 * compiled here, one saved from Loco and one made by WP-CLI the same file.
	 */
	private const L10N_HEADERS = array( 'plural-forms', 'language', 'project-id-version', 'pot-creation-date', 'po-revision-date', 'x-generator' );

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
		/** @var array<string, string> $fields */
		$fields = array();
		$fuzzy  = false;
		$field  = null;

		foreach ( explode( "\n", self::contents( $path ) ) as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				self::push( $entries, $fields, $fuzzy );
				$fields = array();
				$fuzzy  = false;
				$field  = null;
				continue;
			}
			if ( str_starts_with( $trimmed, '#' ) ) {
				if ( str_starts_with( $trimmed, '#,' ) && str_contains( $trimmed, 'fuzzy' ) ) {
					$fuzzy = true;
				}
				continue;
			}
			if ( 1 === preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?)\s+"(.*)"$/s', $trimmed, $m ) ) {
				$field            = $m[1];
				$fields[ $field ] = ( $fields[ $field ] ?? '' ) . stripcslashes( $m[2] );
				continue;
			}
			if ( null !== $field && 1 === preg_match( '/^"(.*)"$/s', $trimmed, $m ) ) {
				$fields[ $field ] = ( $fields[ $field ] ?? '' ) . stripcslashes( $m[1] );
			}
		}
		self::push( $entries, $fields, $fuzzy );

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
		if ( 1 !== preg_match( '/^msgid ""\s*\nmsgstr ((?:".*"\s*\n?)+)/m', self::contents( $path ), $m ) ) {
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
	 * The shape is `wp i18n make-php`'s, byte for byte: `domain`, then the
	 * headers in `L10N_HEADERS` the `.po` carries, then `messages` in the
	 * `.po`'s order, on one line. Only translated, non-fuzzy entries are
	 * written, as `msgfmt` does for the `.mo`, and a plural's forms travel as
	 * one value separated by NUL, which is how WordPress reads them.
	 *
	 * @param string $path   Path to a `.po`.
	 * @param string $domain Text domain. WP-CLI reads it from the `X-Domain` header.
	 */
	public static function l10n( string $path, string $domain ): string {
		$headers = self::headers( $path );
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export writes the PHP literals of a generated catalogue, not debug output.
		$pairs = array( "'domain'=>" . var_export( $domain, true ) );
		foreach ( self::L10N_HEADERS as $name ) {
			if ( isset( $headers[ $name ] ) ) {
				$pairs[] = var_export( $name, true ) . '=>' . var_export( $headers[ $name ], true );
			}
		}
		$items = array();
		foreach ( self::read( $path ) as $entry ) {
			if ( self::is_translated( $entry ) ) {
				$items[] = var_export( self::array_key( $entry['key'] ), true ) . '=>' . var_export( implode( "\0", $entry['forms'] ), true );
			}
		}
		// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$pairs[] = "'messages'=>[" . implode( ',', $items ) . ']';

		return "<?php\nreturn [" . implode( ',', $pairs ) . '];';
	}

	/**
	 * The key as PHP stores it: an integer-like string becomes an int.
	 *
	 * WP-CLI writes `302=>'302'`, not `'302'=>'302'`, because PHP turns a
	 * decimal key into an int, so writing it quoted breaks the byte-for-byte
	 * match with `wp i18n make-php` on the first such msgid.
	 *
	 * @param string $key Entry key.
	 * @return int|string
	 */
	private static function array_key( string $key ): int|string {
		return (string) (int) $key === $key ? (int) $key : $key;
	}

	/**
	 * Appends the entry being read, unless it is the header or empty.
	 *
	 * @param list<array{key: string, msgctxt: string|null, msgid: string, msgid_plural: string|null, forms: list<string>, fuzzy: bool}> $entries Entries so far.
	 * @param array<string, string>                                                                                                      $fields  The entry's fields.
	 * @param bool                                                                                                                       $fuzzy   Whether `#, fuzzy` preceded it.
	 */
	private static function push( array &$entries, array $fields, bool $fuzzy ): void {
		if ( ! isset( $fields['msgid'] ) || '' === $fields['msgid'] ) {
			return;
		}
		$forms = array();
		if ( isset( $fields['msgid_plural'] ) ) {
			for ( $i = 0; isset( $fields[ 'msgstr[' . $i . ']' ] ); $i++ ) {
				$forms[] = $fields[ 'msgstr[' . $i . ']' ];
			}
		} else {
			$forms[] = $fields['msgstr'] ?? '';
		}
		$entries[] = array(
			'key'          => self::key( $fields['msgctxt'] ?? null, $fields['msgid'] ),
			'msgctxt'      => $fields['msgctxt'] ?? null,
			'msgid'        => $fields['msgid'],
			'msgid_plural' => $fields['msgid_plural'] ?? null,
			'forms'        => $forms,
			'fuzzy'        => $fuzzy,
		);
	}

	/**
	 * A catalogue's text.
	 *
	 * @param string $path Path to a local `.po` or `.pot`.
	 */
	private static function contents( string $path ): string {
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local catalogue, never a URL.
	}
}
