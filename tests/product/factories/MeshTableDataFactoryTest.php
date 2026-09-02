<?php
/**
 * Tests for mesh table data factory.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Factories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\DTO\Mesh_Table_Data;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Tests mesh table data factory behavior.
 */
final class MeshTableDataFactoryTest extends TestCase {

	/**
	 * Factory.
	 *
	 * @var Mesh_Table_Data_Factory
	 */
	private Mesh_Table_Data_Factory $factory;

	/**
	 * Set up factory.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->factory = new Mesh_Table_Data_Factory();
	}

	/**
	 * Factory creates rows from mesh results.
	 */
	public function test_creates_table_rows_from_mesh_result(): void {

		$result = new Mesh_Product_Result();

		$result->add_mesh_variation(
			new Catalog_Variation_Entry(
				variation: '110/80 White',
				price: 12.99,
				product_id: 1,
				product_name: 'Test Product',
				edit_url: '',
			),
			new Mesh_Specification(
				raw: '110/80 White',
				mesh_count: 110,
				thread_diameter: 80,
				modifier: null,
				color: 'White',
				pack_size: '10 Pack',
				price_text: '$12.99',
				recognized: true,
			)
		);

		$data = $this->factory->create(
			$result,
		);

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Mesh_Table_Data::class,
			$data
		);

		$this->assertTrue(
			$data->has_rows()
		);

		$this->assertSame(
			1,
			$data->count()
		);

		$rows = $data->get_rows();

		$this->assertCount(
			1,
			$rows
		);

		$row = $rows[0];

		$this->assertSame(
			110,
			$row->get_mesh_count()
		);

		$this->assertSame(
			80,
			$row->get_thread_diameter()
		);

		$this->assertSame(
			'White',
			$row->get_color()
		);

		$this->assertNull(
			$row->get_modifier()
		);

		$this->assertSame(
			'10 Pack',
			$row->get_pack_size()
		);

		$this->assertSame(
			12.99,
			$row->get_price()
		);
	}

	/**
	 * Factory preserves multiple rows.
	 */
	public function test_creates_multiple_rows(): void {

		$result = new Mesh_Product_Result();

		$result->add_mesh_variation(
			new Catalog_Variation_Entry(
				variation: '110/80 White',
				price: 12.99,
				product_id: 1,
				product_name: 'Test Product',
				edit_url: '',
			),
			new Mesh_Specification(
				raw: '110/80 White',
				mesh_count: 110,
				thread_diameter: 80,
				modifier: null,
				color: 'White',
				pack_size: null,
				price_text: '$12.99',
				recognized: true,
			)
		);

		$result->add_mesh_variation(
			new Catalog_Variation_Entry(
				variation: '160/64 Yellow',
				price: 15.99,
				product_id: 1,
				product_name: 'Test Product',
				edit_url: '',
			),
			new Mesh_Specification(
				raw: '160/64 Yellow',
				mesh_count: 160,
				thread_diameter: 64,
				modifier: null,
				color: 'Yellow',
				pack_size: null,
				price_text: '$15.99',
				recognized: true,
			)
		);

		$data = $this->factory->create(
			$result,
		);

		$this->assertTrue(
			$data->has_rows()
		);

		$this->assertSame(
			2,
			$data->count()
		);

		$rows = $data->get_rows();

		$this->assertCount(
			2,
			$rows
		);

		$this->assertSame(
			110,
			$rows[0]->get_mesh_count()
		);

		$this->assertSame(
			160,
			$rows[1]->get_mesh_count()
		);

		$this->assertSame(
			'White',
			$rows[0]->get_color()
		);

		$this->assertSame(
			'Yellow',
			$rows[1]->get_color()
		);

		$this->assertSame(
			12.99,
			$rows[0]->get_price()
		);

		$this->assertSame(
			15.99,
			$rows[1]->get_price()
		);

		$this->assertSame(
			'110/80 White',
			$rows[0]->get_variation_value()
		);

		$this->assertSame(
			'160/64 Yellow',
			$rows[1]->get_variation_value()
		);
	}

	/**
	 * Empty result creates empty table data.
	 */
	public function test_empty_result_creates_empty_table_data(): void {

		$data = $this->factory->create(
			new Mesh_Product_Result(),
		);

		$this->assertFalse(
			$data->has_rows()
		);

		$this->assertSame(
			0,
			$data->count()
		);

		$this->assertSame(
			array(),
			$data->get_rows()
		);
	}
}
