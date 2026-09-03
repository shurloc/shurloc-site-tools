<?php
/**
 * Tests for the Product domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product;

use PHPUnit\Framework\TestCase;

/**
 * Tests the Product domain bootstrap.
 */
final class BootstrapTest extends TestCase {

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['wp_shortcodes']                = array();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['wp_shortcodes']                = array();

		parent::tearDown();
	}

	/**
	 * Verify registering the Product bootstrap wires schema and frontend hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_schema_and_frontend_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey( 'wp_head', $GLOBALS['shurloc_test_actions'] );
		self::assertArrayHasKey(
			'woocommerce_structured_data_product',
			$GLOBALS['shurloc_test_filters']
		);
		self::assertArrayHasKey(
			'woocommerce_related_products',
			$GLOBALS['shurloc_test_filters']
		);
		self::assertArrayHasKey(
			'woocommerce_cart_crosssell_ids',
			$GLOBALS['shurloc_test_filters']
		);
		self::assertArrayHasKey(
			'loop_shop_per_page',
			$GLOBALS['shurloc_test_filters']
		);
	}

	/**
	 * Verify registering the Product bootstrap wires Product administration.
	 *
	 * @return void
	 */
	public function test_register_adds_product_admin_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'add_meta_boxes_product',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'save_post_product',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'admin_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'admin_menu',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'wpseo_primary_term_taxonomies',
			$GLOBALS['shurloc_test_filters']
		);
	}

	/**
	 * Verify registering the Product bootstrap wires mesh table hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_mesh_table_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey( 'shurloc_mesh_table', $GLOBALS['wp_shortcodes'] );
		self::assertArrayHasKey(
			'wp_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);
		self::assertArrayHasKey(
			'woocommerce_product_tabs',
			$GLOBALS['shurloc_test_filters']
		);
	}
}
