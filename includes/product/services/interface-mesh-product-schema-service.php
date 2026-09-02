<?php
/**
 * Mesh product schema service interface.
 *
 * Defines the contract for analyzing mesh products for schema enrichment.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Mesh product schema service interface.
 */
interface Mesh_Product_Schema_Service_Interface {

	/**
	 * Analyze a catalog product for mesh schema data.
	 *
	 * Returns mesh product analysis results when applicable,
	 * otherwise returns null.
	 *
	 * @param Catalog_Product_Entry $product Catalog product.
	 * @return ?Mesh_Product_Result Mesh result or null.
	 */
	public function analyze(
		Catalog_Product_Entry $product
	): ?Mesh_Product_Result;
}
