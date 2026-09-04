<?php
/**
 * Tests for the customer migrations admin controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc\SiteTools\Customer\Migrations\User_Cart_Migration;
use Shurloc\SiteTools\Customer\Migrations\User_Purchase_Migration;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;
use Shurloc_Test_WPDB;
use WC_Order;
use WC_Product;

/**
 * Tests the customer migrations admin controller.
 */
final class CustomerMigrationsControllerTest extends TestCase {

	/**
	 * Controller under test.
	 *
	 * @var Customer_Migrations_Controller
	 */
	private Customer_Migrations_Controller $controller;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_action_metadata']      = array();
		$GLOBALS['shurloc_test_enqueued_scripts']     = array();
		$GLOBALS['shurloc_test_styles']               = array();
		$GLOBALS['shurloc_test_options']              = array();
		$GLOBALS['shurloc_test_nonce_fields']         = array();
		$GLOBALS['shurloc_test_users']                = array();
		$GLOBALS['shurloc_test_orders']               = array();
		$GLOBALS['shurloc_test_user_meta']            = array();
		$GLOBALS['shurloc_test_user_capabilities']    = array();
		$GLOBALS['shurloc_test_admin_referer_checks'] = array();
		$GLOBALS['shurloc_test_nonce_valid']          = true;
		$GLOBALS['shurloc_test_redirects']            = array();
		$GLOBALS['shurloc_test_wp_die_messages']      = array();
		$GLOBALS['shurloc_test_wc_get_orders_args']   = array();
		$GLOBALS['shurloc_test_products']             = array();
		$GLOBALS['shurloc_test_time']                 = 1_000_000;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$_GET = array();

		$purchase_service = new User_Purchase_Service();
		$cart_service     = new User_Cart_Service();

		$purchase_migration = new User_Purchase_Migration(
			purchase_service: $purchase_service,
		);

		$cart_migration = new User_Cart_Migration(
			cart_service: $cart_service,
		);

		$this->controller = new Customer_Migrations_Controller(
			purchase_migration: $purchase_migration,
			cart_migration: $cart_migration,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_action_metadata']      = array();
		$GLOBALS['shurloc_test_enqueued_scripts']     = array();
		$GLOBALS['shurloc_test_styles']               = array();
		$GLOBALS['shurloc_test_options']              = array();
		$GLOBALS['shurloc_test_nonce_fields']         = array();
		$GLOBALS['shurloc_test_users']                = array();
		$GLOBALS['shurloc_test_orders']               = array();
		$GLOBALS['shurloc_test_user_meta']            = array();
		$GLOBALS['shurloc_test_user_capabilities']    = array();
		$GLOBALS['shurloc_test_admin_referer_checks'] = array();
		$GLOBALS['shurloc_test_nonce_valid']          = true;
		$GLOBALS['shurloc_test_redirects']            = array();
		$GLOBALS['shurloc_test_wp_die_messages']      = array();
		$GLOBALS['shurloc_test_wc_get_orders_args']   = array();
		$GLOBALS['shurloc_test_products']             = array();
		$GLOBALS['shurloc_test_time']                 = 1_000_000;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Verify controller hooks are registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_hooks(): void {

		$this->controller->register();

		self::assertContains(
			array(
				$this->controller,
				'handle_purchase_migration',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_post_shurloc_run_purchase_migration']
		);

		self::assertContains(
			array(
				$this->controller,
				'enqueue_assets',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_enqueue_scripts']
		);
	}

	/**
	 * Verify controller registration includes the cart migration action.
	 *
	 * @return void
	 */
	public function test_register_adds_cart_migration_action(): void {

		$this->controller->register();

		self::assertContains(
			array(
				$this->controller,
				'handle_cart_migration',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_post_shurloc_run_cart_migration']
		);
	}

	/**
	 * Verify migration assets are enqueued on the migrations tab.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_on_migrations_page(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'migrations';

		$this->controller->enqueue_assets();

		$handles = array_column(
			$GLOBALS['shurloc_test_enqueued_scripts'],
			'handle'
		);

		self::assertContains(
			'shurloc-customer-migrations',
			$handles
		);

		self::assertArrayHasKey(
			'shurloc-customer-migrations',
			$GLOBALS['shurloc_test_styles']
		);
	}

	/**
	 * Verify migration assets are not enqueued elsewhere.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_is_ignored_outside_migrations_page(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'overview';

		$this->controller->enqueue_assets();

		$handles = array_column(
			$GLOBALS['shurloc_test_enqueued_scripts'],
			'handle'
		);

		self::assertNotContains(
			'shurloc-customer-migrations',
			$handles
		);

		self::assertArrayNotHasKey(
			'shurloc-customer-migrations',
			$GLOBALS['shurloc_test_styles']
		);
	}

	/**
	 * Verify the migrations page renders the purchase migration controls.
	 *
	 * @return void
	 */
	public function test_render_shows_purchase_migration_controls(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Purchase Tracking Seeding',
			$output
		);

		self::assertStringContainsString(
			'Enable this migration',
			$output
		);

		self::assertStringContainsString(
			'Run Purchase Migration',
			$output
		);

		self::assertStringContainsString(
			'disabled',
			$output
		);

		self::assertCount(
			2,
			$GLOBALS['shurloc_test_nonce_fields']
		);

		self::assertSame(
			'shurloc_run_purchase_migration',
			$GLOBALS['shurloc_test_nonce_fields'][0]['action']
		);
	}

	/**
	 * Verify the migrations page displays the purchase last-run version.
	 *
	 * @return void
	 */
	public function test_render_shows_last_run_version(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LAST_RUN_VERSION_OPTION ] = 1;

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Last-run migration version',
			$output
		);

		self::assertMatchesRegularExpression(
			'/Last-run migration version<\/th>\s*<td>\s*1\s*<\/td>/',
			$output
		);
	}

	/**
	 * Verify the migrations page renders the cart migration controls.
	 *
	 * @return void
	 */
	public function test_render_shows_cart_migration_controls(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Cart Tracking Seeding',
			$output
		);

		self::assertStringContainsString(
			'Run Cart Migration',
			$output
		);

		self::assertStringContainsString(
			'shurloc_run_cart_migration',
			$output
		);

		self::assertStringContainsString(
			'stored WooCommerce sessions',
			$output
		);
	}

	/**
	 * Verify the migrations page displays the cart last-run version.
	 *
	 * @return void
	 */
	public function test_render_shows_cart_last_run_version(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LAST_RUN_VERSION_OPTION ] = 1;

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/Cart Tracking Seeding.*?Last-run migration version<\/th>\s*<td>\s*1\s*<\/td>/s',
			$output
		);
	}

	/**
	 * Verify the migrations page renders the running overlay.
	 *
	 * @return void
	 */
	public function test_render_shows_migration_running_overlay(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'shurloc-migration-overlay',
			$output
		);

		self::assertStringContainsString(
			'Migration is running',
			$output
		);

		self::assertStringContainsString(
			'Please keep this page open until the migration completes.',
			$output
		);
	}

	/**
	 * Verify running the purchase migration seeds customer data.
	 *
	 * @return void
	 */
	public function test_run_purchase_migration_seeds_purchase_data(): void {

		$GLOBALS['shurloc_test_users'] = array(
			101,
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

		$this->controller->run_purchase_migration();

		self::assertSame(
			200,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY ]
		);

		self::assertSame(
			'completed',
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY ]
		);
	}

	/**
	 * Verify running the purchase migration returns the expected result URL.
	 *
	 * @return void
	 */
	public function test_run_purchase_migration_returns_result_url(): void {

		$GLOBALS['shurloc_test_users'] = array(
			101,
			102,
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

		$GLOBALS['shurloc_test_orders'][102] = array();

		$redirect_url =
			$this->controller->run_purchase_migration();

		self::assertStringContainsString(
			'page=shurloc-site-tools-customers',
			$redirect_url
		);

		self::assertStringContainsString(
			'tab=migrations',
			$redirect_url
		);

		self::assertStringContainsString(
			'migration=purchase',
			$redirect_url
		);

		self::assertStringContainsString(
			'examined=2',
			$redirect_url
		);

		self::assertStringContainsString(
			'updated=1',
			$redirect_url
		);

		self::assertStringContainsString(
			'skipped=1',
			$redirect_url
		);

		self::assertStringContainsString(
			'errors=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'_wpnonce=test-nonce-shurloc_purchase_migration_result',
			$redirect_url
		);
	}

	/**
	 * Verify running the purchase migration records migration metadata.
	 *
	 * @return void
	 */
	public function test_run_purchase_migration_records_run_metadata(): void {

		$GLOBALS['shurloc_test_users'] = array();

		$this->controller->run_purchase_migration();

		self::assertArrayHasKey(
			User_Purchase_Migration::LAST_RUN_OPTION,
			$GLOBALS['shurloc_test_options']
		);

		self::assertSame(
			User_Purchase_Migration::VERSION,
			$GLOBALS['shurloc_test_options']
				[ User_Purchase_Migration::LAST_RUN_VERSION_OPTION ]
		);
	}

	/**
	 * Verify an unauthorized user cannot run the purchase migration.
	 *
	 * @return void
	 */
	public function test_handle_purchase_migration_rejects_unauthorized_user(): void {

		$GLOBALS['shurloc_test_user_capabilities']
			['manage_options'] = false;

		try {

			$this->controller->handle_purchase_migration();

			self::fail(
				'Expected wp_die() to terminate the request.'
			);

		} catch ( RuntimeException $exception ) {

			self::assertSame(
				'You are not allowed to run this migration.',
				$exception->getMessage()
			);
		}

		self::assertSame(
			array(
				'You are not allowed to run this migration.',
			),
			$GLOBALS['shurloc_test_wp_die_messages']
		);
	}

	/**
	 * Verify a valid migration result nonce displays the purchase result notice.
	 *
	 * @return void
	 */
	public function test_render_displays_valid_purchase_migration_result(): void {

		$_GET['migration'] = 'purchase';
		$_GET['examined']  = '10';
		$_GET['updated']   = '7';
		$_GET['skipped']   = '3';
		$_GET['errors']    = '0';
		$_GET['_wpnonce']  =
			'test-nonce-shurloc_purchase_migration_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Purchase migration complete.',
			$output
		);

		self::assertStringContainsString(
			'Examined: 10',
			$output
		);

		self::assertStringContainsString(
			'Updated: 7',
			$output
		);

		self::assertStringContainsString(
			'Skipped: 3',
			$output
		);

		self::assertStringContainsString(
			'Errors: 0',
			$output
		);
	}

	/**
	 * Verify an invalid migration result nonce does not display a result notice.
	 *
	 * @return void
	 */
	public function test_render_does_not_display_result_with_invalid_nonce(): void {

		$GLOBALS['shurloc_test_nonce_valid'] = false;

		$_GET['migration'] = 'purchase';
		$_GET['examined']  = '10';
		$_GET['updated']   = '7';
		$_GET['skipped']   = '3';
		$_GET['errors']    = '0';
		$_GET['_wpnonce']  = 'invalid-nonce';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringNotContainsString(
			'Purchase migration complete.',
			$output
		);
	}

	/**
	 * Verify migration result data without a nonce is ignored.
	 *
	 * @return void
	 */
	public function test_render_does_not_display_result_without_nonce(): void {

		$_GET['migration'] = 'purchase';
		$_GET['examined']  = '10';
		$_GET['updated']   = '7';
		$_GET['skipped']   = '3';
		$_GET['errors']    = '0';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringNotContainsString(
			'Purchase migration complete.',
			$output
		);
	}

	/**
	 * Verify a locked purchase migration is not executed a second time.
	 *
	 * @return void
	 */
	public function test_run_purchase_migration_does_not_run_when_locked(): void {

		$GLOBALS['shurloc_test_users'] = array(
			101,
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

		$GLOBALS['shurloc_test_options']
			[ User_Purchase_Migration::LOCK_OPTION ] = time();

		$redirect_url =
			$this->controller->run_purchase_migration();

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);

		self::assertStringContainsString(
			'migration=purchase-locked',
			$redirect_url
		);
	}

	/**
	 * Verify a completed purchase migration releases its lock.
	 *
	 * @return void
	 */
	public function test_run_purchase_migration_releases_lock_after_completion(): void {

		$GLOBALS['shurloc_test_users'] = array();

		$this->controller->run_purchase_migration();

		self::assertArrayNotHasKey(
			User_Purchase_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify a locked purchase migration displays a warning notice.
	 *
	 * @return void
	 */
	public function test_render_displays_purchase_migration_locked_notice(): void {

		$_GET['migration'] = 'purchase-locked';
		$_GET['_wpnonce']  =
			'test-nonce-shurloc_purchase_migration_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Purchase migration is already running.',
			$output
		);

		self::assertStringContainsString(
			'No second migration was started.',
			$output
		);
	}

	/**
	 * Verify running the cart migration seeds cart data.
	 *
	 * @return void
	 */
	public function test_run_cart_migration_seeds_cart_data(): void {

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
									'quantity'      => 2,
									'line_subtotal' => 100.00,
									'line_total'    => 90.00,
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
				name: 'Test Product',
				sku: 'TEST-200',
			);

		$this->controller->run_cart_migration();

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Cart_Service::CART_COUNT_META_KEY ]
		);
	}

	/**
	 * Verify running the cart migration returns the expected result URL.
	 *
	 * @return void
	 */
	public function test_run_cart_migration_returns_result_url(): void {

		$GLOBALS['wpdb']->results = array();

		$redirect_url =
			$this->controller->run_cart_migration();

		self::assertStringContainsString(
			'page=shurloc-site-tools-customers',
			$redirect_url
		);

		self::assertStringContainsString(
			'tab=migrations',
			$redirect_url
		);

		self::assertStringContainsString(
			'migration=cart',
			$redirect_url
		);

		self::assertStringContainsString(
			'examined=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'updated=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'skipped=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'errors=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'_wpnonce=test-nonce-shurloc_cart_migration_result',
			$redirect_url
		);
	}

	/**
	 * Verify a locked cart migration is not executed a second time.
	 *
	 * @return void
	 */
	public function test_run_cart_migration_does_not_run_when_locked(): void {

		$GLOBALS['shurloc_test_options']
			[ User_Cart_Migration::LOCK_OPTION ] = 1_000_000;

		$redirect_url =
			$this->controller->run_cart_migration();

		self::assertStringContainsString(
			'migration=cart-locked',
			$redirect_url
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify a completed cart migration releases its lock.
	 *
	 * @return void
	 */
	public function test_run_cart_migration_releases_lock_after_completion(): void {

		$GLOBALS['wpdb']->results = array();

		$this->controller->run_cart_migration();

		self::assertArrayNotHasKey(
			User_Cart_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify a valid cart migration result displays the completion notice.
	 *
	 * @return void
	 */
	public function test_render_displays_valid_cart_migration_result(): void {

		$_GET['migration'] = 'cart';
		$_GET['examined']  = '10';
		$_GET['updated']   = '7';
		$_GET['skipped']   = '3';
		$_GET['errors']    = '0';
		$_GET['_wpnonce']  =
			'test-nonce-shurloc_cart_migration_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Cart migration complete.',
			$output
		);

		self::assertStringContainsString(
			'Examined: 10',
			$output
		);

		self::assertStringContainsString(
			'Updated: 7',
			$output
		);

		self::assertStringContainsString(
			'Skipped: 3',
			$output
		);

		self::assertStringContainsString(
			'Errors: 0',
			$output
		);
	}

	/**
	 * Verify a locked cart migration displays a warning notice.
	 *
	 * @return void
	 */
	public function test_render_displays_cart_migration_locked_notice(): void {

		$_GET['migration'] = 'cart-locked';
		$_GET['_wpnonce']  =
			'test-nonce-shurloc_cart_migration_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Cart migration is already running.',
			$output
		);

		self::assertStringContainsString(
			'No second migration was started.',
			$output
		);
	}

	/**
	 * Verify a purchase result nonce cannot authorize a cart result.
	 *
	 * @return void
	 */
	public function test_render_rejects_cart_result_with_purchase_nonce(): void {

		$_GET['migration'] = 'cart';
		$_GET['examined']  = '10';
		$_GET['updated']   = '7';
		$_GET['skipped']   = '3';
		$_GET['errors']    = '0';
		$_GET['_wpnonce']  =
			'test-nonce-shurloc_purchase_migration_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringNotContainsString(
			'Cart migration complete.',
			$output
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
