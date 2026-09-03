<?php
/**
 * Tests for mesh table row DTO.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\DTO;

use PHPUnit\Framework\TestCase;

/**
 * Mesh table row tests.
 */
final class MeshTableRowTest extends TestCase {

	/**
	 * Constructor populates all properties.
	 */
	public function test_constructor_populates_all_fields(): void {

		$row = new Mesh_Table_Row(
			mesh_count: 110,
			thread_diameter: 80,
			color: 'White',
			modifier: 'S',
			pack_size: '10 Pack',
			price: 12.99,
			variation_value: '110/80 S White $12.99',
		);

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

		$this->assertSame(
			'S',
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

		$this->assertSame(
			'110/80 S White $12.99',
			$row->get_variation_value()
		);
	}

	/**
	 * Nullable fields remain null.
	 */
	public function test_nullable_fields_remain_null(): void {

		$row = new Mesh_Table_Row(
			mesh_count: 230,
			thread_diameter: 40,
			color: 'Yellow',
			modifier: null,
			pack_size: null,
			price: null,
			variation_value: '230/40 Yellow',
		);

		$this->assertSame(
			230,
			$row->get_mesh_count()
		);

		$this->assertSame(
			40,
			$row->get_thread_diameter()
		);

		$this->assertSame(
			'Yellow',
			$row->get_color()
		);

		$this->assertNull(
			$row->get_modifier()
		);

		$this->assertNull(
			$row->get_pack_size()
		);

		$this->assertNull(
			$row->get_price()
		);

		$this->assertSame(
			'230/40 Yellow',
			$row->get_variation_value()
		);
	}

	/**
	 * Returns variation value.
	 */
	public function test_returns_variation_value(): void {

		$row = new Mesh_Table_Row(
			mesh_count: 110,
			thread_diameter: 80,
			color: 'Yellow',
			modifier: null,
			pack_size: null,
			price: 18.17,
			variation_value: '110/80 Yellow $18.17',
		);

		$this->assertSame(
			'110/80 Yellow $18.17',
			$row->get_variation_value()
		);
	}

	/**
	 * Table rows permit a missing mesh color.
	 */
	public function test_color_can_be_null(): void {

		$row = new Mesh_Table_Row(
			mesh_count: 120,
			thread_diameter: 48,
			color: null,
			modifier: 'S',
			pack_size: null,
			price: 24.10,
			variation_value: '120/48 (S) $24.10',
		);

		$this->assertNull(
			$row->get_color()
		);
	}
}
