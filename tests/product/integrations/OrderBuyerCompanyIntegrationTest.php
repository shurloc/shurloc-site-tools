<?php
/**
 * Tests for the order buyer company integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;
use WC_Order;

/**
 * Tests the order buyer company integration.
 */
final class OrderBuyerCompanyIntegrationTest extends TestCase {

	/**
	 * Integration under test.
	 *
	 * @var Order_Buyer_Company_Integration
	 */
	private Order_Buyer_Company_Integration $integration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		$this->integration =
			new Order_Buyer_Company_Integration();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Verify the WooCommerce buyer-name filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_order_buyer_name_filter(): void {

		$this->integration->register();

		self::assertContains(
			array(
				$this->integration,
				'add_billing_company',
			),
			$GLOBALS['shurloc_test_filters']
				['woocommerce_admin_order_buyer_name']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_filter_metadata']
				['woocommerce_admin_order_buyer_name'][0]['priority']
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_filter_metadata']
				['woocommerce_admin_order_buyer_name'][0]['accepted_args']
		);
	}

	/**
	 * Verify the billing company is appended to the buyer name.
	 *
	 * @return void
	 */
	public function test_add_billing_company_appends_company(): void {

		$order = new WC_Order( 100 );

		$order->set_billing_company(
			'Acme Manufacturing'
		);

		$result = $this->integration->add_billing_company(
			buyer: 'John Smith',
			order: $order,
		);

		self::assertSame(
			'John Smith - Acme Manufacturing',
			$result
		);
	}

	/**
	 * Verify orders without a billing company preserve the buyer name.
	 *
	 * @return void
	 */
	public function test_add_billing_company_preserves_buyer_without_company(): void {

		$order = new WC_Order( 100 );

		$result = $this->integration->add_billing_company(
			buyer: 'John Smith',
			order: $order,
		);

		self::assertSame(
			'John Smith',
			$result
		);
	}

	/**
	 * Verify whitespace-only billing companies are ignored.
	 *
	 * @return void
	 */
	public function test_add_billing_company_ignores_whitespace_only_company(): void {

		$order = new WC_Order( 100 );

		$order->set_billing_company(
			'   '
		);

		$result = $this->integration->add_billing_company(
			buyer: 'John Smith',
			order: $order,
		);

		self::assertSame(
			'John Smith',
			$result
		);
	}

	/**
	 * Verify billing company whitespace is trimmed before display.
	 *
	 * @return void
	 */
	public function test_add_billing_company_trims_company(): void {

		$order = new WC_Order( 100 );

		$order->set_billing_company(
			'  Acme Manufacturing  '
		);

		$result = $this->integration->add_billing_company(
			buyer: 'John Smith',
			order: $order,
		);

		self::assertSame(
			'John Smith - Acme Manufacturing',
			$result
		);
	}
}
