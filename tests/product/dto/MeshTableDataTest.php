<?php
/**
 * Tests for mesh table data DTO.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\DTO;

use PHPUnit\Framework\TestCase;

/**
 * Mesh table data tests.
 */
final class MeshTableDataTest extends TestCase {

	/**
	 * Empty table data contains no rows.
	 */
	public function test_empty_table_data_has_no_rows(): void {

		$data = new Mesh_Table_Data(
			rows: array(),
			show_modifier_column: false,
			show_pack_size_column: false,
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

	/**
	 * Table data returns supplied rows.
	 */
	public function test_table_data_returns_rows(): void {

		$row = new Mesh_Table_Row(
			mesh_count: 110,
			thread_diameter: 80,
			color: 'White',
			modifier: null,
			pack_size: '10 Pack',
			price: 12.99,
			variation_value: '110/80 White $12.99',
		);

		$data = new Mesh_Table_Data(
			rows: array(
				$row,
			),
			show_modifier_column: false,
			show_pack_size_column: true,
		);

		$this->assertTrue(
			$data->has_rows()
		);

		$this->assertSame(
			1,
			$data->count()
		);

		$this->assertSame(
			array(
				$row,
			),
			$data->get_rows()
		);
	}

	/**
	 * Multiple rows are preserved in order.
	 */
	public function test_multiple_rows_are_preserved_in_order(): void {

		$first_row = new Mesh_Table_Row(
			mesh_count: 110,
			thread_diameter: 80,
			color: 'White',
			modifier: null,
			pack_size: '10 Pack',
			price: 12.99,
			variation_value: '110/80 White $12.99',
		);

		$second_row = new Mesh_Table_Row(
			mesh_count: 160,
			thread_diameter: 64,
			color: 'Yellow',
			modifier: 'HD',
			pack_size: '20 Pack',
			price: 25.00,
			variation_value: '160/64 HD Yellow $25.00',
		);

		$data = new Mesh_Table_Data(
			rows: array(
				$first_row,
				$second_row,
			),
			show_modifier_column: true,
			show_pack_size_column: true,
		);

		$rows = $data->get_rows();

		$this->assertCount(
			2,
			$rows
		);

		$this->assertSame(
			$first_row,
			$rows[0]
		);

		$this->assertSame(
			$second_row,
			$rows[1]
		);
	}

	/**
	 * Row count matches supplied row collection.
	 */
	public function test_count_returns_number_of_rows(): void {

		$data = new Mesh_Table_Data(
			rows: array(
				new Mesh_Table_Row(
					mesh_count: 110,
					thread_diameter: 80,
					color: 'White',
					modifier: null,
					pack_size: null,
					price: 12.99,
					variation_value: '110/80 White $12.99',
				),
				new Mesh_Table_Row(
					mesh_count: 160,
					thread_diameter: 64,
					color: 'Yellow',
					modifier: null,
					pack_size: null,
					price: 15.99,
					variation_value: '160/64 Yellow $15.99',
				),
				new Mesh_Table_Row(
					mesh_count: 230,
					thread_diameter: 48,
					color: 'White',
					modifier: 'S',
					pack_size: null,
					price: 18.99,
					variation_value: '230/48 S White $18.99',
				),
			),
			show_modifier_column: true,
			show_pack_size_column: false,
		);

		$this->assertSame(
			3,
			$data->count()
		);
	}
}
