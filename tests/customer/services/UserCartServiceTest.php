<?php
/**
 * Tests for the user cart service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Product;
use WC_Cart_Double;
use WooCommerce;

/**
 * Tests the user cart service.
 */
final class UserCartServiceTest extends TestCase {

	/**
	 * Number of seconds in one day.
	 *
	 * @var int
	 */
	private const DAY_IN_SECONDS = 86400;

	/**
	 * Service under test.
	 *
	 * @var User_Cart_Service
	 */
	private User_Cart_Service $service;

	/**
	 * WooCommerce cart test double.
	 *
	 * @var WC_Cart_Double
	 */
	private WC_Cart_Double $cart;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']           = array();
		$GLOBALS['shurloc_test_action_metadata']   = array();
		$GLOBALS['shurloc_test_user_meta']         = array();
		$GLOBALS['shurloc_test_current_user_id']   = 0;
		$GLOBALS['shurloc_test_is_user_logged_in'] = false;
		$GLOBALS['shurloc_test_time']              = 1_000_000;
		$GLOBALS['shurloc_test_orders']            = array();

		$this->cart = new WC_Cart_Double();

		$woocommerce       = new WooCommerce();
		$woocommerce->cart = $this->cart;

		$GLOBALS['shurloc_test_woocommerce'] = $woocommerce;

		$this->service = new User_Cart_Service();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']           = array();
		$GLOBALS['shurloc_test_action_metadata']   = array();
		$GLOBALS['shurloc_test_user_meta']         = array();
		$GLOBALS['shurloc_test_current_user_id']   = 0;
		$GLOBALS['shurloc_test_is_user_logged_in'] = false;
		$GLOBALS['shurloc_test_woocommerce']       = null;
		$GLOBALS['shurloc_test_orders']            = array();

		parent::tearDown();
	}

	/**
	 * Verify the cart totals hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_after_calculate_totals_hook(): void {

		$this->service->register();

		self::assertContains(
			array(
				$this->service,
				'update_cart_snapshot',
			),
			$GLOBALS['shurloc_test_actions']
				['woocommerce_after_calculate_totals']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']
				['woocommerce_after_calculate_totals'][0]['priority']
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_action_metadata']
				['woocommerce_after_calculate_totals'][0]['accepted_args']
		);
	}

	/**
	 * Verify the checkout hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_checkout_order_processed_hook(): void {

		$this->service->register();

		self::assertContains(
			array(
				$this->service,
				'clear_cart_snapshot_after_purchase',
			),
			$GLOBALS['shurloc_test_actions']
				['woocommerce_checkout_order_processed']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']
				['woocommerce_checkout_order_processed'][0]['priority']
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_action_metadata']
				['woocommerce_checkout_order_processed'][0]['accepted_args']
		);
	}

	/**
	 * Verify logged-out users are ignored.
	 *
	 * @return void
	 */
	public function test_logged_out_user_is_ignored(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = false;
		$GLOBALS['shurloc_test_current_user_id']   = 101;

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify an invalid current user ID is ignored.
	 *
	 * @return void
	 */
	public function test_zero_user_id_is_ignored(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		$GLOBALS['shurloc_test_current_user_id']   = 0;

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify a complete cart snapshot is stored using the legacy contract.
	 *
	 * @return void
	 */
	public function test_cart_snapshot_is_stored(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$product = $this->create_product(
			sku: 'TEST-123',
			name: 'Test Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'product_id'    => 100,
					'variation_id'  => 105,
					'quantity'      => 2,
					'line_subtotal' => 160.00,
					'line_total'    => 149.95,
					'data'          => $product,
				),
			)
		);

		$this->cart->set_test_cart_contents_total(
			total: 149.95,
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_COUNT_META_KEY ]
		);

		self::assertSame(
			149.95,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_TOTAL_META_KEY ]
		);

		self::assertSame(
			array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 105,
					'name'          => 'Test Product',
					'sku'           => 'TEST-123',
					'quantity'      => 2,
					'line_subtotal' => 160.00,
					'line_total'    => 149.95,
					'variation'     => array(),
				),
			),
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_ITEMS_META_KEY ]
		);

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_UPDATED_META_KEY ]
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_VERSION_META_KEY ]
		);

		self::assertSame(
			1_000_000 + ( 30 * self::DAY_IN_SECONDS ),
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_EXPIRES_META_KEY ]
		);
	}

	/**
	 * Verify the snapshot version remains the schema version.
	 *
	 * @return void
	 */
	public function test_snapshot_version_does_not_increment(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_VERSION_META_KEY ] = 99;

		$product = $this->create_product(
			sku: 'TEST',
			name: 'Test Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'product_id'    => 100,
					'variation_id'  => 0,
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
					'data'          => $product,
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_VERSION_META_KEY ]
		);
	}

	/**
	 * Verify multiple cart quantities are combined for the item count.
	 *
	 * @return void
	 */
	public function test_multiple_cart_quantities_are_summed(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$product_one = $this->create_product(
			sku: 'ONE',
			name: 'Product One',
		);

		$product_two = $this->create_product(
			sku: 'TWO',
			name: 'Product Two',
		);

		$this->cart->set_test_cart(
			cart: array(
				'first'  => array(
					'product_id'    => 100,
					'variation_id'  => 0,
					'quantity'      => 1,
					'line_subtotal' => 10.00,
					'line_total'    => 10.00,
					'data'          => $product_one,
				),
				'second' => array(
					'product_id'    => 200,
					'variation_id'  => 0,
					'quantity'      => 3,
					'line_subtotal' => 60.00,
					'line_total'    => 60.00,
					'data'          => $product_two,
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			4,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_COUNT_META_KEY ]
		);
	}

	/**
	 * Verify cart contents total is stored.
	 *
	 * @return void
	 */
	public function test_cart_contents_total_is_stored(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$product = $this->create_product(
			sku: 'TEST',
			name: 'Test Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'product_id'    => 100,
					'variation_id'  => 0,
					'quantity'      => 1,
					'line_subtotal' => 125.00,
					'line_total'    => 100.00,
					'data'          => $product,
				),
			)
		);

		$this->cart->set_test_cart_contents_total(
			total: 100.00,
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			100.00,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_TOTAL_META_KEY ]
		);
	}

	/**
	 * Verify variation attributes are preserved for new snapshots.
	 *
	 * @return void
	 */
	public function test_variation_attributes_are_stored(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$product = $this->create_product(
			sku: 'VAR-123',
			name: 'Variation Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'variation-key' => array(
					'product_id'    => 100,
					'variation_id'  => 105,
					'quantity'      => 1,
					'line_subtotal' => 50.00,
					'line_total'    => 50.00,
					'data'          => $product,
					'variation'     => array(
						'attribute_pa_color' => 'yellow',
						'attribute_size'     => 'large',
					),
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		$items = $GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_ITEMS_META_KEY ];

		self::assertSame(
			array(
				'attribute_pa_color' => 'yellow',
				'attribute_size'     => 'large',
			),
			$items[0]['variation']
		);
	}

	/**
	 * Verify invalid variation attributes are excluded.
	 *
	 * @return void
	 */
	public function test_invalid_variation_attributes_are_ignored(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$product = $this->create_product(
			sku: 'VAR-123',
			name: 'Variation Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'variation-key' => array(
					'product_id'    => 100,
					'variation_id'  => 105,
					'quantity'      => 1,
					'line_subtotal' => 50.00,
					'line_total'    => 50.00,
					'data'          => $product,
					'variation'     => array(
						'attribute_pa_color' => 'yellow',
						'attribute_size'     => array( 'invalid' ),
						123                  => 'invalid',
					),
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		$items = $GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_ITEMS_META_KEY ];

		self::assertSame(
			array(
				'attribute_pa_color' => 'yellow',
			),
			$items[0]['variation']
		);
	}

	/**
	 * Verify missing optional item values are normalized.
	 *
	 * @return void
	 */
	public function test_missing_item_values_are_normalized(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$product = $this->create_product(
			sku: 'TEST',
			name: 'Test Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'quantity' => 1,
					'data'     => $product,
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		$items = $GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_ITEMS_META_KEY ];

		self::assertSame(
			array(
				'cart_item_key' => 'abc123',
				'product_id'    => 0,
				'variation_id'  => 0,
				'name'          => 'Test Product',
				'sku'           => 'TEST',
				'quantity'      => 1,
				'line_subtotal' => 0.0,
				'line_total'    => 0.0,
				'variation'     => array(),
			),
			$items[0]
		);
	}

	/**
	 * Verify an item without valid product data is ignored.
	 *
	 * @return void
	 */
	public function test_cart_item_without_product_data_is_ignored(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$this->seed_existing_snapshot(
			user_id: 101,
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'product_id'   => 100,
					'variation_id' => 0,
					'quantity'     => 1,
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertArrayNotHasKey(
			101,
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify zero-quantity items are ignored.
	 *
	 * @return void
	 */
	public function test_zero_quantity_cart_item_is_ignored(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$this->seed_existing_snapshot(
			user_id: 101,
		);

		$product = $this->create_product(
			sku: 'TEST',
			name: 'Test Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'product_id'    => 100,
					'variation_id'  => 0,
					'quantity'      => 0,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
					'data'          => $product,
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertArrayNotHasKey(
			101,
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify an empty cart removes the complete stored snapshot.
	 *
	 * @return void
	 */
	public function test_empty_cart_clears_snapshot(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$this->seed_existing_snapshot(
			user_id: 101,
		);

		$this->cart->set_test_cart(
			cart: array(),
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertArrayNotHasKey(
			101,
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify an existing snapshot receives a refreshed expiration.
	 *
	 * @return void
	 */
	public function test_cart_expiration_is_refreshed(): void {

		$this->log_in_user(
			user_id: 101,
		);

		$GLOBALS['shurloc_test_time'] = 2_000_000;

		$product = $this->create_product(
			sku: 'TEST',
			name: 'Test Product',
		);

		$this->cart->set_test_cart(
			cart: array(
				'abc123' => array(
					'product_id'    => 100,
					'variation_id'  => 0,
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
					'data'          => $product,
				),
			)
		);

		$this->service->update_cart_snapshot(
			cart: $this->cart,
		);

		self::assertSame(
			2_000_000 + ( 30 * self::DAY_IN_SECONDS ),
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_EXPIRES_META_KEY ]
		);
	}

	/**
	 * Verify checkout clears a registered customer's snapshot.
	 *
	 * @return void
	 */
	public function test_checkout_clears_customer_cart_snapshot(): void {

		$this->seed_existing_snapshot(
			user_id: 101,
		);

		$order = new WC_Order( 500 );

		$order->set_customer_id( 101 );

		$GLOBALS['shurloc_test_orders'][500] = $order;

		$this->service->clear_cart_snapshot_after_purchase(
			order_id: 500,
		);

		self::assertArrayNotHasKey(
			101,
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify checkout for a guest order does not clear user metadata.
	 *
	 * @return void
	 */
	public function test_guest_checkout_is_ignored(): void {

		$this->seed_existing_snapshot(
			user_id: 101,
		);

		$order = new WC_Order( 500 );

		$order->set_customer_id( 0 );

		$GLOBALS['shurloc_test_orders'][500] = $order;

		$this->service->clear_cart_snapshot_after_purchase(
			order_id: 500,
		);

		self::assertArrayHasKey(
			101,
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify a missing order is ignored.
	 *
	 * @return void
	 */
	public function test_missing_checkout_order_is_ignored(): void {

		$this->seed_existing_snapshot(
			user_id: 101,
		);

		$this->service->clear_cart_snapshot_after_purchase(
			order_id: 999,
		);

		self::assertArrayHasKey(
			101,
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify a normalized cart snapshot is stored.
	 *
	 * @return void
	 */
	public function test_store_cart_snapshot_stores_expected_meta(): void {

		$cart_contents = array(
			array(
				'cart_item_key' => 'abc123',
				'product_id'    => 200,
				'variation_id'  => 0,
				'name'          => 'Test Product',
				'sku'           => 'TEST-200',
				'quantity'      => 2,
				'line_subtotal' => 100.00,
				'line_total'    => 90.00,
				'variation'     => array(),
			),
		);

		$result = $this->service->store_cart_snapshot(
			user_id: 101,
			cart_contents: $cart_contents,
			contents_total: 90.00,
			updated_at: 1_000_000,
			expires_at: 2_000_000,
		);

		self::assertTrue(
			$result
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_COUNT_META_KEY ]
		);

		self::assertSame(
			90.00,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_TOTAL_META_KEY ]
		);

		self::assertSame(
			$cart_contents,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_ITEMS_META_KEY ]
		);

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_UPDATED_META_KEY ]
		);

		self::assertSame(
			2_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_EXPIRES_META_KEY ]
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_VERSION_META_KEY ]
		);
	}

	/**
	 * Verify stored cart item count uses total quantities.
	 *
	 * @return void
	 */
	public function test_store_cart_snapshot_calculates_total_item_quantity(): void {

		$cart_contents = array(
			array(
				'cart_item_key' => 'abc123',
				'product_id'    => 200,
				'variation_id'  => 0,
				'name'          => 'First Product',
				'sku'           => 'FIRST',
				'quantity'      => 2,
				'line_subtotal' => 50.00,
				'line_total'    => 50.00,
				'variation'     => array(),
			),
			array(
				'cart_item_key' => 'def456',
				'product_id'    => 300,
				'variation_id'  => 0,
				'name'          => 'Second Product',
				'sku'           => 'SECOND',
				'quantity'      => 3,
				'line_subtotal' => 75.00,
				'line_total'    => 75.00,
				'variation'     => array(),
			),
		);

		$this->service->store_cart_snapshot(
			user_id: 101,
			cart_contents: $cart_contents,
			contents_total: 125.00,
			updated_at: 1_000_000,
			expires_at: 2_000_000,
		);

		self::assertSame(
			5,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_COUNT_META_KEY ]
		);
	}

	/**
	 * Verify an invalid user ID is rejected.
	 *
	 * @return void
	 */
	public function test_store_cart_snapshot_rejects_invalid_user_id(): void {

		$result = $this->service->store_cart_snapshot(
			user_id: 0,
			cart_contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 200,
					'variation_id'  => 0,
					'name'          => 'Test Product',
					'sku'           => 'TEST-200',
					'quantity'      => 1,
					'line_subtotal' => 50.00,
					'line_total'    => 50.00,
					'variation'     => array(),
				),
			),
			contents_total: 50.00,
			updated_at: 1_000_000,
			expires_at: 2_000_000,
		);

		self::assertFalse(
			$result
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify an empty cart snapshot is rejected.
	 *
	 * @return void
	 */
	public function test_store_cart_snapshot_rejects_empty_cart_contents(): void {

		$result = $this->service->store_cart_snapshot(
			user_id: 101,
			cart_contents: array(),
			contents_total: 0.00,
			updated_at: 1_000_000,
			expires_at: 2_000_000,
		);

		self::assertFalse(
			$result
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify storing a cart snapshot overwrites existing cart metadata.
	 *
	 * This is intentional for controlled migration and reseeding operations.
	 *
	 * @return void
	 */
	public function test_store_cart_snapshot_overwrites_existing_snapshot(): void {

		$GLOBALS['shurloc_test_user_meta'][101] = array(
			User_Cart_Service::CART_COUNT_META_KEY   =>
				9,
			User_Cart_Service::CART_TOTAL_META_KEY   =>
				999.00,
			User_Cart_Service::CART_ITEMS_META_KEY   =>
				array(),
			User_Cart_Service::CART_UPDATED_META_KEY =>
				9_999_999,
			User_Cart_Service::CART_EXPIRES_META_KEY =>
				9_999_999,
		);

		$cart_contents = array(
			array(
				'cart_item_key' => 'abc123',
				'product_id'    => 200,
				'variation_id'  => 0,
				'name'          => 'Test Product',
				'sku'           => 'TEST-200',
				'quantity'      => 2,
				'line_subtotal' => 100.00,
				'line_total'    => 90.00,
				'variation'     => array(),
			),
		);

		$result = $this->service->store_cart_snapshot(
			user_id: 101,
			cart_contents: $cart_contents,
			contents_total: 90.00,
			updated_at: 1_000_000,
			expires_at: 2_000_000,
		);

		self::assertTrue(
			$result
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_COUNT_META_KEY ]
		);

		self::assertSame(
			90.00,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_TOTAL_META_KEY ]
		);

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_UPDATED_META_KEY ]
		);
	}

	/**
	 * Log in a test user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function log_in_user(
		int $user_id
	): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		$GLOBALS['shurloc_test_current_user_id']   = $user_id;
	}

	/**
	 * Create a product test double.
	 *
	 * @param string $sku  Product SKU.
	 * @param string $name Product name.
	 * @return WC_Product
	 */
	private function create_product(
		string $sku,
		string $name
	): WC_Product {

		$product = new WC_Product();

		$product->set_sku(
			sku: $sku,
		);
		$product->set_name(
			name: $name,
		);

		return $product;
	}

	/**
	 * Seed an existing cart snapshot.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function seed_existing_snapshot(
		int $user_id
	): void {

		$GLOBALS['shurloc_test_user_meta'][ $user_id ] = array(
			User_Cart_Service::CART_COUNT_META_KEY   => 2,
			User_Cart_Service::CART_TOTAL_META_KEY   => 100.00,
			User_Cart_Service::CART_ITEMS_META_KEY   => array(
				array(
					'cart_item_key' => 'old-key',
					'product_id'    => 100,
					'variation_id'  => 0,
					'name'          => 'Old Product',
					'sku'           => 'OLD',
					'quantity'      => 2,
					'line_subtotal' => 100.00,
					'line_total'    => 100.00,
				),
			),
			User_Cart_Service::CART_UPDATED_META_KEY => 900_000,
			User_Cart_Service::CART_VERSION_META_KEY => 1,
			User_Cart_Service::CART_EXPIRES_META_KEY => 3_492_000,
		);
	}
}
