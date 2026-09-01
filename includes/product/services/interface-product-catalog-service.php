<?php
/**
 * Product catalog service interface.
 *
 * Defines catalog product and variation retrieval behavior.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use WC_Product;

/**
 * Product catalog service interface.
 */
interface Product_Catalog_Service_Interface {

	/**
	 * Get catalog product entry.
	 *
	 * Converts a WooCommerce product into a catalog product entry.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Catalog_Product_Entry|null Product entry or null.
	 */
	public function get_product_entry(
		WC_Product $product
	): ?Catalog_Product_Entry;

	/**
	 * Get catalog variation entries.
	 *
	 * Converts WooCommerce product variations into catalog variation
	 * entries for mesh analysis and reporting.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Catalog_Variation_Entry[] Variation entries.
	 */
	public function get_product_variation_entries(
		WC_Product $product
	): array;
}
