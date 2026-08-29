<?php
/**
 * WordPress query test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Query' ) ) {

	/**
	 * WordPress query test double.
	 */
	class WP_Query {

		/**
		 * Stored query variables.
		 *
		 * @var array<string,mixed>
		 */
		private array $query_vars = array();

		/**
		 * Determine whether this is the main query.
		 *
		 * @return bool
		 */
		public function is_main_query(): bool {

			return $GLOBALS['shurloc_test_is_main_query'];
		}

		/**
		 * Retrieve a query variable.
		 *
		 * @param string $query_var     Query variable name.
		 * @param mixed  $default_value Default value.
		 * @return mixed
		 */
		public function get(
			string $query_var,
			$default_value = ''
		) {

			return $this->query_vars[ $query_var ]
				?? $default_value;
		}

		/**
		 * Set a query variable.
		 *
		 * @param string $query_var Query variable name.
		 * @param mixed  $value     Query variable value.
		 * @return void
		 */
		public function set(
			string $query_var,
			$value
		): void {

			$this->query_vars[ $query_var ] =
				$value;
		}
	}
}
