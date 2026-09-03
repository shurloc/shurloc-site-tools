<?php
/**
 * Tests for the Yoast primary product category integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests the Yoast primary product category integration.
 */
final class YoastPrimaryCategoryIntegrationTest extends TestCase {

	/**
	 * Integration under test.
	 *
	 * @var Yoast_Primary_Category_Integration
	 */
	private Yoast_Primary_Category_Integration $integration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		$this->integration =
			new Yoast_Primary_Category_Integration();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Verify the Yoast primary-term taxonomy filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_primary_term_taxonomy_filter(): void {

		$this->integration->register();

		self::assertContains(
			array(
				$this->integration,
				'remove_product_category',
			),
			$GLOBALS['shurloc_test_filters']
				['wpseo_primary_term_taxonomies']
		);

		self::assertSame(
			11,
			$GLOBALS['shurloc_test_filter_metadata']
				['wpseo_primary_term_taxonomies'][0]['priority']
		);

		self::assertSame(
			3,
			$GLOBALS['shurloc_test_filter_metadata']
				['wpseo_primary_term_taxonomies'][0]['accepted_args']
		);
	}

	/**
	 * Verify product categories are removed for products.
	 *
	 * @return void
	 */
	public function test_remove_product_category_removes_product_cat_for_products(): void {

		$taxonomies = array(
			'category'    => 'category',
			'product_cat' => 'product_cat',
		);

		$result = $this->integration->remove_product_category(
			taxonomies: $taxonomies,
			post_type: 'product',
			all_taxonomies: array(),
		);

		self::assertArrayNotHasKey(
			'product_cat',
			$result
		);

		self::assertArrayHasKey(
			'category',
			$result
		);
	}

	/**
	 * Verify product categories remain available for other post types.
	 *
	 * @return void
	 */
	public function test_remove_product_category_preserves_taxonomies_for_other_post_types(): void {

		$taxonomies = array(
			'category'    => 'category',
			'product_cat' => 'product_cat',
		);

		$result = $this->integration->remove_product_category(
			taxonomies: $taxonomies,
			post_type: 'post',
			all_taxonomies: array(),
		);

		self::assertSame(
			$taxonomies,
			$result
		);
	}

	/**
	 * Verify removing product categories does not affect other taxonomies.
	 *
	 * @return void
	 */
	public function test_remove_product_category_preserves_other_product_taxonomies(): void {

		$taxonomies = array(
			'product_cat' => 'product_cat',
			'product_tag' => 'product_tag',
			'pa_color'    => 'pa_color',
		);

		$result = $this->integration->remove_product_category(
			taxonomies: $taxonomies,
			post_type: 'product',
			all_taxonomies: array(),
		);

		self::assertSame(
			array(
				'product_tag' => 'product_tag',
				'pa_color'    => 'pa_color',
			),
			$result
		);
	}

	/**
	 * Verify an absent product category leaves the taxonomy list unchanged.
	 *
	 * @return void
	 */
	public function test_remove_product_category_handles_missing_product_cat(): void {

		$taxonomies = array(
			'product_tag' => 'product_tag',
		);

		$result = $this->integration->remove_product_category(
			taxonomies: $taxonomies,
			post_type: 'product',
			all_taxonomies: array(),
		);

		self::assertSame(
			$taxonomies,
			$result
		);
	}
}
