<?php
/**
 * Mesh product data service.
 *
 * Provides analyzed mesh product data for frontend displays,
 * structured data generation, and other integrations.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer_Interface;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use WC_Product;

/**
 * Mesh product data service.
 */
final class Mesh_Product_Data_Service implements Mesh_Product_Data_Service_Interface {

	/**
	 * Product catalog service.
	 *
	 * @var Product_Catalog_Service_Interface
	 */
	private Product_Catalog_Service_Interface $catalog_service;

	/**
	 * Mesh product analyzer.
	 *
	 * @var Mesh_Product_Analyzer_Interface
	 */
	private Mesh_Product_Analyzer_Interface $mesh_analyzer;

	/**
	 * Constructor.
	 *
	 * @param Product_Catalog_Service_Interface $catalog_service Catalog service.
	 * @param Mesh_Product_Analyzer_Interface   $mesh_analyzer   Mesh analyzer.
	 */
	public function __construct(
		Product_Catalog_Service_Interface $catalog_service,
		Mesh_Product_Analyzer_Interface $mesh_analyzer
	) {

		$this->catalog_service = $catalog_service;
		$this->mesh_analyzer   = $mesh_analyzer;
	}

	/**
	 * Get analyzed mesh data for a product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Mesh_Product_Result
	 */
	public function analyze_product(
		WC_Product $product
	): Mesh_Product_Result {

		$entries = $this->catalog_service->get_product_variation_entries(
			$product
		);

		return $this->mesh_analyzer->analyze(
			$entries
		);
	}

	/**
	 * Determine whether a product is a mesh product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return bool True if mesh specifications exist.
	 */
	public function is_mesh_product(
		WC_Product $product
	): bool {

		return $this->get_product_mesh_data(
			$product
		)->is_mesh_product();
	}

	/**
	 * Get analyzed mesh data for a product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Mesh_Product_Result Analysis result.
	 */
	public function get_product_mesh_data(
		WC_Product $product
	): Mesh_Product_Result {

		$entries = $this->catalog_service->get_product_variation_entries(
			$product
		);

		return $this->mesh_analyzer->analyze(
			$entries
		);
	}
}
