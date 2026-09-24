<?php
/**
 * Customer Journey identity period storage.
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
 * Serializes a visitor's anonymous and authenticated identity periods.
 *
 * @phpstan-type OpenPeriod array{id:int,user_id:int|null,started_at:string}
 */
final class Journey_Identity_Period_Repository {
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
	 * Return the current period, starting a new one when identity changes.
	 *
	 * The visitor row is locked before inspecting periods. When an anonymous
	 * period becomes authenticated, that historical period receives the user
	 * association and link time, then a separate authenticated period begins.
	 * Historical session and event user-at-time fields remain unchanged.
	 *
	 * The timestamp must be a server-generated UTC MySQL datetime. An older
	 * request cannot replace a newer identity period.
	 *
	 * @param int      $visitor_id  Existing visitor row ID.
	 * @param int|null $user_id     Current WordPress user ID, or null for anonymous.
	 * @param string   $observed_at Server-generated UTC datetime.
	 * @return int|null Current period ID, or null when unavailable.
	 */
	public function ensure_current( int $visitor_id, ?int $user_id, string $observed_at ): ?int {
		if (
			0 >= $visitor_id ||
			( null !== $user_id && 0 >= $user_id ) ||
			'' === $observed_at ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$this->lock_visitor( visitor_id: $visitor_id );
			$current = $this->get_open_period( visitor_id: $visitor_id );

			if ( null !== $current && $current['user_id'] === $user_id ) {
				$period_id = $current['id'];
			} else {
				if ( null !== $current ) {
					if ( $observed_at < $current['started_at'] ) {
						throw new RuntimeException( 'Journey identity transition is out of order.' );
					}

					$this->close_period(
						period_id: $current['id'],
						visitor_id: $visitor_id,
						previous_user_id: $current['user_id'],
						user_id: $user_id,
						ended_at: $observed_at,
					);
				}

				$period_id = $this->insert_period(
					visitor_id: $visitor_id,
					user_id: $user_id,
					started_at: $observed_at,
				);
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'Journey identity transaction could not commit.' );
			}

			return $period_id;
		} catch ( Throwable $error ) {
			unset( $error );
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
	}
	// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

	/**
	 * Lock the parent visitor row to serialize all period changes for it.
	 *
	 * @param int $visitor_id Visitor row ID.
	 * @return void
	 * @throws RuntimeException When the visitor is unavailable.
	 */
	private function lock_visitor( int $visitor_id ): void {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d FOR UPDATE',
				$wpdb->prefix . 'shurloc_journey_visitors',
				$visitor_id
			)
		);

		if ( $visitor_id !== $this->positive_id( value: $found ) ) {
			throw new RuntimeException( 'Journey visitor is unavailable.' );
		}
	}

	/**
	 * Read at most two open periods so ambiguous history fails closed.
	 *
	 * @param int $visitor_id Visitor row ID.
	 * @return OpenPeriod|null Current period, or null before the first period.
	 * @throws RuntimeException When period data is unavailable or ambiguous.
	 */
	private function get_open_period( int $visitor_id ): ?array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, user_id, started_at FROM %i WHERE visitor_id = %d AND ended_at IS NULL ORDER BY started_at DESC, id DESC LIMIT 2 FOR UPDATE',
				$this->table_name(),
				$visitor_id
			)
		);

		if ( ! is_array( $rows ) || 1 < count( $rows ) ) {
			throw new RuntimeException( 'Journey open identity periods are unavailable or ambiguous.' );
		}

		if ( array() === $rows ) {
			return null;
		}

		$row = $rows[0];
		if (
			! is_object( $row ) ||
			! isset( $row->id, $row->started_at ) ||
			! is_string( $row->started_at ) ||
			'' === $row->started_at
		) {
			throw new RuntimeException( 'Journey identity period is invalid.' );
		}

		$period_id = $this->positive_id( value: $row->id );
		$user_id   = $this->positive_id( value: $row->user_id ?? null );

		if ( null === $period_id || ( null !== ( $row->user_id ?? null ) && null === $user_id ) ) {
			throw new RuntimeException( 'Journey identity period IDs are invalid.' );
		}

		return array(
			'id'         => $period_id,
			'user_id'    => $user_id,
			'started_at' => $row->started_at,
		);
	}

	/**
	 * Close one period, linking prior anonymous activity on authentication.
	 *
	 * @param int      $period_id        Open period ID.
	 * @param int      $visitor_id       Visitor row ID.
	 * @param int|null $previous_user_id User ID on the open period.
	 * @param int|null $user_id          Current user ID.
	 * @param string   $ended_at         UTC transition time.
	 * @return void
	 * @throws RuntimeException When the conditional update fails.
	 */
	private function close_period(
		int $period_id,
		int $visitor_id,
		?int $previous_user_id,
		?int $user_id,
		string $ended_at
	): void {
		global $wpdb;

		if ( null === $previous_user_id && null !== $user_id ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET user_id = %d, linked_at = %s, ended_at = %s WHERE id = %d AND visitor_id = %d AND user_id IS NULL AND ended_at IS NULL',
					$this->table_name(),
					$user_id,
					$ended_at,
					$ended_at,
					$period_id,
					$visitor_id
				)
			);
		} else {
			$updated = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET ended_at = %s WHERE id = %d AND visitor_id = %d AND ended_at IS NULL',
					$this->table_name(),
					$ended_at,
					$period_id,
					$visitor_id
				)
			);
		}

		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Journey identity period could not close.' );
		}
	}

	/**
	 * Create a new open period for the request's current identity.
	 *
	 * @param int      $visitor_id Visitor row ID.
	 * @param int|null $user_id    WordPress user ID, or null for anonymous.
	 * @param string   $started_at UTC start time.
	 * @return int New period ID.
	 * @throws RuntimeException When insertion fails.
	 */
	private function insert_period( int $visitor_id, ?int $user_id, string $started_at ): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'visitor_id' => $visitor_id,
				'user_id'    => $user_id,
				'started_at' => $started_at,
				'linked_at'  => null === $user_id ? null : $started_at,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( 1 !== $inserted || 0 >= $wpdb->insert_id ) {
			throw new RuntimeException( 'Journey identity period could not be inserted.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Read a positive database ID without numeric coercion.
	 *
	 * @param mixed $value Database value.
	 * @return int|null Positive ID, or null for an invalid value.
	 */
	private function positive_id( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

		return false === $id ? null : $id;
	}

	/**
	 * Get the current WordPress site identity-period table name.
	 *
	 * @return string Full table name.
	 */
	private function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'shurloc_journey_identity_periods';
	}
}
