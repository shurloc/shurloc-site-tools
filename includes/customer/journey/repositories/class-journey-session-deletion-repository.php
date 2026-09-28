<?php
/**
 * Customer Journey individual session deletion.
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
 * Delete one Journey session and its dependent records atomically.
 *
 * Visitor and identity-period records are intentionally preserved because
 * they can belong to other sessions for the same browser or customer.
 */
final class Journey_Session_Deletion_Repository {
	/** Maximum customer and visitor subjects accepted in one bulk deletion. */
	public const MAX_SUBJECTS = 50;

	/**
	 * Journey schema readiness check.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Cart-session correlation cleanup.
	 *
	 * @var Journey_Cart_Link_Repository
	 */
	private Journey_Cart_Link_Repository $cart_links;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator|null      $schema_migrator Journey schema check.
	 * @param Journey_Cart_Link_Repository|null $cart_links      Cart-link cleanup.
	 */
	public function __construct(
		?Journey_Schema_Migrator $schema_migrator = null,
		?Journey_Cart_Link_Repository $cart_links = null
	) {
		$this->schema_migrator = $schema_migrator ?? new Journey_Schema_Migrator();
		$this->cart_links      = $cart_links ?? new Journey_Cart_Link_Repository(
			schema_migrator: $this->schema_migrator,
		);
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Delete one session after locking and revalidating its stored identity.
	 *
	 * @param int $session_id Journey session ID.
	 * @return int|null One when deleted, zero when absent, or null on failure.
	 */
	public function delete_by_id( int $session_id ): ?int {
		if ( 0 >= $session_id || ! $this->schema_migrator->is_ready() ) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$session = $this->lock_session( session_id: $session_id );
			if ( null === $session ) {
				$this->commit();
				return 0;
			}

			if ( ! $this->cart_links->delete_for_sessions( session_ids: array( $session_id ) ) ) {
				throw new RuntimeException( 'Journey session cart-link deletion failed.' );
			}

			$events_deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE session_id = %d',
					$this->events_table(),
					$session_id
				)
			);
			if ( false === $events_deleted ) {
				throw new RuntimeException( 'Journey session event deletion failed.' );
			}

			$session_deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE id = %d AND visitor_id = %d',
					$this->sessions_table(),
					$session_id,
					$session['visitor_id']
				)
			);
			if ( 1 !== $session_deleted ) {
				throw new RuntimeException( 'Journey session changed during deletion.' );
			}

			$this->commit();
			return 1;
		} catch ( Throwable $error ) {
			unset( $error );
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
	}
	// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Delete every Journey session belonging to selected report subjects.
	 *
	 * Customer subjects include sessions linked through identity periods,
	 * sessions that began authenticated, and sessions with authenticated events.
	 * Visitor subjects remain eligible only while they have never been linked.
	 *
	 * @param array $subjects Selected customer and anonymous visitor subjects.
	 * @return int|null Deleted session count, or null on failure.
	 * @phpstan-param array<array-key,mixed> $subjects
	 */
	public function delete_by_subjects( array $subjects ): ?int {
		if ( ! $this->valid_subjects( subjects: $subjects ) || ! $this->schema_migrator->is_ready() ) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$session_ids = $this->lock_subject_sessions( subjects: $subjects );
			if ( array() !== $session_ids ) {
				if ( ! $this->cart_links->delete_for_sessions( session_ids: $session_ids ) ) {
					throw new RuntimeException( 'Journey subject cart-link deletion failed.' );
				}

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
					throw new RuntimeException( 'Journey subjects changed during deletion.' );
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
	 * Lock and collect all sessions represented by selected report subjects.
	 *
	 * @param array $subjects Validated subjects.
	 * @return list<int> Unique session IDs.
	 * @throws RuntimeException When a database result is unavailable or malformed.
	 * @phpstan-param list<array{type:'customer'|'visitor',id:int}> $subjects
	 */
	private function lock_subject_sessions( array $subjects ): array {
		global $wpdb;

		$session_ids = array();
		foreach ( $subjects as $subject ) {
			if ( 'customer' === $subject['type'] ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT s.id FROM %i s
						LEFT JOIN %i p ON p.id = s.identity_period_id
						WHERE p.user_id = %d OR s.user_id_at_start = %d OR EXISTS (
							SELECT 1 FROM %i e WHERE e.session_id = s.id AND e.user_id_at_event = %d
						)
						ORDER BY s.id ASC FOR UPDATE',
						$this->sessions_table(),
						$this->periods_table(),
						$subject['id'],
						$subject['id'],
						$this->events_table(),
						$subject['id']
					)
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT s.id FROM %i s
						INNER JOIN %i v ON v.id = s.visitor_id
						WHERE v.id = %d AND NOT EXISTS (
							SELECT 1 FROM %i p WHERE p.visitor_id = v.id AND p.user_id IS NOT NULL
						)
						ORDER BY s.id ASC FOR UPDATE',
						$this->sessions_table(),
						$this->visitors_table(),
						$subject['id'],
						$this->periods_table()
					)
				);
			}

			if ( ! is_array( $rows ) ) {
				throw new RuntimeException( 'Journey subject session lookup failed.' );
			}

			foreach ( $rows as $row ) {
				$id = is_object( $row ) ? $this->positive_id( value: $row->id ?? null ) : null;
				if ( null === $id ) {
					throw new RuntimeException( 'Journey subject session row is invalid.' );
				}

				$session_ids[ $id ] = true;
			}
		}

		return array_keys( $session_ids );
	}

	/**
	 * Require deletion by a validated list of record IDs.
	 *
	 * @param string $table  Full table name.
	 * @param string $column ID column name.
	 * @param array  $ids    Positive record IDs.
	 * @return int Affected row count.
	 * @throws RuntimeException When the deletion fails.
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
			throw new RuntimeException( 'Journey subject dependent deletion failed.' );
		}

		return $deleted;
	}

	/**
	 * Validate a bounded, unique list of customer and visitor subjects.
	 *
	 * @param array $subjects Untrusted subjects.
	 * @return bool Whether subjects are canonical and unique.
	 * @phpstan-param array<array-key,mixed> $subjects
	 * @phpstan-assert-if-true list<array{type:'customer'|'visitor',id:int}> $subjects
	 */
	private function valid_subjects( array $subjects ): bool {
		if ( array() === $subjects || self::MAX_SUBJECTS < count( $subjects ) || ! array_is_list( $subjects ) ) {
			return false;
		}

		$keys = array();
		foreach ( $subjects as $subject ) {
			if ( ! is_array( $subject ) || array( 'type', 'id' ) !== array_keys( $subject ) ) {
				return false;
			}

			$type = $subject['type'] ?? null;
			$id   = $subject['id'] ?? null;
			if ( ! in_array( $type, array( 'customer', 'visitor' ), true ) || ! is_int( $id ) || 0 >= $id ) {
				return false;
			}

			$key = $type . ':' . $id;
			if ( isset( $keys[ $key ] ) ) {
				return false;
			}
			$keys[ $key ] = true;
		}

		return true;
	}

	/**
	 * Lock one session and read the visitor needed for conditional deletion.
	 *
	 * @param int $session_id Journey session ID.
	 * @return array{visitor_id:int}|null Locked session, or null when absent.
	 * @throws RuntimeException When the database result is malformed.
	 */
	private function lock_session( int $session_id ): ?array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, visitor_id FROM %i WHERE id = %d LIMIT 2 FOR UPDATE',
				$this->sessions_table(),
				$session_id
			)
		);

		if ( ! is_array( $rows ) || 1 < count( $rows ) ) {
			throw new RuntimeException( 'Journey session deletion lookup failed.' );
		}

		if ( array() === $rows ) {
			return null;
		}

		$row        = $rows[0];
		$id         = is_object( $row ) ? $this->positive_id( value: $row->id ?? null ) : null;
		$visitor_id = is_object( $row ) ? $this->positive_id( value: $row->visitor_id ?? null ) : null;

		if ( $session_id !== $id || null === $visitor_id ) {
			throw new RuntimeException( 'Journey session deletion row is invalid.' );
		}

		return array( 'visitor_id' => $visitor_id );
	}

	/**
	 * Parse a positive database identifier without lossy coercion.
	 *
	 * @param mixed $value Stored identifier.
	 * @return int|null Positive integer, or null.
	 */
	private function positive_id( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Commit the deletion transaction.
	 *
	 * @return void
	 * @throws RuntimeException When the commit fails.
	 */
	private function commit(): void {
		global $wpdb;

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Journey session deletion could not commit.' );
		}
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

	/** Get the current site's Journey identity-period table. */
	private function periods_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_identity_periods';
	}

	/** Get the current site's Journey visitors table. */
	private function visitors_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_visitors';
	}
}
