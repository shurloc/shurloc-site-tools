<?php
/**
 * Mesh table data DTO.
 *
 * Represents presentation-ready mesh table data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\DTO;

/**
 * Mesh table data DTO.
 */
final class Mesh_Table_Data {

	/**
	 * Table rows.
	 *
	 * @var Mesh_Table_Row[]
	 */
	private readonly array $rows;

	/**
	 * Whether modifier column should be displayed.
	 *
	 * @var bool
	 */
	private readonly bool $show_modifier_column;

	/**
	 * Whether pack size column should be displayed.
	 *
	 * @var bool
	 */
	private readonly bool $show_pack_size_column;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Table_Row[] $rows                  Table rows.
	 * @param bool             $show_modifier_column Show modifier column.
	 * @param bool             $show_pack_size_column Show pack size column.
	 */
	public function __construct(
		array $rows,
		bool $show_modifier_column,
		bool $show_pack_size_column
	) {

		$this->rows                  = $rows;
		$this->show_modifier_column  = $show_modifier_column;
		$this->show_pack_size_column = $show_pack_size_column;
	}

	/**
	 * Get table rows.
	 *
	 * @return Mesh_Table_Row[] Table rows.
	 */
	public function get_rows(): array {

		return $this->rows;
	}

	/**
	 * Determine whether the table contains rows.
	 *
	 * @return bool True when rows exist.
	 */
	public function has_rows(): bool {

		return ! empty( $this->rows );
	}

	/**
	 * Get row count.
	 *
	 * @return int Number of rows.
	 */
	public function count(): int {

		return count( $this->rows );
	}

	/**
	 * Determine whether modifier column should be displayed.
	 *
	 * @return bool True when modifier column should display.
	 */
	public function show_modifier_column(): bool {

		return $this->show_modifier_column;
	}

	/**
	 * Determine whether pack size column should be displayed.
	 *
	 * @return bool True when pack size column should display.
	 */
	public function show_pack_size_column(): bool {

		return $this->show_pack_size_column;
	}
}
