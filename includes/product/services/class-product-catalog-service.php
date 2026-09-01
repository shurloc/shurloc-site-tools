<?php
/**
 * Product catalog service.
 *
 * Converts WooCommerce products into catalog entries.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use Shurloc\SiteTools\Product\Models\Catalog_Product_Entry;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use WC_Product;
use WC_Product_Variation;
use WP_Comment;

/**
 * Product catalog service.
 */
final class Product_Catalog_Service implements Product_Catalog_Service_Interface {

	/**
	 * Collect a product catalog entry.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Catalog_Product_Entry
	 */
	public function get_product_entry(
		WC_Product $product
	): Catalog_Product_Entry {

		$variations = $this->get_product_variation_entries(
			$product
		);

		return new Catalog_Product_Entry(
			(int) $product->get_id(),
			$product->get_name(),
			(string) get_edit_post_link(
				$product->get_id(),
				''
			),
			(string) get_permalink(
				$product->get_id()
			),
			(string) $product->get_sku(),
			$this->get_product_image_url(
				$product
			),
			wp_strip_all_tags(
				$product->get_short_description()
			),
			wp_strip_all_tags(
				$product->get_description()
			),
			$this->get_category(
				$product
			),
			$this->normalize_price(
				$product->get_price()
			),
			$this->normalize_price(
				$product->get_regular_price()
			),
			$this->normalize_price(
				$product->get_sale_price()
			),
			$this->get_availability(
				$product
			),
			$this->get_brand(
				$product
			),
			'Shur-loc®',
			$this->get_aggregate_rating(
				$product
			),
			$this->get_reviews(
				$product
			),
			$variations
		);
	}

	/**
	 * Collect variation entries for a variable product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return Catalog_Variation_Entry[]
	 */
	public function get_product_variation_entries(
		WC_Product $product
	): array {

		if ( ! $product->is_type( 'variable' ) ) {
			return array();
		}

		$entries = array();

		foreach ( $product->get_children() as $variation_id ) {

			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			$attributes = $variation->get_variation_attributes();

			if ( 1 !== count( $attributes ) ) {
				continue;
			}

			$entries[] = new Catalog_Variation_Entry(
				array_values( $attributes )[0],
				$this->normalize_price(
					$variation->get_price()
				),
				(int) $product->get_id(),
				$product->get_name(),
				(string) get_edit_post_link(
					$product->get_id(),
					''
				)
			);
		}

		return $entries;
	}

	/**
	 * Get WooCommerce product brand.
	 *
	 * Uses the product_brand taxonomy.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	private function get_brand(
		WC_Product $product
	): string {

		$brands = wp_get_post_terms(
			$product->get_id(),
			'product_brand',
			array(
				'fields' => 'names',
			)
		);

		if (
			is_wp_error( $brands )
			|| empty( $brands )
		) {
			return 'Shur-loc®';
		}

		return (string) $brands[0];
	}

	/**
	 * Get WooCommerce product category.
	 *
	 * Uses the product_cat taxonomy.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string|null
	 */
	private function get_category(
		WC_Product $product
	): ?string {

		$categories = wp_get_post_terms(
			$product->get_id(),
			'product_cat',
			array(
				'fields' => 'names',
			)
		);

		if (
			is_wp_error( $categories )
			|| empty( $categories )
		) {
			return null;
		}

		return (string) $categories[0];
	}

	/**
	 * Get aggregate rating data.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array<string,mixed>|null
	 */
	private function get_aggregate_rating(
		WC_Product $product
	): ?array {

		$rating_count = $product->get_rating_count();

		if ( 0 === $rating_count ) {
			return null;
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $product->get_average_rating(),
			'reviewCount' => $rating_count,
		);
	}

	/**
	 * Get product reviews.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_reviews(
		WC_Product $product
	): array {

		$reviews = get_comments(
			array(
				'post_id' => $product->get_id(),
				'status'  => 'approve',
				'type'    => 'review',
			)
		);

		$schema_reviews = array();

		if ( 'integer' === gettype( $reviews ) ) {
			$reviews_tmp[] = $reviews;
			$reviews       = $reviews_tmp;
		}

		foreach ( $reviews as $review ) {

			if ( ! $review instanceof WP_Comment ) {
				continue;
			}

			$schema_reviews[] = array(
				'@type'         => 'Review',
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => get_comment_meta(
						(int) $review->comment_ID,
						'rating',
						true
					),
				),
				'author'        => array(
					'@type' => 'Person',
					'name'  => $review->comment_author,
				),
				'reviewBody'    => $review->comment_content,
				'datePublished' => $review->comment_date,
			);
		}

		return $schema_reviews;
	}

	/**
	 * Get product image URL.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string|null
	 */
	private function get_product_image_url(
		WC_Product $product
	): ?string {

		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return null;
		}

		$image_url = wp_get_attachment_image_url(
			(int) $image_id,
			'full'
		);

		if ( false === $image_url ) {
			return null;
		}

		return $image_url;
	}

	/**
	 * Get product availability.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	private function get_availability(
		WC_Product $product
	): string {

		if ( $product->is_in_stock() ) {
			return 'https://schema.org/InStock';
		}

		return 'https://schema.org/OutOfStock';
	}

	/**
	 * Normalize a WooCommerce price.
	 *
	 * WooCommerce returns an empty string when no price has been set.
	 *
	 * @param string $price WooCommerce price.
	 * @return float|null
	 */
	private function normalize_price(
		string $price
	): ?float {

		if ( '' === $price ) {
			return null;
		}

		return (float) $price;
	}
}
