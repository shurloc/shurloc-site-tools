<?php
/**
 * Tests for Customer Journey event storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests event inserts, keyed retries, and failure boundaries.
 */
final class JourneyEventRepositoryTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare the installed schema version and database.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database                  = new Shurloc_Test_WPDB();
		$this->database->sessions[20]    = array(
			'id'                     => 20,
			'visitor_id'             => 12,
			'identity_period_id'     => 3,
			'user_id_at_start'       => null,
			'began_authenticated'    => 0,
			'started_at'             => '2026-09-16 12:00:00',
			'last_activity_at'       => '2026-09-16 12:00:00',
			'ended_at'               => null,
			'landing_path'           => '/widgets',
			'referrer_host'          => null,
			'utm_source'             => null,
			'utm_medium'             => null,
			'utm_campaign'           => null,
			'utm_term'               => null,
			'utm_content'            => null,
			'page_view_count'        => 0,
			'product_view_count'     => 0,
			'cart_add_count'         => 0,
			'cart_remove_count'      => 0,
			'added_quantity'         => '0.0000',
			'removed_quantity'       => '0.0000',
			'checkout_started_count' => 0,
			'order_created_count'    => 0,
			'active_ms'              => 0,
		);

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
	 * An insert maps every v1 column and honors the current site prefix.
	 *
	 * @return void
	 */
	public function test_inserts_complete_event_with_current_table_prefix(): void {
		$this->database->prefix    = 'shop_';
		$event                     = $this->event();
		$event['event_type']       = Journey_Event_Type::PRODUCT_VIEW;
		$event['user_id_at_event'] = 37;
		$event['page_path']        = '/widgets';
		$event['post_id']          = 8;
		$event['product_id']       = 9;
		$event['variation_id']     = 10;
		$event['active_ms']        = 1234;
		$event['source']           = 'browser';

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			( new Journey_Event_Repository() )->record( $event )
		);
		self::assertSame( 'shop_shurloc_journey_events', $this->database->insert_calls[0]['table'] );
		self::assertSame( $event['session_id'], $this->database->events[1]['session_id'] );
		self::assertSame( $event['visitor_id'], $this->database->events[1]['visitor_id'] );
		self::assertSame( $event['user_id_at_event'], $this->database->events[1]['user_id_at_event'] );
		self::assertSame( $event['event_type'], $this->database->events[1]['event_type'] );
		self::assertSame( $event['occurred_at'], $this->database->events[1]['occurred_at'] );
		self::assertSame( $event['page_path'], $this->database->events[1]['page_path'] );
		self::assertSame( $event['post_id'], $this->database->events[1]['post_id'] );
		self::assertSame( $event['product_id'], $this->database->events[1]['product_id'] );
		self::assertSame( $event['variation_id'], $this->database->events[1]['variation_id'] );
		self::assertNull( $this->database->events[1]['quantity'] );
		self::assertNull( $this->database->events[1]['order_id'] );
		self::assertNull( $this->database->events[1]['related_object_id'] );
		self::assertSame( $event['active_ms'], $this->database->events[1]['active_ms'] );
		self::assertSame( $event['source'], $this->database->events[1]['source'] );
		self::assertNull( $this->database->events[1]['idempotency_key'] );
		self::assertCount( 15, $this->database->insert_calls[0]['formats'] );
		self::assertSame( 1, $this->database->sessions[20]['page_view_count'] );
		self::assertSame( 1, $this->database->sessions[20]['product_view_count'] );
		self::assertSame( 1234, $this->database->sessions[20]['active_ms'] );
		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[20]['last_activity_at'] );
		self::assertSame( 'START TRANSACTION', $this->database->queries[0] );
		self::assertStringStartsWith( 'UPDATE %i SET ', $this->database->queries[1] );
		self::assertSame( 'COMMIT', $this->database->queries[2] );
		self::assertSame( 'shop_shurloc_journey_sessions', $this->database->prepared_queries[0]['args'][0] );
	}

	/**
	 * Nullable idempotency keys permit independent unkeyed events.
	 *
	 * @return void
	 */
	public function test_unkeyed_events_remain_distinct(): void {
		$repository = new Journey_Event_Repository();

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$repository->record( $this->event() )
		);
		self::assertSame(
			array(
				'id'      => 2,
				'created' => true,
			),
			$repository->record( $this->event() )
		);
		self::assertCount( 2, $this->database->events );
		self::assertSame( 2, $this->database->sessions[20]['page_view_count'] );
	}

	/**
	 * A view retry still matches after visible duration has grown in storage.
	 *
	 * @return void
	 */
	public function test_keyed_view_retry_does_not_recount_mutable_duration(): void {
		$repository               = new Journey_Event_Repository();
		$event                    = $this->event();
		$event['idempotency_key'] = str_repeat( 'f', 64 );
		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$repository->record( $event )
		);

		$this->database->events[1]['active_ms']    = 750;
		$this->database->sessions[20]['active_ms'] = 750;
		self::assertSame(
			array(
				'id'      => 1,
				'created' => false,
			),
			$repository->record( $event )
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[20]['page_view_count'] );
		self::assertSame( 750, $this->database->sessions[20]['active_ms'] );
	}

	/**
	 * Cart, checkout, and order events update only their own summary fields.
	 *
	 * @return void
	 */
	public function test_other_event_types_increment_exact_session_totals(): void {
		$repository = new Journey_Event_Repository();
		$add        = array_replace(
			$this->event(),
			array(
				'event_type' => Journey_Event_Type::ADD_TO_CART,
				'product_id' => 9,
				'quantity'   => '0.0001',
			)
		);
		self::assertNotNull( $repository->record( $add ) );
		self::assertStringContainsString( 'CAST(%s AS DECIMAL(16,4))', $this->database->prepared_queries[0]['query'] );
		$add['quantity'] = '0.0002';
		self::assertNotNull( $repository->record( $add ) );

		$remove = array_replace(
			$add,
			array(
				'event_type' => Journey_Event_Type::REMOVE_FROM_CART,
				'quantity'   => '0.0001',
			)
		);
		self::assertNotNull( $repository->record( $remove ) );
		$checkout = array_replace( $this->event(), array( 'event_type' => Journey_Event_Type::CHECKOUT_STARTED ) );
		self::assertNotNull( $repository->record( $checkout ) );
		$order = array_replace(
			$this->event(),
			array(
				'event_type'      => Journey_Event_Type::ORDER_CREATED,
				'order_id'        => 81,
				'idempotency_key' => str_repeat( 'e', 64 ),
			)
		);
		self::assertNotNull( $repository->record( $order ) );

		$summary = $this->database->sessions[20];
		self::assertSame( 0, $summary['page_view_count'] );
		self::assertSame( 0, $summary['product_view_count'] );
		self::assertSame( 2, $summary['cart_add_count'] );
		self::assertSame( 1, $summary['cart_remove_count'] );
		self::assertSame( '0.0003', $summary['added_quantity'] );
		self::assertSame( '0.0001', $summary['removed_quantity'] );
		self::assertSame( 1, $summary['checkout_started_count'] );
		self::assertSame( 1, $summary['order_created_count'] );
		self::assertSame( 0, $summary['active_ms'] );
		self::assertCount( 5, $this->database->events );
	}

	/**
	 * A WooCommerce order creation retry resolves to its existing event row.
	 *
	 * @return void
	 */
	public function test_order_created_retry_uses_key_and_order_id(): void {
		$repository               = new Journey_Event_Repository();
		$event                    = $this->event();
		$event['event_type']      = Journey_Event_Type::ORDER_CREATED;
		$event['order_id']        = 81;
		$event['idempotency_key'] = str_repeat( 'a', 64 );

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$repository->record( $event )
		);
		$event['visitor_id'] = 13;
		$event['session_id'] = 21;
		self::assertSame(
			array(
				'id'      => 1,
				'created' => false,
			),
			$repository->record( $event )
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[20]['order_created_count'] );
		self::assertSame( 'wp_shurloc_journey_events', $this->database->prepared_queries[1]['args'][0] );
		self::assertSame( str_repeat( 'a', 64 ), $this->database->prepared_queries[1]['args'][1] );
	}

	/**
	 * Distinct keys may refer to the same order and a reused key must match.
	 *
	 * @return void
	 */
	public function test_order_id_is_not_unique_and_key_collision_fails_closed(): void {
		$repository               = new Journey_Event_Repository();
		$event                    = $this->event();
		$event['event_type']      = Journey_Event_Type::ORDER_CREATED;
		$event['order_id']        = 81;
		$event['idempotency_key'] = str_repeat( 'a', 64 );

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$repository->record( $event )
		);
		$event['idempotency_key'] = str_repeat( 'b', 64 );
		self::assertSame(
			array(
				'id'      => 2,
				'created' => true,
			),
			$repository->record( $event )
		);
		$event['order_id'] = 82;
		self::assertNull( $repository->record( $event ) );
		self::assertCount( 2, $this->database->events );
		self::assertSame( 2, $this->database->sessions[20]['order_created_count'] );
	}

	/**
	 * Non-order retries compare visitor and event details, including quantity.
	 *
	 * @return void
	 */
	public function test_keyed_non_order_retry_checks_logical_event(): void {
		$repository               = new Journey_Event_Repository();
		$event                    = $this->event();
		$event['event_type']      = Journey_Event_Type::ADD_TO_CART;
		$event['page_path']       = '/widgets';
		$event['post_id']         = 8;
		$event['product_id']      = 9;
		$event['quantity']        = '1.0000';
		$event['active_ms']       = 0;
		$event['source']          = 'browser';
		$event['idempotency_key'] = str_repeat( 'c', 64 );

		self::assertSame(
			array(
				'id'      => 1,
				'created' => true,
			),
			$repository->record( $event )
		);
		$event['quantity'] = '1';
		self::assertSame(
			array(
				'id'      => 1,
				'created' => false,
			),
			$repository->record( $event )
		);
		$event['quantity'] = '2';
		self::assertNull( $repository->record( $event ) );
		$event['quantity']   = '1';
		$event['product_id'] = 10;
		self::assertNull( $repository->record( $event ) );
		$event['product_id'] = 9;
		$event['post_id']    = 11;
		self::assertNull( $repository->record( $event ) );
		$event['post_id']   = 8;
		$event['page_path'] = '/different';
		self::assertNull( $repository->record( $event ) );
		$event['page_path'] = '/widgets';
		$event['active_ms'] = 1;
		self::assertNull( $repository->record( $event ) );
		$event['active_ms'] = 0;
		$event['source']    = 'woocommerce';
		self::assertNull( $repository->record( $event ) );
		$event['source']     = 'browser';
		$event['visitor_id'] = 13;
		self::assertNull( $repository->record( $event ) );
		$event['visitor_id'] = 12;
		$event['event_type'] = Journey_Event_Type::REMOVE_FROM_CART;
		self::assertNull( $repository->record( $event ) );
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[20]['cart_add_count'] );
		self::assertSame( '1.0000', $this->database->sessions[20]['added_quantity'] );
	}

	/**
	 * Invalid event values never reach the database.
	 *
	 * @return void
	 */
	public function test_rejects_invalid_event_fields(): void {
		$invalid_cases = array(
			array( 'session_id' => 0 ),
			array( 'visitor_id' => 0 ),
			array( 'user_id_at_event' => 0 ),
			array( 'event_type' => 'ORDER_PAID' ),
			array( 'occurred_at' => '2026-02-30 12:00:00' ),
			array( 'page_path' => '/widgets?email=private' ),
			array( 'page_path' => str_repeat( 'a', 1025 ) ),
			array( 'product_id' => 0 ),
			array( 'quantity' => '0' ),
			array( 'quantity' => '1.00000' ),
			array( 'active_ms' => -1 ),
			array( 'source' => 'Browser / UI' ),
			array( 'idempotency_key' => 'short' ),
			array( 'event_type' => Journey_Event_Type::ORDER_CREATED ),
			array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'product_id' => 9,
			),
			array(
				'event_type' => Journey_Event_Type::ORDER_CREATED,
				'order_id'   => 81,
			),
			array(
				'event_type'      => Journey_Event_Type::ORDER_CREATED,
				'idempotency_key' => str_repeat( 'a', 64 ),
			),
		);
		$repository    = new Journey_Event_Repository();

		foreach ( $invalid_cases as $changes ) {
			self::assertNull( $repository->record( array_replace( $this->event(), $changes ) ) );
		}

		self::assertSame( array(), $this->database->insert_calls );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * Schema and database failures return no event ID or false retry result.
	 *
	 * @return void
	 */
	public function test_schema_and_database_failures_return_null(): void {
		$repository                      = new Journey_Event_Repository();
		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->record( $this->event() ) );
		self::assertSame( array(), $this->database->insert_calls );

		$GLOBALS['shurloc_test_options']   = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database->fail_event_insert = true;
		self::assertNull( $repository->record( $this->event() ) );
		$event                             = $this->event();
		$event['idempotency_key']          = str_repeat( 'd', 64 );
		$this->database->fail_event_select = true;
		self::assertNull( $repository->record( $event ) );
		self::assertSame( array(), $this->database->events );
		self::assertSame( array( 'START TRANSACTION', 'ROLLBACK', 'START TRANSACTION', 'ROLLBACK' ), $this->database->queries );
	}

	/**
	 * A new event cannot create an orphan row or alter another visitor's session.
	 *
	 * @return void
	 */
	public function test_missing_and_mismatched_sessions_roll_back_event_inserts(): void {
		$repository = new Journey_Event_Repository();
		$session    = $this->database->sessions[20];
		unset( $this->database->sessions[20] );
		self::assertNull( $repository->record( $this->event() ) );
		self::assertSame( array(), $this->database->events );

		$this->database->sessions[20] = $session;
		$other_visitor                = array_replace( $this->event(), array( 'visitor_id' => 13 ) );
		self::assertNull( $repository->record( $other_visitor ) );
		self::assertSame( array(), $this->database->events );
		self::assertSame( 0, $this->database->sessions[20]['page_view_count'] );
		self::assertSame(
			array(
				'id'      => 3,
				'created' => true,
			),
			$repository->record( $this->event() )
		);
	}

	/**
	 * Transaction, summary, and commit failures leave both tables unchanged.
	 *
	 * @return void
	 */
	public function test_transaction_failures_roll_back_rows_and_counters(): void {
		$repository                 = new Journey_Event_Repository();
		$this->database->fail_start = true;
		self::assertNull( $repository->record( $this->event() ) );
		self::assertSame( array(), $this->database->insert_calls );

		$this->database->fail_start        = false;
		$this->database->fail_event_insert = true;
		self::assertNull( $repository->record( $this->event() ) );
		$this->database->fail_event_insert         = false;
		$this->database->fail_event_summary_update = true;
		self::assertNull( $repository->record( $this->event() ) );
		$this->database->fail_event_summary_update = false;
		$this->database->fail_commit               = true;
		self::assertNull( $repository->record( $this->event() ) );

		self::assertSame( array(), $this->database->events );
		self::assertSame( 0, $this->database->sessions[20]['page_view_count'] );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
		$this->database->fail_commit = false;
		self::assertSame(
			array(
				'id'      => 3,
				'created' => true,
			),
			$repository->record( $this->event() )
		);
		self::assertSame( 1, $this->database->sessions[20]['page_view_count'] );
	}

	/**
	 * Cumulative duration updates count only new visible time for the original view.
	 *
	 * @return void
	 */
	public function test_view_duration_is_cumulative_and_does_not_extend_session_activity(): void {
		$repository         = new Journey_Event_Repository();
		$event              = $this->event();
		$event['active_ms'] = 500;
		self::assertNotNull( $repository->record( $event ) );

		self::assertTrue( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 1200 ) );
		self::assertSame( 1200, $this->database->events[1]['active_ms'] );
		self::assertSame( 1200, $this->database->sessions[20]['active_ms'] );
		self::assertSame( 1, $this->database->sessions[20]['page_view_count'] );
		self::assertSame( '2026-09-16 12:00:00', $this->database->sessions[20]['last_activity_at'] );

		self::assertTrue( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 1200 ) );
		self::assertTrue( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 900 ) );
		self::assertSame( 1200, $this->database->sessions[20]['active_ms'] );
		self::assertTrue( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 2000 ) );
		self::assertSame( 2000, $this->database->events[1]['active_ms'] );
		self::assertSame( 2000, $this->database->sessions[20]['active_ms'] );
		self::assertSame( 1, $this->database->sessions[20]['page_view_count'] );
	}

	/**
	 * Product views retain the specialized view count and use the site prefix.
	 *
	 * @return void
	 */
	public function test_product_view_duration_updates_both_rows_without_recounting(): void {
		$this->database->prefix = 'shop_';
		$repository             = new Journey_Event_Repository();
		$event                  = $this->event();
		$event['event_type']    = Journey_Event_Type::PRODUCT_VIEW;
		$event['product_id']    = 9;
		self::assertNotNull( $repository->record( $event ) );

		self::assertTrue( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 750 ) );
		self::assertSame( 750, $this->database->events[1]['active_ms'] );
		self::assertSame( 750, $this->database->sessions[20]['active_ms'] );
		self::assertSame( 1, $this->database->sessions[20]['page_view_count'] );
		self::assertSame( 1, $this->database->sessions[20]['product_view_count'] );
		self::assertSame( 'shop_shurloc_journey_events', $this->database->prepared_queries[1]['args'][0] );
		self::assertSame( array( 'shop_shurloc_journey_events', 1, 12 ), $this->database->prepared_queries[1]['args'] );
	}

	/**
	 * Reject unrelated visitors, non-view events, invalid IDs, and unavailable schema.
	 *
	 * @return void
	 */
	public function test_view_duration_rejects_unowned_or_non_view_events(): void {
		$repository = new Journey_Event_Repository();
		self::assertNotNull( $repository->record( $this->event() ) );

		self::assertFalse( $repository->record_view_duration( event_id: 0, visitor_id: 12, total_active_ms: 100 ) );
		self::assertFalse( $repository->record_view_duration( event_id: 1, visitor_id: 0, total_active_ms: 100 ) );
		self::assertFalse( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: -1 ) );
		self::assertFalse( $repository->record_view_duration( event_id: 99, visitor_id: 12, total_active_ms: 100 ) );
		self::assertFalse( $repository->record_view_duration( event_id: 1, visitor_id: 13, total_active_ms: 100 ) );
		self::assertSame( 0, $this->database->events[1]['active_ms'] );

		$cart = array_replace(
			$this->event(),
			array(
				'event_type' => Journey_Event_Type::ADD_TO_CART,
				'product_id' => 9,
				'quantity'   => '1',
			)
		);
		self::assertNotNull( $repository->record( $cart ) );
		self::assertFalse( $repository->record_view_duration( event_id: 2, visitor_id: 12, total_active_ms: 100 ) );
		self::assertSame( 0, $this->database->sessions[20]['active_ms'] );

		$GLOBALS['shurloc_test_options'] = array();
		$queries_before                  = $this->database->queries;
		self::assertFalse( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 100 ) );
		self::assertSame( $queries_before, $this->database->queries );
	}

	/**
	 * Failed reads, writes, and commits never leave a partial duration update.
	 *
	 * @return void
	 */
	public function test_view_duration_failures_roll_back_both_rows(): void {
		$repository = new Journey_Event_Repository();
		self::assertNotNull( $repository->record( $this->event() ) );

		foreach ( array( 'fail_start', 'fail_event_select', 'fail_event_duration_update', 'fail_event_summary_update', 'fail_commit' ) as $failure ) {
			$this->database->$failure = true;
			self::assertFalse( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 500 ) );
			self::assertSame( 0, $this->database->events[1]['active_ms'] );
			self::assertSame( 0, $this->database->sessions[20]['active_ms'] );
			$this->database->$failure = false;
		}

		$session = $this->database->sessions[20];
		unset( $this->database->sessions[20] );
		self::assertFalse( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 500 ) );
		self::assertSame( 0, $this->database->events[1]['active_ms'] );
		$this->database->sessions[20] = $session;
		self::assertTrue( $repository->record_view_duration( event_id: 1, visitor_id: 12, total_active_ms: 500 ) );
		self::assertSame( 500, $this->database->events[1]['active_ms'] );
		self::assertSame( 500, $this->database->sessions[20]['active_ms'] );
	}

	/**
	 * Build one valid anonymous page view.
	 *
	 * @return array{session_id:int,visitor_id:int,user_id_at_event:int|null,event_type:string,occurred_at:string,page_path?:string|null,post_id?:int|null,product_id?:int|null,variation_id?:int|null,quantity?:string|null,order_id?:int|null,active_ms?:int,source?:string|null,idempotency_key?:string|null} Event input.
	 */
	private function event(): array {
		return array(
			'session_id'       => 20,
			'visitor_id'       => 12,
			'user_id_at_event' => null,
			'event_type'       => Journey_Event_Type::PAGE_VIEW,
			'occurred_at'      => '2026-09-16 12:00:00',
			'page_path'        => '/widgets',
		);
	}
}
