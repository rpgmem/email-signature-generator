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
 */
final class Theme {

	public const HANDLE = 'email-signature-generator-theme';

	public const CSS_CLASS = 'esg-dark-mode';

	/**
	 * Enqueue the script with the stored setting.
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
		wp_add_inline_script( self::HANDLE, self::script( (string) Settings::get()['dark_mode'] ) );
	}

	/**
	 * The script that puts the class on <html>.
	 *
	 * "on" and "off" are decided here; only "auto" asks the operating system,
	 * and it keeps listening, so switching the system theme repaints the open
	 * page. A saved change arrives as `esg:setting-saved` and repaints too.
	 * An unknown value is treated as "off", so only a known word is printed.
	 *
	 * @param string $mode The stored `dark_mode` setting.
	 */
	public static function script( string $mode ): string {
		if ( ! in_array( $mode, Settings::DARK_MODES, true ) ) {
			$mode = 'off';
		}
		return '(function(mode){'
			. 'var root=document.documentElement;'
			. 'var query=window.matchMedia?window.matchMedia("(prefers-color-scheme: dark)"):null;'
			. 'function apply(){root.classList.toggle("' . self::CSS_CLASS . '",mode==="on"||(mode==="auto"&&!!query&&query.matches));}'
			. 'apply();'
			. 'if(query&&query.addEventListener){query.addEventListener("change",apply);}'
			. 'document.addEventListener("esg:setting-saved",function(e){if(e.detail&&e.detail.key==="dark_mode"){mode=String(e.detail.value);apply();}});'
			. '})("' . $mode . '");';
	}
}
