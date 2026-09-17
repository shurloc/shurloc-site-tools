<?php
/**
 * WordPress REST request test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal request body and header transport for route tests.
	 */
	class WP_REST_Request {
		/**
		 * Raw body.
		 *
		 * @var string
		 */
		private string $body = '';

		/**
		 * Header values indexed by lowercase header name.
		 *
		 * @var array<string,string>
		 */
		private array $headers = array();

		/**
		 * Set the raw request body.
		 *
		 * @param string $body Raw body.
		 * @return void
		 */
		public function set_body( string $body ): void {
			$this->body = $body;
		}

		/**
		 * Read the raw request body.
		 *
		 * @return string Raw body.
		 */
		public function get_body(): string {
			return $this->body;
		}

		/**
		 * Set a request header.
		 *
		 * @param string $name  Header name.
		 * @param string $value Header value.
		 * @return void
		 */
		public function set_header( string $name, string $value ): void {
			$this->headers[ strtolower( $name ) ] = $value;
		}

		/**
		 * Read a request header.
		 *
		 * @param string $name Header name.
		 * @return string|null Header value, if present.
		 */
		public function get_header( string $name ): ?string {
			return $this->headers[ strtolower( $name ) ] ?? null;
		}
	}
}
