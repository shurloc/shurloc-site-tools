<?php
/**
 * Customer Journey cart-session correlation storage.
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
 * Stores idempotent links between WooCommerce carts and Journey sessions.
 */
final class Journey_Cart_Link_Repository {

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
	 * Link one cart token to a Journey session without regressing link activity.
	 *
	 * The owning session is locked before the link is written. This verifies the
	 * visitor relationship and coordinates with later transactional cleanup.
	 * Repeated and out-of-order writes preserve linked_at and only advance
	 * last_seen_at.
	 *
	 * @param string $cart_token_hash Lowercase SHA-256 cart-token hash.
	 * @param int    $visitor_id      Journey visitor ID.
	 * @param int    $session_id      Journey session ID.
	 * @param string $observed_at     Server observation time in UTC.
	 * @return bool Whether the link was stored or was already current.
	 */
	public function link(
		string $cart_token_hash,
		int $visitor_id,
		int $session_id,
		string $observed_at
	): bool {
		$observed = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$observed_at,
			new DateTimeZone( 'UTC' )
		);

		if (
			1 !== preg_match( '/\A[0-9a-f]{64}\z/', $cart_token_hash ) ||
			0 >= $visitor_id ||
			0 >= $session_id ||
			false === $observed ||
			$observed->format( 'Y-m-d H:i:s' ) !== $observed_at ||
			! $this->schema_migrator->is_ready()
		) {
			return false;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$locked_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d AND visitor_id = %d LIMIT 1 FOR UPDATE',
				$this->sessions_table_name(),
				$session_id,
				$visitor_id
			)
		);
		$locked_id = filter_var(
			$locked_id,
			FILTER_VALIDATE_INT,
			array( 'options' => array( 'min_range' => 1 ) )
		);

		if ( false === $locked_id || $session_id !== $locked_id ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$linked = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (cart_token_hash, visitor_id, session_id, linked_at, last_seen_at) '
					. 'VALUES (%s, %d, %d, %s, %s) '
					. 'ON DUPLICATE KEY UPDATE visitor_id = %d, last_seen_at = GREATEST(last_seen_at, %s)',
				$this->table_name(),
				$cart_token_hash,
				$visitor_id,
				$session_id,
				$observed_at,
				$observed_at,
				$visitor_id,
				$observed_at
			)
		);

		if ( false === $linked || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		return true;
	}

	/**
	 * Delete links for a validated list of Journey session IDs.
	 *
	 * The caller may include this operation in a wider retention or privacy
	 * transaction; this method does not open or commit a transaction itself.
	 *
	 * @param array $session_ids Positive, unique Journey session IDs.
	 * @return bool Whether the dependent rows were deleted successfully.
	 * @phpstan-param list<int> $session_ids
	 */
	public function delete_for_sessions( array $session_ids ): bool {
		if ( ! $this->schema_migrator->is_ready() || ! $this->valid_ids( ids: $session_ids ) ) {
			return false;
		}

		if ( array() === $session_ids ) {
			return true;
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $session_ids ), '%d' ) );
		$arguments    = array_merge( array( $this->table_name() ), $session_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only fixed %d placeholders are generated.
		$sql = 'DELETE FROM %i WHERE session_id IN (' . $placeholders . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL has generated placeholders for validated integer IDs.
		return false !== $wpdb->query( $wpdb->prepare( $sql, ...$arguments ) );
	}

	/**
	 * Delete every cart link for one Journey visitor.
	 *
	 * @param int $visitor_id Journey visitor ID.
	 * @return bool Whether the dependent rows were deleted successfully.
	 */
	public function delete_for_visitor( int $visitor_id ): bool {
		if ( 0 >= $visitor_id || ! $this->schema_migrator->is_ready() ) {
			return false;
		}

		global $wpdb;

		return false !== $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE visitor_id = %d',
				$this->table_name(),
				$visitor_id
			)
		);
	}

	/**
	 * Validate a list of positive, unique integer IDs.
	 *
	 * @param array $ids Untrusted ID list.
	 * @return bool Whether the value is a canonical ID list.
	 * @phpstan-param list<int> $ids
	 */
	private function valid_ids( array $ids ): bool {
		$seen = array();

		foreach ( $ids as $id ) {
			if ( 0 >= $id || isset( $seen[ $id ] ) ) {
				return false;
			}

			$seen[ $id ] = true;
		}

		return true;
	}

	/**
	 * Use the current WordPress site's Journey cart-links table.
	 *
	 * @return string Full table name.
	 */
	private function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'shurloc_journey_cart_links';
	}

	/**
	 * Use the current WordPress site's Journey sessions table.
	 *
	 * @return string Full table name.
	 */
	private function sessions_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'shurloc_journey_sessions';
	}
}
