<?php
/**
 * Customer Journey visitor storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_UUID;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Resolves opaque UUIDs to visitor rows without creating identity periods.
 */
final class Journey_Visitor_Repository {
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
	 * Find the database ID for a valid visitor UUID.
	 *
	 * @param string $uuid Canonical visitor UUID.
	 * @return int|null Visitor row ID, or null when absent or unavailable.
	 */
	public function find_id_by_uuid( string $uuid ): ?int {
		if ( ! Journey_Visitor_UUID::is_valid( value: $uuid ) || ! $this->schema_migrator->is_ready() ) {
			return null;
		}

		return $this->lookup_id( uuid: $uuid );
	}

	/**
	 * Find or insert one visitor, including a concurrent unique-key winner.
	 *
	 * First-touch attribution and identity periods belong to later units.
	 * The timestamp must be a server-generated UTC MySQL datetime.
	 *
	 * @param string $uuid    Canonical visitor UUID.
	 * @param string $seen_at Server-generated UTC datetime.
	 * @return int|null Visitor row ID, or null when storage is unavailable.
	 */
	public function find_or_create( string $uuid, string $seen_at ): ?int {
		if (
			! Journey_Visitor_UUID::is_valid( value: $uuid ) ||
			'' === $seen_at ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		$visitor_id = $this->lookup_id( uuid: $uuid );
		if ( null !== $visitor_id ) {
			return $visitor_id;
		}

		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'visitor_uuid' => $uuid,
				'created_at'   => $seen_at,
				'last_seen_at' => $seen_at,
			),
			array( '%s', '%s', '%s' )
		);

		if ( 1 === $inserted && 0 < $wpdb->insert_id ) {
			return (int) $wpdb->insert_id;
		}

		// A different request may have inserted the same unique UUID first.
		return $this->lookup_id( uuid: $uuid );
	}

	/**
	 * Advance last_seen_at without allowing older requests to move it back.
	 *
	 * The timestamp must be a server-generated UTC MySQL datetime. A zero-row
	 * update is successful when the stored timestamp is already as recent.
	 *
	 * @param int    $visitor_id Visitor row ID.
	 * @param string $seen_at    Server-generated UTC datetime.
	 * @return bool Whether the database accepted the update query.
	 */
	public function mark_seen( int $visitor_id, string $seen_at ): bool {
		if ( 0 >= $visitor_id || '' === $seen_at || ! $this->schema_migrator->is_ready() ) {
			return false;
		}

		global $wpdb;

		return false !== $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET last_seen_at = %s WHERE id = %d AND last_seen_at < %s',
				$this->table_name(),
				$seen_at,
				$visitor_id,
				$seen_at
			)
		);
	}

	/**
	 * Look up a valid UUID after schema readiness has been checked.
	 *
	 * @param string $uuid Canonical visitor UUID.
	 * @return int|null Positive visitor row ID, or null when absent.
	 */
	private function lookup_id( string $uuid ): ?int {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE visitor_uuid = %s LIMIT 1',
				$this->table_name(),
				$uuid
			)
		);

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

		return false === $id ? null : $id;
	}

	/**
	 * Use the current WordPress site table prefix.
	 *
	 * @return string Full visitor table name.
	 */
	private function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'shurloc_journey_visitors';
	}
}
