<?php
/**
 * Tests for the Customer domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Admin\User_Filters;

/**
 * Tests the Customer domain bootstrap.
 */
final class BootstrapTest extends TestCase {

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Verify the Customer bootstrap registers customer services.
	 *
	 * @return void
	 */
	public function test_register_adds_customer_service_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'wp',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_init',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'wp_login',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'woocommerce_after_calculate_totals',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'woocommerce_checkout_order_processed',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the Customer bootstrap registers admin functionality.
	 *
	 * @return void
	 */
	public function test_register_adds_customer_admin_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'admin_menu',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'manage_users_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'manage_users_custom_column',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'manage_users_sortable_columns',
			$GLOBALS['shurloc_test_filters']
		);
	}

	/**
	 * Verify the Customer bootstrap registers shared user filters.
	 *
	 * @return void
	 */
	public function test_register_adds_user_filter_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'manage_users_extra_tablenav',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			User_Filters::FILTER_CONTROLS_ACTION,
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'pre_get_users',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the Customer bootstrap registers migration handlers.
	 *
	 * @return void
	 */
	public function test_register_adds_migration_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'admin_post_shurloc_run_purchase_migration',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_post_shurloc_run_cart_migration',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the Customer bootstrap registers the Tools overview section.
	 *
	 * @return void
	 */
	public function test_register_adds_tools_overview_section(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'shurloc_tools_overview',
			$GLOBALS['shurloc_test_actions']
		);
	}
}
