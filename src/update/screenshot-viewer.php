<?php
/**
 * Screenshot viewer in the "View details" modal.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Update;

use EmailSignatureGenerator\Plugin;

/**
 * Loads the viewer that opens the modal's screenshots in an overlay instead
 * of a new tab (`assets/js/screenshots.js`).
 *
 * The modal is core's `plugin-install.php?tab=plugin-information` page, shown
 * in an iframe, and it fires `admin_enqueue_scripts` like any admin screen.
 * Its sections pass through kses, which strips scripts, so the viewer has to
 * arrive as an enqueued file. It loads only on that page and only for this
 * plugin's slug, so no other plugin's modal and no other screen carries it.
 */
final class ScreenshotViewer {

	/**
	 * Script and style handle.
	 */
	public const HANDLE = 'email-signature-generator-screenshots';

	/**
	 * Register the enqueue hook.
	 *
	 * Default priority: nothing else on that page depends on the order.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue the viewer on this plugin's "View details" modal.
	 *
	 * @param string $hook_suffix The admin page being loaded.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! self::isOurModal( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, Plugin::url() . 'assets/css/screenshots.css', array(), Plugin::assetVersion( 'assets/css/screenshots.css' ) );
		wp_enqueue_script( self::HANDLE, Plugin::url() . 'assets/js/screenshots.js', array(), Plugin::assetVersion( 'assets/js/screenshots.js' ), array( 'in_footer' => true ) );
		wp_localize_script(
			self::HANDLE,
			'esgScreenshots',
			array(
				'dialog'   => __( 'Screenshots', 'email-signature-generator' ),
				'close'    => __( 'Close', 'email-signature-generator' ),
				'previous' => __( 'Previous screenshot', 'email-signature-generator' ),
				'next'     => __( 'Next screenshot', 'email-signature-generator' ),
				'zoomIn'   => __( 'Zoom in', 'email-signature-generator' ),
				'zoomOut'  => __( 'Zoom out', 'email-signature-generator' ),
				/* translators: %1$s: number of the screenshot shown, %2$s: number of screenshots */
				'counter'  => __( '%1$s of %2$s', 'email-signature-generator' ),
			)
		);
	}

	/**
	 * Is this the "View details" modal for this plugin?
	 *
	 * A read-only check of the page's own query arguments: core's modal link
	 * carries no nonce, and nothing is changed here beyond loading two files.
	 *
	 * @param string $hook_suffix The admin page being loaded.
	 */
	private static function isOurModal( string $hook_suffix ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing on core's own modal URL.
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$plugin = isset( $_GET['plugin'] ) ? sanitize_key( wp_unslash( $_GET['plugin'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'plugin-install.php' === $hook_suffix && 'plugin-information' === $tab && GithubUpdater::SLUG === $plugin;
	}
}
