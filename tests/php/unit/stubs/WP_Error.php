<?php
/**
 * The part of core's WP_Error that the plugin's validators return.
 */
class WP_Error {

	/** @var string */
	private $code;

	/** @var string */
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}
