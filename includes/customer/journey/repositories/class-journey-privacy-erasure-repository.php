<?php
/**
 * Customer Journey personal-data erasure storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use RuntimeException;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Throwable;

/**
 * Removes one user's Journey history without deleting another shared-browser identity.
 *
 * @phpstan-type ErasureBatch array{
 *     events_removed:int,
 *     sessions_removed:int,
 *     periods_removed:int,
 *     has_more:bool
 * }
 * @phpstan-type PeriodRoot array{id:int,visitor_id:int}
 */
final class Journey_Privacy_Erasure_Repository {
	/** Maximum records removed from one dependent table per request. */
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

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Remove one bounded batch from the user's oldest linked identity period.
	 *
	 * Events are removed before their sessions, and sessions before the identity
	 * period. A successful removal reports more work so the caller performs one
	 * final empty check before declaring the erasure complete.
	 *
	 * @param int $user_id    WordPress user ID whose Journey data is erased.
	 * @param int $batch_size Maximum events or sessions removed in this call.
	 * @return array|null Batch result, or null when erasure could not run safely.
	 * @phpstan-return ErasureBatch|null
	 */
	public function erase_user_batch( int $user_id, int $batch_size ): ?array {
		if (
			1 > $user_id ||
			1 > $batch_size ||
			self::MAX_BATCH_SIZE < $batch_size ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		global $wpdb;

		$period_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, visitor_id FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT 1',
				$this->periods_table(),
				$user_id
			)
		);

		if ( ! is_array( $period_rows ) || 1 < count( $period_rows ) ) {
			return null;
		}

		if ( array() === $period_rows ) {
			return $this->result( has_more: false );
		}

		$period = $this->parse_period_row( row: $period_rows[0] );
		if ( null === $period ) {
			return null;
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$this->lock_visitor( visitor_id: $period['visitor_id'] );

			$locked_period = $this->lock_period(
				period_id: $period['id'],
				visitor_id: $period['visitor_id'],
				user_id: $user_id,
			);

			if ( null === $locked_period ) {
				$this->commit();

				return $this->result( has_more: true );
			}

			$event_ids = $this->event_ids(
				period_id: $locked_period['id'],
				batch_size: $batch_size,
			);

			if ( array() !== $event_ids ) {
				$this->delete_ids(
					table: $this->events_table(),
					ids: $event_ids,
				);
				$this->commit();

				return $this->result(
					events_removed: count( $event_ids ),
					has_more: true,
				);
			}

			$session_ids = $this->session_ids(
				period_id: $locked_period['id'],
				batch_size: $batch_size,
			);

			if ( array() !== $session_ids ) {
				$this->delete_ids(
					table: $this->sessions_table(),
					ids: $session_ids,
				);
				$this->commit();

				return $this->result(
					sessions_removed: count( $session_ids ),
					has_more: true,
				);
			}

			$this->delete_period( period_id: $locked_period['id'] );
			$this->clear_visitor_attribution( visitor_id: $locked_period['visitor_id'] );
			$this->delete_orphan_visitor( visitor_id: $locked_period['visitor_id'] );
			$this->commit();

			return $this->result(
				periods_removed: 1,
				has_more: true,
			);
		} catch ( Throwable $error ) {
			unset( $error );
			$wpdb->query( 'ROLLBACK' );

			return null;
		}
	}
	// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

	/**
	 * Lock the visitor root used by identity and session creation.
	 *
	 * @param int $visitor_id Visitor database ID.
	 * @return void
	 * @throws RuntimeException When the visitor cannot be locked.
	 */
	private function lock_visitor( int $visitor_id ): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d FOR UPDATE',
				$this->visitors_table(),
				$visitor_id
			)
		);

		$ids = $this->parse_ids( rows: $rows, limit: 1 );
		if ( array( $visitor_id ) !== $ids ) {
			throw new RuntimeException( 'Journey privacy visitor lock failed.' );
		}
	}

	/**
	 * Lock and revalidate the probed identity period after locking its visitor.
	 *
	 * @param int $period_id  Identity-period database ID.
	 * @param int $visitor_id Visitor database ID.
	 * @param int $user_id    WordPress user ID.
	 * @return array|null Locked period, or null when another cleanup removed it.
	 * @phpstan-return PeriodRoot|null
	 * @throws RuntimeException When the database result is malformed.
	 */
	private function lock_period( int $period_id, int $visitor_id, int $user_id ): ?array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, visitor_id FROM %i WHERE id = %d AND visitor_id = %d AND user_id = %d FOR UPDATE',
				$this->periods_table(),
				$period_id,
				$visitor_id,
				$user_id
			)
		);

		if ( ! is_array( $rows ) || 1 < count( $rows ) ) {
			throw new RuntimeException( 'Journey privacy identity lock failed.' );
		}

		if ( array() === $rows ) {
			return null;
		}

		$period = $this->parse_period_row( row: $rows[0] );
		if ( null === $period || $period_id !== $period['id'] || $visitor_id !== $period['visitor_id'] ) {
			throw new RuntimeException( 'Journey privacy identity changed concurrently.' );
		}

		return $period;
	}

	/**
	 * Select one bounded event batch for the locked identity period.
	 *
	 * @param int $period_id  Identity-period database ID.
	 * @param int $batch_size Maximum event IDs.
	 * @return list<int> Event IDs.
	 * @throws RuntimeException When the database result is unavailable or malformed.
	 */
	private function event_ids( int $period_id, int $batch_size ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.id FROM %i e INNER JOIN %i s ON s.id = e.session_id
				WHERE s.identity_period_id = %d ORDER BY e.id ASC LIMIT %d FOR UPDATE',
				$this->events_table(),
				$this->sessions_table(),
				$period_id,
				$batch_size
			)
		);

		$ids = $this->parse_ids( rows: $rows, limit: $batch_size );
		if ( null === $ids ) {
			throw new RuntimeException( 'Journey privacy event selection failed.' );
		}

		return $ids;
	}

	/**
	 * Select one bounded session batch for the locked identity period.
	 *
	 * @param int $period_id  Identity-period database ID.
	 * @param int $batch_size Maximum session IDs.
	 * @return list<int> Session IDs.
	 * @throws RuntimeException When the database result is unavailable or malformed.
	 */
	private function session_ids( int $period_id, int $batch_size ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE identity_period_id = %d ORDER BY id ASC LIMIT %d FOR UPDATE',
				$this->sessions_table(),
				$period_id,
				$batch_size
			)
		);

		$ids = $this->parse_ids( rows: $rows, limit: $batch_size );
		if ( null === $ids ) {
			throw new RuntimeException( 'Journey privacy session selection failed.' );
		}

		return $ids;
	}

	/**
	 * Delete a validated ID batch and require every selected row to remain.
	 *
	 * @param string $table Full table name.
	 * @param array  $ids   Selected row IDs.
	 * @return void
	 * @throws RuntimeException When the delete fails or changes concurrently.
	 * @phpstan-param list<int> $ids
	 */
	private function delete_ids( string $table, array $ids ): void {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$arguments    = array_merge( array( $table ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only fixed %d placeholders are generated.
		$sql = 'DELETE FROM %i WHERE id IN (' . $placeholders . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL has generated placeholders for validated integer IDs.
		$deleted = $wpdb->query( $wpdb->prepare( $sql, ...$arguments ) );
		if ( count( $ids ) !== $deleted ) {
			throw new RuntimeException( 'Journey privacy dependent delete failed.' );
		}
	}

	/**
	 * Delete the now-empty identity period.
	 *
	 * @param int $period_id Identity-period database ID.
	 * @return void
	 * @throws RuntimeException When the period changes concurrently.
	 */
	private function delete_period( int $period_id ): void {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE id = %d',
				$this->periods_table(),
				$period_id
			)
		);

		if ( 1 !== $deleted ) {
			throw new RuntimeException( 'Journey privacy identity delete failed.' );
		}
	}

	/**
	 * Clear device-level first-touch values that may describe the erased person.
	 *
	 * @param int $visitor_id Visitor database ID.
	 * @return void
	 * @throws RuntimeException When attribution cannot be cleared.
	 */
	private function clear_visitor_attribution( int $visitor_id ): void {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET first_touch_at = NULL, first_landing_path = NULL, first_referrer_host = NULL, first_utm_source = NULL, first_utm_medium = NULL, first_utm_campaign = NULL, first_utm_term = NULL, first_utm_content = NULL WHERE id = %d',
				$this->visitors_table(),
				$visitor_id
			)
		);

		if ( false === $updated ) {
			throw new RuntimeException( 'Journey privacy attribution clearing failed.' );
		}
	}

	/**
	 * Remove the opaque visitor only when no other browser history depends on it.
	 *
	 * @param int $visitor_id Visitor database ID.
	 * @return void
	 * @throws RuntimeException When the orphan check or delete fails.
	 */
	private function delete_orphan_visitor( int $visitor_id ): void {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE v FROM %i v WHERE v.id = %d
				AND NOT EXISTS (SELECT 1 FROM %i p WHERE p.visitor_id = v.id)
				AND NOT EXISTS (SELECT 1 FROM %i s WHERE s.visitor_id = v.id)
				AND NOT EXISTS (SELECT 1 FROM %i e WHERE e.visitor_id = v.id)',
				$this->visitors_table(),
				$visitor_id,
				$this->periods_table(),
				$this->sessions_table(),
				$this->events_table()
			)
		);

		if ( false === $deleted ) {
			throw new RuntimeException( 'Journey privacy orphan cleanup failed.' );
		}
	}

	/**
	 * Parse a single identity-period root.
	 *
	 * @param mixed $row Database row.
	 * @return array|null Parsed root, or null for malformed data.
	 * @phpstan-return PeriodRoot|null
	 */
	private function parse_period_row( mixed $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id         = $this->positive_int( value: $row->id ?? null );
		$visitor_id = $this->positive_int( value: $row->visitor_id ?? null );

		return null === $id || null === $visitor_id
			? null
			: array(
				'id'         => $id,
				'visitor_id' => $visitor_id,
			);
	}

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

			$id = $this->positive_int( value: $row->id ?? null );
			if ( null === $id || in_array( $id, $ids, true ) ) {
				return null;
			}

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * Parse a positive integer returned by the database.
	 *
	 * @param mixed $value Untrusted database value.
	 * @return int|null Positive integer, or null.
	 */
	private function positive_int( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

		return false === $parsed ? null : $parsed;
	}

	/**
	 * Commit the erasure transaction.
	 *
	 * @return void
	 * @throws RuntimeException When the commit fails.
	 */
	private function commit(): void {
		global $wpdb;

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Journey privacy transaction could not commit.' );
		}
	}

	/**
	 * Build a consistent erasure result.
	 *
	 * @param int  $events_removed   Removed event count.
	 * @param int  $sessions_removed Removed session count.
	 * @param int  $periods_removed  Removed identity-period count.
	 * @param bool $has_more         Whether the caller must continue.
	 * @return array Erasure result.
	 * @phpstan-return ErasureBatch
	 */
	private function result(
		int $events_removed = 0,
		int $sessions_removed = 0,
		int $periods_removed = 0,
		bool $has_more = false
	): array {
		return array(
			'events_removed'   => $events_removed,
			'sessions_removed' => $sessions_removed,
			'periods_removed'  => $periods_removed,
			'has_more'         => $has_more,
		);
	}

	/** Get the current site's Journey visitors table. */
	private function visitors_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_visitors';
	}

	/** Get the current site's Journey identity-periods table. */
	private function periods_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_identity_periods';
	}

	/** Get the current site's Journey sessions table. */
	private function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_sessions';
	}

	/** Get the current site's Journey events table. */
	private function events_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_events';
	}
}
