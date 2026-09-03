<?php
/**
 * Tests for mesh product analysis results.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

use PHPUnit\Framework\TestCase;

/**
 * Tests mesh product result behavior.
 */
final class MeshProductResultTest extends TestCase {

	/**
	 * Recognized mesh variations are stored and returned.
	 */
	public function test_get_mesh_variations_returns_recognized_variations(): void {

		$entry = new Catalog_Variation_Entry(
			variation: '110/80 White $12.99',
			price: 12.99,
			product_id: 1,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);

		$spec = new Mesh_Specification(
			raw: '110/80 White $12.99',
			mesh_count: 110,
			thread_diameter: 80,
			modifier: null,
			color: 'White',
			pack_size: null,
			price_text: '$12.99',
			recognized: true,
			unknown_tokens: array(),
		);

		$result = new Mesh_Product_Result();

		$result->add_mesh_variation(
			entry: $entry,
			spec: $spec,
		);

		$variations = $result->get_mesh_variations();

		$this->assertCount(
			1,
			$variations
		);

		$this->assertSame(
			'110/80 White $12.99',
			$variations[0]['entry']->variation
		);

		$this->assertSame(
			$spec,
			$variations[0]['spec']
		);
	}

	/**
	 * Ignored variations are stored and returned.
	 */
	public function test_get_ignored_variations_returns_ignored_entries(): void {

		$entry = new Catalog_Variation_Entry(
			variation: 'Thin Thread',
			price: 0.0,
			product_id: 1,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);

		$result = new Mesh_Product_Result();

		$result->add_ignored_variation(
			entry: $entry,
		);

		$variations = $result->get_ignored_variations();

		$this->assertCount(
			1,
			$variations
		);

		$this->assertSame(
			'Thin Thread',
			$variations[0]->variation
		);
	}

	/**
	 * Unrecognized variations are stored and returned.
	 */
	public function test_get_unrecognized_variations_returns_entries(): void {

		$entry = new Catalog_Variation_Entry(
			variation: 'Something Unknown',
			price: 10.00,
			product_id: 1,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);

		$result = new Mesh_Product_Result();

		$result->add_unrecognized_variation(
			entry: $entry,
		);

		$variations = $result->get_unrecognized_variations();

		$this->assertCount(
			1,
			$variations
		);

		$this->assertSame(
			'Something Unknown',
			$variations[0]->variation
		);
	}

	/**
	 * Empty result returns empty arrays.
	 */
	public function test_empty_result_returns_empty_arrays(): void {

		$result = new Mesh_Product_Result();

		$this->assertSame(
			array(),
			$result->get_mesh_variations()
		);

		$this->assertSame(
			array(),
			$result->get_ignored_variations()
		);

		$this->assertSame(
			array(),
			$result->get_unrecognized_variations()
		);
	}

	/**
	 * Result correctly identifies mesh products.
	 */
	public function test_is_mesh_product_returns_true_when_mesh_variations_exist(): void {

		$entry = new Catalog_Variation_Entry(
			variation: '110/80 White $12.99',
			price: 12.99,
			product_id: 1,
			product_name: 'Test Mesh Product',
			edit_url: '',
		);

		$spec = new Mesh_Specification(
			raw: '110/80 White $12.99',
			mesh_count: 110,
			thread_diameter: 80,
			modifier: null,
			color: 'White',
			pack_size: null,
			price_text: '$12.99',
			recognized: true,
			unknown_tokens: array(),
		);

		$result = new Mesh_Product_Result();

		$result->add_mesh_variation(
			entry: $entry,
			spec: $spec,
		);

		$this->assertTrue(
			$result->is_mesh_product()
		);
	}

	/**
	 * Empty result is not a mesh product.
	 */
	public function test_is_mesh_product_returns_false_when_no_mesh_variations_exist(): void {

		$result = new Mesh_Product_Result();

		$this->assertFalse(
			$result->is_mesh_product()
		);
	}

	/**
	 * Mesh variation count returns correct value.
	 */
	public function test_mesh_variation_count_returns_number_of_mesh_variations(): void {

		$result = new Mesh_Product_Result();

		$result->add_mesh_variation(
			entry: new Catalog_Variation_Entry(
				variation: '110/80 White $12.99',
				price: 12.99,
				product_id: 1,
				product_name: 'Test Mesh Product',
				edit_url: '',
			),
			spec: new Mesh_Specification(
				raw: '110/80 White $12.99',
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

		$result->add_mesh_variation(
			entry: new Catalog_Variation_Entry(
				variation: '160/64 Yellow $14.99',
				price: 14.99,
				product_id: 1,
				product_name: 'Test Mesh Product',
				edit_url: '',
			),
			spec: new Mesh_Specification(
				raw: '160/64 Yellow $14.99',
				mesh_count: 160,
				thread_diameter: 64,
				modifier: null,
				color: 'Yellow',
				pack_size: null,
				price_text: '$14.99',
				recognized: true,
				unknown_tokens: array(),
			),
		);

		$this->assertSame(
			2,
			$result->mesh_variation_count()
		);
	}
}
