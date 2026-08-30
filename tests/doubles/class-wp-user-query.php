<?php
/**
 * WordPress user query test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * WordPress user query test double.
 */
class WP_User_Query {

	/**
	 * Query variables.
	 *
	 * @var array<string,mixed>
	 */
	private array $query_vars = array();

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $query Query arguments.
	 */
	public function __construct(
		array $query = array()
	) {

		$this->query_vars = $query;
	}

	/**
	 * Get a query variable.
	 *
	 * @param string $query_var Query variable name.
	 * @return mixed
	 */
	public function get(
		string $query_var
	): mixed {

		return $this->query_vars[ $query_var ] ?? null;
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
		mixed $value
	): void {

		$this->query_vars[ $query_var ] = $value;
	}
}
