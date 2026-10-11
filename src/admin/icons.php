<?php
/**
 * The icons of the settings screen.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Admin;

/**
 * Line icons drawn in the text colour, so they follow the admin colour
 * scheme. The drawings are Free Form Certificate's, so the two plugins look
 * alike: 24×24, a stroke in `currentColor`, and only geometry, never a
 * colour, a style or a script of their own.
 */
final class Icons {

	/**
	 * Name => the drawing inside the `<svg>`.
	 */
	private const PATHS = array(
		'settings' => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9L7 7M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
		'email'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
		'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'building' => '<path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 9h4a1 1 0 0 1 1 1v11M3 21h18"/><path d="M8 8h3M8 12h3M8 16h3"/>',
		'layout'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>',
		'list'     => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1" fill="currentColor" stroke="none"/>',
		'book'     => '<path d="M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h8"/>',
		'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><circle cx="12" cy="7.5" r="1.1" fill="currentColor" stroke="none"/>',
		'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
		'brush'    => '<path d="M14 4l6 6-8 8-6-6z"/><path d="M6 12l-3 7 7-3"/>',
		'chart'    => '<path d="M3 20h18M6 20v-6M11 20V8M16 20v-9M21 20V4"/>',
		'sync'     => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>',
	);

	/**
	 * Whether an icon exists.
	 *
	 * @param string $name Icon name.
	 */
	public static function has( string $name ): bool {
		return isset( self::PATHS[ $name ] );
	}

	/**
	 * Every icon name.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		return array_keys( self::PATHS );
	}

	/**
	 * The icon as inline SVG, hidden from assistive technology because the
	 * text beside it says the same; '' for an unknown name.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Width and height in pixels.
	 */
	public static function svg( string $name, int $size = 20 ): string {
		if ( ! self::has( $name ) ) {
			return '';
		}
		return sprintf(
			'<svg class="esg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			max( 1, $size ),
			self::PATHS[ $name ]
		);
	}
}
