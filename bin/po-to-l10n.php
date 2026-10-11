<?php
/**
 * Compile a .po catalogue into the PHP format WordPress 6.5+ prefers.
 *
 *   php bin/po-to-l10n.php languages/email-signature-generator-pt_BR.po
 *
 * Writes the .l10n.php next to the .po. Only translated entries are written,
 * as msgfmt does for the .mo, so the two compiled catalogues agree.
 *
 * @package EmailSignatureGenerator\Build
 */

declare(strict_types=1);

$po = $argv[1] ?? '';
if ( '' === $po || ! is_readable( $po ) ) {
	fwrite( STDERR, "usage: php bin/po-to-l10n.php <file.po>\n" );
	exit( 1 );
}

/**
 * Decode a quoted .po string literal.
 *
 * @param string $literal The literal, quotes included.
 */
function esg_po_unquote( string $literal ): string {
	return stripcslashes( substr( $literal, 1, -1 ) );
}

$entries = array();
$entry   = array();
$field   = '';
foreach ( file( $po, FILE_IGNORE_NEW_LINES ) as $line ) {
	if ( '' === trim( $line ) ) {
		if ( $entry ) {
			$entries[] = $entry;
		}
		$entry = array();
		$field = '';
		continue;
	}
	if ( str_starts_with( $line, '#' ) ) {
		continue;
	}
	if ( preg_match( '/^(msgctxt|msgid|msgstr)\s+(".*")$/', $line, $m ) ) {
		$field           = $m[1];
		$entry[ $field ] = esg_po_unquote( $m[2] );
	} elseif ( '' !== $field && preg_match( '/^(".*")$/', $line, $m ) ) {
		$entry[ $field ] .= esg_po_unquote( $m[1] );
	}
}
if ( $entry ) {
	$entries[] = $entry;
}

$header   = array();
$messages = array();
foreach ( $entries as $e ) {
	if ( ! isset( $e['msgid'], $e['msgstr'] ) ) {
		continue;
	}
	if ( '' === $e['msgid'] ) {
		foreach ( explode( "\n", $e['msgstr'] ) as $h ) {
			if ( str_contains( $h, ':' ) ) {
				[ $k, $v ]                       = explode( ':', $h, 2 );
				$header[ strtolower( trim( $k ) ) ] = trim( $v );
			}
		}
		continue;
	}
	if ( '' === $e['msgstr'] ) {
		continue;
	}
	$key              = isset( $e['msgctxt'] ) ? $e['msgctxt'] . "\4" . $e['msgid'] : $e['msgid'];
	$messages[ $key ] = $e['msgstr'];
}

$out = array(
	'domain'       => 'email-signature-generator',
	'plural-forms' => $header['plural-forms'] ?? 'nplurals=2; plural=(n > 1);',
	'language'     => $header['language'] ?? '',
	'messages'     => $messages,
);

$target = preg_replace( '/\.po$/', '.l10n.php', $po );
file_put_contents( (string) $target, "<?php\nreturn " . var_export( $out, true ) . ";\n" );
echo $target . "\n";
