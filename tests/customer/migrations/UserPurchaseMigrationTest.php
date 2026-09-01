<?php
/**
 * Tests for the user purchase migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Migrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;
use WC_Order;

/**
 * Tests the user purchase migration.
 */
final class UserPurchaseMigrationTest extends TestCase {

	/**
	 * Purchase service.
	 *
	 * @var User_Purchase_Service
	 */
	private User_Purchase_Service $purchase_service;

	/**
	 * Migration under test.
	 *
	 * @var User_Purchase_Migration
	 */
	private User_Purchase_Migration $migration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_users']              = array();
		$GLOBALS['shurloc_test_orders']             = array();
		$GLOBALS['shurloc_test_user_meta']          = array();
		$GLOBALS['shurloc_test_options']            = array();
		$GLOBALS['shurloc_test_wc_get_orders_args'] = array();
		$GLOBALS['shurloc_test_time']               = 1_000_000;

		$this->purchase_service = new User_Purchase_Service();

		$this->migration = new User_Purchase_Migration(
			purchase_service: $this->purchase_service,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_users']              = array();
		$GLOBALS['shurloc_test_orders']             = array();
		$GLOBALS['shurloc_test_user_meta']          = array();
		$GLOBALS['shurloc_test_options']            = array();
		$GLOBALS['shurloc_test_wc_get_orders_args'] = array();
		$GLOBALS['shurloc_test_time']               = 0;

		parent::tearDown();
	}

	/**
	 * Verify the latest qualifying order is seeded.
	 *
	 * @return void
	 */
	public function test_latest_qualifying_order_is_seeded(): void {

		$GLOBALS['shurloc_test_users'] = array( 101 );

		$GLOBALS['shurloc_test_orders'][101] = array(
			$this->create_order(
				order_id: 200,
				user_id: 101,
				status: 'completed',
				timestamp: 1_000_000,
				total: 125.50,
			),
		);

		$result = $this->migration->run();

		self::assertSame(
			1,
			$result['examined']
		);

		self::assertSame(
			1,
			$result['updated']
		);

		self::assertSame(
			0,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['errors']
		);

		self::assertSame(
			200,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY ]
		);
	}

	/**
	 * Verify users without qualifying orders are skipped.
	 *
	 * @return void
	 */
	public function test_user_without_orders_is_skipped(): void {

		$GLOBALS['shurloc_test_users'] = array( 101 );

		$GLOBALS['shurloc_test_orders'][101] = array();

		$result = $this->migration->run();

		self::assertSame(
			1,
			$result['examined']
		);

		self::assertSame(
			0,
			$result['updated']
		);

		self::assertSame(
			1,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['errors']
		);
	}

	/**
	 * Verify multiple users are processed.
	 *
	 * @return void
	 */
	public function test_multiple_users_are_processed(): void {

		$GLOBALS['shurloc_test_users'] = array(
			101,
			102,
			103,
		);

		$GLOBALS['shurloc_test_orders'][101] = array(
			$this->create_order(
				order_id: 200,
				user_id: 101,
				status: 'completed',
				timestamp: 1_000_000,
				total: 125.50,
			),
		);

		$GLOBALS['shurloc_test_orders'][102] = array(
			$this->create_order(
				order_id: 300,
				user_id: 102,
				status: 'processing',
				timestamp: 1_100_000,
				total: 75.00,
			),
		);

		$GLOBALS['shurloc_test_orders'][103] = array();

		$result = $this->migration->run();

		self::assertSame(
			3,
			$result['examined']
		);

		self::assertSame(
			2,
			$result['updated']
		);

		self::assertSame(
			1,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['errors']
		);
	}

	/**
	 * Verify rerunning the migration replaces existing purchase data.
	 *
	 * @return void
	 */
	public function test_rerun_replaces_existing_purchase_snapshot(): void {

		$GLOBALS['shurloc_test_users'] = array( 101 );

		$GLOBALS['shurloc_test_user_meta'][101] = array(
			User_Purchase_Service::LAST_PURCHASE_META_KEY =>
				2_000_000,

			User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY =>
				999,

			User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY =>
				'completed',

			User_Purchase_Service::LAST_PURCHASE_TOTAL_META_KEY =>
				500.00,
		);

		$GLOBALS['shurloc_test_orders'][101] = array(
			$this->create_order(
				order_id: 200,
				user_id: 101,
				status: 'processing',
				timestamp: 1_000_000,
				total: 125.50,
			),
		);

		$result = $this->migration->run();

		self::assertSame(
			1,
			$result['updated']
		);

		self::assertSame(
			200,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY ]
		);
	}

	/**
	 * Verify migration run metadata is recorded.
	 *
	 * @return void
	 */
	public function test_run_records_timestamp_and_version(): void {

		$GLOBALS['shurloc_test_users'] = array();

		$before = time();

		$this->migration->run();

		$after = time();

		$last_run = $GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LAST_RUN_OPTION ];

		self::assertGreaterThanOrEqual(
			$before,
			$last_run
		);

		self::assertLessThanOrEqual(
			$after,
			$last_run
		);

		self::assertSame(
			User_Purchase_Migration::VERSION,
			$GLOBALS['shurloc_test_options']
				[ User_Purchase_Migration::LAST_RUN_VERSION_OPTION ]
		);
	}

	/**
	 * Verify the stored last-run timestamp can be read.
	 *
	 * @return void
	 */
	public function test_get_last_run_returns_stored_timestamp(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LAST_RUN_OPTION ] = 1_000_000;

		self::assertSame(
			1_000_000,
			$this->migration->get_last_run()
		);
	}

	/**
	 * Verify the stored last-run version can be read.
	 *
	 * @return void
	 */
	public function test_get_last_run_version_returns_stored_version(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LAST_RUN_VERSION_OPTION ] = 3;

		self::assertSame(
			3,
			$this->migration->get_last_run_version()
		);
	}

	/**
	 * Verify the migration requests the latest qualifying order.
	 *
	 * @return void
	 */
	public function test_migration_queries_latest_qualifying_order(): void {

		$GLOBALS['shurloc_test_users'] = array( 101 );

		$GLOBALS['shurloc_test_orders'][101] = array();

		$this->migration->run();

		$args =
			$GLOBALS['shurloc_test_wc_get_orders_args'][0];

		self::assertSame(
			101,
			$args['customer_id']
		);

		self::assertSame(
			User_Purchase_Service::QUALIFYING_STATUSES,
			$args['status']
		);

		self::assertSame(
			'date',
			$args['orderby']
		);

		self::assertSame(
			'DESC',
			$args['order']
		);

		self::assertSame(
			1,
			$args['limit']
		);

		self::assertSame(
			'objects',
			$args['return']
		);
	}

	/**
	 * Verify the migration reports an active lock.
	 *
	 * @return void
	 */
	public function test_is_locked_returns_true_for_active_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LOCK_OPTION ] = time();

		self::assertTrue(
			$this->migration->is_locked()
		);
	}

	/**
	 * Verify the migration reports no lock when none exists.
	 *
	 * @return void
	 */
	public function test_is_locked_returns_false_when_no_lock_exists(): void {

		self::assertFalse(
			$this->migration->is_locked()
		);
	}

	/**
	 * Verify a stale migration lock is removed.
	 *
	 * @return void
	 */
	public function test_is_locked_removes_stale_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LOCK_OPTION ] = time() - 901;

		self::assertFalse(
			$this->migration->is_locked()
		);

		self::assertArrayNotHasKey(
			User_Purchase_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify the migration lock can be acquired.
	 *
	 * @return void
	 */
	public function test_acquire_lock_creates_lock(): void {

		$result = $this->migration->acquire_lock();

		self::assertTrue(
			$result
		);

		self::assertArrayHasKey(
			User_Purchase_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify an existing migration lock cannot be acquired again.
	 *
	 * @return void
	 */
	public function test_acquire_lock_returns_false_when_already_locked(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LOCK_OPTION ] = time();

		$result = $this->migration->acquire_lock();

		self::assertFalse(
			$result
		);
	}

	/**
	 * Verify the migration lock can be released.
	 *
	 * @return void
	 */
	public function test_release_lock_removes_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LOCK_OPTION ] = time();

		$this->migration->release_lock();

		self::assertArrayNotHasKey(
			User_Purchase_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify a stale migration lock can be reacquired.
	 *
	 * @return void
	 */
	public function test_acquire_lock_replaces_stale_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LOCK_OPTION ] = time() - 901;

		$result = $this->migration->acquire_lock();

		self::assertTrue(
			$result
		);

		self::assertArrayHasKey(
			User_Purchase_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Create a WooCommerce order test double.
	 *
	 * @param int    $order_id  Order ID.
	 * @param int    $user_id   Customer user ID.
	 * @param string $status    Order status.
	 * @param int    $timestamp Creation timestamp.
	 * @param float  $total     Order total.
	 * @return WC_Order
	 */
	private function create_order(
		int $order_id,
		int $user_id,
		string $status,
		int $timestamp,
		float $total
	): WC_Order {

		$order = new WC_Order( $order_id );

		$order->set_customer_id( $user_id );
		$order->set_status( $status );
		$order->set_date_created( $timestamp );
		$order->set_total( (string) $total );

		return $order;
	}
}
