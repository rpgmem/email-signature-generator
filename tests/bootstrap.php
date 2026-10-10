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
