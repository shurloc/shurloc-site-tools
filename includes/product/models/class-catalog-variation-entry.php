<?php
/**
 * Catalog variation entry model.
 *
 * Represents a single WooCommerce product variation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

/**
 * Catalog variation entry.
 */
final class Catalog_Variation_Entry {

	/**
	 * Variation name.
	 *
	 * Example:
	 * - "110/80 White"
	 *
	 * @var string
	 */
	public string $variation;

	/**
	 * Variation price.
	 *
	 * A null price indicates that no price is assigned.
	 *
	 * @var float|null
	 */
	public ?float $price;

	/**
	 * WooCommerce product ID.
	 *
	 * @var int
	 */
	public int $product_id;

	/**
	 * WooCommerce product name.
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
	 * Constructor.
	 *
	 * @param string     $variation    Variation name.
	 * @param float|null $price        Variation price.
	 * @param int        $product_id   WooCommerce product ID.
	 * @param string     $product_name WooCommerce product name.
	 * @param string     $edit_url     Product edit URL.
	 */
	public function __construct(
		string $variation,
		?float $price,
		int $product_id,
		string $product_name,
		string $edit_url
	) {

		$this->variation    = $variation;
		$this->price        = $price;
		$this->product_id   = $product_id;
		$this->product_name = $product_name;
		$this->edit_url     = $edit_url;
	}

	/**
	 * Compare two catalog variation entries.
	 *
	 * @param Catalog_Variation_Entry $other The entry to compare.
	 * @return bool True if the entries are the same.
	 */
	public function equals(
		Catalog_Variation_Entry $other
	): bool {

		return (
			$this->variation === $other->variation &&
			$this->price === $other->price &&
			$this->product_id === $other->product_id &&
			$this->product_name === $other->product_name &&
			$this->edit_url === $other->edit_url
		);
	}

	/**
	 * Return the catalog variation entry as an associative array.
	 *
	 * @return array{
	 *     variation:string,
	 *     price:float|null,
	 *     product_id:int,
	 *     product_name:string,
	 *     edit_url:string
	 * }
	 */
	public function to_array(): array {

		return array(
			'variation'    => $this->variation,
			'price'        => $this->price,
			'product_id'   => $this->product_id,
			'product_name' => $this->product_name,
			'edit_url'     => $this->edit_url,
		);
	}
}
