<?php
/**
 * Tests for Customer Journey visitor storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Attribution_Sanitizer;
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
		self::assertCount( 1, $this->database->prepared_queries );
		self::assertSame( array(), $this->database->queries );
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
		self::assertCount( 3, $this->database->prepared_queries );
		self::assertSame( 'UPDATE %i SET last_seen_at = %s WHERE id = %d AND last_seen_at < %s', $this->database->prepared_queries[2]['query'] );
	}

	/**
	 * Returning visitors advance last_seen without allowing older requests to regress it.
	 *
	 * @return void
	 */
	public function test_find_or_create_updates_returning_visitor_last_seen(): void {
		$repository = new Journey_Visitor_Repository();
		$uuid       = '123e4567-e89b-42d3-a456-426614174000';

		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:00:00' ) );
		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:05:00' ) );
		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:03:00' ) );
		self::assertSame( '2026-09-16 12:05:00', $this->database->visitor_rows[ $uuid ]['last_seen_at'] );
		self::assertCount( 1, $this->database->insert_calls );
	}

	/**
	 * Failed activity updates cannot return an apparently resolved visitor.
	 *
	 * @return void
	 */
	public function test_find_or_create_returns_null_when_returning_update_fails(): void {
		$repository = new Journey_Visitor_Repository();
		$uuid       = '123e4567-e89b-42d3-a456-426614174000';
		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:00:00' ) );

		$this->database->fail_update = true;
		self::assertNull( $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:10:00' ) );
		self::assertSame( '2026-09-16 12:00:00', $this->database->visitor_rows[ $uuid ]['last_seen_at'] );
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
		self::assertCount( 3, $this->database->prepared_queries );
		self::assertSame( 'UPDATE %i SET last_seen_at = %s WHERE id = %d AND last_seen_at < %s', $this->database->prepared_queries[2]['query'] );
	}

	/**
	 * A concurrent insert winner also requires a successful last-seen update.
	 *
	 * @return void
	 */
	public function test_concurrent_insert_winner_update_failure_returns_null(): void {
		$this->database->race_on_insert = true;
		$this->database->fail_update    = true;
		$uuid                           = '123e4567-e89b-42d3-a456-426614174000';

		self::assertNull(
			( new Journey_Visitor_Repository() )->find_or_create(
				uuid: $uuid,
				seen_at: '2026-09-16 12:00:00'
			)
		);
		self::assertSame( 42, $this->database->visitor_rows[ $uuid ]['id'] );
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

	/**
	 * Store only sanitized first-touch fields using the current table prefix.
	 *
	 * @return void
	 */
	public function test_record_first_touch_stores_sanitized_attribution_once(): void {
		$this->database->prefix = 'shop_';
		$repository             = new Journey_Visitor_Repository();
		$uuid                   = '123e4567-e89b-42d3-a456-426614174000';
		$visitor_id             = $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:00:00' );
		self::assertSame( 1, $visitor_id );

		$attribution = ( new Journey_Attribution_Sanitizer() )->sanitize(
			request_uri: '/product/widget?utm_source=newsletter&email=private%40example.org',
			referrer_url: 'https://Example.org/article?token=secret',
		);
		self::assertTrue(
			$repository->record_first_touch(
				visitor_id: 1,
				observed_at: '2026-09-16 12:00:01',
				attribution: $attribution
			)
		);

		$row = $this->database->visitor_rows[ $uuid ];
		self::assertSame( '2026-09-16 12:00:01', $row['first_touch_at'] ?? null );
		self::assertSame( '/product/widget', $row['first_landing_path'] ?? null );
		self::assertSame( 'example.org', $row['first_referrer_host'] ?? null );
		self::assertSame( 'newsletter', $row['first_utm_source'] ?? null );
		self::assertNull( $row['first_utm_medium'] ?? null );
		self::assertSame( 'shop_shurloc_journey_visitors', $this->database->prepared_queries[1]['args'][0] );
		self::assertSame( 1, $this->database->prepared_queries[1]['args'][9] );
		self::assertStringContainsString( 'first_touch_at IS NULL OR first_touch_at > %s', $this->database->prepared_queries[1]['query'] );
		self::assertNotContains( 'private@example.org', $this->database->prepared_queries[1]['args'] );
		self::assertNotContains( 'https://Example.org/article?token=secret', $this->database->prepared_queries[1]['args'] );
		self::assertSame( '', $this->database->prepared_queries[1]['args'][5] );
		self::assertStringContainsString( "first_utm_medium = NULLIF(%s, '')", $this->database->prepared_queries[1]['query'] );
	}

	/**
	 * A later direct visit cannot erase the earliest known acquisition.
	 *
	 * @return void
	 */
	public function test_record_first_touch_preserves_earliest_timestamp(): void {
		$repository = new Journey_Visitor_Repository();
		$uuid       = '123e4567-e89b-42d3-a456-426614174000';
		self::assertSame( 1, $repository->find_or_create( uuid: $uuid, seen_at: '2026-09-16 12:00:00' ) );

		$sanitizer = new Journey_Attribution_Sanitizer();
		$original  = $sanitizer->sanitize( '/first?utm_source=newsletter', null );
		$later     = $sanitizer->sanitize( '/later', null );
		$earlier   = $sanitizer->sanitize( '/earlier?utm_source=search', null );

		self::assertTrue( $repository->record_first_touch( 1, '2026-09-16 12:00:00', $original ) );
		self::assertTrue( $repository->record_first_touch( 1, '2026-09-16 13:00:00', $later ) );
		self::assertSame( '/first', $this->database->visitor_rows[ $uuid ]['first_landing_path'] ?? null );
		self::assertSame( 'newsletter', $this->database->visitor_rows[ $uuid ]['first_utm_source'] ?? null );

		self::assertTrue( $repository->record_first_touch( 1, '2026-09-16 11:00:00', $earlier ) );
		self::assertSame( '2026-09-16 11:00:00', $this->database->visitor_rows[ $uuid ]['first_touch_at'] ?? null );
		self::assertSame( '/earlier', $this->database->visitor_rows[ $uuid ]['first_landing_path'] ?? null );
		self::assertSame( 'search', $this->database->visitor_rows[ $uuid ]['first_utm_source'] ?? null );
	}

	/**
	 * Invalid page context and schema failure must not reach the visitor table.
	 *
	 * @return void
	 */
	public function test_record_first_touch_rejects_invalid_context_and_unready_schema(): void {
		$repository   = new Journey_Visitor_Repository();
		$sanitizer    = new Journey_Attribution_Sanitizer();
		$attribution  = $sanitizer->sanitize( '/first', null );
		$invalid_page = $sanitizer->sanitize( 'https://example.org/first', null );

		self::assertFalse( $repository->record_first_touch( 0, '2026-09-16 12:00:00', $attribution ) );
		self::assertFalse( $repository->record_first_touch( 1, '', $attribution ) );
		self::assertFalse( $repository->record_first_touch( 1, '2026-09-16 12:00:00', $invalid_page ) );
		$GLOBALS['shurloc_test_options'] = array();
		self::assertFalse( $repository->record_first_touch( 1, '2026-09-16 12:00:00', $attribution ) );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * A failed first-touch update reports the storage failure.
	 *
	 * @return void
	 */
	public function test_record_first_touch_reports_database_failure(): void {
		$this->database->fail_update = true;
		$attribution                 = ( new Journey_Attribution_Sanitizer() )->sanitize( '/first', null );

		self::assertFalse( ( new Journey_Visitor_Repository() )->record_first_touch( 1, '2026-09-16 12:00:00', $attribution ) );
	}
}
