<?php
/**
 * Tests for product schema integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Renderers\Product_Schema_Renderer_Double;
use Shurloc\SiteTools\Product\Services\Product_Catalog_Service_Double;
use Shurloc\SiteTools\Product\Services\Product_Schema_Service_Double;
use WC_Product;

/**
 * Tests product schema integration.
 */
final class ProductSchemaIntegrationTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_is_product'] = true;
		$GLOBALS['shurloc_test_products']   = array();

		new WC_Product( 123 );
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_is_product'] = true;
		$GLOBALS['shurloc_test_products']   = array();

		parent::tearDown();
	}

	/**
	 * Integration should generate and render product schema.
	 */
	public function test_renders_generated_product_schema(): void {

		$catalog_entry = $this->create_catalog_entry();

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => 'Test Product',
		);

		$catalog_service = new Product_Catalog_Service_Double(
			product_entry: $catalog_entry,
		);

		$schema_service = new Product_Schema_Service_Double(
			schema: $schema,
		);

		$renderer = new Product_Schema_Renderer_Double();

		$integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $schema_service,
			renderer: $renderer,
		);

		$integration->render_product_schema();

		$this->assertCount(
			1,
			$catalog_service->get_product_entry_calls()
		);

		$this->assertCount(
			1,
			$schema_service->get_calls()
		);

		$this->assertSame(
			$catalog_entry,
			$schema_service->get_calls()[0]
		);

		$this->assertCount(
			1,
			$renderer->get_calls()
		);

		$this->assertSame(
			$schema,
			$renderer->get_calls()[0]
		);
	}

	/**
	 * Integration should not render schema outside product pages.
	 */
	public function test_does_not_render_when_not_product_page(): void {

		$GLOBALS['shurloc_test_is_product'] = false;

		$catalog_service = new Product_Catalog_Service_Double();

		$schema_service = new Product_Schema_Service_Double();

		$renderer = new Product_Schema_Renderer_Double();

		$integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $schema_service,
			renderer: $renderer,
		);

		$integration->render_product_schema();

		$this->assertEmpty(
			$catalog_service->get_product_entry_calls()
		);

		$this->assertEmpty(
			$schema_service->get_calls()
		);

		$this->assertEmpty(
			$renderer->get_calls()
		);
	}

	/**
	 * Integration should not render when catalog entry is unavailable.
	 */
	public function test_does_not_render_when_catalog_entry_is_null(): void {

		$catalog_service = new Product_Catalog_Service_Double(
			return_null_product_entry: true,
		);

		$schema_service = new Product_Schema_Service_Double();

		$renderer = new Product_Schema_Renderer_Double();

		$integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $schema_service,
			renderer: $renderer,
		);

		$integration->render_product_schema();

		$this->assertCount(
			1,
			$catalog_service->get_product_entry_calls()
		);

		$this->assertEmpty(
			$schema_service->get_calls()
		);

		$this->assertEmpty(
			$renderer->get_calls()
		);
	}

	/**
	 * Integration should not render when schema generation fails.
	 */
	public function test_does_not_render_when_schema_is_null(): void {

		$catalog_entry = $this->create_catalog_entry();

		$catalog_service = new Product_Catalog_Service_Double(
			product_entry: $catalog_entry,
		);

		$schema_service = new Product_Schema_Service_Double(
			schema: null,
		);

		$renderer = new Product_Schema_Renderer_Double();

		$integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $schema_service,
			renderer: $renderer,
		);

		$integration->render_product_schema();

		$this->assertCount(
			1,
			$catalog_service->get_product_entry_calls()
		);

		$this->assertCount(
			1,
			$schema_service->get_calls()
		);

		$this->assertSame(
			$catalog_entry,
			$schema_service->get_calls()[0]
		);

		$this->assertEmpty(
			$renderer->get_calls()
		);
	}

	/**
	 * Mesh products should render aggregate offer schema.
	 */
	public function test_mesh_product_outputs_aggregate_offer_schema(): void {

		$catalog_entry = new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Test Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/test-mesh-product/',
			sku: 'TEST-MESH-123',
			image_url: 'https://example.com/image.jpg',
			short_description: 'Short product description.',
			description: 'This is the long product description.',
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
				new Catalog_Variation_Entry(
					variation: '160/64 White $25.00',
					price: 25.0,
					product_id: 123,
					product_name: 'Test Mesh Product',
					edit_url: '',
				),
			),
		);

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => 'Test Mesh Product',
			'offers'   => array(
				'@type'      => 'AggregateOffer',
				'lowPrice'   => '20.00',
				'highPrice'  => '25.00',
				'offerCount' => 2,
			),
		);

		$catalog_service = new Product_Catalog_Service_Double(
			product_entry: $catalog_entry,
		);

		$schema_service = new Product_Schema_Service_Double(
			schema: $schema,
		);

		$renderer = new Product_Schema_Renderer_Double();

		$integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $schema_service,
			renderer: $renderer,
		);

		$integration->render_product_schema();

		$this->assertCount(
			1,
			$catalog_service->get_product_entry_calls()
		);

		$this->assertCount(
			1,
			$schema_service->get_calls()
		);

		$this->assertSame(
			$catalog_entry,
			$schema_service->get_calls()[0]
		);

		$this->assertCount(
			1,
			$renderer->get_calls()
		);

		$this->assertSame(
			$schema,
			$renderer->get_calls()[0]
		);
	}

	/**
	 * Non-mesh products should render standard offer schema.
	 */
	public function test_non_mesh_product_outputs_standard_offer_schema(): void {

		$catalog_entry = new Catalog_Product_Entry(
			product_id: 456,
			product_name: 'Non Mesh Product',
			edit_url: '',
			product_url: 'https://example.com/product/non-mesh-product/',
			sku: 'NON-MESH-456',
			image_url: 'https://example.com/non-mesh-image.jpg',
			short_description: 'Short description.',
			description: 'Long product description.',
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

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => 'Non Mesh Product',
			'offers'   => array(
				'@type'         => 'Offer',
				'price'         => '15.00',
				'priceCurrency' => 'USD',
			),
		);

		$catalog_service = new Product_Catalog_Service_Double(
			product_entry: $catalog_entry,
		);

		$schema_service = new Product_Schema_Service_Double(
			schema: $schema,
		);

		$renderer = new Product_Schema_Renderer_Double();

		$integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $schema_service,
			renderer: $renderer,
		);

		$integration->render_product_schema();

		$this->assertCount(
			1,
			$catalog_service->get_product_entry_calls()
		);

		$this->assertCount(
			1,
			$schema_service->get_calls()
		);

		$this->assertSame(
			$catalog_entry,
			$schema_service->get_calls()[0]
		);

		$this->assertCount(
			1,
			$renderer->get_calls()
		);

		$this->assertSame(
			$schema,
			$renderer->get_calls()[0]
		);
	}

	/**
	 * Create catalog product entry fixture.
	 *
	 * @return Catalog_Product_Entry
	 */
	private function create_catalog_entry(): Catalog_Product_Entry {

		return new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Test Product',
			edit_url: '',
			product_url: 'https://example.com/product/test-product/',
			sku: 'TEST-123',
			image_url: null,
			short_description: 'Short product description.',
			description: 'This is the product description.',
			category: null,
			price: null,
			regular_price: null,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: 'Shur-loc®',
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: array(),
		);
	}
}
