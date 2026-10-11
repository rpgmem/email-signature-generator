<?php
/**
 * Saves a settings toggle the moment it is flipped.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Admin;

use EmailSignatureGenerator\Config;
use EmailSignatureGenerator\Settings;
use WP_Error;

/**
 * The AJAX half of the settings screen's toggles.
 *
 * Every toggle is also a named field of the screen's form, so "Save Changes"
 * posts its current state and never undoes what a toggle already saved. Only
 * toggles save here; text fields still wait for the form.
 */
final class Autosave {

	public const ACTION = 'email_signature_generator_autosave';

	/**
	 * Hook the handler. Logged-in only: there is no `nopriv` variant.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * The AJAX request: check the nonce and the capability, apply, store.
	 */
	public static function handle(): void {
		if ( false === check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The page expired. Reload it and try again.', 'email-signature-generator' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'email-signature-generator' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by check_ajax_referer() above.
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$value = isset( $_POST['value'] ) ? map_deep( wp_unslash( $_POST['value'] ), 'sanitize_text_field' ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$settings = self::apply( Settings::get(), $key, $value );
		if ( $settings instanceof WP_Error ) {
			wp_send_json_error( array( 'message' => $settings->get_error_message() ), 400 );
		}

		update_option( Settings::OPTION, $settings );
		wp_send_json_success( array( 'value' => self::read( Settings::get(), $key ) ) );
	}

	/**
	 * Apply one toggle to the settings.
	 *
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param string               $key      `uppercase_name`, `offer_download`, `restrict_email_domain`, `models`, or `fields.<model>.<field>.<required|locked>`.
	 * @param mixed                $value    '1' / '0', or the list of model ids for `models`.
	 * @return array<string, mixed>|WP_Error The sanitized settings, or why the change was refused.
	 */
	public static function apply( array $settings, string $key, $value ) {
		if ( in_array( $key, array( 'uppercase_name', 'offer_download', 'restrict_email_domain' ), true ) ) {
			$settings[ $key ] = self::on( $value );
			return Settings::sanitize( $settings );
		}

		if ( 'models' === $key ) {
			$settings['models'] = array_map( 'strval', is_array( $value ) ? $value : array() );
			return Settings::sanitize( $settings );
		}

		$parts = explode( '.', $key );
		$field = 4 === count( $parts ) && 'fields' === $parts[0] ? self::field( $parts[1], $parts[2] ) : null;
		if ( null === $field || ! in_array( $parts[3], array( 'required', 'locked' ), true ) ) {
			return new WP_Error( 'unknown_key', __( 'This setting cannot be saved on its own. Use "Save Changes".', 'email-signature-generator' ) );
		}
		list( , $model, $name, $flag ) = $parts;

		if ( 'required' === $flag && Settings::alwaysRequired( $name ) ) {
			return new WP_Error( 'always_required', __( 'This field is always required.', 'email-signature-generator' ) );
		}
		if ( 'locked' === $flag && ! Settings::lockable( $name ) ) {
			return new WP_Error( 'not_lockable', __( 'The name and the email cannot be locked.', 'email-signature-generator' ) );
		}

		// A field never saved before starts from its built-in values, or
		// locking the link would also blank its default.
		$override = $settings['fields'][ $model ][ $name ] ?? array(
			'default'  => (string) ( $field['default'] ?? '' ),
			'required' => ! empty( $field['required'] ),
			'locked'   => false,
		);
		$on       = self::on( $value );

		if ( 'locked' === $flag && $on && '' === (string) ( $override['default'] ?? '' ) ) {
			return new WP_Error( 'locked_empty', __( 'Type a default and save the page before locking this field.', 'email-signature-generator' ) );
		}

		$override[ $flag ]                     = $on;
		$settings['fields'][ $model ][ $name ] = $override;
		return Settings::sanitize( $settings );
	}

	/**
	 * The stored value of a toggle, as the screen should show it.
	 *
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param string               $key      Toggle key.
	 * @return bool|list<string>
	 */
	public static function read( array $settings, string $key ) {
		if ( in_array( $key, array( 'uppercase_name', 'offer_download', 'restrict_email_domain', 'models' ), true ) ) {
			return $settings[ $key ];
		}
		$parts = explode( '.', $key );
		return ! empty( $settings['fields'][ $parts[1] ?? '' ][ $parts[2] ?? '' ][ $parts[3] ?? '' ] );
	}

	/**
	 * A field of a model, as the configuration defines it.
	 *
	 * @param string $model Model id.
	 * @param string $key   Field key.
	 * @return array<string, mixed>|null
	 */
	private static function field( string $model, string $key ): ?array {
		foreach ( Config::defaults( '' )['models'] as $m ) {
			if ( $m['id'] !== $model ) {
				continue;
			}
			foreach ( $m['fields'] as $f ) {
				if ( $f['key'] === $key ) {
					return $f;
				}
			}
		}
		return null;
	}

	/**
	 * Whether a posted toggle value means "on".
	 *
	 * @param mixed $value Posted value.
	 */
	private static function on( $value ): bool {
		return is_scalar( $value ) && in_array( (string) $value, array( '1', 'true', 'on' ), true );
	}
}
