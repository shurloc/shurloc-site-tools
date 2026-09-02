<?php
/**
 * Mesh product data service double.
 *
 * Provides controlled mesh product analysis results for tests.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use WC_Product;

/**
 * Mesh product data service double.
 */
final class Mesh_Product_Data_Service_Double implements Mesh_Product_Data_Service_Interface {

	/**
	 * Analysis result.
	 *
	 * @var Mesh_Product_Result
	 */
	private Mesh_Product_Result $result;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Product_Result $result Analysis result.
	 */
	public function __construct(
		Mesh_Product_Result $result
	) {

		$this->result = $result;
	}

	/**
	 * Analyze product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Mesh_Product_Result
	 */
	public function analyze_product(
		WC_Product $product
	): Mesh_Product_Result {

		return $this->result;
	}

	/**
	 * Determine whether product contains mesh data.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return bool True if mesh product.
	 */
	public function is_mesh_product(
		WC_Product $product
	): bool {

		return $this->result->is_mesh_product();
	}
}
