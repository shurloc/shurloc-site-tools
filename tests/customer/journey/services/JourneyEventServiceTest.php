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
}
