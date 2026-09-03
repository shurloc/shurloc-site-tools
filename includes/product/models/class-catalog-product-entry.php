<?php
/**
 * Catalog product entry model.
 *
 * Represents a WooCommerce product and its catalog variations.
 * Contains the product-level information required for catalog analysis
 * and structured data generation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

/**
 * Catalog product entry.
 */
final class Catalog_Product_Entry {

	/**
	 * Product ID.
	 *
	 * @var int
	 */
	public int $product_id;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	public string $product_name;

	/**
	 * Product edit URL.
	 *
	 * @var string
	 */
	public string $edit_url;

	/**
	 * Public product URL.
	 *
	 * @var string
	 */
	public string $product_url;

	/**
	 * Product SKU.
	 *
	 * @var string
	 */
	public string $sku;

	/**
	 * Product image URL.
	 *
	 * @var string|null
	 */
	public ?string $image_url;

	/**
	 * Product short description.
	 *
	 * @var string
	 */
	public string $short_description;

	/**
	 * Product description.
	 *
	 * HTML stripped.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Product category.
	 *
	 * @var string|null
	 */
	public ?string $category;

	/**
	 * Product current price.
	 *
	 * @var float|null
	 */
	public ?float $price;

	/**
	 * Product regular price.
	 *
	 * @var float|null
	 */
	public ?float $regular_price;

	/**
	 * Product sale price.
	 *
	 * @var float|null
	 */
	public ?float $sale_price;

	/**
	 * Product availability.
	 *
	 * @var string
	 */
	public string $availability;

	/**
	 * Product brand.
	 *
	 * Uses WooCommerce product_brand taxonomy when available.
	 *
	 * @var string|null
	 */
	public ?string $brand;

	/**
	 * Product manufacturer.
	 *
	 * @var string
	 */
	public string $manufacturer;

	/**
	 * Aggregate rating data.
	 *
	 * Contains ratingValue, reviewCount, and related schema values.
	 *
	 * @var array<string,mixed>|null
	 */
	public ?array $aggregate_rating;

	/**
	 * Product reviews.
	 *
	 * Each review contains schema-ready review data.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public array $reviews;

	/**
	 * Product variations.
	 *
	 * @var Catalog_Variation_Entry[]
	 */
	public array $variations;

	/**
	 * Constructor.
	 *
	 * @param int                            $product_id Product ID.
	 * @param string                         $product_name Product name.
	 * @param string                         $edit_url Product edit URL.
	 * @param string                         $product_url Public product URL.
	 * @param string                         $sku Product SKU.
	 * @param string|null                    $image_url Product image URL.
	 * @param string                         $short_description Product short description.
	 * @param string                         $description Product description (HTML stripped).
	 * @param string|null                    $category Product category.
	 * @param float|null                     $price Product current price.
	 * @param float|null                     $regular_price Product regular price.
	 * @param float|null                     $sale_price Product sale price.
	 * @param string                         $availability Product availability.
	 * @param string|null                    $brand Product brand.
	 * @param string                         $manufacturer Product manufacturer.
	 * @param array<string,mixed>|null       $aggregate_rating Aggregate rating data.
	 * @param array<int,array<string,mixed>> $reviews Product reviews.
	 * @param Catalog_Variation_Entry[]      $variations Product variations.
	 */
	public function __construct(
		int $product_id,
		string $product_name,
		string $edit_url,
		string $product_url,
		string $sku,
		?string $image_url,
		string $short_description,
		string $description,
		?string $category,
		?float $price,
		?float $regular_price,
		?float $sale_price,
		string $availability,
		?string $brand,
		string $manufacturer,
		?array $aggregate_rating,
		array $reviews,
		array $variations
	) {

		$this->product_id        = $product_id;
		$this->product_name      = $product_name;
		$this->edit_url          = $edit_url;
		$this->product_url       = $product_url;
		$this->sku               = $sku;
		$this->image_url         = $image_url;
		$this->short_description = $short_description;
		$this->description       = $description;
		$this->category          = $category;
		$this->price             = $price;
		$this->regular_price     = $regular_price;
		$this->sale_price        = $sale_price;
		$this->availability      = $availability;
		$this->brand             = $brand;
		$this->manufacturer      = $manufacturer;
		$this->aggregate_rating  = $aggregate_rating;
		$this->reviews           = $reviews;
		$this->variations        = $variations;
	}
}
