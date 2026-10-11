<?php
/**
 * PHPUnit bootstrap for the unit suite: plain PHP with Brain Monkey, no
 * WordPress. The plugin's classes load through Composer's classmap; the main
 * plugin file is read as text by the tests that need it, never included,
 * because including it would call WordPress functions at load time.
 */

declare(strict_types=1);

// Patchwork must load before anything defines the functions Brain Monkey
// redefines, so it comes before the Composer autoloader.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// The main plugin file is never included here (see above), so its version
// constant is read from the file's text: one literal, never a copy.
if ( ! defined( 'EMAIL_SIGNATURE_GENERATOR_VERSION' ) ) {
	$esg_main = (string) file_get_contents( __DIR__ . '/../email-signature-generator.php' );
	if ( 1 !== preg_match( "/define\\( 'EMAIL_SIGNATURE_GENERATOR_VERSION', '([^']+)' \\);/", $esg_main, $esg_match ) ) {
		throw new RuntimeException( 'The main plugin file defines no EMAIL_SIGNATURE_GENERATOR_VERSION.' );
	}
	define( 'EMAIL_SIGNATURE_GENERATOR_VERSION', $esg_match[1] );
	unset( $esg_main, $esg_match );
}
