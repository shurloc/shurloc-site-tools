<?php
/**
 * Tests for the plugin bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools;

use PHPUnit\Framework\TestCase;

/**
 * Tests the plugin bootstrap.
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

		parent::tearDown();
	}

	/**
	 * Verify the plugin bootstrap registers its domains.
	 *
	 * @return void
	 */
	public function test_bootstrap_registers_domains(): void {

		shurloc_site_tools_bootstrap();

		/*
		 * Customer domain.
		 */

		self::assertArrayHasKey(
			'manage_users_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'pre_get_users',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_post_shurloc_run_purchase_migration',
			$GLOBALS['shurloc_test_actions']
		);

		/*
		 * Media domain.
		 */

		self::assertArrayHasKey(
			'manage_upload_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'admin_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);

		/*
		 * SEO domain.
		 */

		self::assertArrayHasKey(
			'wp_head',
			$GLOBALS['shurloc_test_actions']
		);

		/*
		 * Product domain.
		 */

		self::assertArrayHasKey(
			'woocommerce_structured_data_product',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'woocommerce_related_products',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'add_meta_boxes_product',
			$GLOBALS['shurloc_test_actions']
		);
	}
}
