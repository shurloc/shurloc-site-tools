<?php
/**
 * Tests for the user purchase service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

use PHPUnit\Framework\TestCase;
use WC_Order;

/**
 * Tests the user purchase service.
 */
final class UserPurchaseServiceTest extends TestCase {

	/**
	 * Service under test.
	 *
	 * @var User_Purchase_Service
	 */
	private User_Purchase_Service $service;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_user_meta']       = array();

		$this->service = new User_Purchase_Service();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_user_meta']       = array();

		parent::tearDown();
	}

	/**
	 * Verify qualifying order hooks are registered.
	 *
	 * @return void
	 */
	public function test_register_adds_qualifying_order_hooks(): void {

		$this->service->register();

		foreach (
			array(
				'woocommerce_order_status_on-hold',
				'woocommerce_order_status_processing',
				'woocommerce_order_status_completed',
			) as $hook
		) {
			self::assertContains(
				array(
					$this->service,
					'track_qualifying_order',
				),
				$GLOBALS['shurloc_test_actions'][ $hook ]
			);

			self::assertSame(
				2,
				$GLOBALS['shurloc_test_action_metadata'][ $hook ][0]['accepted_args']
			);
		}
	}

	/**
	 * Verify the general order status hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_order_status_changed_hook(): void {

		$this->service->register();

		self::assertContains(
			array(
				$this->service,
				'update_tracked_order_status',
			),
			$GLOBALS['shurloc_test_actions']['woocommerce_order_status_changed']
		);

		self::assertSame(
			4,
			$GLOBALS['shurloc_test_action_metadata']
				['woocommerce_order_status_changed'][0]['accepted_args']
		);
	}

	/**
	 * Verify an on-hold order is tracked.
	 *
	 * @return void
	 */
	public function test_on_hold_order_is_tracked(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'on-hold',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		$this->assert_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'on-hold',
			total: 125.50,
		);
	}

	/**
	 * Verify a processing order is tracked.
	 *
	 * @return void
	 */
	public function test_processing_order_is_tracked(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'processing',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		self::assertSame(
			'processing',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);
	}

	/**
	 * Verify a completed order is tracked.
	 *
	 * @return void
	 */
	public function test_completed_order_is_tracked(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'completed',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		self::assertSame(
			'completed',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);
	}

	/**
	 * Verify a non-qualifying order is ignored.
	 *
	 * @return void
	 */
	public function test_non_qualifying_order_is_ignored(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'pending',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify guest orders are ignored.
	 *
	 * @return void
	 */
	public function test_guest_order_is_ignored(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 0,
			status: 'processing',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify orders without creation dates are ignored.
	 *
	 * @return void
	 */
	public function test_order_without_creation_date_is_ignored(): void {

		$order = new WC_Order( 200 );

		$order->set_customer_id( 101 );
		$order->set_status( 'processing' );
		$order->set_date_created( null );
		$order->set_total( '125.50' );

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify a newer order replaces the current last purchase.
	 *
	 * @return void
	 */
	public function test_newer_order_replaces_current_purchase(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 100,
			timestamp: 900_000,
			status: 'completed',
			total: 50.00,
		);

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'processing',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		$this->assert_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
		);
	}

	/**
	 * Verify an older order does not replace the current purchase.
	 *
	 * @return void
	 */
	public function test_older_order_is_ignored(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
		);

		$order = $this->create_order(
			order_id: 100,
			user_id: 101,
			status: 'completed',
			timestamp: 900_000,
			total: 50.00,
		);

		$this->service->track_qualifying_order(
			order_id: 100,
			order: $order,
		);

		$this->assert_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
		);
	}

	/**
	 * Verify a greater order ID wins when timestamps are equal.
	 *
	 * @return void
	 */
	public function test_greater_order_id_wins_equal_timestamp_tie(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 100,
			timestamp: 1_000_000,
			status: 'processing',
			total: 50.00,
		);

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'completed',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->track_qualifying_order(
			order_id: 200,
			order: $order,
		);

		self::assertSame(
			200,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY ]
		);
	}

	/**
	 * Verify a lower order ID loses when timestamps are equal.
	 *
	 * @return void
	 */
	public function test_lower_order_id_loses_equal_timestamp_tie(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
		);

		$order = $this->create_order(
			order_id: 100,
			user_id: 101,
			status: 'completed',
			timestamp: 1_000_000,
			total: 50.00,
		);

		$this->service->track_qualifying_order(
			order_id: 100,
			order: $order,
		);

		self::assertSame(
			200,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY ]
		);
	}

	/**
	 * Verify cancellation updates the currently tracked order status.
	 *
	 * @return void
	 */
	public function test_cancelled_tracked_order_updates_status(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
		);

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'cancelled',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$this->service->update_tracked_order_status(
			order_id: 200,
			old_status: 'processing',
			new_status: 'cancelled',
			order: $order,
		);

		self::assertSame(
			'cancelled',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);
	}

	/**
	 * Verify a refund updates the currently tracked order status.
	 *
	 * @return void
	 */
	public function test_refunded_tracked_order_updates_status(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'completed',
			total: 125.50,
		);

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'refunded',
			timestamp: 1_000_000,
			total: 0.00,
		);

		$this->service->update_tracked_order_status(
			order_id: 200,
			old_status: 'completed',
			new_status: 'refunded',
			order: $order,
		);

		self::assertSame(
			'refunded',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);

		self::assertSame(
			0.00,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_TOTAL_META_KEY ]
		);
	}

	/**
	 * Verify status changes on older orders are ignored.
	 *
	 * @return void
	 */
	public function test_status_change_on_older_order_is_ignored(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
		);

		$order = $this->create_order(
			order_id: 100,
			user_id: 101,
			status: 'cancelled',
			timestamp: 900_000,
			total: 50.00,
		);

		$this->service->update_tracked_order_status(
			order_id: 100,
			old_status: 'processing',
			new_status: 'cancelled',
			order: $order,
		);

		self::assertSame(
			'processing',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);
	}

	/**
	 * Verify qualifying status changes are ignored by the status-only handler.
	 *
	 * @return void
	 */
	public function test_qualifying_status_change_is_ignored_by_status_handler(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'on-hold',
			total: 125.50,
		);

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'processing',
			timestamp: 1_000_000,
			total: 130.00,
		);

		$this->service->update_tracked_order_status(
			order_id: 200,
			old_status: 'on-hold',
			new_status: 'processing',
			order: $order,
		);

		self::assertSame(
			'on-hold',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);
	}

	/**
	 * Verify a purchase snapshot can be stored directly from an order.
	 *
	 * @return void
	 */
	public function test_store_purchase_from_order_stores_purchase_meta(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'completed',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$result = $this->service->store_purchase_from_order(
			user_id: 101,
			order: $order,
		);

		self::assertTrue( $result );

		$this->assert_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'completed',
			total: 125.50,
		);
	}

	/**
	 * Verify an invalid user ID is rejected.
	 *
	 * @return void
	 */
	public function test_store_purchase_from_order_rejects_invalid_user_id(): void {

		$order = $this->create_order(
			order_id: 200,
			user_id: 0,
			status: 'completed',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$result = $this->service->store_purchase_from_order(
			user_id: 0,
			order: $order,
		);

		self::assertFalse( $result );

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify an order without a creation date cannot be stored.
	 *
	 * @return void
	 */
	public function test_store_purchase_from_order_rejects_order_without_creation_date(): void {

		$order = new WC_Order( 200 );

		$order->set_customer_id( 101 );
		$order->set_status( 'completed' );
		$order->set_date_created( null );
		$order->set_total( '125.50' );

		$result = $this->service->store_purchase_from_order(
			user_id: 101,
			order: $order,
		);

		self::assertFalse( $result );

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify direct purchase storage overwrites an existing snapshot.
	 *
	 * This is intentional for controlled migration and reseeding operations.
	 *
	 * @return void
	 */
	public function test_store_purchase_from_order_overwrites_existing_purchase(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 300,
			timestamp: 2_000_000,
			status: 'completed',
			total: 250.00,
		);

		$order = $this->create_order(
			order_id: 200,
			user_id: 101,
			status: 'processing',
			timestamp: 1_000_000,
			total: 125.50,
		);

		$result = $this->service->store_purchase_from_order(
			user_id: 101,
			order: $order,
		);

		self::assertTrue( $result );

		$this->assert_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000,
			status: 'processing',
			total: 125.50,
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

	/**
	 * Seed last-purchase user metadata.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $order_id  Order ID.
	 * @param int    $timestamp Purchase timestamp.
	 * @param string $status    Order status.
	 * @param float  $total     Order total.
	 * @return void
	 */
	private function seed_purchase_meta(
		int $user_id,
		int $order_id,
		int $timestamp,
		string $status,
		float $total
	): void {

		$GLOBALS['shurloc_test_user_meta'][ $user_id ] = array(
			User_Purchase_Service::LAST_PURCHASE_META_KEY =>
				$timestamp,
			User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY =>
				$order_id,
			User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY =>
				$status,
			User_Purchase_Service::LAST_PURCHASE_TOTAL_META_KEY =>
				$total,
		);
	}

	/**
	 * Assert last-purchase user metadata.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $order_id  Expected order ID.
	 * @param int    $timestamp Expected timestamp.
	 * @param string $status    Expected status.
	 * @param float  $total     Expected total.
	 * @return void
	 */
	private function assert_purchase_meta(
		int $user_id,
		int $order_id,
		int $timestamp,
		string $status,
		float $total
	): void {

		self::assertSame(
			$timestamp,
			$GLOBALS['shurloc_test_user_meta'][ $user_id ]
				[ User_Purchase_Service::LAST_PURCHASE_META_KEY ]
		);

		self::assertSame(
			$order_id,
			$GLOBALS['shurloc_test_user_meta'][ $user_id ]
				[ User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY ]
		);

		self::assertSame(
			$status,
			$GLOBALS['shurloc_test_user_meta'][ $user_id ]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);

		self::assertSame(
			$total,
			$GLOBALS['shurloc_test_user_meta'][ $user_id ]
				[ User_Purchase_Service::LAST_PURCHASE_TOTAL_META_KEY ]
		);
	}
}
