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
		$event['user_id_at_event'] = 37;
		$event['page_path']        = '/widgets';
		$event['post_id']          = 8;
		$event['product_id']       = 9;
		$event['variation_id']     = 10;
		$event['quantity']         = '2.5000';
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
		self::assertSame( $event['quantity'], $this->database->events[1]['quantity'] );
		self::assertNull( $this->database->events[1]['order_id'] );
		self::assertNull( $this->database->events[1]['related_object_id'] );
		self::assertSame( $event['active_ms'], $this->database->events[1]['active_ms'] );
		self::assertSame( $event['source'], $this->database->events[1]['source'] );
		self::assertNull( $this->database->events[1]['idempotency_key'] );
		self::assertCount( 15, $this->database->insert_calls[0]['formats'] );
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
		self::assertSame( 'wp_shurloc_journey_events', $this->database->prepared_queries[0]['args'][0] );
		self::assertSame( str_repeat( 'a', 64 ), $this->database->prepared_queries[0]['args'][1] );
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
		$event['active_ms']       = 1000;
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
		$event['active_ms'] = 1001;
		self::assertNull( $repository->record( $event ) );
		$event['active_ms'] = 1000;
		$event['source']    = 'woocommerce';
		self::assertNull( $repository->record( $event ) );
		$event['source']     = 'browser';
		$event['visitor_id'] = 13;
		self::assertNull( $repository->record( $event ) );
		$event['visitor_id'] = 12;
		$event['event_type'] = Journey_Event_Type::REMOVE_FROM_CART;
		self::assertNull( $repository->record( $event ) );
		self::assertCount( 1, $this->database->events );
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
		);
	}
}
