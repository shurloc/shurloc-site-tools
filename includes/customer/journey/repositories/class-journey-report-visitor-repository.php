<?php
/**
 * Customer Journey report-subject selector reads.
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
 * List recent report subjects and anonymous visitors without exposing UUIDs.
 *
 * Admin controllers must check report-viewing capability before calling this
 * repository. Visitors linked to any WordPress user belong in customer reports.
 *
 * @phpstan-type AnonymousVisitor array{id:int, created_at:string, last_seen_at:string, first_touch_at:string|null}
 * @phpstan-type RecentSubject array{subject_type:'customer'|'visitor', subject_id:int, last_activity_at:string}
 */
final class Journey_Report_Visitor_Repository {
	/** Maximum visitors in one selector page. */
	public const MAX_PAGE_SIZE = 100;

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
	 * Read authenticated users and never-linked visitors by latest event.
	 *
	 * Each subject appears once. Authenticated events are grouped by their
	 * server-recorded WordPress user ID. Anonymous visitors are included only
	 * while no identity period has ever linked that visitor to a user.
	 *
	 * When supplied, from_utc is inclusive and until_utc is exclusive. The two
	 * range boundaries must be supplied together.
	 *
	 * @param int         $limit     Number of report subjects, at most MAX_PAGE_SIZE.
	 * @param string|null $from_utc  Optional inclusive UTC datetime.
	 * @param string|null $until_utc Optional exclusive UTC datetime.
	 * @return list<RecentSubject>|null Recent subjects or null on failure.
	 */
	public function recent_subjects( int $limit = 50, ?string $from_utc = null, ?string $until_utc = null ): ?array {
		if (
			1 > $limit || self::MAX_PAGE_SIZE < $limit ||
			( null === $from_utc ) !== ( null === $until_utc ) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}
		if (
			null !== $from_utc && null !== $until_utc &&
			( ! $this->valid_time( value: $from_utc ) || ! $this->valid_time( value: $until_utc ) || $from_utc >= $until_utc )
		) {
			return null;
		}

		global $wpdb;
		$from_utc  = $from_utc ?? '1000-01-01 00:00:00';
		$until_utc = $until_utc ?? '9999-12-31 23:59:59';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT recent.subject_type, recent.subject_id, MAX(recent.occurred_at) AS last_activity_at
				FROM (
					SELECT \'customer\' AS subject_type, e.user_id_at_event AS subject_id, e.occurred_at
					FROM %i e
					WHERE e.user_id_at_event IS NOT NULL AND e.occurred_at >= %s AND e.occurred_at < %s
					UNION ALL
					SELECT \'visitor\' AS subject_type, e.visitor_id AS subject_id, e.occurred_at
					FROM %i e
					WHERE e.user_id_at_event IS NULL AND e.occurred_at >= %s AND e.occurred_at < %s
					AND NOT EXISTS (
						SELECT 1 FROM %i p WHERE p.visitor_id = e.visitor_id AND p.user_id IS NOT NULL
					)
				) recent
				GROUP BY recent.subject_type, recent.subject_id
				ORDER BY last_activity_at DESC, recent.subject_type ASC, recent.subject_id DESC
				LIMIT %d',
				$wpdb->prefix . 'shurloc_journey_events',
				$from_utc,
				$until_utc,
				$wpdb->prefix . 'shurloc_journey_events',
				$from_utc,
				$until_utc,
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$limit
			)
		);

		if ( ! is_array( $rows ) || count( $rows ) > $limit ) {
			return null;
		}

		$subjects = array();
		foreach ( $rows as $row ) {
			$subject = $this->parse_recent_subject( row: $row );
			if ( null === $subject ) {
				return null;
			}

			$subjects[] = $subject;
		}

		return $subjects;
	}

	/**
	 * Read a newest-first page of visitors with anonymous journey events.
	 *
	 * Pass the last row's last_seen_at and ID as the next cursor. Null means
	 * invalid input, unavailable schema, or an unavailable/malformed result.
	 * An empty array means no matching visitors remain.
	 *
	 * @param int         $limit      Number of visitors, at most MAX_PAGE_SIZE.
	 * @param string|null $before_at  Cursor UTC datetime, paired with before_id.
	 * @param int|null    $before_id  Cursor visitor ID, paired with before_at.
	 * @return list<AnonymousVisitor>|null Visitor page or null on failure.
	 */
	public function anonymous_visitors( int $limit = 50, ?string $before_at = null, ?int $before_id = null ): ?array {
		if (
			1 > $limit || self::MAX_PAGE_SIZE < $limit ||
			( null === $before_at ) !== ( null === $before_id ) ||
			( null !== $before_at && ! $this->valid_time( value: $before_at ) ) ||
			( null !== $before_id && 0 >= $before_id ) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT v.id, v.created_at, v.last_seen_at, v.first_touch_at
				FROM %i v
				WHERE (v.last_seen_at < %s OR (v.last_seen_at = %s AND v.id < %d))
				AND NOT EXISTS (
					SELECT 1 FROM %i p WHERE p.visitor_id = v.id AND p.user_id IS NOT NULL
				)
				AND EXISTS (
					SELECT 1 FROM %i e WHERE e.visitor_id = v.id AND e.user_id_at_event IS NULL
				)
				ORDER BY v.last_seen_at DESC, v.id DESC LIMIT %d',
				$wpdb->prefix . 'shurloc_journey_visitors',
				$before_at ?? '9999-12-31 23:59:59',
				$before_at ?? '9999-12-31 23:59:59',
				$before_id ?? PHP_INT_MAX,
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$wpdb->prefix . 'shurloc_journey_events',
				$limit
			)
		);

		if ( ! is_array( $rows ) || count( $rows ) > $limit ) {
			return null;
		}

		$visitors = array();
		foreach ( $rows as $row ) {
			$visitor = $this->parse_visitor( row: $row );
			if ( null === $visitor ) {
				return null;
			}

			$visitors[] = $visitor;
		}

		return $visitors;
	}

	/**
	 * Parse only fields needed by the anonymous visitor selector.
	 *
	 * @param mixed $row Database result.
	 * @return AnonymousVisitor|null Parsed visitor or null for an invalid row.
	 */
	private function parse_visitor( mixed $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id             = $this->positive_id( value: $row->id ?? null );
		$created_at     = $row->created_at ?? null;
		$last_seen_at   = $row->last_seen_at ?? null;
		$first_touch_at = $row->first_touch_at ?? null;

		if (
			null === $id || ! is_string( $created_at ) || ! $this->valid_time( value: $created_at ) ||
			! is_string( $last_seen_at ) || ! $this->valid_time( value: $last_seen_at ) ||
			$created_at > $last_seen_at ||
			( null !== $first_touch_at && ( ! is_string( $first_touch_at ) || ! $this->valid_time( value: $first_touch_at ) || $first_touch_at > $last_seen_at ) )
		) {
			return null;
		}

		return array(
			'id'             => $id,
			'created_at'     => $created_at,
			'last_seen_at'   => $last_seen_at,
			'first_touch_at' => $first_touch_at,
		);
	}

	/**
	 * Parse one recent report subject without accepting raw visitor identity.
	 *
	 * @param mixed $row Database result.
	 * @return RecentSubject|null Parsed subject or null for an invalid row.
	 */
	private function parse_recent_subject( mixed $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$subject_type     = $row->subject_type ?? null;
		$subject_id       = $this->positive_id( value: $row->subject_id ?? null );
		$last_activity_at = $row->last_activity_at ?? null;

		if (
			! in_array( $subject_type, array( 'customer', 'visitor' ), true ) ||
			null === $subject_id ||
			! is_string( $last_activity_at ) ||
			! $this->valid_time( value: $last_activity_at )
		) {
			return null;
		}

		return array(
			'subject_type'     => $subject_type,
			'subject_id'       => $subject_id,
			'last_activity_at' => $last_activity_at,
		);
	}

	/**
	 * Accept only a positive database identifier without lossy coercion.
	 *
	 * @param mixed $value Stored identifier.
	 * @return int|null Valid ID or null.
	 */
	private function positive_id( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Require a canonical UTC MySQL datetime.
	 *
	 * @param string $value Stored timestamp.
	 * @return bool Whether the timestamp is valid.
	 */
	private function valid_time( string $value ): bool {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return false !== $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $value;
	}
}
