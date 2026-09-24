<?php
/**
 * Tests for Customer Journey report page grouping.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;

/**
 * Verify date, session, chronology, and loaded-event subtotal semantics.
 *
 * @phpstan-import-type ReportEvent from Journey_Report_Repository
 * @phpstan-import-type SessionContext from Journey_Report_Session_Repository
 */
final class JourneyReportPageBuilderTest extends TestCase {
	/**
	 * Product views count once as pages, and future events remain visible.
	 *
	 * @return void
	 */
	public function test_builds_dates_sessions_and_loaded_event_totals(): void {
		$events = array(
			$this->event( id: 8, session_id: 2, type: Journey_Event_Type::ORDER_CREATED, occurred_at: '2026-09-18 10:00:00', user_id: 7 ),
			$this->event( id: 7, session_id: 2, type: Journey_Event_Type::CHECKOUT_STARTED, occurred_at: '2026-09-18 09:04:00', user_id: 7 ),
			$this->event( id: 6, session_id: 1, type: Journey_Event_Type::REMOVE_FROM_CART, occurred_at: '2026-09-17 10:05:00', quantity: '1' ),
			$this->event( id: 5, session_id: 1, type: Journey_Event_Type::ADD_TO_CART, occurred_at: '2026-09-17 10:04:00', quantity: '2' ),
			$this->event( id: 4, session_id: 1, type: Journey_Event_Type::PRODUCT_VIEW, occurred_at: '2026-09-17 10:03:00', active_ms: 20000, user_id: 7 ),
			$this->event( id: 3, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-17 10:02:00', active_ms: 10000 ),
			$this->event( id: 2, session_id: 1, type: 'ORDER_PAID', occurred_at: '2026-09-17 10:01:00' ),
		);

		$page = ( new Journey_Report_Page_Builder() )->build(
			events: $events,
			contexts: array(
				1 => $this->context( session_id: 1 ),
				2 => $this->context( session_id: 2 ),
			),
			timezone: new DateTimeZone( 'UTC' )
		);

		self::assertNotNull( $page );
		self::assertCount( 2, $page['days'] );
		self::assertSame( '2026-09-18', $page['days'][0]['date'] );
		self::assertSame( 1, $page['days'][0]['totals']['sessions'] );
		self::assertSame( 1, $page['days'][0]['totals']['checkouts_started'] );
		self::assertSame( 1, $page['days'][0]['totals']['orders_created'] );
		self::assertSame( array( 7, 8 ), array_column( $page['days'][0]['sessions'][0]['events'], 'id' ) );
		self::assertSame( '2026-09-17', $page['days'][1]['date'] );
		self::assertSame( 1, $page['days'][1]['totals']['sessions'] );
		self::assertSame( 2, $page['days'][1]['totals']['pages_viewed'] );
		self::assertSame( 1, $page['days'][1]['totals']['products_viewed'] );
		self::assertSame( 30000, $page['days'][1]['totals']['active_ms'] );
		self::assertSame( 1, $page['days'][1]['totals']['products_added'] );
		self::assertSame( 1, $page['days'][1]['totals']['products_removed'] );
		self::assertSame( array( 2, 3, 4, 5, 6 ), array_column( $page['days'][1]['sessions'][0]['events'], 'id' ) );
		self::assertSame( 30000, $page['days'][1]['sessions'][0]['totals']['active_ms'] );
		self::assertNull( $page['days'][1]['sessions'][0]['events'][1]['user_id_at_event'] );
		self::assertSame( 7, $page['days'][1]['sessions'][0]['events'][2]['user_id_at_event'] );
	}

	/**
	 * Calendar dates use the site timezone, including a session over midnight.
	 *
	 * @return void
	 */
	public function test_groups_one_session_across_local_midnight(): void {
		$page = ( new Journey_Report_Page_Builder() )->build(
			events: array(
				$this->event( id: 2, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 07:01:00' ),
				$this->event( id: 1, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 06:59:00' ),
			),
			contexts: array( 1 => $this->context( session_id: 1 ) ),
			timezone: new DateTimeZone( 'America/Los_Angeles' )
		);

		self::assertNotNull( $page );
		self::assertSame( array( '2026-09-18', '2026-09-17' ), array_column( $page['days'], 'date' ) );
		self::assertSame( 1, $page['days'][0]['totals']['sessions'] );
		self::assertSame( 1, $page['days'][1]['totals']['sessions'] );
		self::assertSame( 1, $page['days'][0]['sessions'][0]['session_id'] );
		self::assertSame( 1, $page['days'][1]['sessions'][0]['session_id'] );
	}

	/**
	 * Sessions appear newest first while events inside each remain chronological.
	 *
	 * @return void
	 */
	public function test_orders_multiple_sessions_within_one_date(): void {
		$page = ( new Journey_Report_Page_Builder() )->build(
			events: array(
				$this->event( id: 3, session_id: 2, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 12:00:00' ),
				$this->event( id: 2, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 11:00:00' ),
				$this->event( id: 1, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 10:00:00' ),
			),
			contexts: array(),
			timezone: new DateTimeZone( 'UTC' )
		);

		self::assertNotNull( $page );
		self::assertSame( 2, $page['days'][0]['totals']['sessions'] );
		self::assertSame( array( 2, 1 ), array_column( $page['days'][0]['sessions'], 'session_id' ) );
		self::assertSame( array( 1, 2 ), array_column( $page['days'][0]['sessions'][1]['events'], 'id' ) );
		self::assertNull( $page['days'][0]['sessions'][0]['context'] );
	}

	/**
	 * Invalid ordering, timestamps, context ownership, and oversized pages fail.
	 *
	 * @return void
	 */
	public function test_rejects_inconsistent_report_pages(): void {
		$builder = new Journey_Report_Page_Builder();
		$utc     = new DateTimeZone( 'UTC' );
		$first   = $this->event( id: 1, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 10:00:00' );
		$second  = $this->event( id: 2, session_id: 1, type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 11:00:00' );

		self::assertNull( $builder->build( events: array( $first, $second ), contexts: array(), timezone: $utc ) );
		self::assertNull( $builder->build( events: array( $first, $first ), contexts: array(), timezone: $utc ) );
		self::assertNull( $builder->build( events: array_fill( 0, Journey_Report_Repository::MAX_PAGE_SIZE + 1, $first ), contexts: array(), timezone: $utc ) );

		$invalid_time                = $first;
		$invalid_time['occurred_at'] = '2026-09-31 10:00:00';
		self::assertNull( $builder->build( events: array( $invalid_time ), contexts: array(), timezone: $utc ) );

		$wrong_visitor               = $this->context( session_id: 1 );
		$wrong_visitor['visitor_id'] = 99;
		self::assertNull( $builder->build( events: array( $first ), contexts: array( 1 => $wrong_visitor ), timezone: $utc ) );
	}

	/**
	 * An empty event page produces an empty, explicitly scoped report page.
	 *
	 * @return void
	 */
	public function test_empty_page(): void {
		self::assertSame(
			array(
				'totals_scope' => 'loaded_events',
				'days'         => array(),
			),
			( new Journey_Report_Page_Builder() )->build( events: array(), contexts: array(), timezone: new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Produce one parsed event from the report repository's public contract.
	 *
	 * @param int         $id          Event ID.
	 * @param int         $session_id  Session ID.
	 * @param string      $type        Event type.
	 * @param string      $occurred_at UTC event time.
	 * @param string|null $quantity    Cart quantity.
	 * @param int         $active_ms   Visible duration.
	 * @param int|null    $user_id     User ID at event time.
	 * @return array Event row.
	 * @phpstan-return ReportEvent
	 */
	private function event(
		int $id,
		int $session_id,
		string $type,
		string $occurred_at,
		?string $quantity = null,
		int $active_ms = 0,
		?int $user_id = null
	): array {
		return array(
			'id'                => $id,
			'session_id'        => $session_id,
			'visitor_id'        => 3,
			'user_id_at_event'  => $user_id,
			'event_type'        => $type,
			'occurred_at'       => $occurred_at,
			'page_path'         => null,
			'post_id'           => null,
			'product_id'        => null,
			'variation_id'      => null,
			'quantity'          => $quantity,
			'order_id'          => null,
			'related_object_id' => null,
			'active_ms'         => $active_ms,
			'source'            => null,
		);
	}

	/**
	 * Produce one session context from the report repository's public contract.
	 *
	 * @param int $session_id Session ID.
	 * @return array Session context.
	 * @phpstan-return SessionContext
	 */
	private function context( int $session_id ): array {
		return array(
			'id'                  => $session_id,
			'visitor_id'          => 3,
			'identity_period_id'  => 4,
			'user_id_at_start'    => null,
			'began_authenticated' => false,
			'started_at'          => '2026-09-17 10:00:00',
			'last_activity_at'    => '2026-09-18 12:00:00',
			'ended_at'            => null,
			'landing_path'        => '/products',
			'referrer_host'       => null,
			'utm_source'          => null,
			'utm_medium'          => null,
			'utm_campaign'        => null,
			'utm_term'            => null,
			'utm_content'         => null,
		);
	}
}
