<?php
/**
 * Mesh product schema service.
 *
 * Coordinates mesh product analysis.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer_Interface;
use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Mesh product schema service.
 */
final class Mesh_Product_Schema_Service implements Mesh_Product_Schema_Service_Interface {

	/**
	 * Mesh analyzer.
	 *
	 * @var Mesh_Product_Analyzer_Interface
	 */
	private Mesh_Product_Analyzer_Interface $analyzer;

	/**
	 * Constructor.
	 *
	 * @param Mesh_Product_Analyzer_Interface $analyzer Mesh analyzer.
	 */
	public function __construct(
		Mesh_Product_Analyzer_Interface $analyzer
	) {

		$this->analyzer = $analyzer;
	}

	/**
	 * Analyze a catalog product for mesh variations.
	 *
	 * Returns null when the product is not a mesh product.
	 *
	 * @param Catalog_Product_Entry $product Catalog product.
	 * @return Mesh_Product_Result|null
	 */
	public function analyze(
		Catalog_Product_Entry $product
	): ?Mesh_Product_Result {

		$result = $this->analyzer->analyze(
			$product->variations
		);

		if ( ! $result->is_mesh_product() ) {
			return null;
		}

		return $result;
	}
}
