<?php
/**
 * WordPress database test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_V1;

/**
 * WordPress database test double.
 *
 * @phpstan-type SessionRow array{
 *     id:int,
 *     visitor_id:int,
 *     identity_period_id:int,
 *     user_id_at_start:int|null,
 *     began_authenticated:int,
 *     started_at:string,
 *     last_activity_at:string,
 *     ended_at:string|null,
 *     landing_path:string|null,
 *     referrer_host:string|null,
 *     utm_source:string|null,
 *     utm_medium:string|null,
 *     utm_campaign:string|null,
 *     utm_term:string|null,
 *     utm_content:string|null
 * }
 */
final class Shurloc_Test_WPDB {

	/**
	 * WordPress database table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * WordPress options table name.
	 *
	 * @var string
	 */
	public string $options = 'wp_options';

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
	 * Journey schema tables keyed by their full names.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public array $tables = array();

	/**
	 * Schema index omitted from SHOW INDEX results.
	 *
	 * @var string
	 */
	public string $missing_index = '';

	/**
	 * Schema column omitted from SHOW COLUMNS results.
	 *
	 * @var string
	 */
	public string $missing_column = '';

	/**
	 * Unique index presented as non-unique.
	 *
	 * @var string
	 */
	public string $non_unique_index = '';

	/**
	 * Replace a migration lock before conditional deletion.
	 *
	 * @var bool
	 */
	public bool $race_on_lock_delete = false;

	/**
	 * Latest inserted row ID.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Visitor fixtures: UUID rows and existing IDs by integer key.
	 *
	 * @var array<int|string,bool|array{id:int,created_at:string,last_seen_at:string}>
	 */
	public array $visitors = array();

	/**
	 * Visitor rows inspected by Journey service tests.
	 *
	 * @var array<string,array{id:int,created_at:string,last_seen_at:string}>
	 */
	public array $visitor_rows = array();

	/**
	 * Identity periods keyed by their ID.
	 *
	 * @var array<int,array{id:int,visitor_id:int,user_id:int|null,started_at:string,linked_at:string|null,ended_at:string|null}>
	 */
	public array $periods = array();

	/**
	 * Journey sessions keyed by their ID.
	 *
	 * @var array<int,SessionRow>
	 */
	public array $sessions = array();

	/**
	 * Insert calls and their arguments.
	 *
	 * @var list<array{table:string,data:array<string,mixed>,formats:array<int,string>}>
	 */
	public array $insert_calls = array();

	/**
	 * Visitor insert calls inspected by service tests.
	 *
	 * @var list<array{table:string,data:array<string,mixed>,formats:array<int,string>}>
	 */
	public array $visitor_insert_calls = array();

	/**
	 * Transaction and update queries.
	 *
	 * @var list<string>
	 */
	public array $queries = array();

	/**
	 * Simulate a concurrent visitor UUID insert.
	 *
	 * @var bool
	 */
	public bool $race_on_insert = false;

	/**
	 * Simulate an identity period insert failure.
	 *
	 * @var bool
	 */
	public bool $fail_insert = false;

	/**
	 * Simulate a visitor insert failure independently.
	 *
	 * @var bool
	 */
	public bool $fail_visitor_insert = false;

	/**
	 * Simulate a visitor timestamp update failure.
	 *
	 * @var bool
	 */
	public bool $fail_update = false;

	/**
	 * Simulate START TRANSACTION failure.
	 *
	 * @var bool
	 */
	public bool $fail_start = false;

	/**
	 * Simulate identity period lookup failure.
	 *
	 * @var bool
	 */
	public bool $fail_select = false;

	/**
	 * Simulate identity period close failure.
	 *
	 * @var bool
	 */
	public bool $fail_close = false;

	/**
	 * Simulate COMMIT failure.
	 *
	 * @var bool
	 */
	public bool $fail_commit = false;

	/**
	 * Simulate session lookup failure.
	 *
	 * @var bool
	 */
	public bool $fail_session_select = false;

	/**
	 * Simulate session insertion failure.
	 *
	 * @var bool
	 */
	public bool $fail_session_insert = false;

	/**
	 * Simulate session activity update failure.
	 *
	 * @var bool
	 */
	public bool $fail_session_update = false;

	/**
	 * Simulate session close failure.
	 *
	 * @var bool
	 */
	public bool $fail_session_close = false;

	/**
	 * Arguments of the latest prepared query.
	 *
	 * @var list<mixed>
	 */
	private array $last_args = array();

	/**
	 * Next identity period ID.
	 *
	 * @var int
	 */
	private int $next_period_id = 1;

	/**
	 * Next visitor ID.
	 *
	 * @var int
	 */
	private int $next_visitor_id = 1;

	/**
	 * Next session ID.
	 *
	 * @var int
	 */
	private int $next_session_id = 1;

	/**
	 * Transaction snapshot of identity periods.
	 *
	 * @var array<int,array{id:int,visitor_id:int,user_id:int|null,started_at:string,linked_at:string|null,ended_at:string|null}>|null
	 */
	private ?array $snapshot = null;

	/**
	 * Transaction snapshot of sessions.
	 *
	 * @var array<int,SessionRow>|null
	 */
	private ?array $snapshot_sessions = null;

	/**
	 * Next period ID before transaction start.
	 *
	 * @var int
	 */
	private int $snapshot_next_period_id = 1;

	/**
	 * Next session ID before transaction start.
	 *
	 * @var int
	 */
	private int $snapshot_next_session_id = 1;

	/**
	 * Prepare a SQL query.
	 *
	 * The test double records the query and arguments. Schema inspection
	 * identifiers are quoted for SHOW queries.
	 *
	 * @param string $query SQL query.
	 * @param mixed  ...$args Query arguments.
	 * @return string
	 */
	public function prepare(
		string $query,
		mixed ...$args
	): string {

		$this->last_args          = array_values( $args );
		$this->prepared_queries[] = array(
			'query' => $query,
			'args'  => $this->last_args,
		);

		if ( str_starts_with( $query, 'SHOW ' ) ) {
			return str_replace( '%i', '`' . (string) $args[0] . '`', $query );
		}

		return $query;
	}

	/**
	 * Return the test collation used by Journey schema statements.
	 *
	 * @return string Charset and collation SQL.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	/**
	 * Return visitor IDs for a UUID lookup or row lock.
	 *
	 * @param string $query Prepared query.
	 * @return string|null Visitor ID, or null when missing.
	 */
	public function get_var( string $query ): ?string {
		if ( str_starts_with( $query, 'SELECT id FROM %i WHERE visitor_uuid = %s' ) ) {
			$uuid = (string) $this->last_args[1];
			$row  = $this->visitors[ $uuid ] ?? $this->visitor_rows[ $uuid ] ?? null;

			return is_array( $row ) ? (string) $row['id'] : null;
		}

		if ( str_starts_with( $query, 'SELECT id FROM %i WHERE id = %d FOR UPDATE' ) ) {
			$id = (int) $this->last_args[1];
			if ( isset( $this->visitors[ $id ] ) ) {
				return (string) $id;
			}

			foreach ( $this->visitors as $row ) {
				if ( is_array( $row ) && $id === $row['id'] ) {
					return (string) $id;
				}
			}
		}

		return null;
	}

	/**
	 * Get database results.
	 *
	 * @param string $query SQL query.
	 * @return array<int,object>|null Rows or null for unavailable schema/period data.
	 */
	public function get_results(
		string $query
	): ?array {
		if ( preg_match( '/^SHOW (COLUMNS|INDEX) FROM `([a-zA-Z0-9_]+)`$/', $query, $matches ) ) {
			$table_name = $matches[2];
			if ( ! isset( $this->tables[ $table_name ] ) ) {
				return null;
			}

			$definition = $this->tables[ $table_name ];
			return 'COLUMNS' === $matches[1]
				? $this->column_rows( columns: $definition['columns'] )
				: $this->index_rows( indexes: $definition['indexes'] );
		}

		if ( str_starts_with( $query, 'SELECT id, user_id, started_at FROM %i' ) ) {
			if ( $this->fail_select ) {
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

		if ( str_starts_with( $query, 'SELECT id, started_at, last_activity_at FROM %i' ) ) {
			if ( $this->fail_session_select ) {
				return null;
			}

			$visitor_id = (int) $this->last_args[1];
			$rows       = array();
			foreach ( $this->sessions as $session ) {
				if ( $visitor_id === $session['visitor_id'] && null === $session['ended_at'] ) {
					$rows[] = (object) array(
						'id'               => (string) $session['id'],
						'started_at'       => $session['started_at'],
						'last_activity_at' => $session['last_activity_at'],
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

		return $this->results;
	}

	/**
	 * Insert a visitor or identity period.
	 *
	 * @param string              $table Database table name.
	 * @param array<string,mixed> $data Column values.
	 * @param array<int,string>   $formats Column formats.
	 * @return int|false Insert count, or false on failure.
	 */
	public function insert( string $table, array $data, array $formats ): int|false {
		$call = array(
			'table'   => $table,
			'data'    => $data,
			'formats' => $formats,
		);

		$this->insert_calls[] = $call;

		if ( str_ends_with( $table, 'shurloc_journey_visitors' ) ) {
			$this->visitor_insert_calls[] = $call;
			$uuid                         = (string) $data['visitor_uuid'];

			if ( $this->race_on_insert ) {
				$this->store_visitor(
					uuid: $uuid,
					row: array(
						'id'           => 42,
						'created_at'   => (string) $data['created_at'],
						'last_seen_at' => (string) $data['last_seen_at'],
					)
				);
				$this->race_on_insert = false;
				return false;
			}

			if ( $this->fail_visitor_insert ||
				isset( $this->visitors[ $uuid ] ) || isset( $this->visitor_rows[ $uuid ] ) ) {
				return false;
			}

			$this->insert_id = $this->next_visitor_id++;
			$this->store_visitor(
				uuid: $uuid,
				row: array(
					'id'           => $this->insert_id,
					'created_at'   => (string) $data['created_at'],
					'last_seen_at' => (string) $data['last_seen_at'],
				)
			);
			return 1;
		}

		if ( str_ends_with( $table, 'shurloc_journey_identity_periods' ) ) {
			if ( $this->fail_insert ) {
				return false;
			}

			$this->insert_id                   = $this->next_period_id++;
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

		if ( str_ends_with( $table, 'shurloc_journey_sessions' ) ) {
			if ( $this->fail_session_insert ) {
				return false;
			}

			$this->insert_id                    = $this->next_session_id++;
			$this->sessions[ $this->insert_id ] = array(
				'id'                  => $this->insert_id,
				'visitor_id'          => (int) $data['visitor_id'],
				'identity_period_id'  => (int) $data['identity_period_id'],
				'user_id_at_start'    => null === $data['user_id_at_start'] ? null : (int) $data['user_id_at_start'],
				'began_authenticated' => (int) $data['began_authenticated'],
				'started_at'          => (string) $data['started_at'],
				'last_activity_at'    => (string) $data['last_activity_at'],
				'ended_at'            => null,
				'landing_path'        => null === $data['landing_path'] ? null : (string) $data['landing_path'],
				'referrer_host'       => null === $data['referrer_host'] ? null : (string) $data['referrer_host'],
				'utm_source'          => null === $data['utm_source'] ? null : (string) $data['utm_source'],
				'utm_medium'          => null === $data['utm_medium'] ? null : (string) $data['utm_medium'],
				'utm_campaign'        => null === $data['utm_campaign'] ? null : (string) $data['utm_campaign'],
				'utm_term'            => null === $data['utm_term'] ? null : (string) $data['utm_term'],
				'utm_content'         => null === $data['utm_content'] ? null : (string) $data['utm_content'],
			);
			return 1;
		}

		return false;
	}

	/**
	 * Keep both visitor fixture views in sync after an insert.
	 *
	 * @param string                                              $uuid Visitor UUID.
	 * @param array{id:int,created_at:string,last_seen_at:string} $row Visitor row.
	 * @return void
	 */
	private function store_visitor( string $uuid, array $row ): void {
		$this->visitors[ $uuid ]     = $row;
		$this->visitor_rows[ $uuid ] = $row;
	}

	/**
	 * Run Journey migration locks, visitor updates, and period transactions.
	 *
	 * @param string $query SQL query.
	 * @return int|false Affected rows, or false on failure.
	 */
	public function query( string $query ): int|false {
		$this->queries[] = $query;

		if ( str_starts_with( $query, 'DELETE FROM %i' ) ) {
			if ( $this->race_on_lock_delete ) {
				$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] = time() . ':replacement';
				$this->race_on_lock_delete = false;
			}

			$option_name = (string) $this->last_args[1];
			$value       = (string) $this->last_args[2];
			if ( ! isset( $GLOBALS['shurloc_test_options'][ $option_name ] ) ||
				$value !== $GLOBALS['shurloc_test_options'][ $option_name ] ) {
				return 0;
			}

			unset( $GLOBALS['shurloc_test_options'][ $option_name ] );
			return 1;
		}

		if ( 'START TRANSACTION' === $query ) {
			if ( $this->fail_start ) {
				return false;
			}

			$this->snapshot                 = $this->periods;
			$this->snapshot_sessions        = $this->sessions;
			$this->snapshot_next_period_id  = $this->next_period_id;
			$this->snapshot_next_session_id = $this->next_session_id;
			return 0;
		}

		if ( 'COMMIT' === $query ) {
			if ( $this->fail_commit ) {
				return false;
			}

			$this->snapshot          = null;
			$this->snapshot_sessions = null;
			return 0;
		}

		if ( 'ROLLBACK' === $query ) {
			if ( null !== $this->snapshot ) {
				$this->periods        = $this->snapshot;
				$this->next_period_id = $this->snapshot_next_period_id;
				$this->snapshot       = null;
			}
			if ( null !== $this->snapshot_sessions ) {
				$this->sessions          = $this->snapshot_sessions;
				$this->next_session_id   = $this->snapshot_next_session_id;
				$this->snapshot_sessions = null;
			}

			return 0;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET last_seen_at = %s' ) ) {
			if ( $this->fail_update ) {
				return false;
			}

			$seen_at    = (string) $this->last_args[1];
			$visitor_id = (int) $this->last_args[2];
			foreach ( $this->visitors as $uuid => $row ) {
				if ( is_string( $uuid ) && is_array( $row ) &&
					$visitor_id === $row['id'] && $seen_at > $row['last_seen_at'] ) {
					$row['last_seen_at']         = $seen_at;
					$this->visitors[ $uuid ]     = $row;
					$this->visitor_rows[ $uuid ] = $row;
					return 1;
				}
			}

			return 0;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET last_activity_at = %s' ) ) {
			if ( $this->fail_session_update ) {
				return false;
			}

			$session_id = (int) $this->last_args[2];
			$visitor_id = (int) $this->last_args[3];
			$observed   = (string) $this->last_args[1];
			if ( ! isset( $this->sessions[ $session_id ] ) ||
				$visitor_id !== $this->sessions[ $session_id ]['visitor_id'] ||
				null !== $this->sessions[ $session_id ]['ended_at'] ||
				$observed <= $this->sessions[ $session_id ]['last_activity_at'] ) {
				return 0;
			}

			$this->sessions[ $session_id ]['last_activity_at'] = $observed;
			return 1;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET ended_at = %s' ) &&
			str_ends_with( (string) $this->last_args[0], 'shurloc_journey_sessions' ) ) {
			if ( $this->fail_session_close ) {
				return false;
			}

			$session_id = (int) $this->last_args[2];
			$visitor_id = (int) $this->last_args[3];
			if ( ! isset( $this->sessions[ $session_id ] ) ||
				$visitor_id !== $this->sessions[ $session_id ]['visitor_id'] ||
				null !== $this->sessions[ $session_id ]['ended_at'] ) {
				return 0;
			}

			$this->sessions[ $session_id ]['ended_at'] = (string) $this->last_args[1];
			return 1;
		}

		if ( $this->fail_close ) {
			return false;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET user_id = %d' ) ) {
			$period_id  = (int) $this->last_args[4];
			$visitor_id = (int) $this->last_args[5];
			if ( ! isset( $this->periods[ $period_id ] ) ||
				$visitor_id !== $this->periods[ $period_id ]['visitor_id'] ||
				null !== $this->periods[ $period_id ]['user_id'] ||
				null !== $this->periods[ $period_id ]['ended_at'] ) {
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
			if ( ! isset( $this->periods[ $period_id ] ) ||
				$visitor_id !== $this->periods[ $period_id ]['visitor_id'] ||
				null !== $this->periods[ $period_id ]['ended_at'] ) {
				return 0;
			}

			$this->periods[ $period_id ]['ended_at'] = (string) $this->last_args[1];
			return 1;
		}

		return false;
	}

	/**
	 * Install the declaration named by a CREATE TABLE statement.
	 *
	 * @param string $sql dbDelta SQL.
	 * @return void
	 */
	public function install_table( string $sql ): void {
		if ( ! preg_match( '/^CREATE TABLE ([a-zA-Z0-9_]+) \(/', $sql, $matches ) ) {
			return;
		}

		$table_name   = $matches[1];
		$table_suffix = substr( $table_name, strlen( $this->prefix ) );
		$definitions  = Journey_Schema_V1::get_table_definitions();
		if ( isset( $definitions[ $table_suffix ] ) ) {
			$this->tables[ $table_name ] = $definitions[ $table_suffix ];
		}
	}

	/**
	 * Build SHOW COLUMNS rows from a stored declaration.
	 *
	 * @param array<string,string> $columns Installed columns.
	 * @return array<int,object> Column rows.
	 * @throws RuntimeException When a test column declaration is invalid.
	 */
	private function column_rows( array $columns ): array {
		$rows = array();
		foreach ( $columns as $name => $sql ) {
			if ( $name === $this->missing_column ) {
				continue;
			}

			if ( ! preg_match( '/^[a-z]+(?:\([0-9,]+\))?(?: unsigned)?/', $sql, $matches ) ) {
				throw new RuntimeException( 'Invalid test column definition.' );
			}

			$rows[] = (object) array(
				'Field' => $name,
				'Type'  => $matches[0],
				'Null'  => str_contains( $sql, 'DEFAULT NULL' ) ? 'YES' : 'NO',
				'Extra' => str_contains( $sql, 'AUTO_INCREMENT' ) ? 'auto_increment' : '',
			);
		}

		return $rows;
	}

	/**
	 * Build SHOW INDEX rows from a stored declaration.
	 *
	 * @param array<string,array<string,mixed>> $indexes Installed indexes.
	 * @return array<int,object> Index rows.
	 */
	private function index_rows( array $indexes ): array {
		$rows = array();
		foreach ( $indexes as $name => $index ) {
			if ( $name === $this->missing_index ) {
				continue;
			}

			foreach ( $index['columns'] as $position => $column_name ) {
				$rows[] = (object) array(
					'Key_name'     => $name,
					'Column_name'  => $column_name,
					'Seq_in_index' => $position + 1,
					'Non_unique'   => $index['unique'] && $name !== $this->non_unique_index ? 0 : 1,
				);
			}
		}

		return $rows;
	}
}
