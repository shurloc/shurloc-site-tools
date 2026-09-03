<?php
/**
 * Tests for the primary product category service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Services;

use PHPUnit\Framework\TestCase;

/**
 * Tests the primary product category service.
 */
final class PrimaryProductCategoryServiceTest extends TestCase {

	/**
	 * Service under test.
	 *
	 * @var Primary_Product_Category_Service
	 */
	private Primary_Product_Category_Service $service;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_post_meta']  = array();
		$GLOBALS['shurloc_test_terms']      = array();
		$GLOBALS['shurloc_test_post_terms'] = array();

		$this->service =
			new Primary_Product_Category_Service();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_post_meta']  = array();
		$GLOBALS['shurloc_test_terms']      = array();
		$GLOBALS['shurloc_test_post_terms'] = array();

		parent::tearDown();
	}

	/**
	 * Verify a valid assigned category can be stored.
	 *
	 * @return void
	 */
	public function test_set_primary_category_stores_valid_assigned_category(): void {

		$this->seed_term(
			term_id: 10,
			taxonomy: 'product_cat',
		);

		$this->assign_term_to_product(
			product_id: 100,
			term_id: 10,
		);

		$result = $this->service->set_primary_category(
			product_id: 100,
			term_id: 10,
		);

		self::assertTrue(
			$result
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_post_meta'][100]
				[ Primary_Product_Category_Service::META_KEY ]
		);
	}

	/**
	 * Verify an unassigned category cannot be stored.
	 *
	 * @return void
	 */
	public function test_set_primary_category_rejects_unassigned_category(): void {

		$this->seed_term(
			term_id: 10,
			taxonomy: 'product_cat',
		);

		$result = $this->service->set_primary_category(
			product_id: 100,
			term_id: 10,
		);

		self::assertFalse(
			$result
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_post_meta']
		);
	}

	/**
	 * Verify a nonexistent category cannot be stored.
	 *
	 * @return void
	 */
	public function test_set_primary_category_rejects_missing_category(): void {

		$result = $this->service->set_primary_category(
			product_id: 100,
			term_id: 10,
		);

		self::assertFalse(
			$result
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_post_meta']
		);
	}

	/**
	 * Verify invalid IDs are rejected.
	 *
	 * @return void
	 */
	public function test_set_primary_category_rejects_invalid_ids(): void {

		self::assertFalse(
			$this->service->set_primary_category(
				product_id: 0,
				term_id: 10,
			)
		);

		self::assertFalse(
			$this->service->set_primary_category(
				product_id: 100,
				term_id: 0,
			)
		);
	}

	/**
	 * Verify setting the current primary category succeeds.
	 *
	 * @return void
	 */
	public function test_set_primary_category_succeeds_when_already_current(): void {

		$this->seed_term(
			term_id: 10,
			taxonomy: 'product_cat',
		);

		$this->assign_term_to_product(
			product_id: 100,
			term_id: 10,
		);

		$GLOBALS['shurloc_test_post_meta'][100]
			[ Primary_Product_Category_Service::META_KEY ] = 10;

		self::assertTrue(
			$this->service->set_primary_category(
				product_id: 100,
				term_id: 10,
			)
		);
	}

	/**
	 * Verify a valid stored primary category can be read.
	 *
	 * @return void
	 */
	public function test_get_primary_category_id_returns_valid_category(): void {

		$this->seed_term(
			term_id: 10,
			taxonomy: 'product_cat',
		);

		$this->assign_term_to_product(
			product_id: 100,
			term_id: 10,
		);

		$GLOBALS['shurloc_test_post_meta'][100]
			[ Primary_Product_Category_Service::META_KEY ] = 10;

		self::assertSame(
			10,
			$this->service->get_primary_category_id(
				product_id: 100,
			)
		);
	}

	/**
	 * Verify stale stored primary-category metadata is ignored.
	 *
	 * @return void
	 */
	public function test_get_primary_category_id_returns_zero_for_unassigned_category(): void {

		$this->seed_term(
			term_id: 10,
			taxonomy: 'product_cat',
		);

		$GLOBALS['shurloc_test_post_meta'][100]
			[ Primary_Product_Category_Service::META_KEY ] = 10;

		self::assertSame(
			0,
			$this->service->get_primary_category_id(
				product_id: 100,
			)
		);
	}

	/**
	 * Verify a missing stored category is treated as unset.
	 *
	 * @return void
	 */
	public function test_get_primary_category_id_returns_zero_when_unset(): void {

		self::assertSame(
			0,
			$this->service->get_primary_category_id(
				product_id: 100,
			)
		);
	}

	/**
	 * Verify invalid product IDs return no primary category.
	 *
	 * @return void
	 */
	public function test_get_primary_category_id_returns_zero_for_invalid_product_id(): void {

		self::assertSame(
			0,
			$this->service->get_primary_category_id(
				product_id: 0,
			)
		);
	}

	/**
	 * Verify the primary category can be cleared.
	 *
	 * @return void
	 */
	public function test_clear_primary_category_removes_stored_meta(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ Primary_Product_Category_Service::META_KEY ] = 10;

		$result = $this->service->clear_primary_category(
			product_id: 100,
		);

		self::assertTrue(
			$result
		);

		self::assertArrayNotHasKey(
			Primary_Product_Category_Service::META_KEY,
			$GLOBALS['shurloc_test_post_meta'][100] ?? array()
		);
	}

	/**
	 * Verify clearing an unset primary category succeeds.
	 *
	 * @return void
	 */
	public function test_clear_primary_category_succeeds_when_already_unset(): void {

		self::assertTrue(
			$this->service->clear_primary_category(
				product_id: 100,
			)
		);
	}

	/**
	 * Verify clearing an invalid product ID fails.
	 *
	 * @return void
	 */
	public function test_clear_primary_category_rejects_invalid_product_id(): void {

		self::assertFalse(
			$this->service->clear_primary_category(
				product_id: 0,
			)
		);
	}

	/**
	 * Seed a test taxonomy term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	private function seed_term(
		int $term_id,
		string $taxonomy
	): void {

		$GLOBALS['shurloc_test_terms'][ $taxonomy ][ $term_id ] =
		(object) array(
			'term_id'  => $term_id,
			'name'     => 'Test Category',
			'slug'     => 'test-category',
			'taxonomy' => $taxonomy,
			'parent'   => 0,
		);
	}

	/**
	 * Assign a test term to a product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $term_id    Term ID.
	 * @return void
	 */
	private function assign_term_to_product(
		int $product_id,
		int $term_id
	): void {

		$GLOBALS['shurloc_test_post_terms'][ $product_id ][] =
			$term_id;
	}
}
