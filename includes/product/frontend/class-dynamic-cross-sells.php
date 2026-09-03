<?php
/**
 * Dynamic cart cross-sells.
 *
 * Preserves eligible manually assigned cross-sells, then fills any remaining
 * positions with eligible products from categories represented in the cart.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Frontend;

use Shurloc\SiteTools\Product\Services\Product_Recommendation_Eligibility_Service;
use WC_Cart;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Builds WooCommerce cart cross-sell recommendations.
 */
final class Dynamic_Cross_Sells {

	/**
	 * Default maximum number of cross-sells.
	 *
	 * @var int
	 */
	private const DEFAULT_LIMIT = 4;

	/**
	 * Number of dynamic candidates to query per remaining position.
	 *
	 * A larger candidate pool allows the eligibility service to reject hidden,
	 * unpublished, or out-of-stock products without exhausting the results.
	 *
	 * @var int
	 */
	private const CANDIDATE_MULTIPLIER = 5;

	/**
	 * Minimum number of dynamic candidates to query.
	 *
	 * @var int
	 */
	private const MINIMUM_CANDIDATE_LIMIT = 20;

	/**
	 * Product recommendation eligibility service.
	 *
	 * @var Product_Recommendation_Eligibility_Service
	 */
	private Product_Recommendation_Eligibility_Service $eligibility;

	/**
	 * Constructor.
	 *
	 * @param Product_Recommendation_Eligibility_Service $eligibility Product recommendation eligibility service.
	 */
	public function __construct(
		Product_Recommendation_Eligibility_Service $eligibility
	) {
		$this->eligibility = $eligibility;
	}

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'woocommerce_cart_crosssell_ids',
			array( $this, 'filter_cross_sell_ids' ),
			20,
			2
		);
	}

	/**
	 * Add dynamic products to the cart cross-sell recommendations.
	 *
	 * Eligible manually assigned cross-sells are retained first. Any remaining
	 * positions are filled with eligible products sharing categories with
	 * products currently in the cart.
	 *
	 * @param int[]   $manual_cross_sell_ids Manually assigned cross-sell IDs.
	 * @param WC_Cart $cart                  WooCommerce cart.
	 *
	 * @return int[]
	 */
	public function filter_cross_sell_ids(
		array $manual_cross_sell_ids,
		WC_Cart $cart
	): array {

		$limit = $this->get_result_limit();

		if ( 0 === $limit ) {
			return array();
		}

		$cart_product_ids = $this->get_cart_product_ids( $cart );

		if ( empty( $cart_product_ids ) ) {
			return array();
		}

		$manual_cross_sell_ids =
			$this->eligibility->filter_eligible_ids(
				$manual_cross_sell_ids,
				$cart_product_ids,
				$limit
			);

		if ( $limit <= count( $manual_cross_sell_ids ) ) {
			return array_slice(
				$manual_cross_sell_ids,
				0,
				$limit
			);
		}

		$category_ids = $this->get_cart_category_ids(
			$cart_product_ids
		);

		if ( empty( $category_ids ) ) {
			return $manual_cross_sell_ids;
		}

		$remaining_limit = $limit - count( $manual_cross_sell_ids );

		$excluded_ids = array_merge(
			$cart_product_ids,
			$manual_cross_sell_ids
		);

		$dynamic_candidate_ids = $this->get_dynamic_candidate_ids(
			$category_ids,
			$excluded_ids,
			$remaining_limit
		);

		return array_merge(
			$manual_cross_sell_ids,
			$dynamic_candidate_ids
		);
	}

	/**
	 * Get all product and variation IDs currently represented in the cart.
	 *
	 * Parent product IDs are needed for category lookup. Variation IDs are
	 * also excluded so a variation already in the cart cannot be recommended.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 *
	 * @return int[]
	 */
	private function get_cart_product_ids(
		WC_Cart $cart
	): array {

		$product_ids = array();

		foreach ( $cart->get_cart() as $cart_item ) {

			$quantity = isset( $cart_item['quantity'] )
				? (int) $cart_item['quantity']
				: 0;

			if ( 0 >= $quantity ) {
				continue;
			}

			if ( isset( $cart_item['product_id'] ) ) {
				$product_ids[] = (int) $cart_item['product_id'];
			}

			if (
				isset( $cart_item['variation_id'] ) &&
				0 < (int) $cart_item['variation_id']
			) {
				$product_ids[] = (int) $cart_item['variation_id'];
			}
		}

		return $this->eligibility->normalize_product_ids(
			$product_ids
		);
	}

	/**
	 * Get the product categories represented in the cart.
	 *
	 * Variation IDs are ignored for taxonomy lookup because product
	 * categories are assigned to parent products.
	 *
	 * @param int[] $cart_product_ids Product and variation IDs in the cart.
	 *
	 * @return int[]
	 */
	private function get_cart_category_ids(
		array $cart_product_ids
	): array {

		$category_ids = array();

		foreach ( $cart_product_ids as $product_id ) {

			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( $product->is_type( 'variation' ) ) {
				continue;
			}

			$product_category_ids = wp_get_post_terms(
				$product_id,
				'product_cat',
				array(
					'fields' => 'ids',
				)
			);

			if (
				is_wp_error( $product_category_ids )
			) {
				continue;
			}

			$category_ids = array_merge(
				$category_ids,
				$product_category_ids
			);
		}

		return $this->eligibility->normalize_product_ids(
			$category_ids
		);
	}

	/**
	 * Query and filter dynamic cross-sell candidates.
	 *
	 * @param int[] $category_ids  Product category IDs.
	 * @param int[] $excluded_ids  Product IDs that must be excluded.
	 * @param int   $limit         Maximum number of dynamic results.
	 *
	 * @return int[]
	 */
	private function get_dynamic_candidate_ids(
		array $category_ids,
		array $excluded_ids,
		int $limit
	): array {

		if ( 0 >= $limit ) {
			return array();
		}

		$candidate_limit = max(
			self::MINIMUM_CANDIDATE_LIMIT,
			$limit * self::CANDIDATE_MULTIPLIER
		);

		$candidate_ids = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $candidate_limit,
				'fields'                 => 'ids',
				'post__not_in'           => $excluded_ids,
				'orderby'                => 'rand',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $category_ids,
						'operator' => 'IN',
					),
				),
			)
		);

		if ( 0 === count( $candidate_ids ) ) {
			return array();
		}

		return $this->eligibility->filter_eligible_ids(
			$candidate_ids,
			$excluded_ids,
			$limit
		);
	}

	/**
	 * Get the maximum number of cross-sell recommendations.
	 *
	 * @return int
	 */
	private function get_result_limit(): int {

		$limit = (int) apply_filters(
			'shurloc_dynamic_cross_sells_limit',
			self::DEFAULT_LIMIT
		);

		return max( 0, $limit );
	}
}
