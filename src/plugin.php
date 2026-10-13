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
	 * Plugin version, defined in the main plugin file beside the `Version:`
	 * header.
	 */
	public const VERSION = \EMAIL_SIGNATURE_GENERATOR_VERSION;

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

		// Shortcodes are registered on `init`, after the translations above
		// (both at the default priority, so registration order decides).
		add_action( 'init', array( Shortcode::class, 'register' ) );

		if ( is_admin() ) {
			Admin\SettingsPage::register();
			Admin\Autosave::register();
		}

		// Not behind is_admin(): WordPress also scans for updates from cron.
		Update\GithubUpdater::register();

		// Screenshot viewer in that update's "View details" modal.
		Update\ScreenshotViewer::register();
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

	/**
	 * The `?ver=` of one of the plugin's own assets: the plugin version plus
	 * the file's modification time.
	 *
	 * The version alone does not change between deploys of the same release,
	 * and hosts serve these files with a year-long cache, so a browser kept
	 * the previous script after an update. The file time changes whenever
	 * the file does.
	 *
	 * @param string $relative Path under the plugin directory, e.g. `assets/js/x.js`.
	 */
	public static function assetVersion( string $relative ): string {
		$time = @filemtime( self::dir() . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file falls back to the version.
		return false === $time ? self::VERSION : self::VERSION . '.' . $time;
	}
}
