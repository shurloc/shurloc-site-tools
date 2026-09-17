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
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Inserts fixed-shape events and recognizes retries by a unique event key.
 *
 * Trusted server code supplies visitor and session IDs. Event-specific input
 * validation and session summary updates belong to later collection units.
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
 */
final class Journey_Event_Repository {
	/**
	 * Journey schema readiness check.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator|null $schema_migrator Journey schema check.
	 */
	public function __construct( ?Journey_Schema_Migrator $schema_migrator = null ) {
		$this->schema_migrator = $schema_migrator ?? new Journey_Schema_Migrator();
	}

	/**
	 * Insert one trusted event or return the prior ID for a matching retry.
	 *
	 * An event key is optional except for ORDER_CREATED. The caller generates
	 * keys for logical events; the database unique index resolves races. A key
	 * collision with a different logical event fails closed.
	 *
	 * @param array<string,mixed> $event Validated, server-owned event fields.
	 * @return RecordResult|null Insert result, matching retry, or null on failure.
	 * @phpstan-param EventInput $event
	 */
	public function record( array $event ): ?array {
		if ( ! $this->is_valid( event: $event ) || ! $this->schema_migrator->is_ready() ) {
			return null;
		}

		global $wpdb;

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
			return array(
				'id'      => (int) $wpdb->insert_id,
				'created' => true,
			);
		}

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
			filter_var( $row->active_ms ?? null, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) !== ( $event['active_ms'] ?? 0 ) ||
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
