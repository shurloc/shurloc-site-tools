<?php
/**
 * Tests for mesh product data service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer_Double;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;
use WC_Product;

/**
 * Tests mesh product data service behavior.
 */
final class MeshProductDataServiceTest extends TestCase {

	/**
	 * Mesh product data service.
	 *
	 * @var Mesh_Product_Data_Service
	 */
	private Mesh_Product_Data_Service $service;

	/**
	 * Analysis result used by the analyzer double.
	 *
	 * @var Mesh_Product_Result
	 */
	private Mesh_Product_Result $result;

	/**
	 * Analyzer double.
	 *
	 * @var Mesh_Product_Analyzer_Double
	 */
	private Mesh_Product_Analyzer_Double $analyzer;


	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->result = new Mesh_Product_Result();

		$entry = new Catalog_Variation_Entry(
			variation: '110/80 White ($12.99)',
			price: 12.99,
			product_id: 1,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);

		$catalog_service = new Product_Catalog_Service_Double(
			variation_entries: array( $entry ),
		);

		$this->analyzer = new Mesh_Product_Analyzer_Double(
			result: $this->result,
		);

		$this->service = new Mesh_Product_Data_Service(
			catalog_service: $catalog_service,
			mesh_analyzer: $this->analyzer,
		);
	}


	/**
	 * Analyze product returns mesh product result.
	 */
	public function test_analyze_product_returns_analysis_result(): void {

		$product = new WC_Product(
			1,
		);

		$result = $this->service->analyze_product(
			product: $product,
		);

		$this->assertSame(
			$this->result,
			$result
		);
	}


	/**
	 * Mesh product detection returns true when analyzer identifies mesh.
	 */
	public function test_is_mesh_product_returns_true_for_mesh_product(): void {

		$this->result->add_mesh_variation(
			entry: new Catalog_Variation_Entry(
				variation: '110/80 White ($12.99)',
				price: 12.99,
				product_id: 1,
				product_name: 'Test Mesh Product',
				edit_url: '',
			),
			spec: new Mesh_Specification(
				raw: '110/80 White ($12.99)',
				mesh_count: 110,
				thread_diameter: 80,
				modifier: null,
				color: 'White',
				pack_size: null,
				price_text: '$12.99',
				recognized: true,
				unknown_tokens: array(),
			),
		);

		$product = new WC_Product(
			1,
		);

		$this->assertTrue(
			$this->service->is_mesh_product(
				product: $product
			)
		);
	}


	/**
	 * Mesh product detection returns false when analyzer finds no mesh.
	 */
	public function test_is_mesh_product_returns_false_for_non_mesh_product(): void {

		$product = new WC_Product(
			1,
		);

		$this->assertFalse(
			$this->service->is_mesh_product(
				product: $product
			)
		);
	}


	/**
	 * Catalog variations are passed to the analyzer.
	 */
	public function test_analyze_product_passes_catalog_variations_to_analyzer(): void {

		$product = new WC_Product(
			1,
		);

		$this->service->analyze_product(
			product: $product,
		);

		$entries = $this->analyzer->get_entries();

		$this->assertCount(
			1,
			$entries
		);

		$this->assertSame(
			'110/80 White ($12.99)',
			$entries[0]->variation
		);

		$this->assertSame(
			12.99,
			$entries[0]->price
		);
	}
}
