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
}
