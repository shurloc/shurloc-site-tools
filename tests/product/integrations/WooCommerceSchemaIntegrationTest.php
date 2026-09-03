<?php
/**
 * Tests for WooCommerce schema integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests WooCommerce Product schema suppression.
 */
final class WooCommerceSchemaIntegrationTest extends TestCase {

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
	}

	/**
	 * Verify that the WooCommerce Product schema filter is registered.
	 *
	 * @return void
	 */
	public function test_registers_product_schema_filter(): void {

		$integration = new WooCommerce_Schema_Integration();

		$integration->register();

		self::assertNotFalse(
			has_filter(
				'woocommerce_structured_data_product',
				array( $integration, 'remove_product_schema' )
			)
		);
	}

	/**
	 * Verify that WooCommerce Product schema is removed.
	 *
	 * @return void
	 */
	public function test_removes_product_schema(): void {

		$integration = new WooCommerce_Schema_Integration();

		self::assertSame(
			array(),
			$integration->remove_product_schema(
				array(
					'@type' => 'Product',
					'name'  => 'Widget',
				)
			)
		);
	}
}
