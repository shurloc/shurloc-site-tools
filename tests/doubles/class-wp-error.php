<?php
/**
 * WordPress error test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal error code and data transport for REST tests.
	 */
	class WP_Error {
		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct(
			private string $code = '',
			private string $message = '',
			private mixed $data = ''
		) {}

		/**
		 * Read the error code.
		 *
		 * @return string Error code.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Read the error message.
		 *
		 * @return string Error message.
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * Read the error data.
		 *
		 * @return mixed Error data.
		 */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}
