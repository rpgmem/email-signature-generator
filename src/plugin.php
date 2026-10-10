<?php
/**
 * Plugin bootstrap.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Owns the plugin's identity (version, floors, paths) and wires its hooks.
 */
final class Plugin {

	/**
	 * Plugin version. Kept in step with the `Version:` header and the
	 * `Stable tag` in readme.txt.
	 */
	public const VERSION = '0.1.0';

	/**
	 * Minimum PHP version, as declared by the `Requires PHP:` header.
	 */
	public const REQUIRES_PHP = '8.3';

	/**
	 * Minimum WordPress version, as declared by the `Requires at least:` header.
	 */
	public const REQUIRES_WP = '6.8';

	/**
	 * Plugin slug: the folder, the main file's basename and the text domain.
	 */
	public const SLUG = 'email-signature-generator';

	/**
	 * Absolute path of the main plugin file, set by boot().
	 *
	 * @var string
	 */
	private static string $file = '';

	/**
	 * Wire the plugin. Called once from the main plugin file.
	 *
	 * @param string $file Absolute path of the main plugin file.
	 */
	public static function boot( string $file ): void {
		self::$file = $file;

		// `init` is the earliest hook where translations may load without a
		// "loaded too early" notice since WordPress 6.7; default priority.
		add_action( 'init', array( self::class, 'loadTextdomain' ) );
	}

	/**
	 * Load the bundled translations from languages/.
	 */
	public static function loadTextdomain(): void {
		load_plugin_textdomain( self::SLUG, false, dirname( plugin_basename( self::$file ) ) . '/languages' );
	}

	/**
	 * Absolute path of the main plugin file.
	 */
	public static function file(): string {
		return self::$file;
	}

	/**
	 * Absolute path of the plugin directory, with a trailing slash.
	 */
	public static function dir(): string {
		return plugin_dir_path( self::$file );
	}

	/**
	 * URL of the plugin directory, with a trailing slash.
	 */
	public static function url(): string {
		return plugin_dir_url( self::$file );
	}
}
