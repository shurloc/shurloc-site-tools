<?php
/**
 * Customer Journey report reads.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Read bounded event pages for a customer's historical identity periods.
 *
 * A visitor can later be used by a different account. An anonymous event
 * belongs to a customer only when it falls inside a period linked to that
 * customer; authenticated events use their user ID captured at event time.
 * Session summary counters cannot be used for these customer totals because
 * a session can cross an identity transition. Admin controllers must check
 * report-viewing capability before calling this repository.
 *
 * @phpstan-type ReportEvent array{
 *     id:int,
 *     session_id:int,
 *     visitor_id:int,
 *     user_id_at_event:int|null,
 *     event_type:string,
 *     occurred_at:string,
 *     page_path:string|null,
 *     post_id:int|null,
 *     product_id:int|null,
 *     variation_id:int|null,
 *     quantity:string|null,
 *     order_id:int|null,
 *     related_object_id:int|null,
 *     active_ms:int,
 *     source:string|null
 * }
 */
final class Journey_Report_Repository {
	/** Maximum events in one admin report query. */
	public const MAX_PAGE_SIZE = 100;

	/** Maximum UTC date range scanned by one admin report query. */
	public const MAX_RANGE_DAYS = 31;

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
	 * Read one newest-first page in a required, half-open UTC date range.
	 *
	 * Pass the last returned event's occurred_at and ID as the next cursor.
	 * Null means invalid input, unavailable schema, or an unavailable/malformed
	 * database result. An empty array means the valid query found no events.
	 *
	 * @param int         $user_id    WordPress customer ID.
	 * @param string      $from_utc   Inclusive UTC MySQL datetime.
	 * @param string      $until_utc  Exclusive UTC MySQL datetime.
	 * @param int         $limit      Number of events, at most MAX_PAGE_SIZE.
	 * @param string|null $before_at  Cursor UTC datetime, paired with before_id.
	 * @param int|null    $before_id  Cursor event ID, paired with before_at.
	 * @return list<ReportEvent>|null Event page or null on failure.
	 */
	public function customer_events(
		int $user_id,
		string $from_utc,
		string $until_utc,
		int $limit = 50,
		?string $before_at = null,
		?int $before_id = null
	): ?array {
		$from_timestamp  = $this->timestamp( value: $from_utc );
		$until_timestamp = $this->timestamp( value: $until_utc );

		if (
			0 >= $user_id ||
			null === $from_timestamp ||
			null === $until_timestamp ||
			$from_timestamp >= $until_timestamp ||
			$until_timestamp - $from_timestamp > self::MAX_RANGE_DAYS * 86400 ||
			1 > $limit ||
			self::MAX_PAGE_SIZE < $limit ||
			( null === $before_at ) !== ( null === $before_id ) ||
			( null !== $before_at && ( null === $this->timestamp( value: $before_at ) || $before_at < $from_utc || $before_at >= $until_utc ) ) ||
			( null !== $before_id && 0 >= $before_id ) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.id, e.session_id, e.visitor_id, e.user_id_at_event, e.event_type, e.occurred_at, e.page_path, e.post_id, e.product_id, e.variation_id, e.quantity, e.order_id, e.related_object_id, e.active_ms, e.source
				FROM %i e
				WHERE e.occurred_at >= %s AND e.occurred_at < %s
				AND (e.occurred_at < %s OR (e.occurred_at = %s AND e.id < %d))
				AND (e.user_id_at_event = %d OR (e.user_id_at_event IS NULL AND EXISTS (
					SELECT 1 FROM %i p WHERE p.visitor_id = e.visitor_id AND p.user_id = %d
					AND p.linked_at IS NOT NULL AND p.started_at <= e.occurred_at AND p.ended_at > e.occurred_at
					AND NOT EXISTS (
						SELECT 1 FROM %i prior WHERE prior.visitor_id = e.visitor_id AND prior.ended_at = e.occurred_at
					)
				)))
				ORDER BY e.occurred_at DESC, e.id DESC LIMIT %d',
				$wpdb->prefix . 'shurloc_journey_events',
				$from_utc,
				$until_utc,
				$before_at ?? $until_utc,
				$before_at ?? $until_utc,
				$before_id ?? PHP_INT_MAX,
				$user_id,
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$user_id,
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$limit
			)
		);

		if ( ! is_array( $rows ) || count( $rows ) > $limit ) {
			return null;
		}

		$events = array();
		foreach ( $rows as $row ) {
			$event = $this->parse_event( row: $row );
			if ( null === $event ) {
				return null;
			}

			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Parse one database event into report-safe primitive types.
	 *
	 * @param mixed $row Database result.
	 * @return ReportEvent|null Parsed event or null for an invalid row.
	 */
	private function parse_event( mixed $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id         = $this->integer( value: $row->id ?? null, minimum: 1 );
		$session_id = $this->integer( value: $row->session_id ?? null, minimum: 1 );
		$visitor_id = $this->integer( value: $row->visitor_id ?? null, minimum: 1 );
		$active_ms  = $this->integer( value: $row->active_ms ?? null, minimum: 0 );

		if (
			null === $id || null === $session_id || null === $visitor_id || null === $active_ms ||
			! isset( $row->event_type, $row->occurred_at ) ||
			! is_string( $row->event_type ) || '' === $row->event_type ||
			! is_string( $row->occurred_at ) || null === $this->timestamp( value: $row->occurred_at )
		) {
			return null;
		}

		foreach ( array( 'user_id_at_event', 'post_id', 'product_id', 'variation_id', 'order_id', 'related_object_id' ) as $field ) {
			if ( null !== ( $row->$field ?? null ) && null === $this->integer( value: $row->$field, minimum: 1 ) ) {
				return null;
			}
		}

		foreach ( array( 'page_path', 'quantity', 'source' ) as $field ) {
			if ( null !== ( $row->$field ?? null ) && ! is_string( $row->$field ) ) {
				return null;
			}
		}

		return array(
			'id'                => $id,
			'session_id'        => $session_id,
			'visitor_id'        => $visitor_id,
			'user_id_at_event'  => $this->optional_id( value: $row->user_id_at_event ?? null ),
			'event_type'        => $row->event_type,
			'occurred_at'       => $row->occurred_at,
			'page_path'         => $row->page_path ?? null,
			'post_id'           => $this->optional_id( value: $row->post_id ?? null ),
			'product_id'        => $this->optional_id( value: $row->product_id ?? null ),
			'variation_id'      => $this->optional_id( value: $row->variation_id ?? null ),
			'quantity'          => $row->quantity ?? null,
			'order_id'          => $this->optional_id( value: $row->order_id ?? null ),
			'related_object_id' => $this->optional_id( value: $row->related_object_id ?? null ),
			'active_ms'         => $active_ms,
			'source'            => $row->source ?? null,
		);
	}

	/**
	 * Parse a nullable database identifier after the row has been validated.
	 *
	 * @param mixed $value Stored identifier.
	 * @return int|null Identifier or null.
	 */
	private function optional_id( mixed $value ): ?int {
		return null === $value ? null : $this->integer( value: $value, minimum: 1 );
	}

	/**
	 * Accept only canonical decimal integers within the platform range.
	 *
	 * @param mixed $value   Stored integer.
	 * @param int   $minimum Smallest accepted value.
	 * @return int|null Valid integer or null.
	 */
	private function integer( mixed $value, int $minimum ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => $minimum ) ) );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Parse a real UTC MySQL datetime, including leap-day validation.
	 *
	 * @param string $value UTC timestamp.
	 * @return int|null Unix timestamp or null for an invalid value.
	 */
	private function timestamp( string $value ): ?int {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return false !== $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $value ? $parsed->getTimestamp() : null;
	}
}
