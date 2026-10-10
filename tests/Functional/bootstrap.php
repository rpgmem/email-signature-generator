<?php
/**
 * Bootstrap for the functional suite, which loads a real WordPress.
 *
 * Expects vendor/wp-phpunit/wp-phpunit/ and a wp-tests-config.php at the
 * project root, created by bin/install-wp-tests.sh.
 */

declare(strict_types=1);

$project_root = dirname( __DIR__, 2 );

require_once $project_root . '/vendor/autoload.php';

$tests_dir = $project_root . '/vendor/wp-phpunit/wp-phpunit';
$config    = $project_root . '/wp-tests-config.php';

if ( ! file_exists( $config ) ) {
	fwrite( STDERR, "wp-tests-config.php not found at $config: run bin/install-wp-tests.sh first.\n" );
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', $config );
}

require_once $tests_dir . '/includes/functions.php';

// Load the plugin as WordPress would, before its own hooks fire.
tests_add_filter(
	'muplugins_loaded',
	static function () use ( $project_root ): void {
		require_once $project_root . '/email-signature-generator.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
