<?php
/**
 * Mesh product analyzer interface.
 *
 * Defines mesh product analysis behavior.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Analyzers;

use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;

/**
 * Mesh product analyzer contract.
 */
interface Mesh_Product_Analyzer_Interface {

	/**
	 * Analyze catalog variation entries.
	 *
	 * Determines recognized, ignored, and unrecognized mesh variations.
	 *
	 * @param Catalog_Variation_Entry[] $entries Catalog entries.
	 * @return Mesh_Product_Result Analysis result.
	 */
	public function analyze(
		array $entries
	): Mesh_Product_Result;
}
