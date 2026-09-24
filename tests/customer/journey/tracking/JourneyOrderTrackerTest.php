<?php
/**
 * Tests for Customer Journey checkout order creation tracking.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Tracking;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;
use WC_Order;

/**
 * Verify checkout order hooks create one event for each persisted order.
 */
final class JourneyOrderTrackerTest extends TestCase {
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
	private array $original_cookies;

	/**
	 * Original server environment.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server;

	/**
	 * Prepare a browser checkout request with the Journey schema installed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies                        = $_COOKIE;
		$this->original_server                         = $_SERVER;
		$_COOKIE                                       = array();
		$_SERVER['HTTP_USER_AGENT']                    = 'Mozilla/5.0';
		$_SERVER['REQUEST_URI']                        = '/checkout';
		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_action_metadata']       = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_options']               = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_orders']                = array();
		$GLOBALS['shurloc_test_is_admin']              = false;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                                       = $this->original_cookies;
		$_SERVER                                       = $this->original_server;
		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_action_metadata']       = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_orders']                = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the shared test database double.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();
		parent::tearDown();
	}

	/**
	 * Classic and Store API hooks share one idempotent order callback.
	 *
	 * @return void
	 */
	public function test_registers_checkout_creation_and_store_api_fallback_hooks(): void {
		$tracker = new Journey_Order_Tracker();
		$tracker->register();

		foreach (
			array(
				'woocommerce_checkout_order_created',
				'woocommerce_store_api_checkout_order_created',
				'woocommerce_store_api_checkout_update_order_from_request',
			) as $hook
		) {
			self::assertSame( array( array( $tracker, 'order_created' ) ), $GLOBALS['shurloc_test_actions'][ $hook ] );
			self::assertSame( 1, $GLOBALS['shurloc_test_action_metadata'][ $hook ][0]['accepted_args'] );
		}
		self::assertCount( 3, $GLOBALS['shurloc_test_actions'] );
		self::assertSame( array(), $this->database->events );
	}

	/**
	 * A persisted classic checkout order records creation before payment.
	 *
	 * @return void
	 */
	public function test_classic_checkout_records_created_order_without_payment_status(): void {
		$order = new WC_Order( 81 );
		$order->set_status( 'pending' );
		$order->set_customer_id( 92 );
		$GLOBALS['shurloc_test_orders'][81] = $order;

		( new Journey_Order_Tracker() )->order_created( $order );

		self::assertCount( 1, $this->database->events );
		self::assertSame( Journey_Event_Type::ORDER_CREATED, $this->database->events[1]['event_type'] );
		self::assertSame( 81, $this->database->events[1]['order_id'] );
		self::assertSame( 'woocommerce', $this->database->events[1]['source'] );
		self::assertNull( $this->database->events[1]['page_path'] );
		self::assertNull( $this->database->events[1]['user_id_at_event'] );
		self::assertSame( hash( 'sha256', 'shurloc_journey:ORDER_CREATED:81' ), $this->database->events[1]['idempotency_key'] );
		self::assertSame( 1, $this->database->sessions[1]['order_created_count'] );
	}

	/**
	 * A draft order and later Store API updates produce one created event.
	 *
	 * @return void
	 */
	public function test_store_api_draft_and_repeated_order_updates_are_idempotent(): void {
		$order = new WC_Order( 82 );
		$order->set_status( 'checkout-draft' );
		$GLOBALS['shurloc_test_orders'][82] = $order;
		$tracker                            = new Journey_Order_Tracker();
		$tracker->order_created( $order );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		$order->set_status( 'pending' );
		$tracker->order_created( $order );
		$tracker->order_created( $order );
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[1]['order_created_count'] );

		$another = new WC_Order( 83 );
		$another->set_status( 'failed' );
		$GLOBALS['shurloc_test_orders'][83] = $another;
		$tracker->order_created( $another );
		self::assertCount( 2, $this->database->events );
		self::assertSame( 83, $this->database->events[2]['order_id'] );
		self::assertSame( 2, $this->database->sessions[1]['order_created_count'] );
	}

	/**
	 * Reject hook values without a matching persisted WooCommerce order.
	 *
	 * @return void
	 */
	public function test_rejects_invalid_or_nonpersisted_orders_without_creating_identity(): void {
		$tracker = new Journey_Order_Tracker();
		$tracker->order_created( 81 );
		$tracker->order_created( new WC_Order() );
		$tracker->order_created( new WC_Order( 81 ) );
		$GLOBALS['shurloc_test_orders'][82] = new WC_Order( 83 );
		$tracker->order_created( new WC_Order( 82 ) );

		self::assertSame( array(), $this->database->events );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * Collection consent and schema readiness gate order events centrally.
	 *
	 * @return void
	 */
	public function test_collection_policy_and_schema_gate_order_creation(): void {
		$order                              = new WC_Order( 84 );
		$GLOBALS['shurloc_test_orders'][84] = $order;
		$tracker                            = new Journey_Order_Tracker();

		$GLOBALS['shurloc_test_options'] = array();
		$tracker->order_created( $order );
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		$tracker->order_created( $order );
		$GLOBALS['shurloc_test_filters']  = array();
		$GLOBALS['shurloc_test_is_admin'] = true;
		$tracker->order_created( $order );

		self::assertSame( array(), $this->database->events );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * A failed event insert can succeed on a later checkout callback.
	 *
	 * @return void
	 */
	public function test_storage_failure_does_not_mark_order_created_before_retry(): void {
		$order                              = new WC_Order( 85 );
		$GLOBALS['shurloc_test_orders'][85] = $order;
		$tracker                            = new Journey_Order_Tracker();
		$this->database->fail_event_insert  = true;
		$tracker->order_created( $order );
		self::assertArrayNotHasKey( 1, $this->database->events );
		self::assertSame( 0, $this->database->sessions[1]['order_created_count'] );

		$this->database->fail_event_insert = false;
		$tracker->order_created( $order );
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[1]['order_created_count'] );
	}
}
