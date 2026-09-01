<?php
/**
 * Tests for the user cart migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Migrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;
use Shurloc_Test_WPDB;
use WC_Product;

/**
 * Tests the user cart migration.
 */
final class UserCartMigrationTest extends TestCase {

	/**
	 * Cart service.
	 *
	 * @var User_Cart_Service
	 */
	private User_Cart_Service $cart_service;

	/**
	 * Migration under test.
	 *
	 * @var User_Cart_Migration
	 */
	private User_Cart_Migration $migration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_user_meta'] = array();
		$GLOBALS['shurloc_test_options']   = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$GLOBALS['shurloc_test_time']     = 1_000_000;
		$GLOBALS['shurloc_test_users']    = array();
		$GLOBALS['shurloc_test_products'] = array();

		$this->cart_service = new User_Cart_Service();

		$this->migration = new User_Cart_Migration(
			cart_service: $this->cart_service,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_user_meta'] = array();
		$GLOBALS['shurloc_test_options']   = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$GLOBALS['shurloc_test_time']     = 0;
		$GLOBALS['shurloc_test_users']    = array();
		$GLOBALS['shurloc_test_products'] = array();

		parent::tearDown();
	}

	/**
	 * Verify an active migration lock is detected.
	 *
	 * @return void
	 */
	public function test_is_locked_returns_true_for_active_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LOCK_OPTION ] = time();

		self::assertTrue(
			$this->migration->is_locked()
		);
	}

	/**
	 * Verify no lock is reported when none exists.
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
			[ User_Cart_Migration::LOCK_OPTION ] = time() - 901;

		self::assertFalse(
			$this->migration->is_locked()
		);

		self::assertArrayNotHasKey(
			User_Cart_Migration::LOCK_OPTION,
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
			User_Cart_Migration::LOCK_OPTION,
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
			[ User_Cart_Migration::LOCK_OPTION ] = time();

		self::assertFalse(
			$this->migration->acquire_lock()
		);
	}

	/**
	 * Verify a stale migration lock can be reacquired.
	 *
	 * @return void
	 */
	public function test_acquire_lock_replaces_stale_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LOCK_OPTION ] = time() - 901;

		self::assertTrue(
			$this->migration->acquire_lock()
		);

		self::assertArrayHasKey(
			User_Cart_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify the migration lock can be released.
	 *
	 * @return void
	 */
	public function test_release_lock_removes_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LOCK_OPTION ] = time();

		$this->migration->release_lock();

		self::assertArrayNotHasKey(
			User_Cart_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify the stored last-run timestamp can be read.
	 *
	 * @return void
	 */
	public function test_get_last_run_returns_stored_timestamp(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LAST_RUN_OPTION ] = 1_000_000;

		self::assertSame(
			1_000_000,
			$this->migration->get_last_run()
		);
	}

	/**
	 * Verify the stored last-run migration version can be read.
	 *
	 * @return void
	 */
	public function test_get_last_run_version_returns_stored_version(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LAST_RUN_VERSION_OPTION ] = 2;

		self::assertSame(
			2,
			$this->migration->get_last_run_version()
		);
	}

	/**
	 * Verify a valid stored WooCommerce cart session is seeded.
	 *
	 * @return void
	 */
	public function test_run_seeds_valid_cart_session(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => '101',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
				'session_value'  => serialize(
					array(
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
						'cart'        => serialize(
							array(
								'abc123' => array(
									'product_id'    => 200,
									'variation_id'  => 0,
									'quantity'      => 2,
									'line_subtotal' => 100.00,
									'line_total'    => 90.00,
								),
							)
						),
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
						'cart_totals' => serialize(
							array(
								'cart_contents_total' => 90.00,
							)
						),
					)
				),
				'session_expiry' => 2_000_000,
			),
		);

		$GLOBALS['shurloc_test_users'][101] = true;

		$GLOBALS['shurloc_test_products'][200] =
			$this->create_product(
				product_id: 200,
				name: 'Test Product',
				sku: 'TEST-200',
			);

		self::assertCount(
			1,
			$GLOBALS['wpdb']->results
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
			2_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_EXPIRES_META_KEY ]
		);
	}

	/**
	 * Verify guest WooCommerce sessions are skipped.
	 *
	 * @return void
	 */
	public function test_run_skips_guest_session(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => 'guest-session-key',
				'session_value'  => '',
				'session_expiry' => 2_000_000,
			),
		);

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
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify an orphaned numeric WooCommerce session is skipped.
	 *
	 * @return void
	 */
	public function test_run_skips_session_for_missing_user(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => '101',
				'session_value'  => '',
				'session_expiry' => 2_000_000,
			),
		);

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
	}

	/**
	 * Verify a session without cart data is skipped.
	 *
	 * @return void
	 */
	public function test_run_skips_session_without_cart_data(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => '101',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
				'session_value'  => serialize(
					array()
				),
				'session_expiry' => 2_000_000,
			),
		);

		$GLOBALS['shurloc_test_users'][101] = true;

		$result = $this->migration->run();

		self::assertSame(
			1,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['updated']
		);
	}

	/**
	 * Verify cart totals are calculated from line totals when stored totals
	 * are unavailable.
	 *
	 * @return void
	 */
	public function test_run_falls_back_to_line_totals(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => '101',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
				'session_value'  => serialize(
					array(
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
						'cart' => serialize(
							array(
								'abc123' => array(
									'product_id'    => 200,
									'variation_id'  => 0,
									'quantity'      => 1,
									'line_subtotal' => 50.00,
									'line_total'    => 45.00,
								),
								'def456' => array(
									'product_id'    => 300,
									'variation_id'  => 0,
									'quantity'      => 1,
									'line_subtotal' => 75.00,
									'line_total'    => 70.00,
								),
							)
						),
					)
				),
				'session_expiry' => 2_000_000,
			),
		);

		$GLOBALS['shurloc_test_users'][101] = true;

		$GLOBALS['shurloc_test_products'][200] =
			$this->create_product(
				product_id: 200,
				name: 'First Product',
				sku: 'FIRST',
			);

		$GLOBALS['shurloc_test_products'][300] =
			$this->create_product(
				product_id: 300,
				name: 'Second Product',
				sku: 'SECOND',
			);

		$this->migration->run();

		self::assertSame(
			115.00,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_TOTAL_META_KEY ]
		);
	}

	/**
	 * Verify stored variation attributes are preserved.
	 *
	 * @return void
	 */
	public function test_run_preserves_variation_attributes(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => '101',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
				'session_value'  => serialize(
					array(
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce session storage.
						'cart' => serialize(
							array(
								'abc123' => array(
									'product_id'    => 200,
									'variation_id'  => 201,
									'quantity'      => 1,
									'line_subtotal' => 50.00,
									'line_total'    => 50.00,
									'variation'     => array(
										'attribute_pa_color' => 'blue',
										'attribute_pa_size'  => 'large',
									),
								),
							)
						),
					)
				),
				'session_expiry' => 2_000_000,
			),
		);

		$GLOBALS['shurloc_test_users'][101] = true;

		$GLOBALS['shurloc_test_products'][201] =
			$this->create_product(
				product_id: 201,
				name: 'Variation Product',
				sku: 'VAR-201',
			);

		$this->migration->run();

		$cart_contents =
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_ITEMS_META_KEY ];

		self::assertSame(
			array(
				'attribute_pa_color' => 'blue',
				'attribute_pa_size'  => 'large',
			),
			$cart_contents[0]['variation']
		);
	}

	/**
	 * Verify the WooCommerce sessions table uses the active WordPress prefix.
	 *
	 * @return void
	 */
	public function test_run_uses_prefixed_woocommerce_sessions_table(): void {

		$GLOBALS['wpdb']->prefix = 'custom_';

		$this->migration->run();

		self::assertSame(
			'custom_woocommerce_sessions',
			$GLOBALS['wpdb']->prepared_queries[0]['args'][0]
		);
	}

	/**
	 * Verify migration run metadata is recorded.
	 *
	 * @return void
	 */
	public function test_run_records_timestamp_and_version(): void {

		$before = time();

		$this->migration->run();

		$after = time();

		$last_run =
			$GLOBALS['shurloc_test_options']
				[ User_Cart_Migration::LAST_RUN_OPTION ];

		self::assertGreaterThanOrEqual(
			$before,
			$last_run
		);

		self::assertLessThanOrEqual(
			$after,
			$last_run
		);

		self::assertSame(
			User_Cart_Migration::VERSION,
			$GLOBALS['shurloc_test_options']
				[ User_Cart_Migration::LAST_RUN_VERSION_OPTION ]
		);
	}

	/**
	 * Create a WooCommerce product test double.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $name       Product name.
	 * @param string $sku        Product SKU.
	 * @return WC_Product
	 */
	private function create_product(
		int $product_id,
		string $name,
		string $sku
	): WC_Product {

		$product = new WC_Product( $product_id );

		$product->set_name( $name );
		$product->set_sku( $sku );

		return $product;
	}
}
