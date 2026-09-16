<?php
/**
 * Journey identity-period database test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

/**
 * Models visitor locking, identity rows, and transaction rollback.
 */
final class Journey_Identity_Period_Test_WPDB {
	/**
	 * WordPress table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Latest inserted period ID.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Existing visitor IDs.
	 *
	 * @var array<int,bool>
	 */
	public array $visitors = array();

	/**
	 * Stored identity rows keyed by period ID.
	 *
	 * @var array<int,array{id:int,visitor_id:int,user_id:int|null,started_at:string,linked_at:string|null,ended_at:string|null}>
	 */
	public array $periods = array();

	/**
	 * Prepared queries and arguments.
	 *
	 * @var list<array{query:string,args:list<mixed>}>
	 */
	public array $prepared_queries = array();

	/**
	 * Transaction and update SQL queries.
	 *
	 * @var list<string>
	 */
	public array $queries = array();

	/**
	 * Insert calls.
	 *
	 * @var list<array{table:string,data:array<string,mixed>,formats:array<int,string>}>
	 */
	public array $insert_calls = array();

	/**
	 * Simulate START TRANSACTION failure.
	 *
	 * @var bool
	 */
	public bool $fail_start = false;

	/**
	 * Simulate period lookup failure.
	 *
	 * @var bool
	 */
	public bool $fail_select = false;

	/**
	 * Simulate period close failure.
	 *
	 * @var bool
	 */
	public bool $fail_close = false;

	/**
	 * Simulate period insert failure.
	 *
	 * @var bool
	 */
	public bool $fail_insert = false;

	/**
	 * Simulate COMMIT failure.
	 *
	 * @var bool
	 */
	public bool $fail_commit = false;

	/**
	 * Next identity period ID.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * Latest prepared arguments.
	 *
	 * @var list<mixed>
	 */
	private array $last_args = array();

	/**
	 * Transaction snapshot of identity rows.
	 *
	 * @var array<int,array{id:int,visitor_id:int,user_id:int|null,started_at:string,linked_at:string|null,ended_at:string|null}>|null
	 */
	private ?array $snapshot = null;

	/**
	 * Next ID before transaction start.
	 *
	 * @var int
	 */
	private int $snapshot_next_id = 1;

	/**
	 * Capture the query template and arguments.
	 *
	 * @param string $query SQL template.
	 * @param mixed  ...$args Placeholder arguments.
	 * @return string SQL template for this double.
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
	 * Run a transaction boundary or conditional period close.
	 *
	 * @param string $query SQL query.
	 * @return int|false Affected rows, or false on failure.
	 */
	public function query( string $query ): int|false {
		$this->queries[] = $query;

		if ( 'START TRANSACTION' === $query ) {
			if ( $this->fail_start ) {
				return false;
			}

			$this->snapshot         = $this->periods;
			$this->snapshot_next_id = $this->next_id;
			return 0;
		}

		if ( 'COMMIT' === $query ) {
			if ( $this->fail_commit ) {
				return false;
			}

			$this->snapshot = null;
			return 0;
		}

		if ( 'ROLLBACK' === $query ) {
			if ( null !== $this->snapshot ) {
				$this->periods  = $this->snapshot;
				$this->next_id  = $this->snapshot_next_id;
				$this->snapshot = null;
			}

			return 0;
		}

		if ( $this->fail_close ) {
			return false;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET user_id = %d' ) ) {
			$period_id  = (int) $this->last_args[4];
			$visitor_id = (int) $this->last_args[5];

			if (
				! isset( $this->periods[ $period_id ] ) ||
				$visitor_id !== $this->periods[ $period_id ]['visitor_id'] ||
				null !== $this->periods[ $period_id ]['user_id'] ||
				null !== $this->periods[ $period_id ]['ended_at']
			) {
				return 0;
			}

			$this->periods[ $period_id ]['user_id']   = (int) $this->last_args[1];
			$this->periods[ $period_id ]['linked_at'] = (string) $this->last_args[2];
			$this->periods[ $period_id ]['ended_at']  = (string) $this->last_args[3];
			return 1;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET ended_at = %s' ) ) {
			$period_id  = (int) $this->last_args[2];
			$visitor_id = (int) $this->last_args[3];

			if (
				! isset( $this->periods[ $period_id ] ) ||
				$visitor_id !== $this->periods[ $period_id ]['visitor_id'] ||
				null !== $this->periods[ $period_id ]['ended_at']
			) {
				return 0;
			}

			$this->periods[ $period_id ]['ended_at'] = (string) $this->last_args[1];
			return 1;
		}

		return false;
	}

	/**
	 * Simulate locking an existing visitor row.
	 *
	 * @param string $query Prepared visitor lookup query.
	 * @return string|null Visitor ID, or null when absent.
	 */
	public function get_var( string $query ): ?string {
		if ( ! str_starts_with( $query, 'SELECT id FROM %i' ) ) {
			return null;
		}

		$id = (int) $this->last_args[1];

		return isset( $this->visitors[ $id ] ) ? (string) $id : null;
	}

	/**
	 * Return up to two open periods for the locked visitor.
	 *
	 * @param string $query Prepared period lookup query.
	 * @return array<int,object>|null Rows, or null on failure.
	 */
	public function get_results( string $query ): ?array {
		if ( $this->fail_select || ! str_starts_with( $query, 'SELECT id, user_id, started_at FROM %i' ) ) {
			return null;
		}

		$visitor_id = (int) $this->last_args[1];
		$rows       = array();

		foreach ( $this->periods as $period ) {
			if ( $visitor_id === $period['visitor_id'] && null === $period['ended_at'] ) {
				$rows[] = (object) array(
					'id'         => (string) $period['id'],
					'user_id'    => null === $period['user_id'] ? null : (string) $period['user_id'],
					'started_at' => $period['started_at'],
				);
			}
		}

		usort(
			$rows,
			static function ( object $first, object $second ): int {
				$by_start = $second->started_at <=> $first->started_at;

				return 0 !== $by_start ? $by_start : $second->id <=> $first->id;
			}
		);

		return array_slice( $rows, 0, 2 );
	}

	/**
	 * Insert an identity period.
	 *
	 * @param string              $table Table name.
	 * @param array<string,mixed> $data Column values.
	 * @param array<int,string>   $formats Column formats.
	 * @return int|false Inserted row count, or false on failure.
	 */
	public function insert( string $table, array $data, array $formats ): int|false {
		$this->insert_calls[] = array(
			'table'   => $table,
			'data'    => $data,
			'formats' => $formats,
		);

		if ( $this->fail_insert ) {
			return false;
		}

		$this->insert_id                   = $this->next_id++;
		$this->periods[ $this->insert_id ] = array(
			'id'         => $this->insert_id,
			'visitor_id' => (int) $data['visitor_id'],
			'user_id'    => null === $data['user_id'] ? null : (int) $data['user_id'],
			'started_at' => (string) $data['started_at'],
			'linked_at'  => null === $data['linked_at'] ? null : (string) $data['linked_at'],
			'ended_at'   => null,
		);

		return 1;
	}
}
