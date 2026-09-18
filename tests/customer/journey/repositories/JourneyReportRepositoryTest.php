<?php
/**
 * Tests for Customer Journey report reads.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Verify customer report reads are bounded and respect identity history.
 */
final class JourneyReportRepositoryTest extends TestCase {
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
	 * Query by event-time identity and only the matching linked anonymous period.
	 *
	 * @return void
	 */
	public function test_customer_query_uses_event_identity_and_linked_period_boundaries(): void {
		$this->database->prefix  = 'shop_';
		$this->database->results = array(
			$this->event_row(
				id: '42',
				user_id_at_event: '7',
				occurred_at: '2026-09-18 11:00:00'
			),
			$this->event_row(
				id: '31',
				user_id_at_event: null,
				occurred_at: '2026-09-17 11:00:00'
			),
		);

		$events = ( new Journey_Report_Repository() )->customer_events(
			user_id: 7,
			from_utc: '2026-09-01 00:00:00',
			until_utc: '2026-10-01 00:00:00',
		);

		self::assertNotNull( $events );
		self::assertCount( 2, $events );
		self::assertSame( 42, $events[0]['id'] );
		self::assertSame( 7, $events[0]['user_id_at_event'] );
		self::assertSame( 31, $events[1]['id'] );
		self::assertNull( $events[1]['user_id_at_event'] );
		self::assertSame( 19, $events[0]['product_id'] );
		self::assertSame( '2.0000', $events[0]['quantity'] );

		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'shop_shurloc_journey_events',
				'2026-09-01 00:00:00',
				'2026-10-01 00:00:00',
				'2026-10-01 00:00:00',
				'2026-10-01 00:00:00',
				PHP_INT_MAX,
				7,
				'shop_shurloc_journey_identity_periods',
				7,
				'shop_shurloc_journey_identity_periods',
				50,
			),
			$query['args']
		);
		self::assertStringContainsString( 'e.user_id_at_event = %d', $query['query'] );
		self::assertStringContainsString( 'e.user_id_at_event IS NULL AND EXISTS', $query['query'] );
		self::assertStringContainsString( 'p.visitor_id = e.visitor_id AND p.user_id = %d', $query['query'] );
		self::assertStringContainsString( 'p.linked_at IS NOT NULL', $query['query'] );
		self::assertStringContainsString( 'p.started_at <= e.occurred_at AND p.ended_at > e.occurred_at', $query['query'] );
		self::assertStringContainsString( 'prior.visitor_id = e.visitor_id AND prior.ended_at = e.occurred_at', $query['query'] );
		self::assertStringContainsString( 'ORDER BY e.occurred_at DESC, e.id DESC LIMIT %d', $query['query'] );
	}

	/**
	 * A time-and-ID cursor handles multiple events in the same second.
	 *
	 * @return void
	 */
	public function test_cursor_and_page_limit_are_prepared(): void {
		$events = ( new Journey_Report_Repository() )->customer_events(
			user_id: 7,
			from_utc: '2026-09-01 00:00:00',
			until_utc: '2026-10-01 00:00:00',
			limit: 20,
			before_at: '2026-09-18 11:00:00',
			before_id: 42
		);

		self::assertSame( array(), $events );
		$query = $this->database->prepared_queries[0];
		self::assertStringContainsString( '(e.occurred_at = %s AND e.id < %d)', $query['query'] );
		self::assertSame( '2026-09-18 11:00:00', $query['args'][3] );
		self::assertSame( '2026-09-18 11:00:00', $query['args'][4] );
		self::assertSame( 42, $query['args'][5] );
		self::assertSame( 20, $query['args'][10] );
	}

	/**
	 * An anonymous report requires the visitor row and rejects any user link.
	 *
	 * @return void
	 */
	public function test_anonymous_visitor_query_excludes_any_linked_identity(): void {
		$this->database->prefix  = 'shop_';
		$this->database->results = array(
			$this->event_row( id: '31', user_id_at_event: null, occurred_at: '2026-09-17 11:00:00' ),
		);

		$events = ( new Journey_Report_Repository() )->anonymous_visitor_events(
			visitor_id: 3,
			from_utc: '2026-09-01 00:00:00',
			until_utc: '2026-10-01 00:00:00',
		);

		self::assertNotNull( $events );
		self::assertCount( 1, $events );
		self::assertSame( 31, $events[0]['id'] );
		self::assertSame( 3, $events[0]['visitor_id'] );
		self::assertNull( $events[0]['user_id_at_event'] );

		$query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'shop_shurloc_journey_events',
				'shop_shurloc_journey_visitors',
				3,
				'2026-09-01 00:00:00',
				'2026-10-01 00:00:00',
				'2026-10-01 00:00:00',
				'2026-10-01 00:00:00',
				PHP_INT_MAX,
				'shop_shurloc_journey_identity_periods',
				3,
				50,
			),
			$query['args']
		);
		self::assertStringContainsString( 'INNER JOIN %i v ON v.id = e.visitor_id', $query['query'] );
		self::assertStringContainsString( 'e.visitor_id = %d AND e.user_id_at_event IS NULL', $query['query'] );
		self::assertStringContainsString( 'NOT EXISTS', $query['query'] );
		self::assertStringContainsString( 'p.visitor_id = %d AND p.user_id IS NOT NULL', $query['query'] );
		self::assertStringContainsString( 'ORDER BY e.occurred_at DESC, e.id DESC LIMIT %d', $query['query'] );
	}

	/**
	 * Anonymous reads use the same bounded date and keyset pagination rules.
	 *
	 * @return void
	 */
	public function test_anonymous_visitor_query_validates_bounds_and_cursor(): void {
		$repository = new Journey_Report_Repository();
		$valid      = array(
			'visitor_id' => 3,
			'from_utc'   => '2026-09-01 00:00:00',
			'until_utc'  => '2026-10-01 00:00:00',
		);

		self::assertNull( $repository->anonymous_visitor_events( ...array_replace( $valid, array( 'visitor_id' => 0 ) ) ) );
		self::assertNull( $repository->anonymous_visitor_events( ...array_replace( $valid, array( 'until_utc' => '2026-10-03 00:00:00' ) ) ) );
		self::assertNull( $repository->anonymous_visitor_events( ...array_replace( $valid, array( 'limit' => 101 ) ) ) );
		self::assertNull( $repository->anonymous_visitor_events( ...array_replace( $valid, array( 'before_id' => 31 ) ) ) );
		self::assertSame( array(), $this->database->prepared_queries );

		self::assertSame(
			array(),
			$repository->anonymous_visitor_events(
				visitor_id: 3,
				from_utc: '2026-09-01 00:00:00',
				until_utc: '2026-10-01 00:00:00',
				limit: 25,
				before_at: '2026-09-17 11:00:00',
				before_id: 31
			)
		);
		$query = $this->database->prepared_queries[0];
		self::assertSame( '2026-09-17 11:00:00', $query['args'][5] );
		self::assertSame( '2026-09-17 11:00:00', $query['args'][6] );
		self::assertSame( 31, $query['args'][7] );
		self::assertSame( 25, $query['args'][10] );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->anonymous_visitor_events( ...$valid ) );
		self::assertCount( 1, $this->database->prepared_queries );
	}

	/**
	 * Bad arguments and an unavailable schema never reach event storage.
	 *
	 * @return void
	 */
	public function test_invalid_inputs_and_unavailable_schema_fail_closed(): void {
		$repository = new Journey_Report_Repository();
		$valid      = array(
			'user_id'   => 7,
			'from_utc'  => '2026-09-01 00:00:00',
			'until_utc' => '2026-10-01 00:00:00',
		);

		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'user_id' => 0 ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'from_utc' => '2026-09-31 00:00:00' ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'until_utc' => '2026-09-01 00:00:00' ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'until_utc' => '2026-10-03 00:00:00' ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'limit' => 0 ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'limit' => 101 ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'before_at' => '2026-09-18 11:00:00' ) ) ) );
		self::assertNull( $repository->customer_events( ...array_replace( $valid, array( 'before_id' => 42 ) ) ) );
		$invalid_cursor = array_replace(
			$valid,
			array(
				'before_at' => '2026-10-01 00:00:00',
				'before_id' => 42,
			)
		);
		self::assertNull( $repository->customer_events( ...$invalid_cursor ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->customer_events( ...$valid ) );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Malformed database output fails the whole page without partial results.
	 *
	 * @return void
	 */
	public function test_malformed_database_rows_fail_closed(): void {
		$this->database->results = array(
			$this->event_row( id: '42', user_id_at_event: '7', occurred_at: '2026-09-18 11:00:00' ),
			(object) array( 'id' => 'bad' ),
		);

		$result = ( new Journey_Report_Repository() )->customer_events(
			user_id: 7,
			from_utc: '2026-09-01 00:00:00',
			until_utc: '2026-10-01 00:00:00'
		);
		self::assertNull( $result );
	}

	/**
	 * Produce one representative database event row.
	 *
	 * @param string      $id               Event ID.
	 * @param string|null $user_id_at_event Captured authenticated user ID.
	 * @param string      $occurred_at      Event timestamp.
	 * @return object Database row.
	 */
	private function event_row( string $id, ?string $user_id_at_event, string $occurred_at ): object {
		return (object) array(
			'id'                => $id,
			'session_id'        => '8',
			'visitor_id'        => '3',
			'user_id_at_event'  => $user_id_at_event,
			'event_type'        => 'ADD_TO_CART',
			'occurred_at'       => $occurred_at,
			'page_path'         => null,
			'post_id'           => null,
			'product_id'        => '19',
			'variation_id'      => null,
			'quantity'          => '2.0000',
			'order_id'          => null,
			'related_object_id' => null,
			'active_ms'         => '0',
			'source'            => 'woocommerce',
		);
	}
}
