<?php
/**
 * Tests for product schema service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer;
use Shurloc\SiteTools\Product\Generators\Product_Schema_Generator;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;

/**
 * Tests product schema generation.
 */
final class ProductSchemaServiceTest extends TestCase {

	/**
	 * Mesh products should generate aggregate offers.
	 */
	public function test_mesh_products_generate_aggregate_offer(): void {

		$schema = $this->create_service()->generate(
			product: $this->create_mesh_product_entry(),
		);

		$this->assertSame(
			'Product',
			$schema['@type']
		);

		$this->assertSame(
			'Test Mesh Product',
			$schema['name']
		);

		$this->assertSame(
			'AggregateOffer',
			$schema['offers']['@type']
		);

		$this->assertSame(
			'20.00',
			$schema['offers']['lowPrice']
		);

		$this->assertSame(
			'20.00',
			$schema['offers']['highPrice']
		);

		$this->assertSame(
			1,
			$schema['offers']['offerCount']
		);
	}


	/**
	 * Non-mesh products should generate standard offers.
	 */
	public function test_non_mesh_products_generate_standard_offer(): void {

		$schema = $this->create_service()->generate(
			product: $this->create_non_mesh_product_entry(),
		);

		$this->assertSame(
			'Product',
			$schema['@type']
		);

		$this->assertSame(
			'Non Mesh Product',
			$schema['name']
		);

		$this->assertArrayHasKey(
			'offers',
			$schema
		);

		$this->assertSame(
			'Offer',
			$schema['offers']['@type']
		);

		$this->assertSame(
			'15.00',
			$schema['offers']['price']
		);

		$this->assertSame(
			'USD',
			$schema['offers']['priceCurrency']
		);
	}


	/**
	 * Products without pricing should not generate offers.
	 */
	public function test_products_without_price_do_not_generate_offers(): void {

		$product = new Catalog_Product_Entry(
			product_id: 789,
			product_name: 'No Price Product',
			edit_url: '',
			product_url: 'https://example.com/product/no-price-product/',
			sku: 'NO-PRICE-789',
			image_url: null,
			short_description: 'Short description.',
			description: 'Product description.',
			category: null,
			price: null,
			regular_price: null,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: null,
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: array(),
		);

		$schema = $this->create_service()->generate(
			product: $product,
		);

		$this->assertSame(
			'Product',
			$schema['@type']
		);

		$this->assertArrayNotHasKey(
			'offers',
			$schema
		);
	}


	/**
	 * Product schema should preserve basic product information.
	 */
	public function test_product_schema_preserves_product_information(): void {

		$schema = $this->create_service()->generate(
			product: $this->create_mesh_product_entry(),
		);

		$this->assertSame(
			'https://example.com/product/test-mesh-product/#product',
			$schema['@id']
		);

		$this->assertSame(
			'https://example.com/product/test-mesh-product/',
			$schema['url']
		);

		$this->assertSame(
			'TEST-MESH-123',
			$schema['sku']
		);

		$this->assertSame(
			'https://example.com/image.jpg',
			$schema['image']
		);

		$this->assertSame(
			'https://example.com/product/test-mesh-product/',
			$schema['mainEntityOfPage']['@id']
		);
	}


	/**
	 * Service should delegate mesh analysis to mesh schema service.
	 */
	public function test_delegates_mesh_analysis_to_mesh_schema_service(): void {

		$product = $this->create_mesh_product_entry();

		$mesh_result = new Mesh_Product_Result();

		$mesh_schema_service = new Mesh_Product_Schema_Service_Double(
			result: $mesh_result,
		);

		$schema = $this->create_service(
			mesh_schema_service: $mesh_schema_service,
		)->generate(
			product: $product,
		);

		$calls = $mesh_schema_service->get_calls();

		$this->assertCount(
			1,
			$calls
		);

		$this->assertSame(
			$product,
			$calls[0]
		);

		$this->assertSame(
			'Product',
			$schema['@type']
		);
	}


	/**
	 * Service should enrich schema when mesh analysis returns a result.
	 */
	public function test_uses_mesh_analysis_result_when_available(): void {

		$product = $this->create_mesh_product_entry();

		$mesh_result = new Mesh_Product_Result();

		$mesh_schema_service = new Mesh_Product_Schema_Service_Double(
			result: $mesh_result,
		);

		$schema = $this->create_service(
			mesh_schema_service: $mesh_schema_service,
		)->generate(
			product: $product,
		);

		$calls = $mesh_schema_service->get_calls();

		$this->assertCount(
			1,
			$calls
		);

		$this->assertSame(
			$product,
			$calls[0]
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertIsArray(
			$schema
		);

		$this->assertSame(
			'Product',
			$schema['@type']
		);
	}


	/**
	 * Service should generate schema when mesh analysis returns null.
	 */
	public function test_generates_schema_when_mesh_analysis_returns_null(): void {

		$product = $this->create_mesh_product_entry();

		$mesh_schema_service = new Mesh_Product_Schema_Service_Double(
			result: null,
		);

		$schema = $this->create_service(
			mesh_schema_service: $mesh_schema_service,
		)->generate(
			product: $product,
		);

		$calls = $mesh_schema_service->get_calls();

		$this->assertCount(
			1,
			$calls
		);

		$this->assertSame(
			$product,
			$calls[0]
		);

		$this->assertSame(
			'Product',
			$schema['@type']
		);

		$this->assertSame(
			'Test Mesh Product',
			$schema['name']
		);

		$this->assertArrayNotHasKey(
			'offers',
			$schema
		);
	}


	/**
	 * Create product schema service.
	 *
	 * @param Mesh_Product_Schema_Service_Interface|null $mesh_schema_service Mesh schema service.
	 * @return Product_Schema_Service
	 */
	private function create_service(
		?Mesh_Product_Schema_Service_Interface $mesh_schema_service = null
	): Product_Schema_Service {

		if ( null === $mesh_schema_service ) {
			$mesh_schema_service = new Mesh_Product_Schema_Service(
				analyzer: new Mesh_Product_Analyzer(
					parser: new Mesh_Parser(),
				),
			);
		}

		return new Product_Schema_Service(
			generator: new Product_Schema_Generator(),
			mesh_schema_service: $mesh_schema_service,
		);
	}


	/**
	 * Create mesh product fixture.
	 *
	 * @return Catalog_Product_Entry
	 */
	private function create_mesh_product_entry(): Catalog_Product_Entry {

		return new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Test Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/test-mesh-product/',
			sku: 'TEST-MESH-123',
			image_url: 'https://example.com/image.jpg',
			short_description: 'Short product description.',
			description: 'This is the product description.',
			category: null,
			price: null,
			regular_price: null,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: null,
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: array(
				new Catalog_Variation_Entry(
					variation: '110/80 Yellow $20.00',
					price: 20.0,
					product_id: 123,
					product_name: 'Test Mesh Product',
					edit_url: '',
				),
			),
		);
	}


	/**
	 * Create non-mesh product fixture.
	 *
	 * @return Catalog_Product_Entry
	 */
	private function create_non_mesh_product_entry(): Catalog_Product_Entry {

		return new Catalog_Product_Entry(
			product_id: 456,
			product_name: 'Non Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/non-mesh-product/',
			sku: 'NON-MESH-456',
			image_url: 'https://example.com/non-mesh-image.jpg',
			short_description: 'Short description.',
			description: 'Product description.',
			category: null,
			price: 15.0,
			regular_price: 15.0,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: null,
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: array(),
		);
	}
}
