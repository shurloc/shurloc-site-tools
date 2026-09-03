<?php
/**
 * Tests for the related products customization.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Frontend;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Services\Product_Recommendation_Eligibility_Service;
use Test_WC_Product;
use WC_Product;
use WP_Post;

/**
 * Tests the related-products customization.
 */
final class RelatedProductsTest extends TestCase {

	/**
	 * Related-products class under test.
	 *
	 * @var Related_Products
	 */
	private Related_Products $related_products;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_products']    = array();
		$GLOBALS['shurloc_test_product_ids'] = array();
		$GLOBALS['shurloc_test_terms']       = array();
		$GLOBALS['shurloc_test_transients']  = array();
		$GLOBALS['shurloc_test_options']     = array();
		$GLOBALS['shurloc_test_post_types']  = array();
		$GLOBALS['shurloc_test_autosaves']   = array();
		$GLOBALS['shurloc_test_revisions']   = array();

		$eligibility =
			new Product_Recommendation_Eligibility_Service();

		$this->related_products =
			new Related_Products( $eligibility );
	}

	/**
	 * Clear test state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_products']    = array();
		$GLOBALS['shurloc_test_product_ids'] = array();
		$GLOBALS['shurloc_test_terms']       = array();
		$GLOBALS['shurloc_test_transients']  = array();
		$GLOBALS['shurloc_test_options']     = array();
		$GLOBALS['shurloc_test_post_types']  = array();
		$GLOBALS['shurloc_test_autosaves']   = array();
		$GLOBALS['shurloc_test_revisions']   = array();

		parent::tearDown();
	}

	/**
	 * Verify that tagged products are prioritized.
	 *
	 * @return void
	 */
	public function test_tagged_products_are_prioritized(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 301 );
		new WC_Product( 302 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201, 202 );

		$result = $this->related_products->filter_related_products(
			array( 301, 302 ),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 201, 202, 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that fallback products fill remaining positions.
	 *
	 * @return void
	 */
	public function test_fallback_products_fill_remaining_positions(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 301 );
		new WC_Product( 302 );
		new WC_Product( 303 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201 );

		$result = $this->related_products->filter_related_products(
			array( 301, 302, 303 ),
			100,
			array(
				'posts_per_page' => 3,
			)
		);

		self::assertSame(
			array( 201, 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that fallback products are used when no tags are assigned.
	 *
	 * @return void
	 */
	public function test_fallback_products_are_used_when_product_has_no_tags(): void {

		new WC_Product( 100 );
		new WC_Product( 301 );
		new WC_Product( 302 );

		$result = $this->related_products->filter_related_products(
			array( 301, 302 ),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that the current product is excluded.
	 *
	 * @return void
	 */
	public function test_current_product_is_excluded(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 100, 201 );

		$result = $this->related_products->filter_related_products(
			array( 100, 301 ),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 201, 301 ),
			$result
		);
	}

	/**
	 * Verify that assigned upsells are excluded.
	 *
	 * @return void
	 */
	public function test_upsells_are_excluded(): void {

		$product = new WC_Product( 100 );
		$product->set_upsell_ids( array( 201, 301 ) );

		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 301 );
		new WC_Product( 302 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201, 202 );

		$result = $this->related_products->filter_related_products(
			array( 301, 302 ),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 202, 302 ),
			$result
		);
	}

	/**
	 * Verify that hidden, unpublished, and out-of-stock products are excluded.
	 *
	 * @return void
	 */
	public function test_ineligible_products_are_excluded(): void {

		new WC_Product( 100 );

		$hidden = new Test_WC_Product( 201 );
		$hidden->set_visible( false );

		$out_of_stock = new WC_Product( 202 );
		$out_of_stock->set_stock_status( 'outofstock' );

		$draft = new WC_Product( 301 );
		$draft->set_status( 'draft' );

		new WC_Product( 302 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201, 202 );

		$result = $this->related_products->filter_related_products(
			array( 301, 302 ),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 302 ),
			$result
		);
	}

	/**
	 * Verify that duplicate tagged and fallback products are removed.
	 *
	 * @return void
	 */
	public function test_duplicate_products_are_removed(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201, 202 );

		$result = $this->related_products->filter_related_products(
			array( 201, 202, 301 ),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 201, 202, 301 ),
			$result
		);
	}

	/**
	 * Verify that the requested result limit is respected.
	 *
	 * @return void
	 */
	public function test_result_limit_is_respected(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 203 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			201,
			202,
			203,
		);

		$result = $this->related_products->filter_related_products(
			array( 301 ),
			100,
			array(
				'posts_per_page' => 2,
			)
		);

		self::assertSame(
			array( 201, 202 ),
			$result
		);
	}

	/**
	 * Verify that a zero result limit returns no recommendations.
	 *
	 * @return void
	 */
	public function test_zero_limit_returns_empty_array(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );

		$result = $this->related_products->filter_related_products(
			array( 201 ),
			100,
			array(
				'posts_per_page' => 0,
			)
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify that the default result limit is four.
	 *
	 * @return void
	 */
	public function test_default_limit_is_four(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 203 );
		new WC_Product( 204 );
		new WC_Product( 205 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			201,
			202,
			203,
			204,
			205,
		);

		$result = $this->related_products->filter_related_products(
			array(),
			100,
			array()
		);

		self::assertSame(
			array( 201, 202, 203, 204 ),
			$result
		);
	}

	/**
	 * Verify that the original WooCommerce result is returned when the source
	 * product cannot be loaded.
	 *
	 * @return void
	 */
	public function test_original_results_are_returned_for_missing_product(): void {

		$result = $this->related_products->filter_related_products(
			array( 301, 302 ),
			999,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that generated results are stored in a transient.
	 *
	 * @return void
	 */
	public function test_results_are_cached(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201 );

		$this->related_products->filter_related_products(
			array(),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertArrayHasKey(
			'shurloc_related_products_1_100_4',
			$GLOBALS['shurloc_test_transients']
		);

		self::assertSame(
			array( 201 ),
			$GLOBALS['shurloc_test_transients']
				['shurloc_related_products_1_100_4']
		);
	}

	/**
	 * Verify that cached recommendations are returned without rebuilding.
	 *
	 * @return void
	 */
	public function test_cached_results_are_returned(): void {

		new WC_Product( 100 );
		new WC_Product( 201 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_transients']
			['shurloc_related_products_1_100_4'] = array( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_tag'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 201 );

		$result = $this->related_products->filter_related_products(
			array(),
			100,
			array(
				'posts_per_page' => 4,
			)
		);

		self::assertSame(
			array( 301 ),
			$result
		);
	}

	/**
	 * Verify that cached product IDs are normalized.
	 *
	 * @return void
	 */
	public function test_cached_results_are_normalized(): void {

		new WC_Product( 100 );

		$GLOBALS['shurloc_test_transients']
			['shurloc_related_products_1_100_4'] = array(
				'301',
				301,
				0,
				302,
			);

			$result = $this->related_products->filter_related_products(
				array(),
				100,
				array(
					'posts_per_page' => 4,
				)
			);

		self::assertSame(
			array( 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that invalidating the cache increments the generation.
	 *
	 * @return void
	 */
	public function test_cache_invalidation_increments_generation(): void {

		$product = new WC_Product( 100 );

		$this->related_products->invalidate_cache_after_stock_change(
			$product
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}

	/**
	 * Verify that several hooks only invalidate the cache once per request.
	 *
	 * @return void
	 */
	public function test_cache_is_invalidated_only_once_per_request(): void {

		$product = new WC_Product( 100 );

		$this->related_products->invalidate_cache_after_stock_change(
			$product
		);

		$this->related_products->invalidate_cache_after_stock_change(
			$product
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}

	/**
	 * Verify that invalidation increments an existing generation.
	 *
	 * @return void
	 */
	public function test_cache_invalidation_increments_existing_generation(): void {

		$GLOBALS['shurloc_test_options']
			['shurloc_related_products_cache_generation'] = 7;

		$product = new WC_Product( 100 );

		$this->related_products->invalidate_cache_after_stock_change(
			$product
		);

		self::assertSame(
			8,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}

	/**
	 * Verify that unrelated taxonomy changes do not invalidate the cache.
	 *
	 * @return void
	 */
	public function test_unrelated_taxonomy_does_not_invalidate_cache(): void {

		$GLOBALS['shurloc_test_post_types'][100] = 'product';

		$this->related_products->invalidate_cache_after_term_change(
			100,
			array(),
			array(),
			'post_tag'
		);

		self::assertArrayNotHasKey(
			'shurloc_related_products_cache_generation',
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify that a product-tag change invalidates the cache.
	 *
	 * @return void
	 */
	public function test_product_tag_change_invalidates_cache(): void {

		$GLOBALS['shurloc_test_post_types'][100] = 'product';

		$this->related_products->invalidate_cache_after_term_change(
			100,
			array( 10 ),
			array( 10 ),
			'product_tag'
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}

	/**
	 * Verify that a product-category change invalidates the cache.
	 *
	 * @return void
	 */
	public function test_product_category_change_invalidates_cache(): void {

		$GLOBALS['shurloc_test_post_types'][100] = 'product';

		$this->related_products->invalidate_cache_after_term_change(
			100,
			array( 10 ),
			array( 10 ),
			'product_cat'
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}

	/**
	 * Verify that a term change for a non-product does not invalidate.
	 *
	 * @return void
	 */
	public function test_non_product_term_change_does_not_invalidate_cache(): void {

		$GLOBALS['shurloc_test_post_types'][100] = 'post';

		$this->related_products->invalidate_cache_after_term_change(
			100,
			array( 10 ),
			array( 10 ),
			'product_tag'
		);

		self::assertArrayNotHasKey(
			'shurloc_related_products_cache_generation',
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify that a normal product save invalidates the cache.
	 *
	 * @return void
	 */
	public function test_product_save_invalidates_cache(): void {

		$post = new WP_Post(
			(object) array(
				'ID'        => 123,
				'post_type' => 'product',
			)
		);

		$this->related_products->invalidate_cache_after_product_save(
			100,
			$post,
			true
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}

	/**
	 * Verify that a non-product save does not invalidate the cache.
	 *
	 * @return void
	 */
	public function test_non_product_save_does_not_invalidate_cache(): void {

		$post = new WP_Post(
			(object) array(
				'ID'        => 123,
				'post_type' => 'post',
			)
		);

		$this->related_products->invalidate_cache_after_product_save(
			100,
			$post,
			true
		);

		self::assertArrayNotHasKey(
			'shurloc_related_products_cache_generation',
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify that product autosaves do not invalidate the cache.
	 *
	 * @return void
	 */
	public function test_product_autosave_does_not_invalidate_cache(): void {

		$post = new WP_Post(
			(object) array(
				'ID'        => 123,
				'post_type' => 'product',
			)
		);

		$GLOBALS['shurloc_test_autosaves'][] = 100;

		$this->related_products->invalidate_cache_after_product_save(
			100,
			$post,
			true
		);

		self::assertArrayNotHasKey(
			'shurloc_related_products_cache_generation',
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify that product revisions do not invalidate the cache.
	 *
	 * @return void
	 */
	public function test_product_revision_does_not_invalidate_cache(): void {

		$post = new WP_Post(
			(object) array(
				'ID'        => 123,
				'post_type' => 'product',
			)
		);

		$GLOBALS['shurloc_test_revisions'][] = 100;

		$this->related_products->invalidate_cache_after_product_save(
			100,
			$post,
			true
		);

		self::assertArrayNotHasKey(
			'shurloc_related_products_cache_generation',
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify that stock-status changes invalidate the cache.
	 *
	 * @return void
	 */
	public function test_stock_status_change_invalidates_cache(): void {

		$product = new WC_Product( 100 );

		$this->related_products->invalidate_cache_after_stock_status_change(
			100,
			'outofstock',
			$product
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_options']
				['shurloc_related_products_cache_generation']
		);
	}
}
