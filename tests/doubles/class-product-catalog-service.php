<?php
/**
 * Product catalog service test double.
 *
 * Provides a controllable implementation of the product catalog service
 * interface for unit testing services that depend on catalog data retrieval.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use WC_Product;

/**
 * Product catalog service test double.
 */
final class Product_Catalog_Service_Double implements Product_Catalog_Service_Interface {

	/**
	 * Catalog product entry to return.
	 *
	 * When null, the double creates an entry from the supplied WooCommerce
	 * product to preserve its original behavior.
	 *
	 * @var Catalog_Product_Entry|null
	 */
	private ?Catalog_Product_Entry $product_entry;

	/**
	 * Catalog variation entries to return.
	 *
	 * @var Catalog_Variation_Entry[]
	 */
	private array $variation_entries;

	/**
	 * Calls to get_product_entry().
	 *
	 * @var WC_Product[]
	 */
	private array $product_entry_calls = array();

	/**
	 * Calls to get_product_variation_entries().
	 *
	 * @var WC_Product[]
	 */
	private array $variation_entry_calls = array();

	/**
	 * Whether get_product_entry() should return null.
	 *
	 * @var bool
	 */
	private bool $return_null_product_entry;

	/**
	 * Constructor.
	 *
	 * @param Catalog_Variation_Entry[]  $variation_entries        Variation entries.
	 * @param Catalog_Product_Entry|null $product_entry            Product entry to return.
	 * @param bool                       $return_null_product_entry Whether to return null.
	 */
	public function __construct(
		array $variation_entries = array(),
		?Catalog_Product_Entry $product_entry = null,
		bool $return_null_product_entry = false,
	) {

		$this->variation_entries         = $variation_entries;
		$this->product_entry             = $product_entry;
		$this->return_null_product_entry = $return_null_product_entry;
	}

	/**
	 * Get catalog product entry.
	 *
	 * Returns the configured product entry when supplied. Otherwise, creates
	 * a catalog entry from the WooCommerce product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Catalog_Product_Entry|null Product entry.
	 */
	public function get_product_entry(
		WC_Product $product,
	): ?Catalog_Product_Entry {

		$this->product_entry_calls[] = $product;

		if ( $this->return_null_product_entry ) {
			return null;
		}

		if ( null !== $this->product_entry ) {
			return $this->product_entry;
		}

		return new Catalog_Product_Entry(
			product_id: (int) $product->get_id(),
			product_name: $product->get_name(),
			edit_url: '',
			product_url: '',
			sku: '',
			image_url: null,
			short_description: 'Short description of product.',
			description: 'Description of product.',
			category: null,
			price: null,
			regular_price: null,
			sale_price: null,
			availability: 'https://schema.org/InStock',
			brand: 'Shur-loc®',
			manufacturer: 'Shur-loc®',
			aggregate_rating: null,
			reviews: array(),
			variations: $this->variation_entries,
		);
	}

	/**
	 * Get catalog variation entries.
	 *
	 * Returns the predefined variation entries supplied during construction.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Catalog_Variation_Entry[] Variation entries.
	 */
	public function get_product_variation_entries(
		WC_Product $product,
	): array {

		$this->variation_entry_calls[] = $product;

		return $this->variation_entries;
	}

	/**
	 * Get calls to get_product_entry().
	 *
	 * @return WC_Product[] Products passed to get_product_entry().
	 */
	public function get_product_entry_calls(): array {

		return $this->product_entry_calls;
	}

	/**
	 * Get calls to get_product_variation_entries().
	 *
	 * @return WC_Product[] Products passed to get_product_variation_entries().
	 */
	public function get_product_variation_entry_calls(): array {

		return $this->variation_entry_calls;
	}
}
