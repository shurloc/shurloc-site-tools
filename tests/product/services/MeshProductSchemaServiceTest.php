<?php
/**
 * Tests for mesh product schema service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;

/**
 * Tests mesh product schema enrichment.
 */
final class MeshProductSchemaServiceTest extends TestCase {

	/**
	 * Mesh products should return mesh analysis results.
	 */
	public function test_mesh_products_return_mesh_result(): void {

		$service = $this->create_service();

		$result = $service->analyze(
			product: $this->create_mesh_product_entry(),
		);

		$this->assertNotNull(
			$result
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Mesh_Product_Result::class,
			$result
		);

		$this->assertSame(
			1,
			$result->mesh_variation_count()
		);
	}

	/**
	 * Non-mesh products should return null.
	 */
	public function test_non_mesh_products_return_null(): void {

		$service = $this->create_service();

		$result = $service->analyze(
			product: $this->create_non_mesh_product_entry(),
		);

		$this->assertNull(
			$result
		);
	}

	/**
	 * Mesh analysis should preserve variation data.
	 */
	public function test_mesh_analysis_preserves_variation_data(): void {

		$service = $this->create_service();

		$result = $service->analyze(
			product: $this->create_mesh_product_entry(
				variation: '160/64 White $25.00',
				price: 25.0,
			),
		);

		$this->assertNotNull(
			$result
		);

		$this->assertSame(
			1,
			$result->mesh_variation_count()
		);

		$variation = $result->mesh_variations[0];

		$this->assertSame(
			'160/64 White $25.00',
			$variation['entry']->variation
		);

		$this->assertSame(
			25.0,
			$variation['entry']->price
		);

		$this->assertSame(
			160,
			$variation['spec']->get_mesh_count()
		);

		$this->assertSame(
			64,
			$variation['spec']->get_thread_diameter()
		);

		$this->assertSame(
			'White',
			$variation['spec']->get_color()
		);
	}

	/**
	 * Multiple mesh variations should all be preserved.
	 */
	public function test_multiple_mesh_variations_are_preserved(): void {

		$service = $this->create_service();

		$product = new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Multiple Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/multiple-mesh-product/',
			sku: 'MULTI-MESH-123',
			image_url: 'https://example.com/image.jpg',
			short_description: '',
			description: '',
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
					product_name: 'Multiple Mesh Product',
					edit_url: '',
				),
				new Catalog_Variation_Entry(
					variation: '160/64 White $25.00',
					price: 25.0,
					product_id: 123,
					product_name: 'Multiple Mesh Product',
					edit_url: '',
				),
			),
		);

		$result = $service->analyze(
			product: $product,
		);

		$this->assertNotNull(
			$result
		);

		$this->assertSame(
			2,
			$result->mesh_variation_count()
		);
	}

	/**
	 * Products without variations should return null.
	 */
	public function test_products_without_variations_return_null(): void {

		$service = $this->create_service();

		$product = new Catalog_Product_Entry(
			product_id: 999,
			product_name: 'Empty Product',
			edit_url: '',
			product_url: 'https://example.com/product/empty-product/',
			sku: 'EMPTY-999',
			image_url: null,
			short_description: '',
			description: '',
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

		$result = $service->analyze(
			product: $product,
		);

		$this->assertNull(
			$result
		);
	}


	/**
	 * Mixed variations should preserve only recognized mesh variations.
	 */
	public function test_mixed_variations_preserve_only_mesh_variations(): void {

		$service = $this->create_service();

		$product = new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Mixed Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/mixed-mesh-product/',
			sku: 'MIXED-123',
			image_url: null,
			short_description: '',
			description: '',
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
					product_name: 'Mixed Mesh Product',
					edit_url: '',
				),
				new Catalog_Variation_Entry(
					variation: 'Thin Thread',
					price: null,
					product_id: 123,
					product_name: 'Mixed Mesh Product',
					edit_url: '',
				),
			),
		);

		$result = $service->analyze(
			product: $product,
		);

		$this->assertNotNull(
			$result
		);

		$this->assertSame(
			1,
			$result->mesh_variation_count()
		);
	}

	/**
	 * Invalid mesh specifications should still return mesh results.
	 */
	public function test_invalid_mesh_specifications_are_preserved(): void {

		$service = $this->create_service();

		$result = $service->analyze(
			product: $this->create_mesh_product_entry(
				variation: '350/30 Orange $35.00',
				price: 35.0,
			),
		);

		$this->assertNotNull(
			$result
		);

		$this->assertSame(
			1,
			$result->mesh_variation_count()
		);

		$this->assertFalse(
			$result->mesh_variations[0]['spec']->is_valid()
		);
	}

	/**
	 * Unrecognized variations should return null.
	 */
	public function test_unrecognized_variations_return_null(): void {

		$service = $this->create_service();

		$product = new Catalog_Product_Entry(
			product_id: 789,
			product_name: 'Invalid Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/invalid-mesh-product/',
			sku: 'INVALID-MESH-789',
			image_url: null,
			short_description: '',
			description: '',
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
					variation: 'Standard Product Option',
					price: 10.0,
					product_id: 789,
					product_name: 'Invalid Mesh Product',
					edit_url: '',
				),
			),
		);

		$result = $service->analyze(
			product: $product,
		);

		$this->assertNull(
			$result
		);
	}

	/**
	 * Create mesh schema service.
	 *
	 * @return Mesh_Product_Schema_Service
	 */
	private function create_service(): Mesh_Product_Schema_Service {

		return new Mesh_Product_Schema_Service(
			analyzer: new Mesh_Product_Analyzer(
				parser: new Mesh_Parser(),
			),
		);
	}

	/**
	 * Create mesh product fixture.
	 *
	 * @param string $variation Variation text.
	 * @param float  $price Variation price.
	 * @return Catalog_Product_Entry
	 */
	private function create_mesh_product_entry(
		string $variation = '110/80 Yellow $20.00',
		float $price = 20.0,
	): Catalog_Product_Entry {

		return new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Test Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/test-mesh-product/',
			sku: 'TEST-MESH-123',
			image_url: 'https://example.com/image.jpg',
			short_description: '',
			description: '',
			category: null,
			price: 20.0,
			regular_price: 20.0,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: 'Shur-loc®',
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: array(
				new Catalog_Variation_Entry(
					variation: $variation,
					price: $price,
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
			short_description: '',
			description: '',
			category: null,
			price: 15.0,
			regular_price: 15.0,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: null,
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: array(
				new Catalog_Variation_Entry(
					variation: 'Thin Thread',
					price: null,
					product_id: 456,
					product_name: 'Non Mesh Product',
					edit_url: '',
				),
			),
		);
	}
}
