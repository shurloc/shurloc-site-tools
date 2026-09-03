<?php
/**
 * Mesh table data factory.
 *
 * Converts mesh product analysis results into presentation-ready table data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Factories;

use Shurloc\SiteTools\Product\DTO\Mesh_Table_Data;
use Shurloc\SiteTools\Product\DTO\Mesh_Table_Row;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Mesh table data factory.
 */
final class Mesh_Table_Data_Factory {

	/**
	 * Create table data from mesh product analysis results.
	 *
	 * @param Mesh_Product_Result $result Mesh product analysis result.
	 * @return Mesh_Table_Data Presentation-ready table data.
	 */
	public function create(
		Mesh_Product_Result $result
	): Mesh_Table_Data {

		$rows = array();

		$show_modifier_column  = false;
		$show_pack_size_column = false;

		foreach ( $result->get_mesh_variations() as $variation ) {

			$entry = $variation['entry'];
			$spec  = $variation['spec'];

			if ( null !== $spec->get_modifier() ) {

				$show_modifier_column = true;
			}

			if ( null !== $spec->get_pack_size() ) {

				$show_pack_size_column = true;
			}

			$rows[] = new Mesh_Table_Row(
				$spec->get_mesh_count(),
				$spec->get_thread_diameter(),
				$spec->get_color(),
				$spec->get_modifier(),
				$spec->get_pack_size(),
				$entry->price,
				$spec->get_raw()
			);
		}

		return new Mesh_Table_Data(
			$rows,
			$show_modifier_column,
			$show_pack_size_column
		);
	}
}
