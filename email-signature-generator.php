<?php
/**
 * Plugin Name:       Email Signature Generator
 * Plugin URI:        https://github.com/rpgmem/email-signature-generator
 * Description:       A form, placed with a shortcode, that builds a consistent HTML email signature for your organisation and copies it to Outlook or downloads it as an image.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Author:            Alex Meusburger
 * Author URI:        https://github.com/rpgmem
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       email-signature-generator
 * Domain Path:       /languages
 *
 * @package EmailSignatureGenerator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Inline autoloader for the EmailSignatureGenerator\ namespace, so the plugin
 * ships no Composer runtime. A class maps to a kebab-case file under src/:
 *
 *   EmailSignatureGenerator\Plugin             -> src/plugin.php
 *   EmailSignatureGenerator\Admin\SettingsPage -> src/admin/settings-page.php
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'EmailSignatureGenerator\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$parts    = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$basename = array_pop( $parts );
		$file     = strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $basename ) );
		$dir      = $parts ? implode( '/', array_map( 'strtolower', $parts ) ) . '/' : '';
		$path     = __DIR__ . '/src/' . $dir . $file . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

\EmailSignatureGenerator\Plugin::boot( __FILE__ );
