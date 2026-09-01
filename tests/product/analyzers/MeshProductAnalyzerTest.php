<?php
/**
 * Tests for Mesh_Product_Analyzer.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Analyzers;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;

/**
 * Tests mesh product analysis.
 */
final class MeshProductAnalyzerTest extends TestCase {

	/**
	 * Mesh product analyzer.
	 *
	 * @var Mesh_Product_Analyzer
	 */
	private Mesh_Product_Analyzer $analyzer;

	/**
	 * Set up tests.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->analyzer = new Mesh_Product_Analyzer(
			parser: new Mesh_Parser(),
		);
	}

	/**
	 * A product with recognized mesh variations should be identified as mesh.
	 */
	public function test_recognized_mesh_variations_make_product_a_mesh_product(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: '110/80 Yellow $20.00',
				price: 20.00,
			),
			$this->create_variation_entry(
				variation: '160/64 White $25.00',
				price: 25.00,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$this->assertTrue(
			$result->is_mesh_product()
		);

		$this->assertCount(
			2,
			$result->mesh_variations
		);

		$this->assertCount(
			0,
			$result->ignored_variations
		);

		$this->assertCount(
			0,
			$result->unrecognized_variations
		);
	}


	/**
	 * Zero-price unrecognized variations should be ignored.
	 */
	public function test_zero_price_unrecognized_variations_are_ignored(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: 'Thin Thread',
				price: 0.0,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$this->assertFalse(
			$result->is_mesh_product()
		);

		$this->assertCount(
			1,
			$result->ignored_variations
		);

		$this->assertCount(
			0,
			$result->unrecognized_variations
		);
	}


	/**
	 * Null-price unrecognized variations should be ignored.
	 */
	public function test_null_price_unrecognized_variations_are_ignored(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: 'Thin Thread',
				price: null,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$this->assertCount(
			1,
			$result->ignored_variations
		);
	}


	/**
	 * Paid unrecognized variations should be reported.
	 */
	public function test_paid_unrecognized_variations_are_reported(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: 'Premium Orange',
				price: 35.00,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$this->assertFalse(
			$result->is_mesh_product()
		);

		$this->assertCount(
			1,
			$result->unrecognized_variations
		);
	}


	/**
	 * Recognized variations should retain parsed specification data.
	 */
	public function test_mesh_variations_include_parsed_specifications(): void {

		$entry = $this->create_variation_entry(
			variation: '110/80 Yellow $20.00',
			price: 20.00,
		);

		$result = $this->analyzer->analyze(
			array( $entry ),
		);

		$spec = $result->mesh_variations[0]['spec'];

		$this->assertSame(
			$entry,
			$result->mesh_variations[0]['entry']
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Mesh_Specification::class,
			$spec
		);

		$this->assertSame(
			110,
			$spec->get_mesh_count()
		);

		$this->assertSame(
			80,
			$spec->get_thread_diameter()
		);

		$this->assertSame(
			'Yellow',
			$spec->get_color()
		);

		$this->assertSame(
			'$20.00',
			$spec->get_price_text()
		);
	}


	/**
	 * Recognized but invalid mesh specifications are still mesh products.
	 */
	public function test_invalid_mesh_specifications_are_mesh_products(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: '350/30 Orange $35.00',
				price: 35.00,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$spec = $result->mesh_variations[0]['spec'];

		$this->assertTrue(
			$result->is_mesh_product()
		);

		$this->assertCount(
			1,
			$result->mesh_variations
		);

		$this->assertFalse(
			$spec->is_valid()
		);

		$this->assertSame(
			array( 'Orange' ),
			$spec->get_unknown_tokens()
		);
	}


	/**
	 * Mixed variations should separate mesh, ignored, and unrecognized entries.
	 */
	public function test_mixed_variations_are_classified_correctly(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: '110/80 Yellow $20.00',
				price: 20.00,
			),
			$this->create_variation_entry(
				variation: 'Thin Thread',
				price: null,
			),
			$this->create_variation_entry(
				variation: 'Premium Orange',
				price: 35.00,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$this->assertTrue(
			$result->is_mesh_product()
		);

		$this->assertCount(
			1,
			$result->mesh_variations
		);

		$this->assertCount(
			1,
			$result->ignored_variations
		);

		$this->assertCount(
			1,
			$result->unrecognized_variations
		);
	}


	/**
	 * Empty variation lists should return an empty result.
	 */
	public function test_empty_variation_list_returns_empty_result(): void {

		$result = $this->analyzer->analyze(
			array(),
		);

		$this->assertFalse(
			$result->is_mesh_product()
		);

		$this->assertCount(
			0,
			$result->mesh_variations
		);

		$this->assertCount(
			0,
			$result->ignored_variations
		);

		$this->assertCount(
			0,
			$result->unrecognized_variations
		);
	}


	/**
	 * Duplicate mesh variations should remain separate entries.
	 */
	public function test_duplicate_mesh_variations_are_preserved(): void {

		$entries = array(
			$this->create_variation_entry(
				variation: '110/80 Yellow $20.00',
				price: 20.00,
			),
			$this->create_variation_entry(
				variation: '110/80 Yellow $25.00',
				price: 25.00,
			),
		);

		$result = $this->analyzer->analyze(
			$entries,
		);

		$this->assertTrue(
			$result->is_mesh_product()
		);

		$this->assertCount(
			2,
			$result->mesh_variations
		);

		$this->assertSame(
			20.00,
			$result->mesh_variations[0]['entry']->price
		);

		$this->assertSame(
			25.00,
			$result->mesh_variations[1]['entry']->price
		);
	}

	/**
	 * Create a catalog variation entry for analyzer tests.
	 *
	 * @param string     $variation Variation attribute value.
	 * @param float|null $price     Variation price.
	 * @return Catalog_Variation_Entry
	 */
	private function create_variation_entry(
		string $variation,
		?float $price,
	): Catalog_Variation_Entry {

		return new Catalog_Variation_Entry(
			variation: $variation,
			price: $price,
			product_id: 123,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);
	}
}
