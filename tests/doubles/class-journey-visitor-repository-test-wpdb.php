<?php
/**
 * Visitor repository database test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

/**
 * Small database double for visitor lookup, insertion, and touch queries.
 */
final class Journey_Visitor_Repository_Test_WPDB {
	/**
	 * WordPress database prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * ID returned by the latest successful insert.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Visitor rows keyed by UUID.
	 *
	 * @var array<string,array{id:int,created_at:string,last_seen_at:string}>
	 */
	public array $visitors = array();

	/**
	 * Prepared queries and their arguments.
	 *
	 * @var list<array{query:string,args:list<mixed>}>
	 */
	public array $prepared_queries = array();

	/**
	 * Insert calls and their arguments.
	 *
	 * @var list<array{table:string,data:array<string,mixed>,formats:array<int,string>}>
	 */
	public array $insert_calls = array();

	/**
	 * Simulate a duplicate inserted by another request.
	 *
	 * @var bool
	 */
	public bool $race_on_insert = false;

	/**
	 * Simulate an unrelated insert failure.
	 *
	 * @var bool
	 */
	public bool $fail_insert = false;

	/**
	 * Simulate an update query failure.
	 *
	 * @var bool
	 */
	public bool $fail_update = false;

	/**
	 * Arguments of the latest prepared query.
	 *
	 * @var list<mixed>
	 */
	private array $last_args = array();

	/**
	 * Record a parameterized query.
	 *
	 * @param string $query Query template.
	 * @param mixed  ...$args Placeholder arguments.
	 * @return string Query template for this double.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->last_args          = array_values( $args );
		$this->prepared_queries[] = array(
			'query' => $query,
			'args'  => $this->last_args,
		);

		return $query;
	}

	/**
	 * Return a stored visitor ID by the prepared UUID.
	 *
	 * @param string $query Prepared lookup query.
	 * @return string|null Stored ID, or null when absent.
	 */
	public function get_var( string $query ): ?string {
		if ( ! str_starts_with( $query, 'SELECT id FROM %i' ) ) {
			return null;
		}

		$uuid = (string) $this->last_args[1];

		return isset( $this->visitors[ $uuid ] )
			? (string) $this->visitors[ $uuid ]['id']
			: null;
	}

	/**
	 * Insert a visitor row or simulate a duplicate/failure.
	 *
	 * @param string              $table Database table name.
	 * @param array<string,mixed> $data Column values.
	 * @param array<int,string>   $formats Column formats.
	 * @return int|false Insert count, or false on failure.
	 */
	public function insert( string $table, array $data, array $formats ): int|false {
		$this->insert_calls[] = array(
			'table'   => $table,
			'data'    => $data,
			'formats' => $formats,
		);

		$uuid = (string) $data['visitor_uuid'];

		if ( $this->race_on_insert ) {
			$this->visitors[ $uuid ] = array(
				'id'           => 42,
				'created_at'   => (string) $data['created_at'],
				'last_seen_at' => (string) $data['last_seen_at'],
			);
			$this->race_on_insert    = false;
			return false;
		}

		if ( $this->fail_insert || isset( $this->visitors[ $uuid ] ) ) {
			return false;
		}

		$this->insert_id         = count( $this->visitors ) + 1;
		$this->visitors[ $uuid ] = array(
			'id'           => $this->insert_id,
			'created_at'   => (string) $data['created_at'],
			'last_seen_at' => (string) $data['last_seen_at'],
		);

		return 1;
	}

	/**
	 * Advance a stored timestamp only when the incoming one is newer.
	 *
	 * @param string $query Prepared update query.
	 * @return int|false Updated row count, or false on failure.
	 */
	public function query( string $query ): int|false {
		if ( $this->fail_update || ! str_starts_with( $query, 'UPDATE %i' ) ) {
			return false;
		}

		$visitor_id = (int) $this->last_args[2];
		$seen_at    = (string) $this->last_args[1];

		foreach ( $this->visitors as &$visitor ) {
			if ( $visitor_id === $visitor['id'] && $seen_at > $visitor['last_seen_at'] ) {
				$visitor['last_seen_at'] = $seen_at;
				return 1;
			}
		}

		return 0;
	}
}
