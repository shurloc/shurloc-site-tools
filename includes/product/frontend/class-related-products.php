<?php
/**
 * Related products customization.
 *
 * Prioritizes eligible products that share product tags with the current
 * product, then fills remaining positions with WooCommerce's original
 * related-product recommendations.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Frontend;

use Shurloc\SiteTools\Product\Services\Product_Recommendation_Eligibility_Service;
use WC_Product;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Builds customized WooCommerce related-product recommendations.
 */
final class Related_Products {

	/**
	 * Related-products cache key prefix.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'shurloc_related_products_';

	/**
	 * Cache-generation option name.
	 *
	 * @var string
	 */
	private const CACHE_GENERATION_OPTION =
		'shurloc_related_products_cache_generation';

	/**
	 * Related-products cache lifetime.
	 *
	 * @var int
	 */
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Number of tag-based candidates to query per requested result.
	 *
	 * A larger candidate pool allows the eligibility service to discard
	 * hidden or out-of-stock products without immediately exhausting results.
	 *
	 * @var int
	 */
	private const CANDIDATE_MULTIPLIER = 5;

	/**
	 * Minimum number of tag-based candidates to query.
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
	 * Whether the cache generation has already been incremented this request.
	 *
	 * Several WooCommerce hooks can fire for one product update. This guard
	 * prevents unnecessary repeated generation increments during one request.
	 *
	 * @var bool
	 */
	private bool $cache_invalidated = false;

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
	 * Register WordPress and WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'woocommerce_related_products',
			array( $this, 'filter_related_products' ),
			20,
			3
		);

		add_action(
			'save_post_product',
			array( $this, 'invalidate_cache_after_product_save' ),
			20,
			3
		);

		add_action(
			'set_object_terms',
			array( $this, 'invalidate_cache_after_term_change' ),
			20,
			4
		);

		add_action(
			'woocommerce_product_set_stock',
			array( $this, 'invalidate_cache_after_stock_change' )
		);

		add_action(
			'woocommerce_variation_set_stock',
			array( $this, 'invalidate_cache_after_stock_change' )
		);

		add_action(
			'woocommerce_product_set_stock_status',
			array( $this, 'invalidate_cache_after_stock_status_change' ),
			20,
			3
		);

		add_action(
			'woocommerce_variation_set_stock_status',
			array( $this, 'invalidate_cache_after_stock_status_change' ),
			20,
			3
		);
	}

	/**
	 * Customize WooCommerce related products.
	 *
	 * Selection order:
	 *
	 * 1. Eligible products sharing a tag with the current product.
	 * 2. Eligible IDs from WooCommerce's original related-product results.
	 *
	 * The current product and its upsells are excluded from both groups.
	 *
	 * @param int[]                $related_posts WooCommerce related-product IDs.
	 * @param int                  $product_id    Current product ID.
	 * @param array<string, mixed> $args          Related-products arguments.
	 *
	 * @return int[]
	 */
	public function filter_related_products(
		array $related_posts,
		int $product_id,
		array $args
	): array {

		$limit = $this->get_result_limit( $args );

		if ( 0 === $limit ) {
			return array();
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return $related_posts;
		}

		$cache_key = $this->get_cache_key(
			$product_id,
			$limit
		);

		$cached_ids = get_transient( $cache_key );

		if ( false !== $cached_ids && is_array( $cached_ids ) ) {
			return $this->eligibility->normalize_product_ids(
				$cached_ids
			);
		}

		$excluded_ids = array_merge(
			array( $product_id ),
			$product->get_upsell_ids()
		);

		$excluded_ids = $this->eligibility->normalize_product_ids(
			$excluded_ids
		);

		$results = $this->get_tagged_products(
			$product_id,
			$excluded_ids,
			$limit
		);

		$remaining_limit = $limit - count( $results );

		if ( 0 < $remaining_limit ) {

			$fallback_exclusions = array_merge(
				$excluded_ids,
				$results
			);

			$fallback_ids = $this->eligibility->filter_eligible_ids(
				$related_posts,
				$fallback_exclusions,
				$remaining_limit
			);

			$results = array_merge(
				$results,
				$fallback_ids
			);
		}

		$results = array_slice(
			$this->eligibility->normalize_product_ids( $results ),
			0,
			$limit
		);

		set_transient(
			$cache_key,
			$results,
			self::CACHE_TTL
		);

		return $results;
	}

	/**
	 * Invalidate related-products caches after a product is saved.
	 *
	 * @param int     $post_id Product post ID.
	 * @param WP_Post $post    Product post.
	 * @param bool    $update  Whether an existing post is being updated.
	 *
	 * @return void
	 */
	public function invalidate_cache_after_product_save(
		int $post_id,
		WP_Post $post,
		bool $update
	): void {

		unset( $update );

		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'product' !== $post->post_type ) {
			return;
		}

		$this->invalidate_cache();
	}

	/**
	 * Invalidate caches after product tags or categories change.
	 *
	 * Changing one product's taxonomy can affect the recommendations shown
	 * for every other product sharing those terms.
	 *
	 * @param int          $object_id Product object ID.
	 * @param string|int[] $terms     Assigned terms.
	 * @param int[]        $tt_ids    Term-taxonomy IDs.
	 * @param string       $taxonomy  Taxonomy name.
	 *
	 * @return void
	 */
	public function invalidate_cache_after_term_change(
		int $object_id,
		$terms,
		array $tt_ids,
		string $taxonomy
	): void {

		unset( $terms, $tt_ids );

		if (
			'product_tag' !== $taxonomy &&
			'product_cat' !== $taxonomy
		) {
			return;
		}

		if ( 'product' !== get_post_type( $object_id ) ) {
			return;
		}

		$this->invalidate_cache();
	}

	/**
	 * Invalidate caches after product or variation stock changes.
	 *
	 * @param WC_Product $product Updated product.
	 *
	 * @return void
	 */
	public function invalidate_cache_after_stock_change(
		WC_Product $product
	): void {

		unset( $product );

		$this->invalidate_cache();
	}

	/**
	 * Invalidate caches after a stock-status change.
	 *
	 * WooCommerce passes the product ID, stock status, and product object.
	 * None of those values need to be inspected because any stock-status
	 * change can affect recommendations across the catalog.
	 *
	 * @param int        $product_id  Product or variation ID.
	 * @param string     $stock_status New stock status.
	 * @param WC_Product $product     Updated product.
	 *
	 * @return void
	 */
	public function invalidate_cache_after_stock_status_change(
		int $product_id,
		string $stock_status,
		WC_Product $product
	): void {

		unset( $product_id, $stock_status, $product );

		$this->invalidate_cache();
	}

	/**
	 * Get eligible products sharing tags with the current product.
	 *
	 * @param int   $product_id  Current product ID.
	 * @param int[] $excluded_ids Product IDs that must be excluded.
	 * @param int   $limit       Maximum number of results.
	 *
	 * @return int[]
	 */
	private function get_tagged_products(
		int $product_id,
		array $excluded_ids,
		int $limit
	): array {

		$tag_ids = wp_get_post_terms(
			$product_id,
			'product_tag',
			array(
				'fields' => 'ids',
			)
		);

		if ( is_wp_error( $tag_ids ) || empty( $tag_ids ) ) {
			return array();
		}

		$tag_ids = $this->eligibility->normalize_product_ids(
			$tag_ids
		);

		if ( empty( $tag_ids ) ) {
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
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'product_tag',
						'field'    => 'term_id',
						'terms'    => $tag_ids,
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
	 * Get the requested related-products result limit.
	 *
	 * @param array<string, mixed> $args Related-products arguments.
	 *
	 * @return int
	 */
	private function get_result_limit( array $args ): int {

		if ( ! isset( $args['posts_per_page'] ) ) {
			return 4;
		}

		return max(
			0,
			(int) $args['posts_per_page']
		);
	}

	/**
	 * Build a generation-based related-products transient key.
	 *
	 * Incrementing the generation makes all previously generated keys
	 * unreachable without querying or deleting transient rows directly.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Result limit.
	 *
	 * @return string
	 */
	private function get_cache_key(
		int $product_id,
		int $limit
	): string {

		return sprintf(
			'%s%d_%d_%d',
			self::CACHE_PREFIX,
			$this->get_cache_generation(),
			$product_id,
			$limit
		);
	}

	/**
	 * Get the current cache generation.
	 *
	 * @return int
	 */
	private function get_cache_generation(): int {

		$generation = (int) get_option(
			self::CACHE_GENERATION_OPTION,
			1
		);

		return max( 1, $generation );
	}

	/**
	 * Invalidate every related-products cache entry.
	 *
	 * Old transient records are allowed to expire naturally. Incrementing the
	 * generation prevents them from being read again.
	 *
	 * @return void
	 */
	private function invalidate_cache(): void {

		if ( $this->cache_invalidated ) {
			return;
		}

		$generation = $this->get_cache_generation();

		update_option(
			self::CACHE_GENERATION_OPTION,
			$generation + 1,
			false
		);

		$this->cache_invalidated = true;
	}
}
