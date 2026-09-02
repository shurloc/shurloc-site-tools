<?php
/**
 * Tests for Product_Schema_Generator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Generators;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Tests product schema generation.
 */
final class ProductSchemaGeneratorTest extends TestCase {

	/**
	 * Schema generator.
	 *
	 * @var Product_Schema_Generator
	 */
	private Product_Schema_Generator $generator;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->generator = new Product_Schema_Generator();
	}

	/**
	 * A mesh product should generate an aggregate offer with multiple variations.
	 */
	public function test_generates_product_schema_with_aggregate_offer(): void {

		$schema = $this->generate_schema(
			result: $this->create_mesh_result(),
		);

		$this->assertSame(
			'https://schema.org',
			$schema['@context']
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
			2,
			$schema['offers']['offerCount']
		);
	}

	/**
	 * Product schema should include brand information.
	 */
	public function test_product_schema_includes_brand(): void {

		$schema = $this->generate_schema(
			result: $this->create_mesh_result(),
		);

		$this->assertSame(
			'Brand',
			$schema['brand']['@type']
		);

		$this->assertSame(
			'Test Brand',
			$schema['brand']['name']
		);
	}

	/**
	 * Product schema should include manufacturer information.
	 */
	public function test_product_schema_includes_manufacturer(): void {

		$schema = $this->generate_schema(
			result: $this->create_mesh_result(),
		);

		$this->assertArrayHasKey(
			'manufacturer',
			$schema
		);

		$this->assertSame(
			array(
				'@id' => 'https://shurloc.com/#organization',
			),
			$schema['manufacturer']
		);
	}

	/**
	 * Product schema should include aggregate rating when provided.
	 */
	public function test_product_schema_includes_aggregate_rating_when_present(): void {

		$product = $this->create_product(
			product_name: 'Rated Product',
			product_url: 'https://example.com/product/rated-product/',
			sku: 'RATED-123',
			description: 'Long product description.',
			aggregate_rating: array(
				'@type'       => 'AggregateRating',
				'ratingValue' => '4.8',
				'reviewCount' => 12,
			),
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertArrayHasKey(
			'aggregateRating',
			$schema
		);

		$this->assertSame(
			'AggregateRating',
			$schema['aggregateRating']['@type']
		);

		$this->assertSame(
			'4.8',
			$schema['aggregateRating']['ratingValue']
		);

		$this->assertSame(
			12,
			$schema['aggregateRating']['reviewCount']
		);
	}

	/**
	 * Product schema should include reviews when provided.
	 */
	public function test_product_schema_includes_reviews_when_present(): void {

		$product = $this->create_product(
			product_name: 'Reviewed Product',
			product_url: 'https://example.com/product/reviewed-product/',
			sku: 'REVIEW-123',
			reviews: array(
				array(
					'@type'        => 'Review',
					'author'       => array(
						'@type' => 'Person',
						'name'  => 'John Smith',
					),
					'reviewRating' => array(
						'@type'       => 'Rating',
						'ratingValue' => '5',
					),
					'reviewBody'   => 'Excellent product quality.',
				),
			),
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertArrayHasKey(
			'review',
			$schema
		);

		$this->assertCount(
			1,
			$schema['review']
		);

		$this->assertSame(
			'Review',
			$schema['review'][0]['@type']
		);

		$this->assertSame(
			'John Smith',
			$schema['review'][0]['author']['name']
		);

		$this->assertSame(
			'5',
			$schema['review'][0]['reviewRating']['ratingValue']
		);
	}

	/**
	 * Product schema should not include rating or reviews when none exist.
	 */
	public function test_product_schema_excludes_rating_and_reviews_when_missing(): void {

		$product = $this->create_product(
			product_name: 'Unreviewed Product',
			product_url: 'https://example.com/product/unreviewed-product/',
			sku: 'UNREVIEWED-123',
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertArrayNotHasKey(
			'aggregateRating',
			$schema
		);

		$this->assertArrayNotHasKey(
			'review',
			$schema
		);
	}

	/**
	 * Aggregate offers should contain pricing range.
	 */
	public function test_aggregate_offer_contains_price_range(): void {

		$schema = $this->generate_schema(
			result: $this->create_mesh_result(),
		);

		$offers = $schema['offers'];

		$this->assertSame(
			'AggregateOffer',
			$offers['@type']
		);

		$this->assertSame(
			'20.00',
			$offers['lowPrice']
		);

		$this->assertSame(
			'25.00',
			$offers['highPrice']
		);

		$this->assertSame(
			2,
			$offers['offerCount']
		);

		$this->assertSame(
			'USD',
			$offers['priceCurrency']
		);
	}

	/**
	 * An empty mesh result should not generate offers when product has no price.
	 */
	public function test_empty_result_does_not_generate_offers(): void {

		$product = $this->create_product(
			product_name: 'Empty Product',
			product_url: 'https://example.com/product/empty-product/',
			sku: 'EMPTY-123',
			price: null,
			regular_price: null,
			brand: null,
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertArrayNotHasKey(
			'offers',
			$schema
		);
	}

	/**
	 * Non-mesh products should generate a simple offer.
	 */
	public function test_non_mesh_product_generates_offer_with_product_metadata(): void {

		$product = $this->create_product(
			product_id: 456,
			product_name: 'Non Mesh Product',
			product_url: 'https://example.com/product/non-mesh-product/',
			sku: 'NON-MESH-456',
			image_url: 'https://example.com/non-mesh-image.jpg',
			price: 50.0,
			regular_price: 50.0,
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'Offer',
			$schema['offers']['@type']
		);

		$this->assertSame(
			'50.00',
			$schema['offers']['price']
		);

		$this->assertSame(
			'https://schema.org/InStock',
			$schema['offers']['availability']
		);

		$this->assertSame(
			'https://example.com/product/non-mesh-product/',
			$schema['offers']['url']
		);
	}

	/**
	 * Products without variations should still generate an offer.
	 */
	public function test_product_without_variations_uses_current_price(): void {

		$product = $this->create_product(
			product_id: 789,
			product_name: 'Simple Product',
			product_url: 'https://example.com/product/simple-product/',
			sku: 'SIMPLE-789',
			price: 15.0,
			regular_price: 15.0,
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'15.00',
			$schema['offers']['price']
		);
	}

	/**
	 * Product schema should not include empty image values.
	 */
	public function test_empty_image_is_not_added_to_schema(): void {

		$product = $this->create_product(
			product_name: 'No Image Product',
			product_url: 'https://example.com/product/no-image/',
			sku: 'NO-IMAGE-123',
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertArrayNotHasKey(
			'image',
			$schema
		);
	}

	/**
	 * Sale price should be used for simple product offers.
	 */
	public function test_sale_product_uses_current_sale_price(): void {

		$product = $this->create_product(
			product_id: 100,
			product_name: 'Sale Product',
			product_url: 'https://example.com/product/sale-product/',
			sku: 'SALE-100',
			price: 10.0,
			regular_price: 20.0,
			sale_price: 10.0,
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'Offer',
			$schema['offers']['@type']
		);

		$this->assertSame(
			'10.00',
			$schema['offers']['price']
		);
	}

	/**
	 * Product availability should be included in generated offers.
	 */
	public function test_offer_preserves_product_availability(): void {

		$product = $this->create_product(
			product_id: 101,
			product_name: 'Availability Product',
			product_url: 'https://example.com/product/availability-product/',
			sku: 'AVAILABLE-101',
			availability: 'https://schema.org/OutOfStock',
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'https://schema.org/OutOfStock',
			$schema['offers']['availability']
		);
	}

	/**
	 * Products with a current price should use that price for offers.
	 */
	public function test_current_price_takes_precedence_over_regular_price(): void {

		$product = $this->create_product(
			product_id: 789,
			product_name: 'Current Price Product',
			product_url: 'https://example.com/product/current-price/',
			sku: 'CURRENT-789',
			price: 30.0,
			regular_price: 40.0,
			sale_price: 30.0,
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'30.00',
			$schema['offers']['price']
		);
	}

	/**
	 * Simple product schema includes SKU and description.
	 */
	public function test_simple_product_schema_includes_sku_and_description(): void {

		$product = $this->create_product(
			edit_url: 'https://example.com/edit',
			short_description: 'This is a short description.',
			description: 'This is a test product description.',
			category: 'Screen Printing',
			regular_price: 30.0,
			brand: 'Shur-loc®',
			manufacturer: 'Shur-loc®',
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'TEST-123',
			$schema['sku']
		);

		$this->assertSame(
			'This is a test product description.',
			$schema['description']
		);
	}

	/**
	 * Empty SKU and description are omitted from schema.
	 */
	public function test_empty_sku_and_description_are_not_added_to_schema(): void {

		$product = $this->create_product(
			product_id: 456,
			product_name: 'Empty Product',
			edit_url: 'https://example.com/edit',
			product_url: 'https://example.com/product/empty-product/',
			sku: '',
			short_description: '',
			description: '',
			category: 'Screen Printing',
			regular_price: null,
			brand: 'Shur-loc®',
			manufacturer: 'Shur-loc®',
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertArrayNotHasKey(
			'sku',
			$schema
		);

		$this->assertArrayNotHasKey(
			'description',
			$schema
		);
	}

	/**
	 * Variable product schema includes description.
	 */
	public function test_variable_product_schema_includes_description(): void {

		$product = $this->create_product(
			product_name: 'Variable Mesh Product',
			edit_url: 'https://shurloc.test/wp-admin/post.php?post=123',
			product_url: 'https://shurloc.test/product/variable-mesh-product/',
			sku: 'VAR-123',
			short_description: 'Short description.',
			description: 'This is a variable product description.',
			category: 'Screen Printing',
			regular_price: 30.0,
			brand: 'Shur-loc®',
			manufacturer: 'Shur-loc®',
			variations: array(
				new Catalog_Variation_Entry(
					variation: '110/80 Yellow',
					price: 25.0,
					product_id: 123,
					product_name: 'Variable Mesh Product',
					edit_url: 'https://shurloc.test/wp-admin/post.php?post=123',
				),
			),
		);

		$schema = $this->generator->generate(
			product: $product,
			result: new Mesh_Product_Result(),
		);

		$this->assertSame(
			'This is a variable product description.',
			$schema['description']
		);
	}

	/**
	 * Create mesh result fixture.
	 *
	 * @return Mesh_Product_Result
	 */
	private function create_mesh_result(): Mesh_Product_Result {

		$result = new Mesh_Product_Result();

		$result->add_mesh_variation(
			$this->create_variation(
				variation: '110/80 Yellow $20.00',
				price: 20.0,
			),
			$this->create_spec(
				mesh_count: 110,
				thread_diameter: 80,
				color: 'Yellow',
			),
		);

		$result->add_mesh_variation(
			$this->create_variation(
				variation: '160/64 White $25.00',
				price: 25.0,
			),
			$this->create_spec(
				mesh_count: 160,
				thread_diameter: 64,
				color: 'White',
			),
		);

		return $result;
	}

	/**
	 * Generate schema.
	 *
	 * @param Mesh_Product_Result $result Mesh result.
	 * @return array<string,mixed>
	 */
	private function generate_schema(
		Mesh_Product_Result $result,
	): array {

		return $this->generator->generate(
			product: $this->create_product_entry(),
			result: $result,
		);
	}

	/**
	 * Create product fixture.
	 *
	 * @return Catalog_Product_Entry
	 */
	private function create_product_entry(): Catalog_Product_Entry {

		return $this->create_product(
			product_name: 'Test Mesh Product',
			product_url: 'https://example.com/product/test-mesh-product/',
			sku: 'TEST-MESH-123',
			image_url: 'https://example.com/image.jpg',
			price: null,
			regular_price: null,
			aggregate_rating: array(
				'@type'       => 'AggregateRating',
				'ratingValue' => '5',
				'reviewCount' => '12',
				'bestRating'  => '5',
				'worstRating' => '1',
			),
			reviews: array(
				array(
					'@type'        => 'Review',
					'reviewRating' => array(
						'@type'       => 'Rating',
						'ratingValue' => '5',
						'bestRating'  => '5',
					),
					'author'       => array(
						'@type' => 'Person',
						'name'  => 'Test Customer',
					),
					'reviewBody'   => 'Excellent product quality.',
				),
			),
		);
	}

	/**
	 * Create variation fixture.
	 *
	 * @param string $variation Variation text.
	 * @param float  $price Variation price.
	 * @return Catalog_Variation_Entry
	 */
	private function create_variation(
		string $variation,
		float $price,
	): Catalog_Variation_Entry {

		return new Catalog_Variation_Entry(
			variation: $variation,
			price: $price,
			product_id: 123,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);
	}

	/**
	 * Create mesh specification fixture.
	 *
	 * @param int         $mesh_count       Mesh count.
	 * @param int         $thread_diameter  Thread diameter.
	 * @param string      $color            Color.
	 * @param string|null $modifier         Optional modifier.
	 * @param string|null $pack_size        Optional pack size.
	 * @param string      $price_text       Price text.
	 * @return Mesh_Specification
	 */
	private function create_spec(
		int $mesh_count,
		int $thread_diameter,
		string $color,
		?string $modifier = null,
		?string $pack_size = null,
		string $price_text = '$20.00',
	): Mesh_Specification {

		return new Mesh_Specification(
			raw: $mesh_count . '/' . $thread_diameter . ' ' . $color . ' ' . $price_text,
			mesh_count: $mesh_count,
			thread_diameter: $thread_diameter,
			modifier: $modifier,
			color: $color,
			pack_size: $pack_size,
			price_text: $price_text,
			recognized: true,
			unknown_tokens: array(),
		);
	}

	/**
	 * Create a product fixture.
	 *
	 * @param int                            $product_id Product ID.
	 * @param string                         $product_name Product name.
	 * @param string                         $edit_url Product edit URL.
	 * @param string                         $product_url Product URL.
	 * @param string                         $sku Product SKU.
	 * @param string|null                    $image_url Product image URL.
	 * @param string                         $short_description Short description.
	 * @param string                         $description Description.
	 * @param string|null                    $category Product category.
	 * @param float|null                     $price Current price.
	 * @param float|null                     $regular_price Regular price.
	 * @param float|null                     $sale_price Sale price.
	 * @param string                         $availability Availability.
	 * @param string|null                    $brand Brand.
	 * @param string                         $manufacturer Manufacturer.
	 * @param array<string,mixed>|null       $aggregate_rating Aggregate rating.
	 * @param array<int,array<string,mixed>> $reviews Reviews.
	 * @param Catalog_Variation_Entry[]      $variations Variations.
	 * @return Catalog_Product_Entry
	 */
	private function create_product(
		int $product_id = 123,
		string $product_name = 'Test Product',
		string $edit_url = '',
		string $product_url = 'https://example.com/product/test-product/',
		string $sku = 'TEST-123',
		?string $image_url = null,
		string $short_description = 'Short product description.',
		string $description = 'This is the long product description.',
		?string $category = null,
		?float $price = 25.0,
		?float $regular_price = 25.0,
		?float $sale_price = null,
		string $availability = 'https://schema.org/InStock',
		?string $brand = 'Test Brand',
		string $manufacturer = 'Shur-loc',
		?array $aggregate_rating = null,
		array $reviews = array(),
		array $variations = array(),
	): Catalog_Product_Entry {

		return new Catalog_Product_Entry(
			product_id: $product_id,
			product_name: $product_name,
			edit_url: $edit_url,
			product_url: $product_url,
			sku: $sku,
			image_url: $image_url,
			short_description: $short_description,
			description: $description,
			category: $category,
			price: $price,
			regular_price: $regular_price,
			sale_price: $sale_price,
			availability: $availability,
			brand: $brand,
			manufacturer: $manufacturer,
			aggregate_rating: $aggregate_rating,
			reviews: $reviews,
			variations: $variations,
		);
	}
}
