<?php
/**
 * Tests for breadcrumb schema integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Tests breadcrumb schema behavior.
 */
final class BreadcrumbSchemaTest extends TestCase {

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']           = array();
		$GLOBALS['shurloc_test_filter_metadata']   = array();
		$GLOBALS['shurloc_test_is_product']        = true;
		$GLOBALS['shurloc_test_wc_breadcrumbs']    = array();
		$GLOBALS['shurloc_test_queried_object_id'] = 0;
		$GLOBALS['shurloc_test_titles']            = array();
		$GLOBALS['shurloc_test_permalinks']        = array();
	}

	/**
	 * Verify that the expected schema filters are registered.
	 *
	 * @return void
	 */
	public function test_registers_schema_filters(): void {

		$schema = new Breadcrumb_Schema();

		$schema->register();

		self::assertNotFalse(
			has_filter(
				'wpseo_schema_breadcrumb',
				array( $schema, 'synchronize_breadcrumb_schema' )
			)
		);
		self::assertNotFalse(
			has_filter(
				'woocommerce_structured_data_breadcrumblist',
				array( $schema, 'disable_woocommerce_breadcrumb_schema' )
			)
		);
		self::assertNotFalse(
			has_filter(
				'wpseo_schema_webpage',
				array( $schema, 'add_webpage_main_entity' )
			)
		);
	}

	/**
	 * Verify that breadcrumbs are unchanged outside product pages.
	 *
	 * @return void
	 */
	public function test_preserves_breadcrumb_schema_outside_product_pages(): void {

		$GLOBALS['shurloc_test_is_product'] = false;

		$schema = new Breadcrumb_Schema();
		$piece  = array(
			'@type' => 'BreadcrumbList',
			'@id'   => 'https://example.com/#breadcrumb',
		);

		self::assertSame(
			$piece,
			$schema->synchronize_breadcrumb_schema( $piece )
		);
	}

	/**
	 * Verify that product breadcrumb schema follows WooCommerce breadcrumbs.
	 *
	 * @return void
	 */
	public function test_synchronizes_product_breadcrumb_schema(): void {

		$GLOBALS['shurloc_test_queried_object_id'] = 100;
		$GLOBALS['shurloc_test_titles'][100]       = 'Widget';
		$GLOBALS['shurloc_test_permalinks'][100]   =
			'https://example.com/product/widget/';
		$GLOBALS['shurloc_test_wc_breadcrumbs']    = array(
			array( 'Home', 'https://example.com/' ),
			array( 'Products', 'https://example.com/shop/' ),
			array( 'Hardware', 'https://example.com/product-category/hardware/' ),
		);

		$schema = new Breadcrumb_Schema();

		$result = $schema->synchronize_breadcrumb_schema(
			array( '@id' => 'https://example.com/#breadcrumb' )
		);

		self::assertSame( 'BreadcrumbList', $result['@type'] );
		self::assertSame(
			array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Home',
					'item'     => 'https://example.com/',
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => 'Products',
					'item'     => 'https://example.com/shop/',
				),
				array(
					'@type'    => 'ListItem',
					'position' => 3,
					'name'     => 'Hardware',
					'item'     => 'https://example.com/product-category/hardware/',
				),
				array(
					'@type'    => 'ListItem',
					'position' => 4,
					'name'     => 'Widget',
				),
			),
			$result['itemListElement']
		);
	}

	/**
	 * Verify that WooCommerce breadcrumb schema is disabled on product pages.
	 *
	 * @return void
	 */
	public function test_disables_woocommerce_breadcrumb_schema_on_product_pages(): void {

		$schema      = new Breadcrumb_Schema();
		$breadcrumbs = new \WC_Breadcrumb();
		$markup      = array( '@type' => 'BreadcrumbList' );

		self::assertSame(
			array(),
			$schema->disable_woocommerce_breadcrumb_schema(
				$markup,
				$breadcrumbs
			)
		);
	}

	/**
	 * Verify that WooCommerce breadcrumb schema is preserved outside product pages.
	 *
	 * @return void
	 */
	public function test_preserves_woocommerce_breadcrumb_schema_outside_product_pages(): void {

		$GLOBALS['shurloc_test_is_product'] = false;

		$schema      = new Breadcrumb_Schema();
		$breadcrumbs = new \WC_Breadcrumb();
		$markup      = array( '@type' => 'BreadcrumbList' );

		self::assertSame(
			$markup,
			$schema->disable_woocommerce_breadcrumb_schema(
				$markup,
				$breadcrumbs
			)
		);
	}

	/**
	 * Verify that a product WebPage schema links to the Product node.
	 *
	 * @return void
	 */
	public function test_adds_product_as_webpage_main_entity(): void {

		$schema = new Breadcrumb_Schema();

		$result = $schema->add_webpage_main_entity(
			array(
				'url' => 'https://example.com/product/widget/#webpage',
			)
		);

		self::assertSame(
			array(
				'@id' => 'https://example.com/product/widget/#product',
			),
			$result['mainEntity']
		);
	}

	/**
	 * Verify that a WebPage schema without a URL is unchanged.
	 *
	 * @return void
	 */
	public function test_preserves_webpage_schema_without_url(): void {

		$schema = new Breadcrumb_Schema();
		$data   = array( '@type' => 'WebPage' );

		self::assertSame( $data, $schema->add_webpage_main_entity( $data ) );
	}
}
