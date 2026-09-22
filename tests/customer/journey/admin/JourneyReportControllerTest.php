<?php
/**
 * Tests for Customer Journey report administration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Visitor_Repository;
use Shurloc_Test_WPDB;
use stdClass;

/**
 * Verify capability, bounded selection, orchestration, and pagination.
 */
final class JourneyReportControllerTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Controller under test.
	 *
	 * @var Journey_Report_Controller
	 */
	private Journey_Report_Controller $controller;

	/**
	 * Prepare a ready schema and deterministic local date.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options']           = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_users']             = array();
		$GLOBALS['shurloc_test_user_data']         = array();
		$GLOBALS['shurloc_test_user_capabilities'] = array( Journey_Report_Controller::CAPABILITY => true );
		$GLOBALS['shurloc_test_wp_die_messages']   = array();
		$GLOBALS['shurloc_test_styles']            = array();
		$GLOBALS['shurloc_test_enqueued_scripts']  = array();
		$_GET                                      = array();

		$this->database = new Shurloc_Test_WPDB();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;

		$timezone         = new DateTimeZone( 'America/Los_Angeles' );
		$this->controller = new Journey_Report_Controller(
			report_repository: new Journey_Report_Repository(),
			session_repository: new Journey_Report_Session_Repository(),
			visitor_repository: new Journey_Report_Visitor_Repository(),
			page_builder: new Journey_Report_Page_Builder(),
			renderer: new Journey_Report_Renderer(),
			timezone: $timezone,
			now: new DateTimeImmutable( '2026-09-21 12:00:00', $timezone )
		);
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options']           = array();
		$GLOBALS['shurloc_test_users']             = array();
		$GLOBALS['shurloc_test_user_data']         = array();
		$GLOBALS['shurloc_test_user_capabilities'] = array();
		$GLOBALS['shurloc_test_wp_die_messages']   = array();
		$GLOBALS['shurloc_test_styles']            = array();
		$GLOBALS['shurloc_test_enqueued_scripts']  = array();
		$_GET                                      = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * The landing page lists recent authenticated and anonymous journeys.
	 *
	 * @return void
	 */
	public function test_renders_recent_subject_links_and_bounded_selectors(): void {
		$this->database->prefix               = 'shop_';
		$GLOBALS['shurloc_test_users'][7]     = true;
		$GLOBALS['shurloc_test_user_data'][7] = array( 'display_name' => 'alice' );
		$this->database->result_queue         = array(
			array(
				(object) array(
					'id'             => '12',
					'created_at'     => '2026-09-16 12:00:00',
					'last_seen_at'   => '2026-09-18 12:00:00',
					'first_touch_at' => null,
				),
			),
			array(
				(object) array(
					'subject_type'     => 'customer',
					'subject_id'       => '7',
					'last_activity_at' => '2026-09-18 12:00:00',
				),
				(object) array(
					'subject_type'     => 'visitor',
					'subject_id'       => '12',
					'last_activity_at' => '2026-09-18 12:00:00',
				),
			),
		);

		$output = $this->render();

		self::assertStringContainsString( 'class="wc-customer-search"', $output );
		self::assertStringContainsString( 'data-action="woocommerce_json_search_customers"', $output );
		self::assertStringContainsString( 'Anonymous Visitor #12 — last seen 2026-09-18 5:00 am', $output );
		self::assertStringContainsString( 'name="journey_from" value="2026-09-15"', $output );
		self::assertStringContainsString( 'name="journey_to" value="2026-09-21"', $output );
		self::assertStringContainsString( '<h3>Recent Journeys</h3>', $output );
		self::assertStringContainsString( '>alice (#7)</a>', $output );
		self::assertStringContainsString( '>Anonymous Visitor #12</a>', $output );
		self::assertStringContainsString( '<td>Authenticated customer</td>', $output );
		self::assertStringContainsString( '<td>Anonymous visitor</td>', $output );
		self::assertStringContainsString( 'journey_from=2026-09-12&amp;journey_to=2026-09-18&amp;journey_subject=customer&amp;journey_user_id=7', $output );
		self::assertStringContainsString( 'journey_from=2026-09-12&amp;journey_to=2026-09-18&amp;journey_subject=visitor&amp;journey_visitor_id=12', $output );
		self::assertStringNotContainsString( 'visitor_uuid', $output );
		self::assertCount( 2, $this->database->prepared_queries );
		self::assertSame( 'shop_shurloc_journey_visitors', $this->database->prepared_queries[0]['args'][0] );
		self::assertSame( 50, $this->database->prepared_queries[0]['args'][6] );
		self::assertSame(
			array(
				'shop_shurloc_journey_events',
				'shop_shurloc_journey_events',
				'shop_shurloc_journey_identity_periods',
				50,
			),
			$this->database->prepared_queries[1]['args']
		);
	}

	/**
	 * Deleted customer accounts remain visible without a broken report link.
	 *
	 * @return void
	 */
	public function test_renders_unavailable_customer_without_link(): void {
		$this->database->result_queue = array(
			array(),
			array(
				(object) array(
					'subject_type'     => 'customer',
					'subject_id'       => '9',
					'last_activity_at' => '2026-09-18 12:00:00',
				),
			),
		);

		$output = $this->render();

		self::assertStringContainsString( 'Unavailable customer (#9)', $output );
		self::assertStringNotContainsString( 'journey_user_id=9', $output );
	}

	/**
	 * Customer requests load one event page, its sessions, and linked history.
	 *
	 * @return void
	 */
	public function test_renders_customer_report_with_local_date_boundaries(): void {
		$_GET                                 = array(
			'journey_subject' => 'customer',
			'journey_user_id' => '7',
			'journey_from'    => '2026-09-18',
			'journey_to'      => '2026-09-18',
		);
		$GLOBALS['shurloc_test_users'][7]     = true;
		$GLOBALS['shurloc_test_user_data'][7] = array(
			'display_name' => 'A. Customer',
			'user_email'   => 'private@example.com',
		);
		$this->database->result_queue         = array(
			array( $this->event_row( id: '31', user_id: null ) ),
			array( $this->session_row() ),
		);

		$output = $this->render();

		self::assertStringContainsString( 'A. Customer (#7)', $output );
		self::assertStringContainsString( 'Anonymous, later linked', $output );
		self::assertStringContainsString( '/products', $output );
		self::assertStringNotContainsString( 'private@example.com', $output );
		self::assertCount( 2, $this->database->prepared_queries );
		$event_query = $this->database->prepared_queries[0];
		self::assertSame( '2026-09-18 07:00:00', $event_query['args'][1] );
		self::assertSame( '2026-09-19 07:00:00', $event_query['args'][2] );
		self::assertSame( 7, $event_query['args'][6] );
		self::assertStringContainsString( 'p.user_id = %d', $event_query['query'] );
		self::assertSame( array( 'wp_shurloc_journey_sessions', 9 ), $this->database->prepared_queries[1]['args'] );
	}

	/**
	 * Anonymous reports retain their never-linked query and identity semantics.
	 *
	 * @return void
	 */
	public function test_renders_never_linked_anonymous_report(): void {
		$_GET                         = array(
			'journey_subject'    => 'visitor',
			'journey_visitor_id' => '12',
			'journey_from'       => '2026-09-18',
			'journey_to'         => '2026-09-18',
		);
		$this->database->result_queue = array(
			array( $this->event_row( id: '31', user_id: null ) ),
			array( $this->session_row() ),
		);

		$output = $this->render();

		self::assertStringContainsString( '<h3 class="shurloc-journey-subject">Anonymous Visitor #12</h3>', $output );
		self::assertStringContainsString( '<td>Anonymous</td>', $output );
		self::assertStringNotContainsString( 'later linked', $output );
		$query = $this->database->prepared_queries[0];
		self::assertStringContainsString( 'e.visitor_id = %d AND e.user_id_at_event IS NULL', $query['query'] );
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $query['query'] );
		self::assertSame( 12, $query['args'][2] );
	}

	/**
	 * Event cursors are validated and passed intact to keyset pagination.
	 *
	 * @return void
	 */
	public function test_passes_a_valid_paired_event_cursor(): void {
		$_GET                             = array(
			'journey_subject'   => 'customer',
			'journey_user_id'   => '7',
			'journey_from'      => '2026-09-18',
			'journey_to'        => '2026-09-18',
			'journey_before_at' => '2026-09-18 12:01:00',
			'journey_before_id' => '32',
		);
		$GLOBALS['shurloc_test_users'][7] = true;
		$this->database->result_queue     = array(
			array( $this->event_row( id: '31', user_id: '7' ) ),
			array( $this->session_row() ),
		);

		$this->render();

		$args = $this->database->prepared_queries[0]['args'];
		self::assertSame( '2026-09-18 12:01:00', $args[3] );
		self::assertSame( '2026-09-18 12:01:00', $args[4] );
		self::assertSame( 32, $args[5] );
	}

	/**
	 * A full event page emits an older-events keyset link.
	 *
	 * @return void
	 */
	public function test_full_event_page_renders_older_events_link(): void {
		$_GET                             = array(
			'journey_subject' => 'customer',
			'journey_user_id' => '7',
			'journey_from'    => '2026-09-18',
			'journey_to'      => '2026-09-18',
		);
		$GLOBALS['shurloc_test_users'][7] = true;

		$events = array();
		for ( $id = 50; 1 <= $id; --$id ) {
			$events[] = $this->event_row( id: (string) $id, user_id: '7' );
		}
		$this->database->result_queue = array(
			$events,
			array( $this->session_row() ),
		);

		$output = $this->render();

		self::assertStringContainsString( '>Older events</a>', $output );
		self::assertStringContainsString( 'journey_before_at=2026-09-18+12%3A00%3A00', $output );
		self::assertStringContainsString( 'journey_before_id=1', $output );
	}

	/**
	 * Invalid subject, date, customer, and cursor inputs perform no report query.
	 *
	 * @return void
	 */
	public function test_invalid_report_requests_fail_closed(): void {
		$requests = array(
			array( 'journey_subject' => 'invalid' ),
			array(
				'journey_subject' => 'customer',
				'journey_user_id' => '7',
			),
			array(
				'journey_subject'    => 'visitor',
				'journey_visitor_id' => '12',
				'journey_from'       => '2026-09-31',
				'journey_to'         => '2026-09-31',
			),
			array(
				'journey_subject'    => 'visitor',
				'journey_visitor_id' => '12',
				'journey_from'       => '2026-08-01',
				'journey_to'         => '2026-09-18',
			),
			array(
				'journey_subject'    => 'visitor',
				'journey_visitor_id' => '12',
				'journey_before_at'  => '2026-09-18 12:00:00',
			),
			array(
				'journey_subject'    => 'visitor',
				'journey_visitor_id' => '12',
				'journey_before_id'  => 'invalid',
			),
		);

		foreach ( $requests as $request ) {
			$_GET = $request;
			$this->render();
		}

		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Unavailable schema fails closed with a visible retry-oriented notice.
	 *
	 * @return void
	 */
	public function test_unavailable_schema_renders_notice_without_query(): void {
		$GLOBALS['shurloc_test_options'] = array();

		$output = $this->render();

		self::assertStringContainsString( 'Journey reporting is unavailable. Verify the Journey schema and retry.', $output );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Report rendering enforces the Customer Tools capability before any read.
	 *
	 * @return void
	 */
	public function test_denies_unauthorized_report_requests(): void {
		$GLOBALS['shurloc_test_user_capabilities'][ Journey_Report_Controller::CAPABILITY ] = false;

		try {
			$this->controller->render();
			self::fail( 'Expected wp_die() to terminate the request.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'permission to view Customer Journey reports', $exception->getMessage() );
		}

		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * WooCommerce selector assets load only for authorized Journey tab requests.
	 *
	 * @return void
	 */
	public function test_enqueues_customer_search_assets_only_on_journey_tab(): void {
		$this->controller->enqueue_assets();
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );

		$_GET = array(
			'page' => Journey_Report_Controller::PAGE_SLUG,
			'tab'  => Journey_Report_Controller::TAB_SLUG,
		);
		$this->controller->enqueue_assets();

		self::assertArrayHasKey( 'woocommerce_admin_styles', $GLOBALS['shurloc_test_styles'] );
		self::assertSame( 'wc-enhanced-select', $GLOBALS['shurloc_test_enqueued_scripts'][0]['handle'] );

		$GLOBALS['shurloc_test_user_capabilities'][ Journey_Report_Controller::CAPABILITY ] = false;
		$this->controller->enqueue_assets();
		self::assertCount( 1, $GLOBALS['shurloc_test_enqueued_scripts'] );
	}

	/**
	 * Capture controller output.
	 *
	 * @return string Rendered markup.
	 */
	private function render(): string {
		ob_start();
		$this->controller->render();
		return (string) ob_get_clean();
	}

	/**
	 * Build a report event database row.
	 *
	 * @param string      $id      Event ID.
	 * @param string|null $user_id User at event capture.
	 * @return stdClass Database row.
	 */
	private function event_row( string $id, ?string $user_id ): stdClass {
		return (object) array(
			'id'                => $id,
			'session_id'        => '9',
			'visitor_id'        => '12',
			'user_id_at_event'  => $user_id,
			'event_type'        => Journey_Event_Type::PAGE_VIEW,
			'occurred_at'       => '2026-09-18 12:00:00',
			'page_path'         => '/products',
			'post_id'           => null,
			'product_id'        => null,
			'variation_id'      => null,
			'quantity'          => null,
			'order_id'          => null,
			'related_object_id' => null,
			'active_ms'         => '1000',
			'source'            => 'browser',
		);
	}

	/**
	 * Build a session context database row.
	 *
	 * @return stdClass Database row.
	 */
	private function session_row(): stdClass {
		return (object) array(
			'id'                  => '9',
			'visitor_id'          => '12',
			'identity_period_id'  => '4',
			'user_id_at_start'    => null,
			'began_authenticated' => '0',
			'started_at'          => '2026-09-18 12:00:00',
			'last_activity_at'    => '2026-09-18 12:01:00',
			'ended_at'            => null,
			'landing_path'        => '/products',
			'referrer_host'       => 'example.org',
			'utm_source'          => 'newsletter',
			'utm_medium'          => 'email',
			'utm_campaign'        => 'autumn',
			'utm_term'            => null,
			'utm_content'         => null,
		);
	}
}
