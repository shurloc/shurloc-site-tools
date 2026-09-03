<?php
/**
 * WooCommerce product breadcrumb customization.
 *
 * Adds the Products breadcrumb and selects the canonical product category
 * used in the visible WooCommerce breadcrumb trail.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Frontend;

use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Customizes the visible WooCommerce product breadcrumb trail.
 */
final class Product_Breadcrumbs {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'woocommerce_get_breadcrumb',
			array( $this, 'add_products_breadcrumb' ),
			20
		);

		add_filter(
			'woocommerce_breadcrumb_main_term',
			array( $this, 'select_breadcrumb_category' ),
			20,
			2
		);
	}

	/**
	 * Insert the Products breadcrumb immediately after Home.
	 *
	 * @param array<int, array<int, string>> $crumbs Breadcrumb items.
	 *
	 * @return array<int, array<int, string>>
	 */
	public function add_products_breadcrumb( array $crumbs ): array {

		if ( ! $this->is_product_page() ) {
			return $crumbs;
		}

		if ( empty( $crumbs ) ) {
			return $crumbs;
		}

		$products_crumb = array(
			__( 'Products', 'shurloc-site-tools' ),
			$this->get_products_url(),
		);

		if ( $this->contains_products_crumb( $crumbs, $products_crumb[1] ) ) {
			return $crumbs;
		}

		/*
		 * Insert Products immediately after Home.
		 *
		 * If WooCommerce unexpectedly omits Home, array_splice() still inserts
		 * Products after the first existing breadcrumb item.
		 */
		array_splice(
			$crumbs,
			1,
			0,
			array( $products_crumb )
		);

		return $crumbs;
	}

	/**
	 * Select the canonical product category for WooCommerce breadcrumbs.
	 *
	 * WooCommerce automatically includes the selected category's ancestors,
	 * so this filter only needs to select the correct leaf category.
	 *
	 * Selection order:
	 *
	 * 1. Yoast primary product category, when assigned to the product.
	 * 2. Deepest assigned product category.
	 * 3. Product count, as a tie-breaker.
	 * 4. Lowest term ID, as a deterministic final tie-breaker.
	 *
	 * @param WP_Term             $main_term Category selected by WooCommerce.
	 * @param array<int, WP_Term> $terms     Categories assigned to the product.
	 *
	 * @return WP_Term
	 */
	public function select_breadcrumb_category(
		WP_Term $main_term,
		array $terms
	): WP_Term {

		if ( ! $this->is_product_page() ) {
			return $main_term;
		}

		if ( empty( $terms ) ) {
			return $main_term;
		}

		$product_id = get_queried_object_id();

		if ( 0 === $product_id ) {
			return $main_term;
		}

		$primary_term = $this->get_primary_category(
			$product_id,
			$terms
		);

		if ( $primary_term instanceof WP_Term ) {
			return $primary_term;
		}

		$deepest_term = $this->get_deepest_category( $terms );

		if ( $deepest_term instanceof WP_Term ) {
			return $deepest_term;
		}

		return $main_term;
	}

	/**
	 * Determine whether the breadcrumb trail already contains Products.
	 *
	 * @param array<int, array<int, string>> $crumbs       Breadcrumb items.
	 * @param string                         $products_url Products page URL.
	 *
	 * @return bool
	 */
	private function contains_products_crumb(
		array $crumbs,
		string $products_url
	): bool {

		$normalized_products_url = untrailingslashit( $products_url );

		foreach ( $crumbs as $crumb ) {

			if (
				! isset( $crumb[1] )
			) {
				continue;
			}

			if (
				untrailingslashit( $crumb[1] ) === $normalized_products_url
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the Yoast primary product category when it is assigned to the product.
	 *
	 * @param int                 $product_id Product ID.
	 * @param array<int, WP_Term> $terms      Assigned product categories.
	 *
	 * @return WP_Term|null
	 */
	private function get_primary_category(
		int $product_id,
		array $terms
	): ?WP_Term {

		$primary_id = (int) get_post_meta(
			$product_id,
			'_yoast_wpseo_primary_product_cat',
			true
		);

		if ( 0 === $primary_id ) {
			return null;
		}

		foreach ( $terms as $term ) {

			if ( 'product_cat' !== $term->taxonomy ) {
				continue;
			}

			if ( $primary_id === (int) $term->term_id ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Select the deepest assigned product category.
	 *
	 * Product count and term ID are used as deterministic tie-breakers.
	 *
	 * @param array<int, WP_Term> $terms Assigned product categories.
	 *
	 * @return WP_Term|null
	 */
	private function get_deepest_category( array $terms ): ?WP_Term {

		$selected   = null;
		$best_depth = -1;
		$best_count = -1;

		foreach ( $terms as $term ) {

			if ( 'product_cat' !== $term->taxonomy ) {
				continue;
			}

			$depth = count(
				get_ancestors(
					$term->term_id,
					'product_cat',
					'taxonomy'
				)
			);

			$count = (int) $term->count;

			if (
				$depth > $best_depth ||
				(
					$best_depth === $depth &&
					$count > $best_count
				) ||
				(
					$best_depth === $depth &&
					$best_count === $count &&
					(
						null === $selected ||
						$term->term_id < $selected->term_id
					)
				)
			) {
				$selected   = $term;
				$best_depth = $depth;
				$best_count = $count;
			}
		}

		return $selected;
	}

	/**
	 * Get the Products breadcrumb URL.
	 *
	 * Uses the configured WooCommerce shop page when available and falls
	 * back to the site's /shop/ URL.
	 *
	 * @return string
	 */
	private function get_products_url(): string {

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url = wc_get_page_permalink( 'shop' );

			if ( '' !== $shop_url ) {
				return $shop_url;
			}
		}

		return home_url( '/shop/' );
	}

	/**
	 * Determine whether the current request is a product page.
	 *
	 * @return bool
	 */
	private function is_product_page(): bool {
		return function_exists( 'is_product' ) && is_product();
	}
}
