<?php
/**
 * Tests for Customer Journey event orchestration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_UUID;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests server-owned identity, field checks, and event persistence.
 */
final class JourneyEventServiceTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Original request cookies.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_cookies = array();

	/**
	 * Original server request context.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server = array();

	/**
	 * Prepare an eligible request with an installed Journey schema.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies = $_COOKIE;
		$this->original_server  = $_SERVER;
		$_COOKIE                = array();

		$GLOBALS['shurloc_test_options']                     = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_filters']                     = array();
		$GLOBALS['shurloc_test_is_admin']                    = false;
		$GLOBALS['shurloc_test_doing_ajax']                  = false;
		$GLOBALS['shurloc_journey_test_doing_cron']          = false;
		$GLOBALS['shurloc_journey_test_current_user']        = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']        = array();
		$GLOBALS['shurloc_journey_cookie_test_result']       = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl']       = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore request and database globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                                       = $this->original_cookies;
		$_SERVER                                       = $this->original_server;
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl'] = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Resolve anonymous identity and store only sanitized page context.
	 *
	 * @return void
	 */
	public function test_page_view_uses_server_identity_time_and_sanitized_path(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/shurloc/v1/journey';
		$this->database->prefix = 'shop_';
		$before                 = gmdate( 'Y-m-d H:i:s' );

		$result = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::PAGE_VIEW,
			page_uri: '/article?utm_source=newsletter&email=private%40example.org',
			referrer_url: 'https://Example.org/post?token=secret',
			post_id: 25,
			source: 'browser',
		);
		$after  = gmdate( 'Y-m-d H:i:s' );

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$result
		);
		self::assertSame( 'shop_shurloc_journey_events', $this->database->insert_calls[3]['table'] );
		self::assertSame( 1, $this->database->events[1]['visitor_id'] );
		self::assertSame( 1, $this->database->events[1]['session_id'] );
		self::assertNull( $this->database->events[1]['user_id_at_event'] );
		self::assertSame( '/article', $this->database->events[1]['page_path'] );
		self::assertSame( 25, $this->database->events[1]['post_id'] );
		self::assertSame( 'browser', $this->database->events[1]['source'] );
		self::assertSame( '/article', $this->database->sessions[1]['landing_path'] );
		self::assertSame( 'newsletter', $this->database->sessions[1]['utm_source'] );
		self::assertSame( 'example.org', $this->database->sessions[1]['referrer_host'] );
		self::assertGreaterThanOrEqual( $before, $this->database->events[1]['occurred_at'] );
		self::assertLessThanOrEqual( $after, $this->database->events[1]['occurred_at'] );
		self::assertSame( 1, $this->database->sessions[1]['page_view_count'] );
		self::assertCount( 1, $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * A product page produces one specialized page view, not two events.
	 *
	 * @return void
	 */
	public function test_product_view_counts_as_one_page_and_product_view(): void {
		$result = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::PRODUCT_VIEW,
			page_uri: '/product/widget?utm_campaign=fall',
			product_id: 9,
			variation_id: 10,
			active_ms: 500,
		);

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$result
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( Journey_Event_Type::PRODUCT_VIEW, $this->database->events[1]['event_type'] );
		self::assertSame( '/product/widget', $this->database->events[1]['page_path'] );
		self::assertSame( 9, $this->database->events[1]['product_id'] );
		self::assertSame( 10, $this->database->events[1]['variation_id'] );
		self::assertSame( 1, $this->database->sessions[1]['page_view_count'] );
		self::assertSame( 1, $this->database->sessions[1]['product_view_count'] );
		self::assertSame( 500, $this->database->sessions[1]['active_ms'] );
	}

	/**
	 * A retried browser view key reuses the first event and its one view count.
	 *
	 * @return void
	 */
	public function test_keyed_view_retry_does_not_create_a_second_event(): void {
		$service = new Journey_Event_Service();
		$key     = str_repeat( 'a', 64 );
		$first   = $service->record(
			event_type: Journey_Event_Type::PAGE_VIEW,
			page_uri: '/article',
			source: 'browser',
			idempotency_key: $key,
		);

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];

		$retry = $service->record(
			event_type: Journey_Event_Type::PAGE_VIEW,
			page_uri: '/article',
			source: 'browser',
			idempotency_key: $key,
		);

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$first
		);
		self::assertSame(
			array(
				'id'      => 1,
				'created' => false,
			),
			$retry
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[1]['page_view_count'] );
	}

	/**
	 * Separate event services share a newly issued identity in one PHP request.
	 *
	 * @return void
	 */
	public function test_multiple_events_in_one_request_keep_one_visitor_and_session(): void {
		$first  = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::PAGE_VIEW,
			page_uri: '/first',
		);
		$second = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::CHECKOUT_STARTED,
			page_uri: '/checkout',
		);

		self::assertSame( 1, $first['id'] ?? null );
		self::assertSame( 2, $second['id'] ?? null );
		self::assertSame( $this->database->events[1]['visitor_id'], $this->database->events[2]['visitor_id'] );
		self::assertSame( $this->database->events[1]['session_id'], $this->database->events[2]['session_id'] );
		self::assertCount( 1, $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertCount( 1, $this->database->visitor_rows );
		self::assertCount( 1, $this->database->sessions );
	}

	/**
	 * Later authenticated events retain the original anonymous session start.
	 *
	 * @return void
	 */
	public function test_authenticated_follow_up_uses_current_user_without_rewriting_history(): void {
		$service = new Journey_Event_Service();
		self::assertNotNull( $service->record( event_type: Journey_Event_Type::PAGE_VIEW, page_uri: '/first' ) );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ]      = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User( 37 );
		$result                                       = $service->record(
			event_type: Journey_Event_Type::CHECKOUT_STARTED,
			page_uri: '/checkout',
			source: 'browser',
		);

		self::assertSame(
			array(
				'id'      => 2,
				'created' => true,
			),
			$result
		);
		self::assertSame( 1, $this->database->events[2]['visitor_id'] );
		self::assertSame( 1, $this->database->events[2]['session_id'] );
		self::assertSame( 37, $this->database->events[2]['user_id_at_event'] );
		self::assertNull( $this->database->events[1]['user_id_at_event'] );
		self::assertNull( $this->database->sessions[1]['user_id_at_start'] );
		self::assertSame( 1, $this->database->sessions[1]['checkout_started_count'] );
	}

	/**
	 * Reloads and retries use one checkout start for the current session.
	 *
	 * @return void
	 */
	public function test_checkout_start_is_idempotent_within_one_session(): void {
		$first                                        = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::CHECKOUT_STARTED,
			page_uri: '/checkout?utm_source=email',
			source: 'browser',
		);
		$_COOKIE[ Journey_Visitor_Cookie::NAME ]      = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User( 37 );
		$retry                                        = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::CHECKOUT_STARTED,
			page_uri: '/checkout?utm_campaign=fall',
			source: 'browser',
		);

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$first
		);
		self::assertSame(
			array(
				'id'      => 1,
				'created' => false,
			),
			$retry
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( '/checkout', $this->database->events[1]['page_path'] );
		self::assertNull( $this->database->events[1]['user_id_at_event'] );
		self::assertSame( hash( 'sha256', 'shurloc_journey:CHECKOUT_STARTED:1' ), $this->database->events[1]['idempotency_key'] );
		self::assertSame( 1, $this->database->sessions[1]['checkout_started_count'] );
	}

	/**
	 * A returning visitor may start checkout again in a later session.
	 *
	 * @return void
	 */
	public function test_checkout_start_is_recorded_again_after_session_timeout(): void {
		$first = ( new Journey_Event_Service() )->record( event_type: Journey_Event_Type::CHECKOUT_STARTED, page_uri: '/checkout', source: 'browser' );
		self::assertSame( 1, $first['id'] ?? null );
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];

		$past                        = gmdate( 'Y-m-d H:i:s', time() - Journey_Session_Service::DEFAULT_TIMEOUT_SECONDS - 5 );
		$row                         = $this->database->sessions[1];
		$row['started_at']           = $past;
		$row['last_activity_at']     = $past;
		$this->database->sessions[1] = $row;

		$next = ( new Journey_Event_Service() )->record( event_type: Journey_Event_Type::CHECKOUT_STARTED, page_uri: '/checkout?utm_source=return', source: 'browser' );
		self::assertSame(
			array(
				'id'      => 2,
				'created' => true,
			),
			$next
		);
		self::assertCount( 2, $this->database->events );
		self::assertSame( 2, $this->database->events[2]['session_id'] );
		self::assertNotSame( $this->database->events[1]['idempotency_key'], $this->database->events[2]['idempotency_key'] );
		self::assertSame( 1, $this->database->sessions[1]['checkout_started_count'] );
		self::assertSame( 1, $this->database->sessions[2]['checkout_started_count'] );
	}

	/**
	 * Cart quantities and order creation use their dedicated event semantics.
	 *
	 * @return void
	 */
	public function test_cart_events_and_order_creation_are_recorded_once(): void {
		$service = new Journey_Event_Service();
		self::assertNotNull(
			$service->record(
				event_type: Journey_Event_Type::ADD_TO_CART,
				product_id: 9,
				variation_id: 10,
				quantity: '2.5000',
				source: 'woocommerce',
			)
		);

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		self::assertNotNull(
			$service->record(
				event_type: Journey_Event_Type::REMOVE_FROM_CART,
				product_id: 9,
				variation_id: 10,
				quantity: '0.5000',
				source: 'woocommerce',
			)
		);

		$order = $service->record(
			event_type: Journey_Event_Type::ORDER_CREATED,
			order_id: 81,
			source: 'woocommerce',
		);
		$retry = $service->record(
			event_type: Journey_Event_Type::ORDER_CREATED,
			order_id: 81,
			source: 'woocommerce',
		);

		self::assertSame(
			array(
				'id'      => 3,
				'created' => true,
			),
			$order
		);
		self::assertSame(
			array(
				'id'      => 3,
				'created' => false,
			),
			$retry
		);
		self::assertSame( hash( 'sha256', 'shurloc_journey:ORDER_CREATED:81' ), $this->database->events[3]['idempotency_key'] );
		self::assertSame( 81, $this->database->events[3]['order_id'] );
		self::assertCount( 3, $this->database->events );
		self::assertSame( 1, $this->database->sessions[1]['cart_add_count'] );
		self::assertSame( 1, $this->database->sessions[1]['cart_remove_count'] );
		self::assertSame( '2.5000', $this->database->sessions[1]['added_quantity'] );
		self::assertSame( '0.5000', $this->database->sessions[1]['removed_quantity'] );
		self::assertSame( 1, $this->database->sessions[1]['order_created_count'] );
	}

	/**
	 * Invalid details do not create a cookie, visitor, session, or event.
	 *
	 * @return void
	 */
	public function test_invalid_event_details_are_rejected_before_identity_resolution(): void {
		$service = new Journey_Event_Service();
		$invalid = array(
			array( 'event_type' => Journey_Event_Type::PAGE_VIEW ),
			array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'page_uri'   => 'https://example.org/private',
			),
			array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'page_uri'   => '/page',
				'product_id' => 9,
			),
			array(
				'event_type' => Journey_Event_Type::PRODUCT_VIEW,
				'page_uri'   => '/product',
			),
			array(
				'event_type' => Journey_Event_Type::ADD_TO_CART,
				'product_id' => 9,
				'quantity'   => '0',
			),
			array( 'event_type' => Journey_Event_Type::ORDER_CREATED ),
			array(
				'event_type'      => Journey_Event_Type::ORDER_CREATED,
				'order_id'        => 81,
				'idempotency_key' => str_repeat( 'a', 64 ),
			),
			array( 'event_type' => 'ORDER_PAID' ),
			array(
				'event_type' => Journey_Event_Type::CHECKOUT_STARTED,
				'source'     => 'unsafe/source',
			),
			array(
				'event_type'      => Journey_Event_Type::CHECKOUT_STARTED,
				'idempotency_key' => str_repeat( 'a', 64 ),
			),
		);

		foreach ( $invalid as $arguments ) {
			self::assertNull( $service->record( ...$arguments ) );
		}

		self::assertSame( array(), $this->database->insert_calls );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertSame( array(), $this->database->events );
	}

	/**
	 * Collection policy and schema readiness gate the event service.
	 *
	 * @return void
	 */
	public function test_collection_policy_schema_and_storage_failures_prevent_events(): void {
		$service = new Journey_Event_Service();
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		self::assertNull( $service->record( event_type: Journey_Event_Type::PAGE_VIEW, page_uri: '/page' ) );
		self::assertSame( array(), $this->database->insert_calls );

		$GLOBALS['shurloc_test_filters'] = array();
		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $service->record( event_type: Journey_Event_Type::PAGE_VIEW, page_uri: '/page' ) );
		self::assertSame( array(), $this->database->insert_calls );

		$GLOBALS['shurloc_test_options']   = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database->fail_event_insert = true;
		self::assertNull( $service->record( event_type: Journey_Event_Type::PAGE_VIEW, page_uri: '/page' ) );
		self::assertSame( array(), $this->database->events );
	}

	/**
	 * Visible time belongs to the original view and never extends its session.
	 *
	 * @return void
	 */
	public function test_view_duration_uses_same_request_visitor_and_cumulative_totals(): void {
		$view = ( new Journey_Event_Service() )->record(
			event_type: Journey_Event_Type::PAGE_VIEW,
			page_uri: '/article',
		);
		self::assertSame( 1, $view['id'] ?? null );

		$last_activity = $this->database->sessions[1]['last_activity_at'];
		$service       = new Journey_Event_Service();

		self::assertTrue( $service->record_view_duration( event_id: 1, total_active_ms: 1200 ) );
		self::assertTrue( $service->record_view_duration( event_id: 1, total_active_ms: 1200 ) );
		self::assertTrue( $service->record_view_duration( event_id: 1, total_active_ms: 900 ) );
		self::assertSame( 1200, $this->database->events[1]['active_ms'] );
		self::assertSame( 1200, $this->database->sessions[1]['active_ms'] );

		self::assertTrue( $service->record_view_duration( event_id: 1, total_active_ms: 2000 ) );
		self::assertSame( 2000, $this->database->events[1]['active_ms'] );
		self::assertSame( 2000, $this->database->sessions[1]['active_ms'] );
		self::assertSame( 1, $this->database->sessions[1]['page_view_count'] );
		self::assertSame( $last_activity, $this->database->sessions[1]['last_activity_at'] );
		self::assertCount( 1, $this->database->visitor_rows );
		self::assertCount( 1, $this->database->sessions );
		self::assertCount( 1, $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * A different browser cannot add time to another visitor's view.
	 *
	 * @return void
	 */
	public function test_view_duration_requires_current_visitor_to_own_the_view(): void {
		self::assertNotNull(
			( new Journey_Event_Service() )->record(
				event_type: Journey_Event_Type::PRODUCT_VIEW,
				page_uri: '/product/widget',
				product_id: 9,
			)
		);

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = Journey_Visitor_UUID::generate();
		self::assertFalse( ( new Journey_Event_Service() )->record_view_duration( event_id: 1, total_active_ms: 500 ) );
		self::assertSame( 0, $this->database->events[1]['active_ms'] );
		self::assertSame( 0, $this->database->sessions[1]['active_ms'] );
		self::assertCount( 1, $this->database->sessions );
	}

	/**
	 * Current consent and schema readiness gate duration writes.
	 *
	 * @return void
	 */
	public function test_view_duration_respects_collection_policy_and_schema_readiness(): void {
		self::assertNotNull(
			( new Journey_Event_Service() )->record(
				event_type: Journey_Event_Type::PAGE_VIEW,
				page_uri: '/article',
			)
		);

		$service = new Journey_Event_Service();
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		self::assertFalse( $service->record_view_duration( event_id: 1, total_active_ms: 500 ) );

		$GLOBALS['shurloc_test_filters'] = array();
		$GLOBALS['shurloc_test_options'] = array();
		self::assertFalse( $service->record_view_duration( event_id: 1, total_active_ms: 500 ) );
		self::assertSame( 0, $this->database->events[1]['active_ms'] );
		self::assertSame( 0, $this->database->sessions[1]['active_ms'] );
	}

	/**
	 * Malformed duration input is rejected before issuing a visitor cookie.
	 *
	 * @return void
	 */
	public function test_invalid_view_duration_is_rejected_before_identity_resolution(): void {
		$service = new Journey_Event_Service();
		self::assertFalse( $service->record_view_duration( event_id: 0, total_active_ms: 500 ) );
		self::assertFalse( $service->record_view_duration( event_id: 1, total_active_ms: -1 ) );
		self::assertSame( array(), $this->database->insert_calls );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}
}
