<?php
/**
 * The [email_signature] shortcode.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Places the generator on a page.
 *
 * The shortcode prints an empty container that carries the configuration;
 * the script draws the form inside it. What a person types stays in their
 * browser, so the plugin never receives or stores it.
 */
final class Shortcode {

	public const TAG = 'email_signature';

	public const SCRIPT_HANDLE = 'email-signature-generator';

	/**
	 * Google's tag, enqueued only when Analytics is on.
	 */
	public const GTAG_HANDLE = 'email-signature-generator-gtag';

	public const STYLE_HANDLE = 'email-signature-generator';

	public const HTML2CANVAS_HANDLE = 'email-signature-generator-html2canvas';

	/**
	 * The vendored html2canvas release. The file name carries it, so bumping
	 * the constant without renaming the file 404s instead of serving the old
	 * bundle under a new cache key.
	 */
	public const HTML2CANVAS_VERSION = '1.4.1';

	/**
	 * Hook the shortcode.
	 */
	public static function register(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * Attribute `models`: a comma-separated list of model ids to offer, in
	 * that order, among those the settings enable. Unknown ids are ignored;
	 * when none is known, every enabled model is offered.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string The container markup.
	 */
	public static function render( $atts = array() ): string {
		$atts = shortcode_atts( array( 'models' => '' ), is_array( $atts ) ? $atts : array(), self::TAG );

		if ( Settings::ACCESS_LOGGED_IN === Settings::get()['access'] && ! is_user_logged_in() ) {
			return sprintf(
				'<p class="esg-login-required">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Log in to create your email signature.', 'email-signature-generator' ),
				esc_url( wp_login_url( (string) get_permalink() ) ),
				esc_html__( 'Log in', 'email-signature-generator' )
			);
		}

		$config = self::restrictModels( Settings::siteConfig(), (string) $atts['models'] );

		self::enqueue( Settings::get() );

		// The width is the page's, not the signature's, so it is not in the
		// configuration. It is inline because a block theme's constrained
		// layout sets max-width on every child of the content at the same
		// specificity as the stylesheet, and loads later.
		// The Design tab's colours, corners and font ride on the same
		// attribute, as custom properties the stylesheet reads.
		$settings = Settings::get();
		$vars     = Settings::styleVars( $settings );
		return sprintf(
			'<div class="esg-generator" style="%3$s" data-esg-config="%1$s"><noscript>%2$s</noscript></div>',
			esc_attr( (string) wp_json_encode( $config ) ),
			esc_html__( 'The signature generator needs JavaScript.', 'email-signature-generator' ),
			esc_attr( 'max-width:' . (int) $settings['form_width'] . 'px' . ( '' !== $vars ? '; ' . $vars : '' ) )
		);
	}

	/**
	 * Keep only the requested models, in the requested order.
	 *
	 * @param array<string, mixed> $config The configuration.
	 * @param string               $ids    Comma-separated model ids.
	 * @return array<string, mixed> The configuration with the models filtered.
	 */
	public static function restrictModels( array $config, string $ids ): array {
		$wanted = array_filter( array_map( 'trim', explode( ',', $ids ) ) );
		if ( array() === $wanted ) {
			return $config;
		}

		$by_id = array();
		foreach ( $config['models'] as $model ) {
			$by_id[ $model['id'] ] = $model;
		}

		$models = array();
		foreach ( $wanted as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$models[] = $by_id[ $id ];
			}
		}

		if ( array() !== $models ) {
			$config['models'] = $models;
		}
		return $config;
	}

	/**
	 * Load the script, its image library and the form's stylesheet. Called
	 * from render(), so they load only on pages that show the generator;
	 * Google's tag too when the settings turn Analytics on, and nothing of
	 * Google's otherwise.
	 *
	 * @param array<string, mixed> $settings Sanitized settings.
	 */
	private static function enqueue( array $settings ): void {
		wp_enqueue_style( self::STYLE_HANDLE, Plugin::url() . 'assets/css/signature-generator.css', array(), Plugin::assetVersion( 'assets/css/signature-generator.css' ) );
		wp_enqueue_script(
			self::HTML2CANVAS_HANDLE,
			Plugin::url() . 'assets/vendor/html2canvas-' . self::HTML2CANVAS_VERSION . '.min.js',
			array(),
			self::HTML2CANVAS_VERSION,
			array( 'in_footer' => true )
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			Plugin::url() . 'assets/js/signature-generator.js',
			array( self::HTML2CANVAS_HANDLE ),
			Plugin::assetVersion( 'assets/js/signature-generator.js' ),
			array( 'in_footer' => true )
		);

		if ( ! Settings::analyticsOn( $settings ) ) {
			return;
		}
		$id = (string) $settings['analytics_id'];
		// Google's own loader, with no version argument: the URL is Google's.
		wp_enqueue_script( self::GTAG_HANDLE, 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ), array(), null, array( 'strategy' => 'async' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- a third-party URL that versions itself.
		wp_add_inline_script(
			self::GTAG_HANDLE,
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . (string) wp_json_encode( $id ) . ');'
		);
	}
}
