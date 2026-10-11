<?php
/**
 * A minimal WP_Error for the unit suite, which runs without WordPress. It
 * carries what the plugin reads: one code and one message.
 */

declare(strict_types=1);

// phpcs:disable -- test double of a WordPress core class.
class WP_Error {

	private string $code;

	private string $message;

	public function __construct( string $code = '', string $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}
