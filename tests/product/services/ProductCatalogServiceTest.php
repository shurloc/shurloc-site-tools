<?php
/**
 * Tests for Product_Catalog_Service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Test_WC_Product;
use Test_WC_Product_Variation;
use WC_Product;
use WC_Product_Variation;

/**
 * Tests product catalog service.
 */
final class ProductCatalogServiceTest extends TestCase {

	/**
	 * Product catalog service instance.
	 *
	 * @var Product_Catalog_Service
	 */
	private Product_Catalog_Service $service;

	/**
	 * Setup tests.
	 */
	protected function setUp(): void {

		$this->service = new Product_Catalog_Service();
	}

	/**
	 * Teardown tests.
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_products'] = array();

		parent::tearDown();
	}

	/**
	 * Simple product should create catalog entry.
	 */
	public function test_simple_product_creates_catalog_entry(): void {

		$product = new Test_WC_Product( 123 );

		$product->set_name(
			name: 'Test Product',
		);

		$product->set_sku(
			sku: 'TEST-123',
		);

		$product->set_short_description(
			short_description: 'Test short description.',
		);

		$product->set_description(
			description: 'Test full description.',
		);

		$product->set_category(
			category: 'Screen Printing',
		);

		$product->set_price(
			price: '25.00',
		);

		$product->set_regular_price(
			price: '30.00',
		);

		$product->set_sale_price(
			price: '25.00',
		);

		$product->set_stock_status(
			status: 'instock',
		);

		wp_set_object_terms(
			123,
			array( 'Screen Printing' ),
			'product_cat'
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Catalog_Product_Entry::class,
			$entry
		);

		$this->assertSame(
			123,
			$entry->product_id
		);

		$this->assertSame(
			'Test Product',
			$entry->product_name
		);

		$this->assertSame(
			'TEST-123',
			$entry->sku
		);

		$this->assertSame(
			'Test short description.',
			$entry->short_description
		);

		$this->assertSame(
			'Test full description.',
			$entry->description
		);

		$this->assertSame(
			'Screen Printing',
			$entry->category
		);

		$this->assertSame(
			25.0,
			$entry->price
		);

		$this->assertSame(
			30.0,
			$entry->regular_price
		);

		$this->assertSame(
			25.0,
			$entry->sale_price
		);

		$this->assertSame(
			'https://schema.org/InStock',
			$entry->availability
		);
	}

	/**
	 * Product should default manufacturer to Shur-loc.
	 */
	public function test_product_manufacturer_defaults_to_shurloc(): void {

		$product = new WC_Product( 456 );

		$product->set_name(
			name: 'Manufacturer Test',
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'Shur-loc®',
			$entry->manufacturer
		);
	}

	/**
	 * Product brand should be loaded from product data.
	 */
	public function test_product_brand_is_loaded(): void {

		$product = new WC_Product( 789 );

		$product->set_name(
			name: 'Branded Product',
		);

		wp_set_object_terms(
			789,
			array( 'Murakami' ),
			'product_brand'
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'Murakami',
			$entry->brand
		);
	}

	/**
	 * Product without brand should default to Shur-loc.
	 */
	public function test_product_without_brand_defaults_to_shurloc(): void {

		$product = new WC_Product( 999 );

		$product->set_name(
			name: 'No Brand Product',
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'Shur-loc®',
			$entry->brand
		);
	}

	/**
	 * Product without reviews should have no aggregate rating.
	 */
	public function test_product_without_reviews_has_no_rating_data(): void {

		$product = new WC_Product( 1000 );

		$product->set_name(
			name: 'No Review Product',
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertNull(
			$entry->aggregate_rating
		);

		$this->assertSame(
			array(),
			$entry->reviews
		);
	}

	/**
	 * Out of stock products should preserve availability.
	 */
	public function test_out_of_stock_product_sets_availability(): void {

		$product = new WC_Product( 111 );

		$product->set_stock_status(
			status: 'outofstock',
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'https://schema.org/OutOfStock',
			$entry->availability
		);
	}

	/**
	 * Empty WooCommerce prices should normalize to null.
	 */
	public function test_empty_prices_are_normalized_to_null(): void {

		$product = new WC_Product( 222 );

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertNull(
			$entry->price
		);

		$this->assertNull(
			$entry->regular_price
		);

		$this->assertNull(
			$entry->sale_price
		);
	}

	/**
	 * Variable product returns variations.
	 */
	public function test_variable_product_returns_variations(): void {

		$product = new Test_WC_Product( 200 );

		$product->set_name(
			name: 'Variable Product',
		);

		$product->set_type(
			type: 'variable',
		);

		$product->set_children(
			children: array(
				201,
			),
		);

		$variation = new Test_WC_Product_Variation( 201 );

		$variation->set_variation_attributes(
			attributes: array(
				'attribute_select-mesh-count' => '110/80 Yellow',
			),
		);

		$GLOBALS['shurloc_test_products'][201] = $variation;

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertCount(
			1,
			$entry->variations
		);
	}

	/**
	 * Product descriptions should remove HTML tags.
	 */
	public function test_product_descriptions_are_stripped_of_html(): void {

		$product = new WC_Product( 400 );

		$product->set_name(
			name: 'HTML Product',
		);

		$product->set_short_description(
			short_description: '<p>Short <strong>description</strong></p>',
		);

		$product->set_description(
			description: '<div>Full <em>description</em></div>',
		);

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'Short description',
			$entry->short_description
		);

		$this->assertSame(
			'Full description',
			$entry->description
		);
	}

	/**
	 * Mesh variation data should survive catalog conversion.
	 */
	public function test_mesh_variation_data_survives_catalog_conversion(): void {

		$product = new Test_WC_Product( 500 );

		$product->set_name(
			name: 'Mesh Product',
		);

		$product->set_type(
			type: 'variable',
		);

		$product->set_children(
			children: array(
				501,
			),
		);

		$variation = new Test_WC_Product_Variation( 501 );

		$variation->set_variation_attributes(
			attributes: array(
				'attribute_select-mesh-count' => '160/64 White',
			),
		);

		$variation->set_price(
			price: '25.00',
		);

		$GLOBALS['shurloc_test_products'][501] = $variation;

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'160/64 White',
			$entry->variations[0]->variation
		);

		$this->assertSame(
			25.0,
			$entry->variations[0]->price
		);
	}

	/**
	 * Mesh variation attribute values are preserved.
	 */
	public function test_mesh_variation_attribute_value_is_preserved(): void {

		$product = new Test_WC_Product( 300 );

		$product->set_name(
			name: 'Mesh Product',
		);

		$product->set_type(
			type: 'variable',
		);

		$product->set_children(
			children: array(
				301,
			),
		);

		$variation = new Test_WC_Product_Variation( 301 );

		$variation->set_variation_attributes(
			attributes: array(
				'attribute_select-mesh-count' => '160/64 White',
			),
		);

		$GLOBALS['shurloc_test_products'][301] = $variation;

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertSame(
			'160/64 White',
			$entry->variations[0]->variation
		);
	}

	/**
	 * Variations without attributes are ignored.
	 */
	public function test_variations_without_attributes_are_ignored(): void {

		$product = new Test_WC_Product( 400 );

		$product->set_type(
			type: 'variable',
		);

		$product->set_children(
			children: array(
				401,
			),
		);

		$variation = new WC_Product_Variation( 401 );

		$GLOBALS['shurloc_test_products'][401] = $variation;

		$entry = $this->service->get_product_entry(
			product: $product,
		);

		$this->assertCount(
			0,
			$entry->variations
		);
	}

	/**
	 * Preserves WooCommerce variation order.
	 */
	public function test_preserves_product_variation_order(): void {

		$product = new Test_WC_Product( 600 );

		$product->set_name(
			name: 'Variation Order Product',
		);

		$product->set_type(
			type: 'variable',
		);

		$product->set_children(
			children: array(
				601,
				602,
				603,
			),
		);

		$first_variation = new Test_WC_Product_Variation( 601 );

		$first_variation->set_variation_attributes(
			attributes: array(
				'attribute_select-mesh-count' => '230/40 Yellow $30.00',
			),
		);

		$second_variation = new Test_WC_Product_Variation( 602 );

		$second_variation->set_variation_attributes(
			attributes: array(
				'attribute_select-mesh-count' => '110/80 White $20.00',
			),
		);

		$third_variation = new Test_WC_Product_Variation( 603 );

		$third_variation->set_variation_attributes(
			attributes: array(
				'attribute_select-mesh-count' => '156/64 Yellow $25.00',
			),
		);

		$GLOBALS['shurloc_test_products'][601] = $first_variation;
		$GLOBALS['shurloc_test_products'][602] = $second_variation;
		$GLOBALS['shurloc_test_products'][603] = $third_variation;

		$entries = $this->service->get_product_variation_entries(
			product: $product,
		);

		$this->assertCount(
			3,
			$entries
		);

		$this->assertSame(
			'230/40 Yellow $30.00',
			$entries[0]->variation
		);

		$this->assertSame(
			'110/80 White $20.00',
			$entries[1]->variation
		);

		$this->assertSame(
			'156/64 Yellow $25.00',
			$entries[2]->variation
		);
	}
}
