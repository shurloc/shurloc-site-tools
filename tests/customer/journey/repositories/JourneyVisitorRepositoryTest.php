<?php
/**
 * Tests for Customer Journey visitor storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests visitor lookup, idempotent creation, schema gating, and timestamps.
 */
final class JourneyVisitorRepositoryTest extends TestCase {
	/**
	 * Database double under test.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Install the ready schema option and a fresh database double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Unavailable schema prevents all visitor table access.
	 *
	 * @return void
	 */
	public function test_unready_schema_prevents_reads_and_writes(): void {
		$GLOBALS['shurloc_test_options'] = array();
		$repository                      = new Journey_Visitor_Repository();
		$uuid                            = '123e4567-e89b-42d3-a456-426614174000';

		self::assertNull( $repository->find_id_by_uuid( uuid: $uuid ) );
		self::assertNull( $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:00:00' ) );
		self::assertFalse( $repository->mark_seen( visitor_id: 1, seen_at: '2026-09-16 12:00:00' ) );
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $this->database->insert_calls );
	}

	/**
	 * Lookup validates UUIDs and uses the current WordPress table prefix.
	 *
	 * @return void
	 */
	public function test_find_id_by_uuid_uses_prefix_and_parameterized_lookup(): void {
		$this->database->prefix            = 'shop_';
		$uuid                              = '123e4567-e89b-42d3-a456-426614174000';
		$this->database->visitors[ $uuid ] = array(
			'id'           => 7,
			'created_at'   => '2026-09-16 12:00:00',
			'last_seen_at' => '2026-09-16 12:00:00',
		);

		$repository = new Journey_Visitor_Repository();
		self::assertSame( 7, $repository->find_id_by_uuid( uuid: $uuid ) );
		self::assertNull( $repository->find_id_by_uuid( uuid: 'invalid' ) );
		self::assertSame(
			array(
				array(
					'query' => 'SELECT id FROM %i WHERE visitor_uuid = %s LIMIT 1',
					'args'  => array( 'shop_shurloc_journey_visitors', $uuid ),
				),
			),
			$this->database->prepared_queries
		);
	}

	/**
	 * A new visitor is stored once with UUID and server timestamps.
	 *
	 * @return void
	 */
	public function test_find_or_create_inserts_once_and_reuses_existing_row(): void {
		$repository = new Journey_Visitor_Repository();
		$uuid       = '123e4567-e89b-42d3-a456-426614174000';
		$seen_at    = '2026-09-16 12:00:00';

		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: $seen_at ) );
		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: $seen_at ) );
		self::assertSame(
			array(
				array(
					'table'   => 'wp_shurloc_journey_visitors',
					'data'    => array(
						'visitor_uuid' => $uuid,
						'created_at'   => $seen_at,
						'last_seen_at' => $seen_at,
					),
					'formats' => array( '%s', '%s', '%s' ),
				),
			),
			$this->database->insert_calls
		);
		self::assertCount( 1, $this->database->visitors );
	}

	/**
	 * A concurrent insert of the unique UUID resolves to the winning row.
	 *
	 * @return void
	 */
	public function test_find_or_create_returns_concurrent_insert_winner(): void {
		$this->database->race_on_insert = true;
		$uuid                           = '123e4567-e89b-42d3-a456-426614174000';

		self::assertSame(
			42,
			( new Journey_Visitor_Repository() )->find_or_create(
				uuid: $uuid,
				seen_at: '2026-09-16 12:00:00'
			)
		);
		self::assertCount( 2, $this->database->prepared_queries );
	}

	/**
	 * An unrelated insert error cannot invent a visitor ID.
	 *
	 * @return void
	 */
	public function test_find_or_create_returns_null_after_insert_failure(): void {
		$this->database->fail_visitor_insert = true;

		self::assertNull(
			( new Journey_Visitor_Repository() )->find_or_create(
				uuid: '123e4567-e89b-42d3-a456-426614174000',
				seen_at: '2026-09-16 12:00:00'
			)
		);
		self::assertSame( array(), $this->database->visitors );
	}

	/**
	 * Invalid identifiers and absent timestamps cannot reach storage.
	 *
	 * @return void
	 */
	public function test_invalid_inputs_are_rejected_before_database_access(): void {
		$repository = new Journey_Visitor_Repository();

		self::assertNull( $repository->find_or_create( uuid: 'invalid', seen_at: '2026-09-16 12:00:00' ) );
		self::assertNull(
			$repository->find_or_create(
				uuid: '123e4567-e89b-42d3-a456-426614174000',
				seen_at: ''
			)
		);
		self::assertFalse( $repository->mark_seen( visitor_id: 0, seen_at: '2026-09-16 12:00:00' ) );
		self::assertFalse( $repository->mark_seen( visitor_id: 1, seen_at: '' ) );
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $this->database->insert_calls );
	}

	/**
	 * Later activity advances last_seen_at but older activity cannot regress it.
	 *
	 * @return void
	 */
	public function test_mark_seen_updates_only_newer_timestamp(): void {
		$uuid                              = '123e4567-e89b-42d3-a456-426614174000';
		$this->database->visitors[ $uuid ] = array(
			'id'           => 7,
			'created_at'   => '2026-09-16 12:00:00',
			'last_seen_at' => '2026-09-16 12:00:00',
		);
		$repository                        = new Journey_Visitor_Repository();

		self::assertTrue( $repository->mark_seen( visitor_id: 7, seen_at: '2026-09-16 12:05:00' ) );
		self::assertTrue( $repository->mark_seen( visitor_id: 7, seen_at: '2026-09-16 12:03:00' ) );
		$visitor = $this->database->visitors[ $uuid ];
		self::assertIsArray( $visitor );
		self::assertSame( '2026-09-16 12:05:00', $visitor['last_seen_at'] );
		self::assertSame(
			array(
				'query' => 'UPDATE %i SET last_seen_at = %s WHERE id = %d AND last_seen_at < %s',
				'args'  => array( 'wp_shurloc_journey_visitors', '2026-09-16 12:05:00', 7, '2026-09-16 12:05:00' ),
			),
			$this->database->prepared_queries[0]
		);

		$this->database->fail_update = true;
		self::assertFalse( $repository->mark_seen( visitor_id: 7, seen_at: '2026-09-16 12:10:00' ) );
	}
}
