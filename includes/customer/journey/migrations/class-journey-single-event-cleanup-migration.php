<?php
/**
 * Customer Journey single-event cleanup migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

use RuntimeException;
use Throwable;

/**
 * Deletes Journey sessions with one event and no recorded active time.
 */
final class Journey_Single_Event_Cleanup_Migration {

	/** Current migration version. */
	public const VERSION = 1;

	/** Maximum sessions deleted in one transaction. */
	public const BATCH_SIZE = 1000;

	/** Option storing the timestamp of the most recent successful run. */
	public const LAST_RUN_OPTION =
		'shurloc_journey_single_event_cleanup_last_run';

	/** Option storing the version used for the most recent successful run. */
	public const LAST_RUN_VERSION_OPTION =
		'shurloc_journey_single_event_cleanup_last_run_version';

	/** Shared lock for destructive Journey maintenance migrations. */
	public const LOCK_OPTION =
		Journey_Data_Truncation_Migration::LOCK_OPTION;

	/** Maximum age of a migration lock in seconds. */
	private const LOCK_TIMEOUT = 900;

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
	public function __construct(
		?Journey_Schema_Migrator $schema_migrator = null
	) {
		$this->schema_migrator =
			$schema_migrator ?? new Journey_Schema_Migrator();
	}

	/**
	 * Delete all sessions containing one event and zero active milliseconds.
	 *
	 * @return array{deleted:int,errors:int} Migration result.
	 */
	public function run(): array {
		$result = array(
			'deleted' => 0,
			'errors'  => 0,
		);

		if ( ! $this->schema_migrator->is_ready() ) {
			$result['errors'] = 1;
			return $result;
		}

		do {
			$deleted = $this->delete_batch();

			if ( null === $deleted ) {
				$result['errors'] = 1;
				return $result;
			}

			$result['deleted'] += $deleted;
		} while ( self::BATCH_SIZE === $deleted );

		update_option( self::LAST_RUN_OPTION, time() );
		update_option( self::LAST_RUN_VERSION_OPTION, self::VERSION );

		return $result;
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Delete one dependency-safe batch of matching sessions.
	 *
	 * Visitor and identity-period rows are retained because they can be shared
	 * by other sessions for the same browser or customer.
	 *
	 * @return int|null Deleted session count, or null on failure.
	 */
	private function delete_batch(): ?int {
		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id FROM %i
					WHERE event_count = 1 AND active_ms = 0
					ORDER BY id ASC LIMIT %d FOR UPDATE',
					$this->sessions_table(),
					self::BATCH_SIZE
				)
			);

			$session_ids = $this->parse_ids( rows: $rows );
			if ( null === $session_ids ) {
				throw new RuntimeException(
					'Journey single-event cleanup selection failed.'
				);
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
					throw new RuntimeException(
						'Journey single-event sessions changed concurrently.'
					);
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
	 * Parse a bounded database ID result without numeric coercion.
	 *
	 * @param mixed $rows Database result rows.
	 * @return list<int>|null IDs, or null for unavailable or malformed data.
	 */
	private function parse_ids( mixed $rows ): ?array {
		if ( ! is_array( $rows ) || count( $rows ) > self::BATCH_SIZE ) {
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

			$id = filter_var(
				$value,
				FILTER_VALIDATE_INT,
				array( 'options' => array( 'min_range' => 1 ) )
			);

			if ( false === $id || in_array( $id, $ids, true ) ) {
				return null;
			}

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * Delete records matching a validated list of session IDs.
	 *
	 * @param string $table  Full table name.
	 * @param string $column Session ID column name.
	 * @param array  $ids    Positive session IDs.
	 * @return int Affected row count.
	 * @throws RuntimeException When the delete fails.
	 * @phpstan-param list<int> $ids
	 */
	private function require_delete(
		string $table,
		string $column,
		array $ids
	): int {
		global $wpdb;

		$placeholders = implode(
			', ',
			array_fill( 0, count( $ids ), '%d' )
		);
		$arguments    = array_merge(
			array( $table, $column ),
			$ids
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only fixed %d placeholders are generated.
		$sql = 'DELETE FROM %i WHERE %i IN (' . $placeholders . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL has generated placeholders for validated integer IDs.
		$deleted = $wpdb->query( $wpdb->prepare( $sql, ...$arguments ) );

		if ( false === $deleted ) {
			throw new RuntimeException(
				'Journey single-event dependent deletion failed.'
			);
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
			throw new RuntimeException(
				'Journey single-event cleanup transaction could not commit.'
			);
		}
	}

	/**
	 * Determine whether a Journey maintenance migration is locked.
	 *
	 * @return bool True when an active lock exists.
	 */
	public function is_locked(): bool {
		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );

		if ( 0 === $locked_at ) {
			return false;
		}

		if ( time() - $locked_at > self::LOCK_TIMEOUT ) {
			delete_option( self::LOCK_OPTION );
			return false;
		}

		return true;
	}

	/**
	 * Attempt to acquire the Journey maintenance migration lock.
	 *
	 * @return bool True when the lock was acquired.
	 */
	public function acquire_lock(): bool {
		if ( $this->is_locked() ) {
			return false;
		}

		return add_option(
			self::LOCK_OPTION,
			time(),
			'',
			false
		);
	}

	/**
	 * Release the Journey maintenance migration lock.
	 *
	 * @return void
	 */
	public function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Get the timestamp of the most recent successful run.
	 *
	 * @return int Last-run timestamp, or zero when never run.
	 */
	public function get_last_run(): int {
		return (int) get_option( self::LAST_RUN_OPTION, 0 );
	}

	/**
	 * Get the version used for the most recent successful run.
	 *
	 * @return int Last-run version, or zero when never run.
	 */
	public function get_last_run_version(): int {
		return (int) get_option( self::LAST_RUN_VERSION_OPTION, 0 );
	}

	/** Get the current Journey session table name. */
	private function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_sessions';
	}

	/** Get the current Journey event table name. */
	private function events_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_events';
	}

	/** Get the current Journey cart-link table name. */
	private function cart_links_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shurloc_journey_cart_links';
	}
}
