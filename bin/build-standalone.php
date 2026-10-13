<?php
/**
 * Build the standalone HTML generator.
 *
 *   php bin/build-standalone.php <version> <out-file> [locale] [logo-url]
 *
 * With `-` as <out-file> it prints only the configuration, as JSON: the JS
 * tests read the real configuration that way rather than a copy.
 *
 * The output is one self-contained HTML file: the form's stylesheet, the
 * vendored html2canvas, the generator script and the dark-mode switch are
 * inlined (with no setting to read, the page follows the operating system,
 * as the setting's "auto" does), and the
 * configuration is the plugin's own default (`Config::defaults()`),
 * translated into <locale> (default pt_BR) from the bundled catalogue.
 * So the standalone file and the plugin cannot drift apart.
 *
 * The signature links to its logo by URL, because an email client does not
 * keep an embedded image. By default that is the bundled logo at the release
 * tag on GitHub, which stays put for as long as the tag exists.
 *
 * Runs without WordPress: the few functions Config needs are shimmed below.
 *
 * @package EmailSignatureGenerator\Build
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$esg_version = $argv[1] ?? '';
$esg_out     = $argv[2] ?? '';
$esg_locale  = $argv[3] ?? 'pt_BR';
$esg_root    = dirname( __DIR__ );

if ( '' === $esg_version || '' === $esg_out ) {
	fwrite( STDERR, "usage: php bin/build-standalone.php <version> <out-file> [locale] [logo-url]\n" );
	exit( 1 );
}

$esg_logo = $argv[4] ?? 'https://raw.githubusercontent.com/rpgmem/email-signature-generator/v' . $esg_version . '/assets/img/default-logo.png';

// The translations, from the same catalogue WordPress reads.
$esg_catalogue = $esg_root . '/languages/email-signature-generator-' . $esg_locale . '.l10n.php';
$GLOBALS['esg_messages'] = array();
if ( 'en_US' !== $esg_locale ) {
	if ( ! is_readable( $esg_catalogue ) ) {
		fwrite( STDERR, "No catalogue for locale {$esg_locale}: {$esg_catalogue}\n" );
		exit( 1 );
	}
	$esg_l10n                = require $esg_catalogue;
	$GLOBALS['esg_messages'] = is_array( $esg_l10n['messages'] ?? null ) ? $esg_l10n['messages'] : array();
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translate a string from the loaded catalogue.
	 *
	 * @param string $text   Source string.
	 * @param string $domain Text domain (unused).
	 */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		$translated = $GLOBALS['esg_messages'][ $text ] ?? '';
		return '' !== $translated ? $translated : $text;
	}
}

define( 'ABSPATH', $esg_root . '/' );
require $esg_root . '/src/plugin.php';
require $esg_root . '/src/regions.php';
require $esg_root . '/src/config.php';
require $esg_root . '/src/shortcode.php';
require $esg_root . '/src/settings.php';
require $esg_root . '/src/theme.php';

$esg_config = \EmailSignatureGenerator\Config::defaults( $esg_logo );

if ( '-' === $esg_out ) {
	echo json_encode( $esg_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), "\n";
	exit( 0 );
}

/**
 * Read a file the build inlines, or stop.
 *
 * @param string $path Absolute path.
 */
function esg_read( string $path ): string {
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		fwrite( STDERR, "Cannot read {$path}\n" );
		exit( 1 );
	}
	return $contents;
}

/**
 * Make text safe inside an inline <script>: a closing tag would end it.
 *
 * @param string $js Script source.
 */
function esg_inline_script( string $js ): string {
	return str_ireplace( '</script', '<\/script', $js );
}

$esg_css   = esg_read( $esg_root . '/assets/css/signature-generator.css' );
$esg_h2c   = esg_read( $esg_root . '/assets/vendor/html2canvas-' . \EmailSignatureGenerator\Shortcode::HTML2CANVAS_VERSION . '.min.js' );
$esg_js    = esg_read( $esg_root . '/assets/js/signature-generator.js' );
$esg_json  = json_encode( $esg_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
$esg_lang  = str_replace( '_', '-', $esg_locale );
$esg_title = __( 'Email Signature Generator', 'email-signature-generator' );
$esg_e     = static fn( string $s ): string => htmlspecialchars( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

$esg_html = '<!DOCTYPE html>
<html lang="' . $esg_e( $esg_lang ) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="generator" content="Email Signature Generator ' . $esg_e( $esg_version ) . '">
<title>' . $esg_e( $esg_title ) . '</title>
<script>
' . esg_inline_script( \EmailSignatureGenerator\Theme::script( 'auto' ) ) . '
</script>
<style>
body { margin: 0; padding: 16px; background: #f0f0f1; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
:root.' . \EmailSignatureGenerator\Theme::CSS_CLASS . ' { color-scheme: dark; }
:root.' . \EmailSignatureGenerator\Theme::CSS_CLASS . ' body { background: #1d2327; color: #e7e8ea; }
' . $esg_css . '
</style>
</head>
<body>
<!-- Email Signature Generator ' . $esg_e( $esg_version ) . ', standalone build. https://github.com/rpgmem/email-signature-generator -->
<div class="esg-generator" data-esg-config="' . $esg_e( $esg_json ) . '"><noscript>' . $esg_e( __( 'The signature generator needs JavaScript.', 'email-signature-generator' ) ) . '</noscript></div>
<script>
' . esg_inline_script( $esg_h2c ) . '
</script>
<script>
' . esg_inline_script( $esg_js ) . '
</script>
</body>
</html>
';

if ( false === file_put_contents( $esg_out, $esg_html ) ) {
	fwrite( STDERR, "Cannot write {$esg_out}\n" );
	exit( 1 );
}
echo $esg_out . "\n";
