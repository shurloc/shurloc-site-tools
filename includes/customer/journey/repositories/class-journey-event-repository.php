<?php
/**
 * Customer Journey event storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Field_Validator;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Summary_Delta;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Atomically inserts events and their session summary increments.
 *
 * Trusted server code supplies visitor and session IDs. Browser claims must be
 * checked by a later collection layer before reaching this repository. Both
 * Journey tables must use a transactional storage engine for rollback safety.
 *
 * @phpstan-type EventInput array{
 *     session_id:int,
 *     visitor_id:int,
 *     user_id_at_event:int|null,
 *     event_type:string,
 *     occurred_at:string,
 *     page_path?:string|null,
 *     post_id?:int|null,
 *     product_id?:int|null,
 *     variation_id?:int|null,
 *     quantity?:string|null,
 *     order_id?:int|null,
 *     active_ms?:int,
 *     source?:string|null,
 *     idempotency_key?:string|null
 * }
 * @phpstan-type RecordResult array{id:int,created:bool}
 * @phpstan-import-type SummaryDelta from Journey_Event_Summary_Delta
 */
final class Journey_Event_Repository {
	/** Allow for request latency and second-resolution event timestamps. */
	public const DEFAULT_DURATION_GRACE_SECONDS = 60;

	/** Filter the duration plausibility allowance in seconds. */
	public const DURATION_GRACE_FILTER = 'shurloc_site_tools_journey_duration_grace_seconds';

	/** Maximum accepted filtered allowance in seconds. */
	private const MAX_DURATION_GRACE_SECONDS = 3600;

	/**
	 * Type-specific field rules.
	 *
	 * @var Journey_Event_Field_Validator
	 */
	private Journey_Event_Field_Validator $field_validator;

	/**
	 * Journey schema readiness check.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator|null       $schema_migrator Journey schema check.
	 * @param Journey_Event_Field_Validator|null $field_validator Type-specific rules.
	 */
	public function __construct(
		?Journey_Schema_Migrator $schema_migrator = null,
		?Journey_Event_Field_Validator $field_validator = null
	) {
		$this->schema_migrator = $schema_migrator ?? new Journey_Schema_Migrator();
		$this->field_validator = $field_validator ?? new Journey_Event_Field_Validator();
	}

	/**
	 * Insert one trusted event or return the prior ID for a matching retry.
	 *
	 * An event key is optional except for ORDER_CREATED. The caller generates
	 * keys for logical events; the database unique index resolves races. A key
	 * collision with a different logical event fails closed. Event insertion and
	 * the matching session's counter increments share one database transaction.
	 *
	 * @param array<string,mixed> $event Validated, server-owned event fields.
	 * @return RecordResult|null Insert result, matching retry, or null on failure.
	 * @phpstan-param EventInput $event
	 */
	public function record( array $event ): ?array {
		if (
			! $this->field_validator->is_valid( event: $event ) ||
			! $this->is_valid( event: $event ) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		$delta = Journey_Event_Summary_Delta::for_event(
			event_type: $event['event_type'],
			quantity: $event['quantity'] ?? null,
			active_ms: $event['active_ms'] ?? 0,
		);
		if ( null === $delta ) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		$key      = $event['idempotency_key'] ?? null;
		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'session_id'        => $event['session_id'],
				'visitor_id'        => $event['visitor_id'],
				'user_id_at_event'  => $event['user_id_at_event'],
				'event_type'        => $event['event_type'],
				'occurred_at'       => $event['occurred_at'],
				'page_path'         => $event['page_path'] ?? null,
				'post_id'           => $event['post_id'] ?? null,
				'product_id'        => $event['product_id'] ?? null,
				'variation_id'      => $event['variation_id'] ?? null,
				'quantity'          => $event['quantity'] ?? null,
				'order_id'          => $event['order_id'] ?? null,
				'related_object_id' => null,
				'active_ms'         => $event['active_ms'] ?? 0,
				'source'            => $event['source'] ?? null,
				'idempotency_key'   => $key,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( 1 === $inserted && 0 < $wpdb->insert_id ) {
			$id = (int) $wpdb->insert_id;
			if (
				! $this->increment_session_summary(
					session_id: $event['session_id'],
					visitor_id: $event['visitor_id'],
					delta: $delta
				) ||
				false === $wpdb->query( 'COMMIT' )
			) {
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			return array(
				'id'      => $id,
				'created' => true,
			);
		}

		$wpdb->query( 'ROLLBACK' );
		if ( null === $key ) {
			return null;
		}

		$id = $this->matching_retry_id( key: $key, event: $event );
		return null === $id ? null : array(
			'id'      => $id,
			'created' => false,
		);
	}

	/**
	 * Store a view's cumulative visible duration without recounting the view.
	 *
	 * The caller must resolve the visitor from the current server request. A
	 * cumulative total makes duplicate and out-of-order browser deliveries safe.
	 * The event row is locked while the increase is applied to both the event
	 * and its original session. The total cannot exceed elapsed server time since
	 * the view plus a filtered delivery allowance. This is a plausibility bound,
	 * not an inactivity cutoff. Duration updates do not extend session activity.
	 *
	 * @param int $event_id        Existing page or product view event ID.
	 * @param int $visitor_id      Server-resolved visitor ID.
	 * @param int $total_active_ms Cumulative visible time for this view.
	 * @return bool Whether the total was accepted or was already recorded.
	 */
	public function record_view_duration( int $event_id, int $visitor_id, int $total_active_ms ): bool {
		if (
			0 >= $event_id ||
			0 >= $visitor_id ||
			0 > $total_active_ms ||
			! $this->schema_migrator->is_ready()
		) {
			return false;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT session_id, event_type, occurred_at, active_ms FROM %i WHERE id = %d AND visitor_id = %d LIMIT 2 FOR UPDATE',
				$this->table_name(),
				$event_id,
				$visitor_id
			)
		);

		if ( ! is_array( $rows ) || 1 !== count( $rows ) || ! is_object( $rows[0] ) ||
			! isset( $rows[0]->session_id, $rows[0]->event_type, $rows[0]->occurred_at, $rows[0]->active_ms ) ||
			! Journey_Event_Type::counts_as_page_view( value: $rows[0]->event_type ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$session_id  = filter_var( $rows[0]->session_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$stored_ms   = filter_var( $rows[0]->active_ms, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		$occurred_at = is_string( $rows[0]->occurred_at )
			? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $rows[0]->occurred_at, new DateTimeZone( 'UTC' ) )
			: false;
		if ( false === $session_id || false === $stored_ms || false === $occurred_at ||
			$occurred_at->format( 'Y-m-d H:i:s' ) !== $rows[0]->occurred_at ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		if ( $total_active_ms > $stored_ms ) {
			$grace_seconds = apply_filters( self::DURATION_GRACE_FILTER, self::DEFAULT_DURATION_GRACE_SECONDS );
			if ( ! is_int( $grace_seconds ) || 0 > $grace_seconds || self::MAX_DURATION_GRACE_SECONDS < $grace_seconds ) {
				$grace_seconds = self::DEFAULT_DURATION_GRACE_SECONDS;
			}

			$elapsed_seconds = time() - $occurred_at->getTimestamp();
			if ( -$grace_seconds > $elapsed_seconds ||
				$total_active_ms > ( max( 0, $elapsed_seconds ) + $grace_seconds ) * 1000 ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}

			$delta = Journey_Event_Summary_Delta::for_view_duration( active_ms: $total_active_ms - $stored_ms );
			if ( null === $delta ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}

			$updated = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET active_ms = %d WHERE id = %d AND visitor_id = %d AND active_ms = %d',
					$this->table_name(),
					$total_active_ms,
					$event_id,
					$visitor_id,
					$stored_ms
				)
			);

			if ( 1 !== $updated || ! $this->increment_session_summary(
				session_id: $session_id,
				visitor_id: $visitor_id,
				delta: $delta
			) ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		return true;
	}

	/**
	 * Apply one safe, sparse counter delta to the owning session.
	 *
	 * Each identifier comes from the fixed internal delta map and is prepared
	 * as a database identifier. Decimal quantities are cast explicitly, so
	 * arithmetic does not go through PHP floating point values.
	 *
	 * @param int                 $session_id Session ID.
	 * @param int                 $visitor_id Visitor ID.
	 * @param array<string,mixed> $delta      Counter increments.
	 * @return bool Whether exactly one session row was updated.
	 * @phpstan-param SummaryDelta $delta
	 */
	private function increment_session_summary( int $session_id, int $visitor_id, array $delta ): bool {
		global $wpdb;

		$assignments = array();
		$args        = array( $wpdb->prefix . 'shurloc_journey_sessions' );
		foreach ( $delta as $column => $increment ) {
			$assignments[] = '%i = %i + ' . ( is_int( $increment ) ? '%d' : 'CAST(%s AS DECIMAL(16,4))' );
			$args[]        = $column;
			$args[]        = $column;
			$args[]        = $increment;
		}

		$args[] = $session_id;
		$args[] = $visitor_id;
		$sql    = 'UPDATE %i SET ' . implode( ', ', $assignments ) . ' WHERE id = %d AND visitor_id = %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal delta keys are identifier placeholders; every value is prepared.
		return 1 === $wpdb->query( $wpdb->prepare( $sql, ...$args ) );
	}

	/**
	 * Reject values that cannot fit the fixed v1 event columns.
	 *
	 * @param array<string,mixed> $event Trusted event fields.
	 * @return bool Whether the row is structurally valid.
	 * @phpstan-param EventInput $event
	 */
	private function is_valid( array $event ): bool {
		$occurred_at = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$event['occurred_at'],
			new DateTimeZone( 'UTC' )
		);
		$key         = $event['idempotency_key'] ?? null;
		$path        = $event['page_path'] ?? null;
		$quantity    = $event['quantity'] ?? null;
		$source      = $event['source'] ?? null;

		if (
			0 >= $event['session_id'] ||
			0 >= $event['visitor_id'] ||
			( null !== $event['user_id_at_event'] && 0 >= $event['user_id_at_event'] ) ||
			! Journey_Event_Type::is_supported( value: $event['event_type'] ) ||
			false === $occurred_at ||
			$occurred_at->format( 'Y-m-d H:i:s' ) !== $event['occurred_at'] ||
			( null !== $key && 1 !== preg_match( '/\A[0-9a-f]{64}\z/', $key ) ) ||
			( Journey_Event_Type::ORDER_CREATED === $event['event_type'] && null === $key ) ||
			( Journey_Event_Type::ORDER_CREATED === $event['event_type'] && ! isset( $event['order_id'] ) ) ||
			( null !== $path && ( '' === $path || 1024 < strlen( $path ) || ! str_starts_with( $path, '/' ) || str_starts_with( $path, '//' ) || str_contains( $path, '?' ) || str_contains( $path, '#' ) ) ) ||
			( null !== $source && ( '' === $source || 32 < strlen( $source ) || 1 !== preg_match( '/\A[a-z][a-z0-9_]*\z/', $source ) ) ) ||
			0 > ( $event['active_ms'] ?? 0 )
		) {
			return false;
		}

		foreach ( array( 'post_id', 'product_id', 'variation_id', 'order_id' ) as $column ) {
			if ( isset( $event[ $column ] ) && 0 >= $event[ $column ] ) {
				return false;
			}
		}

		if ( null !== $quantity && (
			1 !== preg_match( '/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/', $quantity ) ||
			1 !== preg_match( '/[1-9]/', $quantity )
		) ) {
			return false;
		}

		return true;
	}

	/**
	 * Look up an existing logical event after a keyed insert fails.
	 *
	 * @param string              $key   Canonical idempotency key.
	 * @param array<string,mixed> $event Event being retried.
	 * @return int|null Matching existing event ID, or null on mismatch or failure.
	 * @phpstan-param EventInput $event
	 */
	private function matching_retry_id( string $key, array $event ): ?int {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, visitor_id, event_type, order_id, page_path, post_id, product_id, variation_id, quantity, active_ms, source FROM %i WHERE idempotency_key = %s LIMIT 2',
				$this->table_name(),
				$key
			)
		);

		if ( ! is_array( $rows ) || 1 !== count( $rows ) ) {
			return null;
		}

		$row = $rows[0];
		if ( ! is_object( $row ) || ! isset( $row->id, $row->visitor_id, $row->event_type ) ||
			! is_string( $row->event_type ) || $row->event_type !== $event['event_type'] ) {
			return null;
		}

		$id = filter_var( $row->id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $id ) {
			return null;
		}

		if ( Journey_Event_Type::ORDER_CREATED === $event['event_type'] ) {
			$order_id = $event['order_id'] ?? null;
			return null !== $order_id && isset( $row->order_id ) &&
				filter_var( $row->order_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) === $order_id
				? $id
				: null;
		}

		if (
			filter_var( $row->visitor_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) !== $event['visitor_id'] ||
			( $row->page_path ?? null ) !== ( $event['page_path'] ?? null ) ||
			$this->optional_id( $row->post_id ?? null ) !== ( $event['post_id'] ?? null ) ||
			$this->optional_id( $row->product_id ?? null ) !== ( $event['product_id'] ?? null ) ||
			$this->optional_id( $row->variation_id ?? null ) !== ( $event['variation_id'] ?? null ) ||
			$this->optional_id( $row->order_id ?? null ) !== ( $event['order_id'] ?? null ) ||
			$this->normalize_quantity( $row->quantity ?? null ) !== $this->normalize_quantity( $event['quantity'] ?? null ) ||
			( ! Journey_Event_Type::counts_as_page_view( value: $event['event_type'] ) &&
				filter_var( $row->active_ms ?? null, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) !== ( $event['active_ms'] ?? 0 ) ) ||
			( $row->source ?? null ) !== ( $event['source'] ?? null )
		) {
			return null;
		}

		return $id;
	}

	/**
	 * Convert a nullable stored ID without accepting malformed rows.
	 *
	 * @param mixed $value Stored ID.
	 * @return int|false|null Stored ID, null, or false for a malformed value.
	 */
	private function optional_id( mixed $value ): int|false|null {
		if ( null === $value ) {
			return null;
		}

		$id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return $id;
	}

	/**
	 * Compare decimal quantities regardless of database scale padding.
	 *
	 * @param mixed $value Decimal value.
	 * @return string|null Canonical decimal or null for a null value.
	 */
	private function normalize_quantity( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$parts = explode( '.', (string) $value, 2 );
		if ( 1 === count( $parts ) ) {
			return $parts[0];
		}

		$fraction = rtrim( $parts[1], '0' );
		return '' === $fraction ? $parts[0] : $parts[0] . '.' . $fraction;
	}

	/**
	 * Use the current WordPress site's Journey events table.
	 *
	 * @return string Full table name.
	 */
	private function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'shurloc_journey_events';
	}
}
