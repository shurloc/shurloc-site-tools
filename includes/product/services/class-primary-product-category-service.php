<?php
/**
 * Primary product category service.
 *
 * Manages the primary WooCommerce product category stored for a product.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages primary product category data.
 */
final class Primary_Product_Category_Service {

	/**
	 * Primary product category meta key.
	 *
	 * Preserves compatibility with Yoast SEO's primary product category
	 * metadata.
	 *
	 * @var string
	 */
	public const META_KEY =
		'_yoast_wpseo_primary_product_cat';

	/**
	 * Product category taxonomy.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'product_cat';

	/**
	 * Get the primary category ID for a product.
	 *
	 * Invalid or no-longer-assigned primary categories are treated as unset.
	 *
	 * @param int $product_id Product ID.
	 * @return int Primary category term ID, or 0 when none is valid.
	 */
	public function get_primary_category_id(
		int $product_id
	): int {

		if ( 0 >= $product_id ) {
			return 0;
		}

		$term_id = (int) get_post_meta(
			$product_id,
			self::META_KEY,
			true
		);

		if ( 0 >= $term_id ) {
			return 0;
		}

		if (
			! $this->is_valid_primary_category(
				product_id: $product_id,
				term_id: $term_id,
			)
		) {
			return 0;
		}

		return $term_id;
	}

	/**
	 * Set the primary category for a product.
	 *
	 * The selected term must exist in the product_cat taxonomy and already be
	 * assigned to the product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $term_id    Product category term ID.
	 * @return bool True when the primary category is stored or already current.
	 */
	public function set_primary_category(
		int $product_id,
		int $term_id
	): bool {

		if (
			0 >= $product_id ||
			0 >= $term_id
		) {
			return false;
		}

		if (
			! $this->is_valid_primary_category(
				product_id: $product_id,
				term_id: $term_id,
			)
		) {
			return false;
		}

		$current_term_id = (int) get_post_meta(
			$product_id,
			self::META_KEY,
			true
		);

		if ( $term_id === $current_term_id ) {
			return true;
		}

		$result = update_post_meta(
			$product_id,
			self::META_KEY,
			$term_id
		);

		return false !== $result;
	}

	/**
	 * Clear the primary category for a product.
	 *
	 * Clearing an already-unset value is considered successful.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True when the primary category is cleared or already unset.
	 */
	public function clear_primary_category(
		int $product_id
	): bool {

		if ( 0 >= $product_id ) {
			return false;
		}

		$current_term_id = (int) get_post_meta(
			$product_id,
			self::META_KEY,
			true
		);

		if ( 0 >= $current_term_id ) {
			return true;
		}

		return delete_post_meta(
			$product_id,
			self::META_KEY
		);
	}

	/**
	 * Determine whether a category may be primary for a product.
	 *
	 * The category must exist in the WooCommerce product category taxonomy
	 * and must already be assigned to the product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $term_id    Product category term ID.
	 * @return bool
	 */
	private function is_valid_primary_category(
		int $product_id,
		int $term_id
	): bool {

		$term = get_term(
			$term_id,
			self::TAXONOMY
		);

		if (
			is_wp_error( $term ) ||
			null === $term
		) {
			return false;
		}

		return has_term(
			$term_id,
			self::TAXONOMY,
			$product_id
		);
	}
}
