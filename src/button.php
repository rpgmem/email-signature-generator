<?php
/**
 * One button family, by what the action does.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Markup of an `.esg-btn` button: a slim copy of Free Form Certificate's
 * `Core\Button` (#106), under this plugin's prefix, so the two plugins'
 * buttons look and behave alike without either needing the other.
 *
 * The category names what the action does — `primary` completes the work,
 * `neutral` opens, picks or copies, `create` adds something, `caution` undoes
 * or resets without losing data, `danger` deletes or empties — and
 * `assets/css/buttons.css` draws each one in both themes. The icon is an
 * {@see Icons} class, drawn as a mask on `::before`, so a script that
 * rewrites the label keeps it. The form's script builds the same markup for
 * the buttons it draws.
 *
 * An icon-only button prints no text: the label becomes its `aria-label` and
 * `title`, so it keeps a name a screen reader announces.
 */
final class Button {

	/**
	 * The categories.
	 *
	 * @var list<string>
	 */
	public const CATEGORIES = array( 'primary', 'neutral', 'create', 'caution', 'danger' );

	/**
	 * What an unknown category falls back to: it says nothing about the action.
	 */
	public const FALLBACK = 'neutral';

	/**
	 * The stylesheet's handle. The settings screen and the form's both
	 * depend on it, so it loads once and before either.
	 */
	public const STYLE_HANDLE = 'email-signature-generator-buttons';

	/**
	 * The stylesheet's source, under the plugin directory.
	 */
	public const STYLE = 'assets/css/buttons.css';

	/**
	 * Register the stylesheet, for the screen's and the form's sheets to
	 * name as a dependency.
	 */
	public static function registerStyle(): void {
		if ( wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			return;
		}
		wp_register_style( self::STYLE_HANDLE, Plugin::url() . Plugin::asset( self::STYLE ), array(), Plugin::assetVersion( Plugin::asset( self::STYLE ) ) );
	}

	/**
	 * The markup.
	 *
	 * Arguments: `label` (required); `category` (default `neutral`); `icon`
	 * (an {@see Icons::CLASSES} name); `type` (`button`, `submit` or `reset`,
	 * default `button`); `id`; `name`; `class` (extra classes, the hooks the
	 * scripts read); `icon_only`; `attrs` (further attributes such as
	 * `data-*`: a scalar value is escaped, `true` prints the bare attribute,
	 * and an `on*` handler is refused). An unknown category is drawn as
	 * {@see self::FALLBACK}; an unknown icon is dropped; an icon-only button
	 * without a label or a known icon prints nothing.
	 *
	 * @param array<string, mixed> $args See above.
	 */
	public static function html( array $args ): string {
		$label     = (string) ( $args['label'] ?? '' );
		$icon      = (string) ( $args['icon'] ?? '' );
		$icon_only = ! empty( $args['icon_only'] );
		$known     = '' !== $icon && Icons::isClass( $icon );
		if ( '' === $label || ( $icon_only && ! $known ) ) {
			return '';
		}

		$category = (string) ( $args['category'] ?? self::FALLBACK );
		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			$category = self::FALLBACK;
		}

		$classes = array( 'esg-btn', 'esg-btn-' . $category );
		if ( $known ) {
			$classes[] = 'esg-icon-' . $icon;
		}
		if ( $icon_only ) {
			$classes[] = 'esg-btn-icon';
		}
		$extra = preg_split( '/\s+/', trim( (string) ( $args['class'] ?? '' ) ) );
		foreach ( false === $extra ? array() : $extra as $one ) {
			if ( '' !== $one ) {
				$classes[] = $one;
			}
		}

		$type  = (string) ( $args['type'] ?? 'button' );
		$attrs = array(
			'type'  => in_array( $type, array( 'button', 'submit', 'reset' ), true ) ? $type : 'button',
			'class' => implode( ' ', array_unique( $classes ) ),
		);
		foreach ( array( 'id', 'name' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
				$attrs[ $key ] = (string) $args[ $key ];
			}
		}
		if ( $icon_only ) {
			$attrs['aria-label'] = $label;
			$attrs['title']      = $label;
		}
		foreach ( is_array( $args['attrs'] ?? null ) ? $args['attrs'] : array() as $key => $value ) {
			$key = strtolower( (string) $key );
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_:.-]*$/', $key ) || str_starts_with( $key, 'on' ) || isset( $attrs[ $key ] ) ) {
				continue;
			}
			if ( true === $value ) {
				$attrs[ $key ] = true;
			} elseif ( is_scalar( $value ) && false !== $value ) {
				$attrs[ $key ] = (string) $value;
			}
		}

		$printed = '';
		foreach ( $attrs as $key => $value ) {
			$printed .= true === $value ? ' ' . $key : ' ' . $key . '="' . esc_attr( $value ) . '"';
		}

		return '<button' . $printed . '>' . ( $icon_only ? '' : esc_html( $label ) ) . '</button>';
	}
}
