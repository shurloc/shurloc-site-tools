<?php
/**
 * Customer Journey retention cleanup storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Throwable;

/**
 * Deletes expired Journey records in bounded, dependency-safe batches.
 */
final class Journey_Retention_Repository {
	/** Maximum roots affected by one retention operation. */
	public const MAX_BATCH_SIZE = 1000;

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
	 * Delete one oldest-first batch of raw events.
	 *
	 * @param string $cutoff_utc Exclusive UTC MySQL datetime cutoff.
	 * @param int    $batch_size Maximum events to delete.
	 * @return int|null Deleted event count, or null on failure.
	 */
	public function delete_events_before( string $cutoff_utc, int $batch_size ): ?int {
		if ( ! $this->can_run( cutoff_utc: $cutoff_utc, batch_size: $batch_size ) ) {
			return null;
		}

		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE occurred_at < %s ORDER BY occurred_at ASC, id ASC LIMIT %d',
				$this->events_table(),
				$cutoff_utc,
				$batch_size
			)
		);

		return false === $deleted ? null : $deleted;
	}

	/**
	 * Delete a batch of stale visitors that have never been identified.
	 *
	 * Cart links, events, sessions, and periods are removed before each visitor
	 * row in the same transaction. The returned count is the number of visitor
	 * roots.
	 *
	 * @param string $cutoff_utc Exclusive last-seen UTC cutoff.
	 * @param int    $batch_size Maximum visitors to delete.
	 * @return int|null Deleted visitor count, or null on failure.
	 */
	public function delete_anonymous_histories_before( string $cutoff_utc, int $batch_size ): ?int {
		return $this->delete_visitor_histories_before(
			cutoff_utc: $cutoff_utc,
			batch_size: $batch_size,
			identified: false,
		);
	}

	/**
	 * Delete a batch of stale visitors that have identified history.
	 *
	 * @param string $cutoff_utc Exclusive last-seen UTC cutoff.
	 * @param int    $batch_size Maximum visitors to delete.
	 * @return int|null Deleted visitor count, or null on failure.
	 */
	public function delete_identified_histories_before( string $cutoff_utc, int $batch_size ): ?int {
		return $this->delete_visitor_histories_before(
			cutoff_utc: $cutoff_utc,
			batch_size: $batch_size,
			identified: true,
		);
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Delete old sessions belonging to visitors with identified history.
	 *
	 * Any remaining cart links and events for a selected session are deleted
	 * first. This keeps the operation safe when retention scopes are configured
	 * in an unexpected order.
	 *
	 * @param string $cutoff_utc Exclusive last-activity UTC cutoff.
	 * @param int    $batch_size Maximum sessions to delete.
	 * @return int|null Deleted session count, or null on failure.
	 */
	public function delete_identified_sessions_before( string $cutoff_utc, int $batch_size ): ?int {
		if ( ! $this->can_run( cutoff_utc: $cutoff_utc, batch_size: $batch_size ) ) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT s.id FROM %i s
					WHERE s.last_activity_at < %s
					AND EXISTS (
						SELECT 1 FROM %i p
						WHERE p.visitor_id = s.visitor_id AND p.user_id IS NOT NULL
					)
					ORDER BY s.last_activity_at ASC, s.id ASC LIMIT %d FOR UPDATE',
					$this->sessions_table(),
					$cutoff_utc,
					$this->periods_table(),
					$batch_size
				)
			);

			$session_ids = $this->parse_ids( rows: $rows, limit: $batch_size );
			if ( null === $session_ids ) {
				throw new RuntimeException( 'Journey session retention selection failed.' );
			}

			if ( array() !== $session_ids ) {
				$this->require_delete(
					table: $this->cart_links_table(),
					column: 'session_id',
					ids: $session_ids,
				);

				$this->require_delete(
					table: $this->events_table(),
					column: 'session_id',
					ids: $session_ids,
				);

				$deleted = $this->require_delete(
					table: $this->sessions_table(),
					column: 'id',
					ids: $session_ids,
				);

				if ( count( $session_ids ) !== $deleted ) {
					throw new RuntimeException( 'Journey session retention changed concurrently.' );
				}
			}

			$this->commit();

			return count( $session_ids );
		} catch ( Throwable $error ) {
			unset( $error );
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
	}
	// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

	/**
	 * Clear expired visitor first-touch attribution for identified history.
	 *
	 * @param string $cutoff_utc Exclusive first-touch UTC cutoff.
	 * @param int    $batch_size Maximum visitors to update.
	 * @return int|null Updated visitor count, or null on failure.
	 */
	public function clear_identified_attribution_before( string $cutoff_utc, int $batch_size ): ?int {
		if ( ! $this->can_run( cutoff_utc: $cutoff_utc, batch_size: $batch_size ) ) {
			return null;
		}

		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET first_touch_at = NULL, first_landing_path = NULL, first_referrer_host = NULL, first_utm_source = NULL, first_utm_medium = NULL, first_utm_campaign = NULL, first_utm_term = NULL, first_utm_content = NULL
				WHERE first_touch_at < %s AND EXISTS (
					SELECT 1 FROM %i p WHERE p.visitor_id = %i.id AND p.user_id IS NOT NULL
				)
				ORDER BY first_touch_at ASC, id ASC LIMIT %d',
				$this->visitors_table(),
				$cutoff_utc,
				$this->periods_table(),
				$this->visitors_table(),
				$batch_size
			)
		);

		return false === $updated ? null : $updated;
	}

	/**
	 * Delete expired linked identity periods no longer used by a session.
	 *
	 * Open periods and periods referenced by retained sessions are preserved.
	 *
	 * @param string $cutoff_utc Exclusive period-end UTC cutoff.
	 * @param int    $batch_size Maximum periods to delete.
	 * @return int|null Deleted period count, or null on failure.
	 */
	public function delete_unreferenced_identity_periods_before( string $cutoff_utc, int $batch_size ): ?int {
		if ( ! $this->can_run( cutoff_utc: $cutoff_utc, batch_size: $batch_size ) ) {
			return null;
		}

		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
				WHERE user_id IS NOT NULL AND ended_at < %s
				AND NOT EXISTS (
					SELECT 1 FROM %i s WHERE s.identity_period_id = %i.id
				)
				ORDER BY ended_at ASC, id ASC LIMIT %d',
				$this->periods_table(),
				$cutoff_utc,
				$this->sessions_table(),
				$this->periods_table(),
				$batch_size
			)
		);

		return false === $deleted ? null : $deleted;
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Delete visitor-rooted histories under one identity classification.
	 *
	 * @param string $cutoff_utc Exclusive last-seen UTC cutoff.
	 * @param int    $batch_size Maximum visitors to delete.
	 * @param bool   $identified Whether selected visitors must have a user link.
	 * @return int|null Deleted visitor count, or null on failure.
	 */
	private function delete_visitor_histories_before(
		string $cutoff_utc,
		int $batch_size,
		bool $identified
	): ?int {
		if ( ! $this->can_run( cutoff_utc: $cutoff_utc, batch_size: $batch_size ) ) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			if ( $identified ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT v.id FROM %i v
						WHERE v.last_seen_at < %s AND EXISTS (
							SELECT 1 FROM %i p WHERE p.visitor_id = v.id AND p.user_id IS NOT NULL
						)
						ORDER BY v.last_seen_at ASC, v.id ASC LIMIT %d FOR UPDATE',
						$this->visitors_table(),
						$cutoff_utc,
						$this->periods_table(),
						$batch_size
					)
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT v.id FROM %i v
						WHERE v.last_seen_at < %s AND NOT EXISTS (
							SELECT 1 FROM %i p WHERE p.visitor_id = v.id AND p.user_id IS NOT NULL
						)
						ORDER BY v.last_seen_at ASC, v.id ASC LIMIT %d FOR UPDATE',
						$this->visitors_table(),
						$cutoff_utc,
						$this->periods_table(),
						$batch_size
					)
				);
			}

			$visitor_ids = $this->parse_ids( rows: $rows, limit: $batch_size );
			if ( null === $visitor_ids ) {
				throw new RuntimeException( 'Journey visitor retention selection failed.' );
			}

			if ( array() !== $visitor_ids ) {
				$this->require_delete( table: $this->cart_links_table(), column: 'visitor_id', ids: $visitor_ids );
				$this->require_delete( table: $this->events_table(), column: 'visitor_id', ids: $visitor_ids );
				$this->require_delete( table: $this->sessions_table(), column: 'visitor_id', ids: $visitor_ids );
				$this->require_delete( table: $this->periods_table(), column: 'visitor_id', ids: $visitor_ids );

				$deleted = $this->require_delete(
					table: $this->visitors_table(),
					column: 'id',
					ids: $visitor_ids,
				);

				if ( count( $visitor_ids ) !== $deleted ) {
					throw new RuntimeException( 'Journey visitor retention changed concurrently.' );
				}
			}

			$this->commit();

			return count( $visitor_ids );
		} catch ( Throwable $error ) {
			unset( $error );
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
	}
	// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

	/**
	 * Parse a bounded database ID result without numeric coercion.
	 *
	 * @param mixed $rows  Database result rows.
	 * @param int   $limit Maximum expected row count.
	 * @return list<int>|null IDs, or null for unavailable or malformed data.
	 */
	private function parse_ids( mixed $rows, int $limit ): ?array {
		if ( ! is_array( $rows ) || count( $rows ) > $limit ) {
			return null;
		}

		$ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				return null;
			}

			$value = $row->id ?? null;
			if ( ! is_int( $value ) && ! is_string( $value ) ) {
				return null;
			}

			$id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false === $id || in_array( $id, $ids, true ) ) {
				return null;
			}

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * Delete records matching a validated list of integer IDs.
	 *
	 * @param string $table  Full table name.
	 * @param string $column ID column name.
	 * @param array  $ids    Positive IDs.
	 * @return int Affected row count.
	 * @throws RuntimeException When the delete fails.
	 * @phpstan-param list<int> $ids
	 */
	private function require_delete( string $table, string $column, array $ids ): int {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$arguments    = array_merge( array( $table, $column ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only fixed %d placeholders are generated.
		$sql = 'DELETE FROM %i WHERE %i IN (' . $placeholders . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL has generated placeholders for validated integer IDs.
		$deleted = $wpdb->query( $wpdb->prepare( $sql, ...$arguments ) );
		if ( false === $deleted ) {
			throw new RuntimeException( 'Journey dependent retention delete failed.' );
		}

		return $deleted;
	}

	/**
	 * Commit the current cleanup transaction.
	 *
	 * @return void
	 * @throws RuntimeException When the commit fails.
	 */
	private function commit(): void {
		global $wpdb;

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Journey retention transaction could not commit.' );
		}
	}

	/**
	 * Validate a retention operation and the Journey schema gate.
	 *
	 * @param string $cutoff_utc UTC MySQL datetime.
	 * @param int    $batch_size Requested batch size.
	 * @return bool Whether database cleanup may run.
	 */
	private function can_run( string $cutoff_utc, int $batch_size ): bool {
		$cutoff = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$cutoff_utc,
			new DateTimeZone( 'UTC' )
		);

		return false !== $cutoff &&
			$cutoff->format( 'Y-m-d H:i:s' ) === $cutoff_utc &&
			1 <= $batch_size &&
			self::MAX_BATCH_SIZE >= $batch_size &&
			$this->schema_migrator->is_ready();
	}

	/**
	 * Get the current site visitor table name.
	 *
	 * @return string Full visitor table name.
	 */
	private function visitors_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_visitors';
	}

	/**
	 * Get the current site identity-period table name.
	 *
	 * @return string Full identity-period table name.
	 */
	private function periods_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_identity_periods';
	}

	/**
	 * Get the current site session table name.
	 *
	 * @return string Full session table name.
	 */
	private function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_sessions';
	}

	/**
	 * Get the current site event table name.
	 *
	 * @return string Full event table name.
	 */
	private function events_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_events';
	}

	/**
	 * Get the current site cart-links table name.
	 *
	 * @return string Full cart-links table name.
	 */
	private function cart_links_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_cart_links';
	}
}
