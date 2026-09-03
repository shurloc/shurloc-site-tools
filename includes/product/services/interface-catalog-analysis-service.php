<?php
/**
 * Catalog analysis service interface.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Reports\Catalog_Report;

/**
 * Provides catalog variation collection and analysis.
 */
interface Catalog_Analysis_Service_Interface {

	/**
	 * Collect catalog variation entries.
	 *
	 * @return Catalog_Variation_Entry[]
	 */
	public function get_variation_entries(): array;

	/**
	 * Collect catalog variation values.
	 *
	 * @return string[]
	 */
	public function get_variation_values(): array;

	/**
	 * Analyze the WooCommerce catalog.
	 *
	 * @return Catalog_Report
	 */
	public function analyze(): Catalog_Report;
}
