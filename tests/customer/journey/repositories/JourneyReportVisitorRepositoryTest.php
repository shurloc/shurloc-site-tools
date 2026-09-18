<?php
/**
 * Tests for Customer Journey anonymous visitor selector reads.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;
use stdClass;

/**
 * Verify bounded and identity-scoped anonymous visitor pages.
 */
final class JourneyReportVisitorRepositoryTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare a ready Journey schema.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database                  = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * The query is bounded, indexed, prefix-aware, and never selects UUIDs.
	 *
	 * @return void
	 */
	public function test_reads_a_bounded_anonymous_visitor_page(): void {
		$this->database->prefix  = 'shop_';
		$this->database->results = array( $this->visitor_row( id: '12' ) );

		$visitors = ( new Journey_Report_Visitor_Repository() )->anonymous_visitors( limit: 25 );

		self::assertSame(
			array(
				array(
					'id'             => 12,
					'created_at'     => '2026-09-16 12:00:00',
					'last_seen_at'   => '2026-09-17 12:00:00',
					'first_touch_at' => null,
				),
			),
			$visitors
		);
		self::assertCount( 1, $this->database->prepared_queries );
		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'shop_shurloc_journey_visitors',
				'9999-12-31 23:59:59',
				'9999-12-31 23:59:59',
				PHP_INT_MAX,
				'shop_shurloc_journey_identity_periods',
				'shop_shurloc_journey_events',
				25,
			),
			$query['args']
		);
		self::assertStringContainsString( 'NOT EXISTS', $query['query'] );
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $query['query'] );
		self::assertStringContainsString( 'e.visitor_id = v.id AND e.user_id_at_event IS NULL', $query['query'] );
		self::assertStringContainsString( 'ORDER BY v.last_seen_at DESC, v.id DESC LIMIT %d', $query['query'] );
		self::assertStringNotContainsString( 'visitor_uuid', $query['query'] );
	}

	/**
	 * Paging uses the timestamp and ID together, including same-second rows.
	 *
	 * @return void
	 */
	public function test_uses_a_paired_keyset_cursor(): void {
		$row                     = $this->visitor_row( id: '11' );
		$row->first_touch_at     = '2026-09-16 12:01:00';
		$this->database->results = array( $row );

		$visitors = ( new Journey_Report_Visitor_Repository() )->anonymous_visitors(
			limit: 10,
			before_at: '2026-09-17 12:00:00',
			before_id: 12
		);

		self::assertSame( 11, $visitors[0]['id'] ?? null );
		self::assertSame( '2026-09-16 12:01:00', $visitors[0]['first_touch_at'] ?? null );
		$query = $this->database->prepared_queries[0];
		self::assertSame( '2026-09-17 12:00:00', $query['args'][1] );
		self::assertSame( '2026-09-17 12:00:00', $query['args'][2] );
		self::assertSame( 12, $query['args'][3] );
		self::assertStringContainsString( 'v.last_seen_at = %s AND v.id < %d', $query['query'] );
	}

	/**
	 * Invalid pagination and unavailable schema never query visitor data.
	 *
	 * @return void
	 */
	public function test_invalid_requests_fail_closed(): void {
		$repository = new Journey_Report_Visitor_Repository();

		self::assertNull( $repository->anonymous_visitors( limit: 0 ) );
		self::assertNull( $repository->anonymous_visitors( limit: 101 ) );
		self::assertNull( $repository->anonymous_visitors( before_at: '2026-09-17 12:00:00' ) );
		self::assertNull( $repository->anonymous_visitors( before_id: 12 ) );
		self::assertNull( $repository->anonymous_visitors( before_at: '2026-02-30 12:00:00', before_id: 12 ) );
		self::assertNull( $repository->anonymous_visitors( before_at: '2026-09-17 12:00:00', before_id: 0 ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->anonymous_visitors() );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Empty pages are valid; malformed result pages are not.
	 *
	 * @return void
	 */
	public function test_database_result_validation(): void {
		$repository = new Journey_Report_Visitor_Repository();

		self::assertSame( array(), $repository->anonymous_visitors() );

		$this->database->results = array( $this->visitor_row( id: '12' ), $this->visitor_row( id: '11' ) );
		self::assertNull( $repository->anonymous_visitors( limit: 1 ) );

		$row                     = $this->visitor_row( id: '12' );
		$row->last_seen_at       = '2026-09-16 11:59:59';
		$this->database->results = array( $row );
		self::assertNull( $repository->anonymous_visitors() );

		$row                     = $this->visitor_row( id: '12' );
		$row->id                 = '0';
		$this->database->results = array( $row );
		self::assertNull( $repository->anonymous_visitors() );

		$row                     = $this->visitor_row( id: '12' );
		$row->first_touch_at     = '2026-09-18 12:00:00';
		$this->database->results = array( $row );
		self::assertNull( $repository->anonymous_visitors() );
	}

	/**
	 * Build one representative visitor row from the v1 schema.
	 *
	 * @param string $id Visitor ID.
	 * @return stdClass Database row.
	 */
	private function visitor_row( string $id ): stdClass {
		return (object) array(
			'id'             => $id,
			'created_at'     => '2026-09-16 12:00:00',
			'last_seen_at'   => '2026-09-17 12:00:00',
			'first_touch_at' => null,
		);
	}
}
