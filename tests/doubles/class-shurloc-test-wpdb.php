<?php
/**
 * WordPress database test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_V1;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_V2;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_V3;

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
 *     utm_content:string|null,
 *     page_view_count:int,
 *     product_view_count:int,
 *     cart_add_count:int,
 *     cart_remove_count:int,
 *     added_quantity:string,
 *     removed_quantity:string,
 *     checkout_started_count:int,
 *     order_created_count:int,
 *     active_ms:int,
 *     user_agent?:string|null,
 *     client_type?:string,
 *     client_name?:string|null,
 *     device_type?:string,
 *     classification_version?:int,
 *     event_count?:int
 * }
 * @phpstan-type VisitorRow array{
 *     id:int,
 *     created_at:string,
 *     last_seen_at:string,
 *     first_touch_at?:string,
 *     first_landing_path?:string,
 *     first_referrer_host?:string|null,
 *     first_utm_source?:string|null,
 *     first_utm_medium?:string|null,
 *     first_utm_campaign?:string|null,
 *     first_utm_term?:string|null,
 *     first_utm_content?:string|null
 * }
 * @phpstan-type CartLinkRow array{
 *     id:int,
 *     cart_token_hash:string,
 *     visitor_id:int,
 *     session_id:int,
 *     linked_at:string,
 *     last_seen_at:string
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
	 * Sequential results for tests that exercise multiple report reads.
	 *
	 * Empty queues preserve the historical results property behavior.
	 *
	 * @var list<array<int,object>|null>
	 */
	public array $result_queue = array();

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
	 * Storage engine substitutions to simulate a database that did not use InnoDB.
	 *
	 * @var array<string,string>
	 */
	public array $table_engine_overrides = array();

	/**
	 * Simulate a failure before Journey schema statements are built.
	 *
	 * @var bool
	 */
	public bool $fail_charset_collate = false;

	/**
	 * Number of Journey charset and collation requests.
	 *
	 * @var int
	 */
	public int $charset_collate_calls = 0;

	/**
	 * Simulate an unavailable table status query.
	 *
	 * @var bool
	 */
	public bool $fail_table_status = false;

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
	 * @var array<int|string,bool|VisitorRow>
	 */
	public array $visitors = array();

	/**
	 * Visitor rows inspected by Journey service tests.
	 *
	 * @var array<string,VisitorRow>
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
	 * Journey events keyed by their ID.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public array $events = array();

	/**
	 * Journey cart links keyed by their ID.
	 *
	 * @var array<int,CartLinkRow>
	 */
	public array $cart_links = array();

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
	 * Sequential affected-row results for otherwise unhandled query calls.
	 *
	 * Empty queues preserve the historical query behavior.
	 *
	 * @var list<int|false>
	 */
	public array $query_result_queue = array();

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
	 * Simulate deletion failure for one session.
	 *
	 * @var bool
	 */
	public bool $fail_session_delete = false;

	/**
	 * Simulate an event insertion failure.
	 *
	 * @var bool
	 */
	public bool $fail_event_insert = false;

	/**
	 * Simulate an event lookup failure.
	 *
	 * @var bool
	 */
	public bool $fail_event_select = false;

	/**
	 * Simulate deletion failure for events belonging to one session.
	 *
	 * @var bool
	 */
	public bool $fail_event_delete = false;

	/**
	 * Simulate a cart-link upsert failure.
	 *
	 * @var bool
	 */
	public bool $fail_cart_link_upsert = false;

	/**
	 * Simulate a cart-link visitor lookup failure.
	 *
	 * @var bool
	 */
	public bool $fail_cart_link_select = false;

	/**
	 * Simulate a cart-link cleanup failure.
	 *
	 * @var bool
	 */
	public bool $fail_cart_link_delete = false;

	/**
	 * Simulate a view-duration event update failure.
	 *
	 * @var bool
	 */
	public bool $fail_event_duration_update = false;

	/**
	 * Simulate a session summary update failure.
	 *
	 * @var bool
	 */
	public bool $fail_event_summary_update = false;

	/**
	 * Simulate failure while backfilling v3 session event totals.
	 *
	 * @var bool
	 */
	public bool $fail_event_count_backfill = false;

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
	 * Next Journey event ID.
	 *
	 * @var int
	 */
	private int $next_event_id = 1;

	/**
	 * Next Journey cart-link ID.
	 *
	 * @var int
	 */
	private int $next_cart_link_id = 1;

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
	 * Transaction snapshot of Journey events.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private ?array $snapshot_events = null;

	/**
	 * Transaction snapshot of Journey cart links.
	 *
	 * @var array<int,CartLinkRow>|null
	 */
	private ?array $snapshot_cart_links = null;

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
	 * @throws RuntimeException When the configured schema failure is active.
	 */
	public function get_charset_collate(): string {
		++$this->charset_collate_calls;

		if ( $this->fail_charset_collate ) {
			throw new RuntimeException( 'Simulated schema failure.' );
		}

		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	/**
	 * Return visitor IDs for a UUID lookup or row lock.
	 *
	 * @param string $query Prepared query.
	 * @return string|null Visitor ID, or null when missing.
	 */
	public function get_var( string $query ): ?string {
		if ( str_starts_with( $query, 'SELECT visitor_id FROM %i WHERE cart_token_hash = %s ORDER BY last_seen_at DESC, id DESC LIMIT 1' ) ) {
			if ( $this->fail_cart_link_select ) {
				return null;
			}

			$cart_token_hash = (string) $this->last_args[1];
			$links           = array_values(
				array_filter(
					$this->cart_links,
					static fn ( array $link ): bool => $cart_token_hash === $link['cart_token_hash']
				)
			);

			usort(
				$links,
				static function ( array $first, array $second ): int {
					$activity_order = $second['last_seen_at'] <=> $first['last_seen_at'];

					return 0 !== $activity_order
						? $activity_order
						: $second['id'] <=> $first['id'];
				}
			);

			return array() === $links ? null : (string) $links[0]['visitor_id'];
		}

		if ( str_starts_with( $query, 'SELECT id FROM %i WHERE id = %d AND visitor_id = %d LIMIT 1 FOR UPDATE' ) ) {
			if ( $this->fail_session_select ) {
				return null;
			}

			$session_id = (int) $this->last_args[1];
			$visitor_id = (int) $this->last_args[2];

			return isset( $this->sessions[ $session_id ] ) &&
				$visitor_id === $this->sessions[ $session_id ]['visitor_id']
				? (string) $session_id
				: null;
		}

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
		if ( 'SHOW TABLE STATUS WHERE Name = %s' === $query ) {
			$table_name = (string) $this->last_args[0];
			if ( $this->fail_table_status || ! isset( $this->tables[ $table_name ] ) ) {
				return null;
			}

			return array(
				(object) array(
					'Name'   => $table_name,
					'Engine' => $this->tables[ $table_name ]['engine'] ?? null,
				),
			);
		}

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

		if ( str_starts_with( $query, 'SELECT id, visitor_id FROM %i WHERE id = %d LIMIT 2 FOR UPDATE' ) ) {
			if ( $this->fail_session_select ) {
				return null;
			}

			$session_id = (int) $this->last_args[1];
			if ( ! isset( $this->sessions[ $session_id ] ) ) {
				return array();
			}

			return array(
				(object) array(
					'id'         => (string) $session_id,
					'visitor_id' => (string) $this->sessions[ $session_id ]['visitor_id'],
				),
			);
		}

		if ( str_starts_with( $query, 'SELECT id, visitor_id, event_type, order_id, page_path, post_id, product_id, variation_id, quantity, active_ms, source FROM %i WHERE idempotency_key = %s' ) ) {
			if ( $this->fail_event_select ) {
				return null;
			}

			$key = (string) $this->last_args[1];
			foreach ( $this->events as $event ) {
				if ( $key === $event['idempotency_key'] ) {
					return array(
						(object) array(
							'id'           => (string) $event['id'],
							'visitor_id'   => (string) $event['visitor_id'],
							'event_type'   => $event['event_type'],
							'order_id'     => null === $event['order_id'] ? null : (string) $event['order_id'],
							'page_path'    => $event['page_path'],
							'post_id'      => null === $event['post_id'] ? null : (string) $event['post_id'],
							'product_id'   => null === $event['product_id'] ? null : (string) $event['product_id'],
							'variation_id' => null === $event['variation_id'] ? null : (string) $event['variation_id'],
							'quantity'     => $event['quantity'],
							'active_ms'    => (string) $event['active_ms'],
							'source'       => $event['source'],
						),
					);
				}
			}

			return array();
		}

		if ( str_starts_with( $query, 'SELECT session_id, event_type, occurred_at, active_ms FROM %i WHERE id = %d AND visitor_id = %d' ) ) {
			if ( $this->fail_event_select ) {
				return null;
			}

			$event_id   = (int) $this->last_args[1];
			$visitor_id = (int) $this->last_args[2];
			$event      = $this->events[ $event_id ] ?? null;
			if ( null === $event || $visitor_id !== $event['visitor_id'] ) {
				return array();
			}

			return array(
				(object) array(
					'session_id'  => (string) $event['session_id'],
					'event_type'  => $event['event_type'],
					'occurred_at' => $event['occurred_at'],
					'active_ms'   => (string) $event['active_ms'],
				),
			);
		}

		if ( array() !== $this->result_queue ) {
			return array_shift( $this->result_queue );
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
				'id'                     => $this->insert_id,
				'visitor_id'             => (int) $data['visitor_id'],
				'identity_period_id'     => (int) $data['identity_period_id'],
				'user_id_at_start'       => null === $data['user_id_at_start'] ? null : (int) $data['user_id_at_start'],
				'began_authenticated'    => (int) $data['began_authenticated'],
				'started_at'             => (string) $data['started_at'],
				'last_activity_at'       => (string) $data['last_activity_at'],
				'ended_at'               => null,
				'landing_path'           => null === $data['landing_path'] ? null : (string) $data['landing_path'],
				'referrer_host'          => null === $data['referrer_host'] ? null : (string) $data['referrer_host'],
				'utm_source'             => null === $data['utm_source'] ? null : (string) $data['utm_source'],
				'utm_medium'             => null === $data['utm_medium'] ? null : (string) $data['utm_medium'],
				'utm_campaign'           => null === $data['utm_campaign'] ? null : (string) $data['utm_campaign'],
				'utm_term'               => null === $data['utm_term'] ? null : (string) $data['utm_term'],
				'utm_content'            => null === $data['utm_content'] ? null : (string) $data['utm_content'],
				'page_view_count'        => 0,
				'product_view_count'     => 0,
				'cart_add_count'         => 0,
				'cart_remove_count'      => 0,
				'added_quantity'         => '0.0000',
				'removed_quantity'       => '0.0000',
				'checkout_started_count' => 0,
				'order_created_count'    => 0,
				'active_ms'              => 0,
				'user_agent'             => null === ( $data['user_agent'] ?? null ) ? null : (string) $data['user_agent'],
				'client_type'            => (string) ( $data['client_type'] ?? 'unknown' ),
				'client_name'            => null === ( $data['client_name'] ?? null ) ? null : (string) $data['client_name'],
				'device_type'            => (string) ( $data['device_type'] ?? 'unknown' ),
				'classification_version' => (int) ( $data['classification_version'] ?? 0 ),
				'event_count'            => 0,
			);
			return 1;
		}

		if ( str_ends_with( $table, 'shurloc_journey_events' ) ) {
			if ( $this->fail_event_insert ) {
				return false;
			}

			$key = $data['idempotency_key'];
			if ( null !== $key ) {
				foreach ( $this->events as $event ) {
					if ( $key === $event['idempotency_key'] ) {
						return false;
					}
				}
			}

			$this->insert_id                  = $this->next_event_id++;
			$this->events[ $this->insert_id ] = array_merge( array( 'id' => $this->insert_id ), $data );
			return 1;
		}

		return false;
	}

	/**
	 * Keep both visitor fixture views in sync after an insert.
	 *
	 * @param string              $uuid Visitor UUID.
	 * @param array<string,mixed> $row  Visitor row.
	 * @return void
	 * @phpstan-param VisitorRow $row
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

		if ( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s' === $query ) {
			$option_name = (string) $this->last_args[1];

			if ( $this->race_on_lock_delete ) {
				$GLOBALS['shurloc_test_options'][ $option_name ] = time() . ':replacement';
				$this->race_on_lock_delete                       = false;
			}

			$value = (string) $this->last_args[2];
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
			$this->snapshot_events          = $this->events;
			$this->snapshot_cart_links      = $this->cart_links;
			$this->snapshot_next_period_id  = $this->next_period_id;
			$this->snapshot_next_session_id = $this->next_session_id;
			return 0;
		}

		if ( 'COMMIT' === $query ) {
			if ( $this->fail_commit ) {
				return false;
			}

			$this->snapshot            = null;
			$this->snapshot_sessions   = null;
			$this->snapshot_events     = null;
			$this->snapshot_cart_links = null;
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
			if ( null !== $this->snapshot_events ) {
				$this->events          = $this->snapshot_events;
				$this->snapshot_events = null;
			}
			if ( null !== $this->snapshot_cart_links ) {
				$this->cart_links          = $this->snapshot_cart_links;
				$this->snapshot_cart_links = null;
			}

			return 0;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET event_count = page_view_count + cart_add_count + cart_remove_count + checkout_started_count + order_created_count' ) ) {
			if ( $this->fail_event_count_backfill ) {
				return false;
			}

			$updated = 0;
			foreach ( $this->sessions as $session_id => $session ) {
				$event_count = $session['page_view_count'] +
					$session['cart_add_count'] +
					$session['cart_remove_count'] +
					$session['checkout_started_count'] +
					$session['order_created_count'];

				if ( ( $session['event_count'] ?? 0 ) !== $event_count ) {
					$this->sessions[ $session_id ]['event_count'] = $event_count;
					++$updated;
				}
			}

			return $updated;
		}

		if ( str_starts_with( $query, 'INSERT INTO %i (cart_token_hash, visitor_id, session_id, linked_at, last_seen_at)' ) ) {
			if ( $this->fail_cart_link_upsert ) {
				return false;
			}

			$cart_token_hash = (string) $this->last_args[1];
			$visitor_id      = (int) $this->last_args[2];
			$session_id      = (int) $this->last_args[3];
			$linked_at       = (string) $this->last_args[4];
			$last_seen_at    = (string) $this->last_args[5];

			foreach ( $this->cart_links as $id => $link ) {
				if ( $cart_token_hash !== $link['cart_token_hash'] || $session_id !== $link['session_id'] ) {
					continue;
				}

				$changed = $visitor_id !== $link['visitor_id'] || $last_seen_at > $link['last_seen_at'];

				$this->cart_links[ $id ]['visitor_id'] = $visitor_id;
				if ( $last_seen_at > $link['last_seen_at'] ) {
					$this->cart_links[ $id ]['last_seen_at'] = $last_seen_at;
				}

				return $changed ? 2 : 0;
			}

			$id                      = $this->next_cart_link_id++;
			$this->cart_links[ $id ] = array(
				'id'              => $id,
				'cart_token_hash' => $cart_token_hash,
				'visitor_id'      => $visitor_id,
				'session_id'      => $session_id,
				'linked_at'       => $linked_at,
				'last_seen_at'    => $last_seen_at,
			);

			return 1;
		}

		if ( str_starts_with( $query, 'DELETE FROM %i WHERE session_id IN (' ) &&
			str_ends_with( (string) $this->last_args[0], 'shurloc_journey_cart_links' ) ) {
			if ( $this->fail_cart_link_delete ) {
				return false;
			}

			$session_ids = array_map( 'intval', array_slice( $this->last_args, 1 ) );
			$deleted     = 0;

			foreach ( $this->cart_links as $id => $link ) {
				if ( in_array( $link['session_id'], $session_ids, true ) ) {
					unset( $this->cart_links[ $id ] );
					++$deleted;
				}
			}

			return $deleted;
		}

		if ( 'DELETE FROM %i WHERE visitor_id = %d' === $query &&
			str_ends_with( (string) $this->last_args[0], 'shurloc_journey_cart_links' ) ) {
			if ( $this->fail_cart_link_delete ) {
				return false;
			}

			$visitor_id = (int) $this->last_args[1];
			$deleted    = 0;

			foreach ( $this->cart_links as $id => $link ) {
				if ( $visitor_id === $link['visitor_id'] ) {
					unset( $this->cart_links[ $id ] );
					++$deleted;
				}
			}

			return $deleted;
		}

		if ( 'DELETE FROM %i WHERE session_id = %d' === $query &&
			str_ends_with( (string) $this->last_args[0], 'shurloc_journey_events' ) ) {
			if ( $this->fail_event_delete ) {
				return false;
			}

			$session_id = (int) $this->last_args[1];
			$deleted    = 0;
			foreach ( $this->events as $event_id => $event ) {
				if ( $session_id === $event['session_id'] ) {
					unset( $this->events[ $event_id ] );
					++$deleted;
				}
			}

			return $deleted;
		}

		if ( 'DELETE FROM %i WHERE id = %d AND visitor_id = %d' === $query &&
			str_ends_with( (string) $this->last_args[0], 'shurloc_journey_sessions' ) ) {
			if ( $this->fail_session_delete ) {
				return false;
			}

			$session_id = (int) $this->last_args[1];
			$visitor_id = (int) $this->last_args[2];
			if ( ! isset( $this->sessions[ $session_id ] ) ||
				$visitor_id !== $this->sessions[ $session_id ]['visitor_id'] ) {
				return 0;
			}

			unset( $this->sessions[ $session_id ] );
			return 1;
		}

		if ( str_starts_with( $query, 'UPDATE %i SET %i = %i + ' ) &&
			str_ends_with( $query, ' WHERE id = %d AND visitor_id = %d' ) &&
			str_ends_with( (string) $this->last_args[0], 'shurloc_journey_sessions' ) ) {
			if ( $this->fail_event_summary_update ) {
				return false;
			}

			$session_id = (int) $this->last_args[ count( $this->last_args ) - 2 ];
			$visitor_id = (int) $this->last_args[ count( $this->last_args ) - 1 ];
			if ( ! isset( $this->sessions[ $session_id ] ) || $visitor_id !== $this->sessions[ $session_id ]['visitor_id'] ) {
				return 0;
			}

			preg_match_all( '/%i = %i \+ (?:%d|CAST\(%s AS DECIMAL\(16,4\)\))/', $query, $assignments );
			if ( 1 + 3 * count( $assignments[0] ) + 2 !== count( $this->last_args ) ) {
				return false;
			}

			foreach ( $assignments[0] as $index => $assignment ) {
				$column    = (string) $this->last_args[ 1 + 3 * $index ];
				$increment = $this->last_args[ 3 + 3 * $index ];
				if ( $column !== $this->last_args[ 2 + 3 * $index ] ) {
					return false;
				}

				if ( str_contains( $assignment, 'CAST' ) ) {
					switch ( $column ) {
						case 'added_quantity':
							$this->sessions[ $session_id ]['added_quantity'] = $this->add_decimal( $this->sessions[ $session_id ]['added_quantity'], (string) $increment );
							break;
						case 'removed_quantity':
							$this->sessions[ $session_id ]['removed_quantity'] = $this->add_decimal( $this->sessions[ $session_id ]['removed_quantity'], (string) $increment );
							break;
						default:
							return false;
					}
					continue;
				}

				switch ( $column ) {
					case 'event_count':
						$this->sessions[ $session_id ]['event_count'] = ( $this->sessions[ $session_id ]['event_count'] ?? 0 ) + (int) $increment;
						break;
					case 'page_view_count':
						$this->sessions[ $session_id ]['page_view_count'] += (int) $increment;
						break;
					case 'product_view_count':
						$this->sessions[ $session_id ]['product_view_count'] += (int) $increment;
						break;
					case 'cart_add_count':
						$this->sessions[ $session_id ]['cart_add_count'] += (int) $increment;
						break;
					case 'cart_remove_count':
						$this->sessions[ $session_id ]['cart_remove_count'] += (int) $increment;
						break;
					case 'checkout_started_count':
						$this->sessions[ $session_id ]['checkout_started_count'] += (int) $increment;
						break;
					case 'order_created_count':
						$this->sessions[ $session_id ]['order_created_count'] += (int) $increment;
						break;
					case 'active_ms':
						$this->sessions[ $session_id ]['active_ms'] += (int) $increment;
						break;
					default:
						return false;
				}
			}

			return 1;
		}

		if ( 'UPDATE %i SET active_ms = %d WHERE id = %d AND visitor_id = %d AND active_ms = %d' === $query ) {
			if ( $this->fail_event_duration_update ) {
				return false;
			}

			$event_id   = (int) $this->last_args[2];
			$visitor_id = (int) $this->last_args[3];
			$stored_ms  = (int) $this->last_args[4];
			if ( ! isset( $this->events[ $event_id ] ) ||
				$visitor_id !== $this->events[ $event_id ]['visitor_id'] ||
				$stored_ms !== $this->events[ $event_id ]['active_ms'] ) {
				return 0;
			}

			$this->events[ $event_id ]['active_ms'] = (int) $this->last_args[1];
			return 1;
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

		if ( str_starts_with( $query, 'UPDATE %i SET first_touch_at = %s' ) ) {
			if ( $this->fail_update ) {
				return false;
			}

			$visitor_id = (int) $this->last_args[9];
			$observed   = (string) $this->last_args[1];
			foreach ( $this->visitors as $uuid => $row ) {
				if ( ! is_string( $uuid ) || ! is_array( $row ) ||
					$visitor_id !== $row['id'] ||
					( isset( $row['first_touch_at'] ) && $row['first_touch_at'] <= $observed ) ) {
					continue;
				}

				$row['first_touch_at']       = $observed;
				$row['first_landing_path']   = (string) $this->last_args[2];
				$row['first_referrer_host']  = '' === $this->last_args[3] ? null : (string) $this->last_args[3];
				$row['first_utm_source']     = '' === $this->last_args[4] ? null : (string) $this->last_args[4];
				$row['first_utm_medium']     = '' === $this->last_args[5] ? null : (string) $this->last_args[5];
				$row['first_utm_campaign']   = '' === $this->last_args[6] ? null : (string) $this->last_args[6];
				$row['first_utm_term']       = '' === $this->last_args[7] ? null : (string) $this->last_args[7];
				$row['first_utm_content']    = '' === $this->last_args[8] ? null : (string) $this->last_args[8];
				$this->visitors[ $uuid ]     = $row;
				$this->visitor_rows[ $uuid ] = $row;
				return 1;
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

		if ( array() !== $this->query_result_queue ) {
			return array_shift( $this->query_result_queue );
		}

		return false;
	}

	/**
	 * Add DECIMAL(16,4) fixture values without floating point conversion.
	 *
	 * @param string $current Current decimal value.
	 * @param string $increment Decimal increment.
	 * @return string Exact four-place decimal sum.
	 */
	private function add_decimal( string $current, string $increment ): string {
		$current_parts   = explode( '.', $current, 2 );
		$increment_parts = explode( '.', $increment, 2 );
		$current_units   = (int) $current_parts[0] * 10000 + (int) str_pad( $current_parts[1] ?? '', 4, '0' );
		$increment_units = (int) $increment_parts[0] * 10000 + (int) str_pad( $increment_parts[1] ?? '', 4, '0' );
		$sum             = $current_units + $increment_units;

		return intdiv( $sum, 10000 ) . '.' . str_pad( (string) ( $sum % 10000 ), 4, '0', STR_PAD_LEFT );
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
		$definitions  = array_merge(
			Journey_Schema_V1::get_table_definitions(),
			Journey_Schema_V2::get_table_definitions(),
		);
		if ( 'shurloc_journey_sessions' === $table_suffix && str_contains( $sql, 'classification_version' ) ) {
			$definitions[ $table_suffix ] = Journey_Schema_V3::get_table_definitions()[ $table_suffix ];
		}
		if ( isset( $definitions[ $table_suffix ] ) ) {
			$definition = $definitions[ $table_suffix ];

			$definition['engine'] = preg_match( '/\bENGINE=([a-zA-Z0-9_]+)/', $sql, $engine_matches )
				? $engine_matches[1]
				: '';
			$definition['engine'] = $this->table_engine_overrides[ $table_name ] ?? $definition['engine'];

			$this->tables[ $table_name ] = $definition;
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
