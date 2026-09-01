<?php
/**
 * WordPress database test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * WordPress database test double.
 */
final class Shurloc_Test_WPDB {

	/**
	 * WordPress database table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Results returned by get_results().
	 *
	 * @var array<int,object>
	 */
	public array $results = array();

	/**
	 * Prepared queries recorded during tests.
	 *
	 * @var array<int,array{
	 *     query:string,
	 *     args:array<int|string,mixed>
	 * }>
	 */
	public array $prepared_queries = array();

	/**
	 * Prepare a SQL query.
	 *
	 * The test double records the query and arguments and returns the SQL
	 * unchanged. It does not attempt to reproduce wpdb placeholder handling.
	 *
	 * @param string $query SQL query.
	 * @param mixed  ...$args Query arguments.
	 * @return string
	 */
	public function prepare(
		string $query,
		mixed ...$args
	): string {

		$this->prepared_queries[] = array(
			'query' => $query,
			'args'  => $args,
		);

		return $query;
	}

	/**
	 * Get database results.
	 *
	 * @param string $query SQL query.
	 * @return array<int,object>
	 */
	public function get_results(
		string $query
	): array {

		unset( $query );

		return $this->results;
	}
}
