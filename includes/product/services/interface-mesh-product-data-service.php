<?php
/**
 * Mesh product data service interface.
 *
 * Defines mesh product data retrieval behavior.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use WC_Product;

/**
 * Mesh product data service interface.
 */
interface Mesh_Product_Data_Service_Interface {

	/**
	 * Analyze a WooCommerce product for mesh data.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Mesh_Product_Result Mesh product analysis result.
	 */
	public function analyze_product(
		WC_Product $product
	): Mesh_Product_Result;

	/**
	 * Determine whether a product contains mesh specifications.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return bool True if the product contains mesh specifications.
	 */
	public function is_mesh_product(
		WC_Product $product
	): bool;
}
