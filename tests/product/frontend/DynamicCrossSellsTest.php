<?php
/**
 * Tests for dynamic cart cross-sells.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Frontend;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Services\Product_Recommendation_Eligibility_Service;
use Test_WC_Cart;
use Test_WC_Product;
use Test_WC_Product_Variation;
use WC_Cart;
use WC_Product;

/**
 * Tests the dynamic cart cross-sells customization.
 */
final class DynamicCrossSellsTest extends TestCase {

	/**
	 * Dynamic cross-sells class under test.
	 *
	 * @var Dynamic_Cross_Sells
	 */
	private Dynamic_Cross_Sells $cross_sells;

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
		$GLOBALS['shurloc_test_filters']     = array();

		$eligibility =
			new Product_Recommendation_Eligibility_Service();

		$this->cross_sells =
			new Dynamic_Cross_Sells( $eligibility );
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
		$GLOBALS['shurloc_test_filters']     = array();

		parent::tearDown();
	}

	/**
	 * Verify that eligible manual cross-sells are preserved first.
	 *
	 * @return void
	 */
	public function test_manual_cross_sells_are_preserved_first(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 301 );
		new WC_Product( 302 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 301, 302 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201, 202 ),
			$cart
		);

		self::assertSame(
			array( 201, 202, 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that manual cross-sells fill the entire result when enough exist.
	 *
	 * @return void
	 */
	public function test_manual_cross_sells_fill_entire_result(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 203 );
		new WC_Product( 204 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 301 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201, 202, 203, 204 ),
			$cart
		);

		self::assertSame(
			array( 201, 202, 203, 204 ),
			$result
		);
	}

	/**
	 * Verify that excess manual cross-sells are trimmed to the limit.
	 *
	 * @return void
	 */
	public function test_manual_cross_sells_are_trimmed_to_limit(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 202 );
		new WC_Product( 203 );
		new WC_Product( 204 );
		new WC_Product( 205 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201, 202, 203, 204, 205 ),
			$cart
		);

		self::assertSame(
			array( 201, 202, 203, 204 ),
			$result
		);
	}

	/**
	 * Verify that products currently in the cart are excluded.
	 *
	 * @return void
	 */
	public function test_cart_products_are_excluded(): void {

		$first_cart_product  = new WC_Product( 100 );
		$second_cart_product = new WC_Product( 101 );

		new WC_Product( 201 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_terms'][101]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			100,
			101,
			301,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $first_cart_product ),
				$this->create_cart_item( $second_cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 100, 201 ),
			$cart
		);

		self::assertSame(
			array( 201, 301 ),
			$result
		);
	}

	/**
	 * Verify that variations currently in the cart are excluded.
	 *
	 * @return void
	 */
	public function test_cart_variations_are_excluded(): void {

		$parent_product = new WC_Product( 100 );

		$variation = new Test_WC_Product_Variation( 150 );
		$variation->set_type( 'variation' );

		new WC_Product( 201 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			150,
			301,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item(
					$parent_product,
					150
				),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 150, 201 ),
			$cart
		);

		self::assertSame(
			array( 201, 301 ),
			$result
		);
	}

	/**
	 * Verify that zero-quantity cart items are ignored.
	 *
	 * @return void
	 */
	public function test_zero_quantity_cart_items_are_ignored(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item(
					$cart_product,
					0,
					0
				),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify that an empty cart returns no cross-sells.
	 *
	 * @return void
	 */
	public function test_empty_cart_returns_empty_array(): void {

		new WC_Product( 201 );

		$cart = $this->create_cart( array() );

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify that dynamic candidates fill remaining positions.
	 *
	 * @return void
	 */
	public function test_dynamic_candidates_fill_remaining_positions(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 301 );
		new WC_Product( 302 );
		new WC_Product( 303 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			301,
			302,
			303,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array( 201, 301, 302, 303 ),
			$result
		);
	}

	/**
	 * Verify that dynamic candidates are returned without manual cross-sells.
	 *
	 * @return void
	 */
	public function test_dynamic_candidates_are_used_without_manual_cross_sells(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 301 );
		new WC_Product( 302 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array( 301, 302 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that products from multiple cart categories can be considered.
	 *
	 * @return void
	 */
	public function test_categories_from_multiple_cart_products_are_used(): void {

		$first_product  = new WC_Product( 100 );
		$second_product = new WC_Product( 101 );

		new WC_Product( 301 );
		new WC_Product( 302 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_terms'][101]['product_cat'] = array( 20 );
		$GLOBALS['shurloc_test_product_ids']               = array( 301, 302 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $first_product ),
				$this->create_cart_item( $second_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that duplicate category IDs do not affect the result.
	 *
	 * @return void
	 */
	public function test_duplicate_category_ids_are_normalized(): void {

		$first_product  = new WC_Product( 100 );
		$second_product = new WC_Product( 101 );

		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_terms'][101]['product_cat'] = array(
			10,
			10,
		);

		$GLOBALS['shurloc_test_product_ids'] = array( 301 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $first_product ),
				$this->create_cart_item( $second_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 301 ),
			$result
		);
	}

	/**
	 * Verify that manual cross-sells already selected are not duplicated by
	 * dynamic results.
	 *
	 * @return void
	 */
	public function test_manual_cross_sells_are_not_duplicated_dynamically(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			201,
			301,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array( 201, 301 ),
			$result
		);
	}

	/**
	 * Verify that duplicate manual cross-sells are removed.
	 *
	 * @return void
	 */
	public function test_duplicate_manual_cross_sells_are_removed(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 202 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201, 201, 202, 201 ),
			$cart
		);

		self::assertSame(
			array( 201, 202 ),
			$result
		);
	}

	/**
	 * Verify that hidden, unpublished, and out-of-stock manual products are
	 * excluded.
	 *
	 * @return void
	 */
	public function test_ineligible_manual_cross_sells_are_excluded(): void {

		$cart_product = new WC_Product( 100 );

		$hidden = new Test_WC_Product( 201 );
		$hidden->set_visible( false );

		$draft = new WC_Product( 202 );
		$draft->set_status( 'draft' );

		$out_of_stock = new WC_Product( 203 );
		$out_of_stock->set_stock_status( 'outofstock' );

		new WC_Product( 204 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201, 202, 203, 204 ),
			$cart
		);

		self::assertSame(
			array( 204 ),
			$result
		);
	}

	/**
	 * Verify that hidden, unpublished, and out-of-stock dynamic products are
	 * excluded.
	 *
	 * @return void
	 */
	public function test_ineligible_dynamic_candidates_are_excluded(): void {

		$cart_product = new WC_Product( 100 );

		$hidden = new Test_WC_Product( 301 );
		$hidden->set_visible( false );

		$draft = new WC_Product( 302 );
		$draft->set_status( 'draft' );

		$out_of_stock = new WC_Product( 303 );
		$out_of_stock->set_stock_status( 'outofstock' );

		new WC_Product( 304 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			301,
			302,
			303,
			304,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 304 ),
			$result
		);
	}

	/**
	 * Verify that missing manual products are excluded.
	 *
	 * @return void
	 */
	public function test_missing_manual_products_are_excluded(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201, 999 ),
			$cart
		);

		self::assertSame(
			array( 201 ),
			$result
		);
	}

	/**
	 * Verify that missing dynamic products are excluded.
	 *
	 * @return void
	 */
	public function test_missing_dynamic_products_are_excluded(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 301 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			301,
			999,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 301 ),
			$result
		);
	}

	/**
	 * Verify that no dynamic products are added when the cart products have no
	 * categories.
	 *
	 * @return void
	 */
	public function test_manual_results_are_returned_when_cart_has_no_categories(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );
		new WC_Product( 301 );

		$GLOBALS['shurloc_test_product_ids'] = array( 301 );

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array( 201 ),
			$result
		);
	}

	/**
	 * Verify that the default result limit is four.
	 *
	 * @return void
	 */
	public function test_default_result_limit_is_four(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 301 );
		new WC_Product( 302 );
		new WC_Product( 303 );
		new WC_Product( 304 );
		new WC_Product( 305 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			301,
			302,
			303,
			304,
			305,
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 301, 302, 303, 304 ),
			$result
		);
	}

	/**
	 * Verify that the result limit can be filtered.
	 *
	 * @return void
	 */
	public function test_result_limit_can_be_filtered(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 301 );
		new WC_Product( 302 );
		new WC_Product( 303 );

		$GLOBALS['shurloc_test_terms'][100]['product_cat'] = array( 10 );
		$GLOBALS['shurloc_test_product_ids']               = array(
			301,
			302,
			303,
		);

		add_filter(
			'shurloc_dynamic_cross_sells_limit',
			static function (): int {
				return 2;
			}
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array(),
			$cart
		);

		self::assertSame(
			array( 301, 302 ),
			$result
		);
	}

	/**
	 * Verify that a zero filtered limit returns no cross-sells.
	 *
	 * @return void
	 */
	public function test_zero_filtered_limit_returns_empty_array(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );

		add_filter(
			'shurloc_dynamic_cross_sells_limit',
			static function (): int {
				return 0;
			}
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify that a negative filtered limit is treated as zero.
	 *
	 * @return void
	 */
	public function test_negative_filtered_limit_is_treated_as_zero(): void {

		$cart_product = new WC_Product( 100 );

		new WC_Product( 201 );

		add_filter(
			'shurloc_dynamic_cross_sells_limit',
			static function (): int {
				return -5;
			}
		);

		$cart = $this->create_cart(
			array(
				$this->create_cart_item( $cart_product ),
			)
		);

		$result = $this->cross_sells->filter_cross_sell_ids(
			array( 201 ),
			$cart
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Create a WooCommerce cart test double.
	 *
	 * @param array<int,array<string,mixed>> $items Cart items.
	 *
	 * @return WC_Cart
	 */
	private function create_cart(
		array $items
	): WC_Cart {

		$cart = new Test_WC_Cart();
		$cart->set_cart( $items );

		return $cart;
	}

	/**
	 * Create a WooCommerce cart item.
	 *
	 * @param WC_Product $product      Parent product.
	 * @param int        $variation_id Variation ID.
	 * @param int        $quantity     Cart quantity.
	 *
	 * @return array<string,mixed>
	 */
	private function create_cart_item(
		WC_Product $product,
		int $variation_id = 0,
		int $quantity = 1
	): array {

		return array(
			'product_id'   => $product->get_id(),
			'variation_id' => $variation_id,
			'quantity'     => $quantity,
			'data'         => $product,
		);
	}
}
