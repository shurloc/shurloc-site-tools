<?php
/**
 * Tests for the primary product category metabox.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Services\Primary_Product_Category_Service;
use WP_Post;
use WP_Screen;

/**
 * Tests the primary product category metabox.
 */
final class PrimaryProductCategoryMetaboxTest extends TestCase {

	/**
	 * Primary category service.
	 *
	 * @var Primary_Product_Category_Service
	 */
	private Primary_Product_Category_Service $service;

	/**
	 * Metabox under test.
	 *
	 * @var Primary_Product_Category_Metabox
	 */
	private Primary_Product_Category_Metabox $metabox;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']           = array();
		$GLOBALS['shurloc_test_action_metadata']   = array();
		$GLOBALS['shurloc_test_post_meta']         = array();
		$GLOBALS['shurloc_test_terms']             = array();
		$GLOBALS['shurloc_test_post_terms']        = array();
		$GLOBALS['shurloc_test_nonce_fields']      = array();
		$GLOBALS['shurloc_test_user_capabilities'] = array();
		$GLOBALS['shurloc_test_styles']            = array();
		$GLOBALS['shurloc_test_enqueued_scripts']  = array();
		$GLOBALS['shurloc_test_nonce_valid']       = true;
		$GLOBALS['shurloc_test_nonce_fields']      = array();

		$_POST = array();

		$this->service =
			new Primary_Product_Category_Service();

		$this->metabox =
			new Primary_Product_Category_Metabox(
				service: $this->service,
			);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']           = array();
		$GLOBALS['shurloc_test_action_metadata']   = array();
		$GLOBALS['shurloc_test_post_meta']         = array();
		$GLOBALS['shurloc_test_terms']             = array();
		$GLOBALS['shurloc_test_post_terms']        = array();
		$GLOBALS['shurloc_test_nonce_fields']      = array();
		$GLOBALS['shurloc_test_user_capabilities'] = array();
		$GLOBALS['shurloc_test_styles']            = array();
		$GLOBALS['shurloc_test_enqueued_scripts']  = array();
		$GLOBALS['shurloc_test_nonce_valid']       = true;
		$GLOBALS['shurloc_test_nonce_fields']      = array();

		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Verify the metabox registers its WordPress hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_metabox_hooks(): void {

		$this->metabox->register();

		self::assertContains(
			array(
				$this->metabox,
				'add_metabox',
			),
			$GLOBALS['shurloc_test_actions']
				['add_meta_boxes_product']
		);

		self::assertContains(
			array(
				$this->metabox,
				'save',
			),
			$GLOBALS['shurloc_test_actions']
				['save_post_product']
		);

		self::assertContains(
			array(
				$this->metabox,
				'enqueue_assets',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_enqueue_scripts']
		);
	}

	/**
	 * Verify the metabox renders its category selector.
	 *
	 * @return void
	 */
	public function test_render_shows_primary_category_selector(): void {

		$GLOBALS['shurloc_test_terms']['product_cat'][10] =
			(object) array(
				'term_id'  => 10,
				'name'     => 'Hardware',
				'slug'     => 'hardware',
				'taxonomy' => 'product_cat',
				'parent'   => 0,
			);

		$post = new WP_Post(
			(object) array(
				'ID'        => 100,
				'post_type' => 'product',
			)
		);

		ob_start();

		$this->metabox->render(
			post: $post,
		);

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Primary Category',
			$output
		);

		self::assertStringContainsString(
			'Hardware',
			$output
		);

		self::assertStringContainsString(
			'shurloc_primary_product_category',
			$output
		);
	}

	/**
	 * Verify saving a valid category stores it as primary.
	 *
	 * @return void
	 */
	public function test_save_stores_valid_primary_category(): void {

		$GLOBALS['shurloc_test_terms']['product_cat'][10] =
			(object) array(
				'term_id'  => 10,
				'name'     => 'Hardware',
				'slug'     => 'hardware',
				'taxonomy' => 'product_cat',
				'parent'   => 0,
			);

		$GLOBALS['shurloc_test_post_terms'][100] = array(
			10,
		);

		$GLOBALS['shurloc_test_user_capabilities']
			['edit_post'] = true;

		$GLOBALS['shurloc_test_nonce_valid'] = true;

		$_POST['shurloc_primary_product_category_nonce'] =
			'test-nonce-shurloc_save_primary_product_category';

		$_POST['shurloc_primary_product_category'] =
			'10';

		$this->metabox->save(
			post_id: 100,
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_post_meta'][100]
				[ Primary_Product_Category_Service::META_KEY ]
		);
	}

	/**
	 * Verify an empty selection clears the primary category.
	 *
	 * @return void
	 */
	public function test_save_clears_primary_category_when_empty(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ Primary_Product_Category_Service::META_KEY ] = 10;

		$GLOBALS['shurloc_test_user_capabilities']
			['edit_post'] = true;

		$GLOBALS['shurloc_test_nonce_valid'] = true;

		$_POST['shurloc_primary_product_category_nonce'] =
			'test-nonce-shurloc_save_primary_product_category';

		$_POST['shurloc_primary_product_category'] =
			'';

		$this->metabox->save(
			post_id: 100,
		);

		self::assertArrayNotHasKey(
			Primary_Product_Category_Service::META_KEY,
			$GLOBALS['shurloc_test_post_meta'][100] ?? array()
		);
	}

	/**
	 * Verify saving without a nonce does nothing.
	 *
	 * @return void
	 */
	public function test_save_without_nonce_does_nothing(): void {

		$GLOBALS['shurloc_test_user_capabilities']
			['edit_post'] = true;

		$_POST['shurloc_primary_product_category'] =
			'10';

		$this->metabox->save(
			post_id: 100,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_post_meta']
		);
	}

	/**
	 * Verify saving without edit capability does nothing.
	 *
	 * @return void
	 */
	public function test_save_without_capability_does_nothing(): void {

		$GLOBALS['shurloc_test_user_capabilities']
			['edit_post'] = false;

		$GLOBALS['shurloc_test_nonce_valid'] = true;

		$_POST['shurloc_primary_product_category_nonce'] =
			'test-nonce-shurloc_save_primary_product_category';

		$_POST['shurloc_primary_product_category'] =
			'10';

		$this->metabox->save(
			post_id: 100,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_post_meta']
		);
	}

	/**
	 * Verify assets are enqueued on product edit screens.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_on_product_edit_screen(): void {

		$GLOBALS['shurloc_test_current_screen'] =
			new WP_Screen();

		$GLOBALS['shurloc_test_current_screen']->post_type =
			'product';

		$this->metabox->enqueue_assets(
			hook_suffix: 'post.php',
		);

		$script_handles = array_column(
			$GLOBALS['shurloc_test_enqueued_scripts'],
			'handle'
		);

		$style_handles = array_keys(
			$GLOBALS['shurloc_test_styles']
		);

		self::assertContains(
			'selectWoo',
			$script_handles
		);

		self::assertContains(
			'shurloc-primary-product-category',
			$script_handles
		);

		self::assertContains(
			'select2',
			$style_handles
		);

		self::assertContains(
			'shurloc-primary-product-category',
			$style_handles
		);
	}
}
