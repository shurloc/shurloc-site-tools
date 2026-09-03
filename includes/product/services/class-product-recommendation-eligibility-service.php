<?php
/**
 * Product recommendation eligibility service.
 *
 * Determines whether a WooCommerce product may be included in a related
 * products or dynamic cross-sells recommendation result.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Services;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Determines product eligibility for recommendation results.
 */
final class Product_Recommendation_Eligibility_Service {

	/**
	 * Determine whether a product is eligible for recommendation.
	 *
	 * @param int|WC_Product $product      Product object or product ID.
	 * @param int[]          $excluded_ids Product IDs that must be excluded.
	 *
	 * @return bool
	 */
	public function is_eligible(
		$product,
		array $excluded_ids = array()
	): bool {

		$product = $this->get_product( $product );

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		$product_id = $product->get_id();

		if ( 0 === $product_id ) {
			return false;
		}

		$excluded_ids = $this->normalize_product_ids( $excluded_ids );

		if ( in_array( $product_id, $excluded_ids, true ) ) {
			return false;
		}

		if ( 'publish' !== $product->get_status() ) {
			return false;
		}

		if ( ! $product->is_visible() ) {
			return false;
		}

		if ( ! $product->is_in_stock() ) {
			return false;
		}

		return true;
	}

	/**
	 * Filter product IDs to eligible recommendation products.
	 *
	 * Product order is preserved.
	 *
	 * Duplicate IDs are removed, and the result may optionally be limited.
	 *
	 * @param int[] $product_ids  Candidate product IDs.
	 * @param int[] $excluded_ids Product IDs that must be excluded.
	 * @param int   $limit        Maximum number of results. Zero means no limit.
	 *
	 * @return int[]
	 */
	public function filter_eligible_ids(
		array $product_ids,
		array $excluded_ids = array(),
		int $limit = 0
	): array {

		$product_ids  = $this->normalize_product_ids( $product_ids );
		$excluded_ids = $this->normalize_product_ids( $excluded_ids );
		$eligible_ids = array();

		foreach ( $product_ids as $product_id ) {

			if ( in_array( $product_id, $eligible_ids, true ) ) {
				continue;
			}

			if ( ! $this->is_eligible( $product_id, $excluded_ids ) ) {
				continue;
			}

			$eligible_ids[] = $product_id;

			if (
				0 < $limit &&
				$limit <= count( $eligible_ids )
			) {
				break;
			}
		}

		return $eligible_ids;
	}

	/**
	 * Determine whether a candidate product ID is already selected.
	 *
	 * This is a convenience method for recommendation builders that assemble
	 * results from several candidate sources.
	 *
	 * @param int   $product_id   Candidate product ID.
	 * @param int[] $selected_ids Product IDs already selected.
	 *
	 * @return bool
	 */
	public function is_selected(
		int $product_id,
		array $selected_ids
	): bool {

		if ( 0 >= $product_id ) {
			return false;
		}

		return in_array(
			$product_id,
			$this->normalize_product_ids( $selected_ids ),
			true
		);
	}

	/**
	 * Normalize a list of product IDs.
	 *
	 * Removes invalid values, converts values to integers, and removes
	 * duplicates while preserving their original order.
	 *
	 * @param array<int|string, mixed> $product_ids Product IDs.
	 *
	 * @return int[]
	 */
	public function normalize_product_ids( array $product_ids ): array {

		$normalized_ids = array();

		foreach ( $product_ids as $product_id ) {

			$product_id = abs( (int) $product_id );

			if ( 0 === $product_id ) {
				continue;
			}

			if ( in_array( $product_id, $normalized_ids, true ) ) {
				continue;
			}

			$normalized_ids[] = $product_id;
		}

		return $normalized_ids;
	}

	/**
	 * Get a WooCommerce product object.
	 *
	 * @param int|WC_Product $product Product object or product ID.
	 *
	 * @return WC_Product|null
	 */
	private function get_product( $product ): ?WC_Product {

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		$product_id = abs( (int) $product );

		if ( 0 === $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		return $product instanceof WC_Product
			? $product
			: null;
	}
}
