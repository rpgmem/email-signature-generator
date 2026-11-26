<?php
/**
 * The dark-mode switch, shared by the settings screen and the form.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Puts `esg-dark-mode` on <html> according to the `dark_mode` setting.
 *
 * One inline script with no file behind it, printed by the settings screen in
 * the head and by the shortcode before the generator script, and inlined
 * into the standalone HTML. The stylesheets key every dark rule on the class,
 * never on `prefers-color-scheme`, so the setting stays the one source of
 * truth: the operating system is asked only when the setting is "auto".
 *
 * When Free Form Certificate is active, its own Dark Mode setting decides
 * instead (#106), so an operator sets it in one place and the two plugins
 * never disagree on one site. It is detected at run time and never required:
 * without it, this plugin's setting applies as before. The standalone HTML has
 * no WordPress and no setting, so it always follows the operating system.
 *
 * Inside Site PWA's app the person can turn the app dark on their own device
 * (site-pwa#23). The app then marks <html> with `data-site-pwa-scheme="dark"`
 * and adds the classes plugins offered through `site_pwa_dark_root_classes`,
 * before the first paint. This plugin offers its class there, and the script
 * keeps the form dark while that mark is present, whatever the setting says.
 */
final class Theme {

	public const HANDLE = 'email-signature-generator-theme';

	public const CSS_CLASS = 'esg-dark-mode';

	/**
	 * Site PWA's filter for the classes its app adds to <html> when the
	 * person turns it dark.
	 */
	public const APP_ROOT_CLASSES_FILTER = 'site_pwa_dark_root_classes';

	/**
	 * Free Form Certificate's settings option, read only, never written.
	 */
	public const FFC_OPTION = 'ffc_settings';

	/**
	 * Offer the class to Site PWA's app. Without the app the filter never
	 * fires.
	 */
	public static function register(): void {
		add_filter( self::APP_ROOT_CLASSES_FILTER, array( self::class, 'appRootClasses' ) );
	}

	/**
	 * Add this plugin's dark class to the app's list, keeping what other
	 * plugins offered.
	 *
	 * @param mixed $classes The list so far.
	 * @return mixed
	 */
	public static function appRootClasses( $classes ) {
		if ( ! is_array( $classes ) ) {
			return $classes;
		}
		$classes[] = self::CSS_CLASS;
		return $classes;
	}

	/**
	 * Whether Free Form Certificate is active, so its Dark Mode decides.
	 * Its main file defines `FFC_VERSION` as it loads, before any hook this
	 * plugin reads the theme on.
	 */
	public static function followsFfc(): bool {
		return defined( 'FFC_VERSION' );
	}

	/**
	 * Free Form Certificate's Dark Mode: its stored `dark_mode`, or "off"
	 * (its own default) when that is missing or not a known value.
	 */
	public static function ffcMode(): string {
		$settings = get_option( self::FFC_OPTION, array() );
		$mode     = is_array( $settings ) ? ( $settings['dark_mode'] ?? 'off' ) : 'off';
		return is_string( $mode ) && in_array( $mode, Settings::DARK_MODES, true ) ? $mode : 'off';
	}

	/**
	 * The mode that applies: Free Form Certificate's when it is active, this
	 * plugin's own otherwise.
	 */
	public static function mode(): string {
		return self::followsFfc() ? self::ffcMode() : (string) Settings::get()['dark_mode'];
	}

	/**
	 * Enqueue the script with the mode that applies.
	 *
	 * @param bool $in_footer False on the settings screen, where the class
	 *                        must land before the page paints; true for the
	 *                        shortcode, which renders after the head and whose
	 *                        form is drawn by a footer script anyway.
	 */
	public static function enqueue( bool $in_footer ): void {
		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}
		wp_register_script( self::HANDLE, false, array(), Plugin::VERSION, array( 'in_footer' => $in_footer ) );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, self::script( self::mode() ) );
	}

	/**
	 * The script that puts the class on <html>.
	 *
	 * "on" and "off" are decided here; only "auto" asks the operating system,
	 * and it keeps listening, so switching the system theme repaints the open
	 * page. A saved change arrives as `esg:setting-saved` and repaints too.
	 * The app's own dark mark on <html> wins over "off", so a footer script
	 * never undoes the dark the app set before the first paint.
	 * An unknown value is treated as "off", so only a known word is printed.
	 *
	 * @param string $mode The mode that applies.
	 */
	public static function script( string $mode ): string {
		if ( ! in_array( $mode, Settings::DARK_MODES, true ) ) {
			$mode = 'off';
		}
		return '(function(mode){'
			. 'var root=document.documentElement;'
			. 'var query=window.matchMedia?window.matchMedia("(prefers-color-scheme: dark)"):null;'
			. 'function apply(){root.classList.toggle("' . self::CSS_CLASS . '",root.getAttribute("data-site-pwa-scheme")==="dark"||mode==="on"||(mode==="auto"&&!!query&&query.matches));}'
			. 'apply();'
			. 'if(query&&query.addEventListener){query.addEventListener("change",apply);}'
			. 'document.addEventListener("esg:setting-saved",function(e){if(e.detail&&e.detail.key==="dark_mode"){mode=String(e.detail.value);apply();}});'
			. '})("' . $mode . '");';
	}
}
