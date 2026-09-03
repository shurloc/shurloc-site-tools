<?php
/**
 * Tests for the product recommendation eligibility service.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Services;

use PHPUnit\Framework\TestCase;
use Test_WC_Product;
use WC_Product;

/**
 * Tests the product recommendation eligibility service.
 */
final class ProductRecommendationEligibilityServiceTest extends TestCase {

	/**
	 * Service under test.
	 *
	 * @var Product_Recommendation_Eligibility_Service
	 */
	private Product_Recommendation_Eligibility_Service $service;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_products'] = array();

		$this->service =
			new Product_Recommendation_Eligibility_Service();
	}

	/**
	 * Clear registered products after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_products'] = array();

		parent::tearDown();
	}

	/**
	 * Verify that an eligible product object is accepted.
	 *
	 * @return void
	 */
	public function test_eligible_product_object_is_accepted(): void {

		$product = new WC_Product( 101 );

		self::assertTrue(
			$this->service->is_eligible( $product )
		);
	}

	/**
	 * Verify that an eligible product ID is resolved and accepted.
	 *
	 * @return void
	 */
	public function test_eligible_product_id_is_accepted(): void {

		new WC_Product( 101 );

		self::assertTrue(
			$this->service->is_eligible( 101 )
		);
	}

	/**
	 * Verify that an unknown product ID is rejected.
	 *
	 * @return void
	 */
	public function test_unknown_product_id_is_rejected(): void {

		self::assertFalse(
			$this->service->is_eligible( 999 )
		);
	}

	/**
	 * Verify that a product with an invalid ID is rejected.
	 *
	 * @return void
	 */
	public function test_product_with_zero_id_is_rejected(): void {

		$product = new WC_Product( 0 );

		self::assertFalse(
			$this->service->is_eligible( $product )
		);
	}

	/**
	 * Verify that an excluded product is rejected.
	 *
	 * @return void
	 */
	public function test_excluded_product_is_rejected(): void {

		$product = new WC_Product( 101 );

		self::assertFalse(
			$this->service->is_eligible(
				$product,
				array( 101 )
			)
		);
	}

	/**
	 * Verify that a non-published product is rejected.
	 *
	 * @return void
	 */
	public function test_non_published_product_is_rejected(): void {

		$product = new WC_Product( 101 );
		$product->set_status( 'draft' );

		self::assertFalse(
			$this->service->is_eligible( $product )
		);
	}

	/**
	 * Verify that a hidden product is rejected.
	 *
	 * @return void
	 */
	public function test_hidden_product_is_rejected(): void {

		$product = new Test_WC_Product( 101 );
		$product->set_visible( false );

		self::assertFalse(
			$this->service->is_eligible( $product )
		);
	}

	/**
	 * Verify that an out-of-stock product is rejected.
	 *
	 * @return void
	 */
	public function test_out_of_stock_product_is_rejected(): void {

		$product = new WC_Product( 101 );
		$product->set_stock_status( 'outofstock' );

		self::assertFalse(
			$this->service->is_eligible( $product )
		);
	}

	/**
	 * Verify that product IDs are normalized.
	 *
	 * @return void
	 */
	public function test_product_ids_are_normalized(): void {

		$result = $this->service->normalize_product_ids(
			array(
				'10',
				10,
				-20,
				0,
				'',
				30,
				'30',
				null,
			)
		);

		self::assertSame(
			array( 10, 20, 30 ),
			$result
		);
	}

	/**
	 * Verify that normalization preserves original order.
	 *
	 * @return void
	 */
	public function test_normalization_preserves_order(): void {

		$result = $this->service->normalize_product_ids(
			array( 30, 10, 20, 10, 30 )
		);

		self::assertSame(
			array( 30, 10, 20 ),
			$result
		);
	}

	/**
	 * Verify that a selected product is detected.
	 *
	 * @return void
	 */
	public function test_selected_product_is_detected(): void {

		self::assertTrue(
			$this->service->is_selected(
				20,
				array( 10, 20, 30 )
			)
		);
	}

	/**
	 * Verify that an unselected product is not detected.
	 *
	 * @return void
	 */
	public function test_unselected_product_is_not_detected(): void {

		self::assertFalse(
			$this->service->is_selected(
				40,
				array( 10, 20, 30 )
			)
		);
	}

	/**
	 * Verify that an invalid product ID is not considered selected.
	 *
	 * @return void
	 */
	public function test_invalid_product_id_is_not_selected(): void {

		self::assertFalse(
			$this->service->is_selected(
				0,
				array( 0 )
			)
		);
	}

	/**
	 * Verify that eligible IDs preserve candidate order.
	 *
	 * @return void
	 */
	public function test_filter_preserves_eligible_product_order(): void {

		new WC_Product( 101 );
		new WC_Product( 102 );
		new WC_Product( 103 );

		$result = $this->service->filter_eligible_ids(
			array( 103, 101, 102 )
		);

		self::assertSame(
			array( 103, 101, 102 ),
			$result
		);
	}

	/**
	 * Verify that duplicate candidate IDs are removed.
	 *
	 * @return void
	 */
	public function test_filter_removes_duplicate_ids(): void {

		new WC_Product( 101 );
		new WC_Product( 102 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 101, 102, 101 )
		);

		self::assertSame(
			array( 101, 102 ),
			$result
		);
	}

	/**
	 * Verify that excluded IDs are removed.
	 *
	 * @return void
	 */
	public function test_filter_removes_excluded_ids(): void {

		new WC_Product( 101 );
		new WC_Product( 102 );
		new WC_Product( 103 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 102, 103 ),
			array( 102 )
		);

		self::assertSame(
			array( 101, 103 ),
			$result
		);
	}

	/**
	 * Verify that ineligible products are removed.
	 *
	 * @return void
	 */
	public function test_filter_removes_ineligible_products(): void {

		new WC_Product( 101 );

		$draft = new WC_Product( 102 );
		$draft->set_status( 'draft' );

		$hidden = new Test_WC_Product( 103 );
		$hidden->set_visible( false );

		$out_of_stock = new WC_Product( 104 );
		$out_of_stock->set_stock_status( 'outofstock' );

		new WC_Product( 105 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 102, 103, 104, 105 )
		);

		self::assertSame(
			array( 101, 105 ),
			$result
		);
	}

	/**
	 * Verify that unknown product IDs are removed.
	 *
	 * @return void
	 */
	public function test_filter_removes_unknown_product_ids(): void {

		new WC_Product( 101 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 999 )
		);

		self::assertSame(
			array( 101 ),
			$result
		);
	}

	/**
	 * Verify that the result limit is respected.
	 *
	 * @return void
	 */
	public function test_filter_respects_limit(): void {

		new WC_Product( 101 );
		new WC_Product( 102 );
		new WC_Product( 103 );
		new WC_Product( 104 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 102, 103, 104 ),
			array(),
			2
		);

		self::assertSame(
			array( 101, 102 ),
			$result
		);
	}

	/**
	 * Verify that a zero limit returns all eligible products.
	 *
	 * @return void
	 */
	public function test_zero_limit_returns_all_eligible_products(): void {

		new WC_Product( 101 );
		new WC_Product( 102 );
		new WC_Product( 103 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 102, 103 ),
			array(),
			0
		);

		self::assertSame(
			array( 101, 102, 103 ),
			$result
		);
	}

	/**
	 * Verify that the limit counts only eligible products.
	 *
	 * @return void
	 */
	public function test_limit_counts_only_eligible_products(): void {

		$draft = new WC_Product( 101 );
		$draft->set_status( 'draft' );

		new WC_Product( 102 );

		$out_of_stock = new WC_Product( 103 );
		$out_of_stock->set_stock_status( 'outofstock' );

		new WC_Product( 104 );
		new WC_Product( 105 );

		$result = $this->service->filter_eligible_ids(
			array( 101, 102, 103, 104, 105 ),
			array(),
			2
		);

		self::assertSame(
			array( 102, 104 ),
			$result
		);
	}
}
