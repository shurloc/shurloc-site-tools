<?php
/**
 * Tests for Customer Journey report-subject selector reads.
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
 * Verify bounded recent subjects and identity-scoped anonymous visitor pages.
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
	 * Recent subjects combine authenticated users and never-linked visitors.
	 *
	 * @return void
	 */
	public function test_reads_bounded_recent_subjects_without_visitor_uuids(): void {
		$this->database->prefix  = 'shop_';
		$this->database->results = array(
			(object) array(
				'subject_type'     => 'customer',
				'subject_id'       => '7',
				'last_activity_at' => '2026-09-18 12:00:00',
			),
			(object) array(
				'subject_type'     => 'visitor',
				'subject_id'       => '12',
				'last_activity_at' => '2026-09-17 12:00:00',
			),
		);

		$subjects = ( new Journey_Report_Visitor_Repository() )->recent_subjects( limit: 25 );

		self::assertSame(
			array(
				array(
					'subject_type'     => 'customer',
					'subject_id'       => 7,
					'last_activity_at' => '2026-09-18 12:00:00',
				),
				array(
					'subject_type'     => 'visitor',
					'subject_id'       => 12,
					'last_activity_at' => '2026-09-17 12:00:00',
				),
			),
			$subjects
		);

		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'shop_shurloc_journey_events',
				'1000-01-01 00:00:00',
				'9999-12-31 23:59:59',
				'shop_shurloc_journey_events',
				'1000-01-01 00:00:00',
				'9999-12-31 23:59:59',
				'shop_shurloc_journey_identity_periods',
				25,
			),
			$query['args']
		);
		self::assertStringContainsString( 'e.user_id_at_event IS NOT NULL', $query['query'] );
		self::assertStringContainsString( 'e.user_id_at_event IS NULL', $query['query'] );
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $query['query'] );
		self::assertStringContainsString( 'GROUP BY recent.subject_type, recent.subject_id', $query['query'] );
		self::assertStringContainsString( 'ORDER BY last_activity_at DESC', $query['query'] );
		self::assertStringNotContainsString( 'visitor_uuid', $query['query'] );
	}

	/**
	 * Optional range boundaries filter both event branches before grouping.
	 *
	 * @return void
	 */
	public function test_filters_recent_subjects_by_half_open_utc_range(): void {
		$this->database->results = array( $this->recent_subject_row() );

		$subjects = ( new Journey_Report_Visitor_Repository() )->recent_subjects(
			limit: 25,
			from_utc: '2026-09-01 07:00:00',
			until_utc: '2026-10-01 07:00:00'
		);

		self::assertSame( 7, $subjects[0]['subject_id'] ?? null );
		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'wp_shurloc_journey_events',
				'2026-09-01 07:00:00',
				'2026-10-01 07:00:00',
				'wp_shurloc_journey_events',
				'2026-09-01 07:00:00',
				'2026-10-01 07:00:00',
				'wp_shurloc_journey_identity_periods',
				25,
			),
			$query['args']
		);
		self::assertSame( 2, substr_count( $query['query'], 'e.occurred_at >= %s AND e.occurred_at < %s' ) );
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

		self::assertNull( $repository->recent_subjects( limit: 0 ) );
		self::assertNull( $repository->recent_subjects( limit: 101 ) );
		self::assertNull( $repository->recent_subjects( from_utc: '2026-09-01 00:00:00' ) );
		self::assertNull( $repository->recent_subjects( until_utc: '2026-10-01 00:00:00' ) );
		self::assertNull( $repository->recent_subjects( from_utc: 'invalid', until_utc: '2026-10-01 00:00:00' ) );
		self::assertNull( $repository->recent_subjects( from_utc: '2026-10-01 00:00:00', until_utc: '2026-09-01 00:00:00' ) );
		self::assertNull( $repository->recent_subjects( from_utc: '2026-10-01 00:00:00', until_utc: '2026-10-01 00:00:00' ) );
		self::assertNull( $repository->anonymous_visitors( limit: 0 ) );
		self::assertNull( $repository->anonymous_visitors( limit: 101 ) );
		self::assertNull( $repository->anonymous_visitors( before_at: '2026-09-17 12:00:00' ) );
		self::assertNull( $repository->anonymous_visitors( before_id: 12 ) );
		self::assertNull( $repository->anonymous_visitors( before_at: '2026-02-30 12:00:00', before_id: 12 ) );
		self::assertNull( $repository->anonymous_visitors( before_at: '2026-09-17 12:00:00', before_id: 0 ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->recent_subjects() );
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
	 * Recent subject rows reject unknown types, invalid IDs, and invalid times.
	 *
	 * @return void
	 */
	public function test_recent_subject_result_validation(): void {
		$repository = new Journey_Report_Visitor_Repository();

		self::assertSame( array(), $repository->recent_subjects() );

		$row                     = $this->recent_subject_row( subject_type: 'unknown' );
		$this->database->results = array( $row );
		self::assertNull( $repository->recent_subjects() );

		$row->subject_type = 'customer';
		$row->subject_id   = '0';
		self::assertNull( $repository->recent_subjects() );

		$row->subject_id       = '7';
		$row->last_activity_at = '2026-02-30 12:00:00';
		self::assertNull( $repository->recent_subjects() );

		$this->database->results = array_fill(
			0,
			2,
			$this->recent_subject_row()
		);
		self::assertNull( $repository->recent_subjects( limit: 1 ) );
	}

	/**
	 * Build one representative recent-subject result row.
	 *
	 * @param string $subject_type Subject type.
	 * @return stdClass Database row.
	 */
	private function recent_subject_row( string $subject_type = 'customer' ): stdClass {
		$row                   = new stdClass();
		$row->subject_type     = $subject_type;
		$row->subject_id       = '7';
		$row->last_activity_at = '2026-09-18 12:00:00';

		return $row;
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
