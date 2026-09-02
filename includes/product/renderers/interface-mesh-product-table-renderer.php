<?php
/**
 * Mesh product table renderer interface.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

use Shurloc\SiteTools\Product\DTO\Mesh_Table_Data;

/**
 * Mesh product table renderer interface.
 */
interface Mesh_Product_Table_Renderer_Interface {

	/**
	 * Render mesh specification table.
	 *
	 * @param Mesh_Table_Data $data Table data.
	 * @return string HTML output.
	 */
	public function render(
		Mesh_Table_Data $data
	): string;
}
