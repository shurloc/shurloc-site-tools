<?php
/**
 * Product tag pagination integration.
 *
 * Controls the number of products displayed on WooCommerce product tag
 * archive pages.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Controls product tag archive pagination.
 */
final class Product_Tag_Pagination_Integration {

	/**
	 * Number of products displayed per product tag archive page.
	 *
	 * @var int
	 */
	private const PRODUCTS_PER_PAGE = 96;

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'loop_shop_per_page',
			array(
				$this,
				'filter_products_per_page',
			),
			PHP_INT_MAX
		);
	}

	/**
	 * Set the number of products displayed on product tag archives.
	 *
	 * Other WooCommerce archive types retain their existing products-per-page
	 * value.
	 *
	 * @param int $per_page Current number of products per page.
	 * @return int
	 */
	public function filter_products_per_page(
		int $per_page
	): int {

		if ( ! is_product_tag() ) {
			return $per_page;
		}

		return self::PRODUCTS_PER_PAGE;
	}
}
