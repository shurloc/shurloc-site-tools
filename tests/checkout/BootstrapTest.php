<?php
/**
 * Tests for the Checkout domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout;

use PHPUnit\Framework\TestCase;

/**
 * Tests the Checkout domain bootstrap.
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
	 * Verify registering the Checkout bootstrap wires admin hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_checkout_admin_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'admin_init',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'admin_menu',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'shurloc_tools_overview',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify registering the Checkout bootstrap wires fee and frontend hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_checkout_fee_and_frontend_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertCount(
			2,
			$GLOBALS['shurloc_test_actions']['woocommerce_cart_calculate_fees']
		);
		self::assertCount(
			2,
			$GLOBALS['shurloc_test_actions']['wp_enqueue_scripts']
		);
	}

	/**
	 * Verify registering the Checkout bootstrap wires payment hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_checkout_payment_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'woocommerce_gateway_title',
			$GLOBALS['shurloc_test_filters']
		);
		self::assertArrayHasKey(
			'woocommerce_email_before_order_table',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'woocommerce_email_after_order_table',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'woocommerce_get_order_item_totals',
			$GLOBALS['shurloc_test_filters']
		);
		self::assertArrayHasKey(
			'woocommerce_cheque_process_payment_order_status',
			$GLOBALS['shurloc_test_filters']
		);
		self::assertArrayHasKey(
			'woocommerce_bacs_process_payment_order_status',
			$GLOBALS['shurloc_test_filters']
		);
	}
}
