<?php
/**
 * Product schema service interface.
 *
 * Defines the contract for generating product schema data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;

/**
 * Product schema service interface.
 */
interface Product_Schema_Service_Interface {

	/**
	 * Generate product schema.
	 *
	 * @param Catalog_Product_Entry $product Catalog product.
	 * @return array<string,mixed>|null
	 */
	public function generate(
		Catalog_Product_Entry $product
	): ?array;
}
