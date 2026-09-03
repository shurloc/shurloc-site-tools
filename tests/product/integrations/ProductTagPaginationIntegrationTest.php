<?php
/**
 * Tests for the product tag pagination integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests the product tag pagination integration.
 */
final class ProductTagPaginationIntegrationTest extends TestCase {

	/**
	 * Integration under test.
	 *
	 * @var Product_Tag_Pagination_Integration
	 */
	private Product_Tag_Pagination_Integration $integration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_is_product_tag']  = false;

		$this->integration =
			new Product_Tag_Pagination_Integration();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_is_product_tag']  = false;

		parent::tearDown();
	}

	/**
	 * Verify the WooCommerce products-per-page filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_products_per_page_filter(): void {

		$this->integration->register();

		self::assertContains(
			array(
				$this->integration,
				'filter_products_per_page',
			),
			$GLOBALS['shurloc_test_filters']
				['loop_shop_per_page']
		);

		self::assertSame(
			PHP_INT_MAX,
			$GLOBALS['shurloc_test_filter_metadata']
				['loop_shop_per_page'][0]['priority']
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_filter_metadata']
				['loop_shop_per_page'][0]['accepted_args']
		);
	}

	/**
	 * Verify product tag archives display 96 products per page.
	 *
	 * @return void
	 */
	public function test_filter_products_per_page_returns_96_for_product_tags(): void {

		$GLOBALS['shurloc_test_is_product_tag'] = true;

		$result = $this->integration->filter_products_per_page(
			per_page: 24,
		);

		self::assertSame(
			96,
			$result
		);
	}

	/**
	 * Verify non-product-tag archives preserve the existing value.
	 *
	 * @return void
	 */
	public function test_filter_products_per_page_preserves_value_for_other_archives(): void {

		$GLOBALS['shurloc_test_is_product_tag'] = false;

		$result = $this->integration->filter_products_per_page(
			per_page: 24,
		);

		self::assertSame(
			24,
			$result
		);
	}
}
