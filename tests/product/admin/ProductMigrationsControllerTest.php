<?php
/**
 * Tests for the product migrations controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Migrations\Yoast_Product_Meta_Cleanup_Migration;

/**
 * Tests the product migrations controller.
 */
final class ProductMigrationsControllerTest extends TestCase {

	/**
	 * Migration under test.
	 *
	 * @var Yoast_Product_Meta_Cleanup_Migration
	 */
	private Yoast_Product_Meta_Cleanup_Migration $migration;

	/**
	 * Controller under test.
	 *
	 * @var Product_Migrations_Controller
	 */
	private Product_Migrations_Controller $controller;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_options']          = array();
		$GLOBALS['shurloc_test_product_ids']      = array();
		$GLOBALS['shurloc_test_post_meta']        = array();
		$GLOBALS['shurloc_test_nonce_checks']     = array();
		$GLOBALS['shurloc_test_nonce_valid']      = true;
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_styles']           = array();

		$_GET  = array();
		$_POST = array();

		$this->migration =
			new Yoast_Product_Meta_Cleanup_Migration();

		$this->controller =
			new Product_Migrations_Controller(
				cleanup_migration: $this->migration,
			);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_options']          = array();
		$GLOBALS['shurloc_test_product_ids']      = array();
		$GLOBALS['shurloc_test_post_meta']        = array();
		$GLOBALS['shurloc_test_nonce_checks']     = array();
		$GLOBALS['shurloc_test_nonce_valid']      = true;
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_styles']           = array();

		$_GET  = array();
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Verify the controller registers its WordPress hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_migration_hooks(): void {

		$this->controller->register();

		self::assertContains(
			array(
				$this->controller,
				'handle_cleanup_migration',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_post_shurloc_run_yoast_product_meta_cleanup']
		);

		self::assertContains(
			array(
				$this->controller,
				'enqueue_assets',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_enqueue_scripts']
		);
	}

	/**
	 * Verify the migrations page renders the cleanup controls.
	 *
	 * @return void
	 */
	public function test_render_shows_cleanup_migration_controls(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Product Data Migrations',
			$output
		);

		self::assertStringContainsString(
			'Yoast Product Metadata Cleanup',
			$output
		);

		self::assertStringContainsString(
			'Run Yoast Metadata Cleanup',
			$output
		);

		self::assertStringContainsString(
			'shurloc_run_yoast_product_meta_cleanup',
			$output
		);

		self::assertMatchesRegularExpression(
			'/This will clear all currently assigned primary product\s+categories\./',
			$output
		);

		self::assertStringContainsString(
			'shurloc-migration-enable',
			$output
		);

		self::assertStringContainsString(
			'shurloc-migration-submit',
			$output
		);

		self::assertStringContainsString(
			'disabled',
			$output
		);
	}

	/**
	 * Verify the migrations page displays the current migration version.
	 *
	 * @return void
	 */
	public function test_render_shows_current_migration_version(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/Current migration version<\/th>\s*<td>\s*1\s*<\/td>/s',
			$output
		);
	}

	/**
	 * Verify the migrations page displays the last-run migration version.
	 *
	 * @return void
	 */
	public function test_render_shows_last_run_version(): void {

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_VERSION_OPTION ] = 1;

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/Last-run migration version<\/th>\s*<td>\s*1\s*<\/td>/s',
			$output
		);
	}

	/**
	 * Verify an unrun migration displays Never.
	 *
	 * @return void
	 */
	public function test_render_shows_never_when_migration_has_not_run(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Never',
			$output
		);
	}

	/**
	 * Verify the migrations page renders the running overlay.
	 *
	 * @return void
	 */
	public function test_render_shows_migration_running_overlay(): void {

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'shurloc-migration-overlay',
			$output
		);

		self::assertStringContainsString(
			'Migration is running',
			$output
		);

		self::assertStringContainsString(
			'Please keep this page open until the migration completes.',
			$output
		);
	}

	/**
	 * Verify running the cleanup migration removes targeted product metadata.
	 *
	 * @return void
	 */
	public function test_run_cleanup_migration_cleans_product_meta(): void {

		$GLOBALS['shurloc_test_product_ids'] = array(
			100,
		);

		$GLOBALS['shurloc_test_post_meta'][100] = array(
			'_yoast_wpseo_primary_product_cat' => 10,
			'_sku'                             => 'TEST-100',
		);

		$this->controller->run_cleanup_migration();

		self::assertArrayNotHasKey(
			'_yoast_wpseo_primary_product_cat',
			$GLOBALS['shurloc_test_post_meta'][100]
		);

		self::assertSame(
			'TEST-100',
			$GLOBALS['shurloc_test_post_meta'][100]['_sku']
		);
	}

	/**
	 * Verify running the cleanup migration returns the expected result URL.
	 *
	 * @return void
	 */
	public function test_run_cleanup_migration_returns_result_url(): void {

		$redirect_url =
			$this->controller->run_cleanup_migration();

		self::assertStringContainsString(
			'page=shurloc-site-tools-products',
			$redirect_url
		);

		self::assertStringContainsString(
			'tab=migrations',
			$redirect_url
		);

		self::assertStringContainsString(
			'migration=yoast-product-meta-cleanup',
			$redirect_url
		);

		self::assertStringContainsString(
			'examined=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'updated=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'skipped=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'errors=0',
			$redirect_url
		);

		self::assertStringContainsString(
			'_wpnonce=test-nonce-shurloc_yoast_product_meta_cleanup_result',
			$redirect_url
		);
	}

	/**
	 * Verify a locked cleanup migration is not executed a second time.
	 *
	 * @return void
	 */
	public function test_run_cleanup_migration_does_not_run_when_locked(): void {

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ] = time();

		$GLOBALS['shurloc_test_product_ids'] = array(
			100,
		);

		$GLOBALS['shurloc_test_post_meta'][100] = array(
			'_yoast_wpseo_primary_product_cat' => 10,
		);

		$redirect_url =
			$this->controller->run_cleanup_migration();

		self::assertStringContainsString(
			'migration=yoast-product-meta-cleanup-locked',
			$redirect_url
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_post_meta'][100]
				['_yoast_wpseo_primary_product_cat']
		);
	}

	/**
	 * Verify a completed cleanup migration releases its lock.
	 *
	 * @return void
	 */
	public function test_run_cleanup_migration_releases_lock_after_completion(): void {

		$this->controller->run_cleanup_migration();

		self::assertArrayNotHasKey(
			Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify a valid migration result displays the completion notice.
	 *
	 * @return void
	 */
	public function test_render_displays_valid_cleanup_migration_result(): void {

		$_GET['migration'] =
			'yoast-product-meta-cleanup';

		$_GET['examined'] = '10';
		$_GET['updated']  = '7';
		$_GET['skipped']  = '3';
		$_GET['errors']   = '0';
		$_GET['_wpnonce'] =
			'test-nonce-shurloc_yoast_product_meta_cleanup_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Yoast product metadata cleanup complete.',
			$output
		);

		self::assertStringContainsString(
			'Examined: 10',
			$output
		);

		self::assertStringContainsString(
			'Updated: 7',
			$output
		);

		self::assertStringContainsString(
			'Skipped: 3',
			$output
		);

		self::assertStringContainsString(
			'Errors: 0',
			$output
		);
	}

	/**
	 * Verify a locked migration displays a warning notice.
	 *
	 * @return void
	 */
	public function test_render_displays_cleanup_migration_locked_notice(): void {

		$_GET['migration'] =
			'yoast-product-meta-cleanup-locked';

		$_GET['_wpnonce'] =
			'test-nonce-shurloc_yoast_product_meta_cleanup_result';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Yoast product metadata cleanup is already running.',
			$output
		);

		self::assertStringContainsString(
			'No second migration was started.',
			$output
		);
	}

	/**
	 * Verify an invalid result nonce suppresses the completion notice.
	 *
	 * @return void
	 */
	public function test_render_rejects_result_with_invalid_nonce(): void {

		$GLOBALS['shurloc_test_nonce_valid'] = false;

		$_GET['migration'] =
			'yoast-product-meta-cleanup';

		$_GET['examined'] = '10';
		$_GET['updated']  = '7';
		$_GET['skipped']  = '3';
		$_GET['errors']   = '0';
		$_GET['_wpnonce'] =
			'invalid-nonce';

		ob_start();

		$this->controller->render();

		$output = (string) ob_get_clean();

		self::assertStringNotContainsString(
			'Yoast product metadata cleanup complete.',
			$output
		);
	}

	/**
	 * Verify migration assets are enqueued on the migrations page.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_on_migrations_page(): void {

		$_GET['page'] = 'shurloc-site-tools-products';
		$_GET['tab']  = 'migrations';

		$this->controller->enqueue_assets();

		$script_handles = array_column(
			$GLOBALS['shurloc_test_enqueued_scripts'],
			'handle'
		);

		self::assertContains(
			'shurloc-product-migrations',
			$script_handles
		);

		self::assertArrayHasKey(
			'shurloc-product-migrations',
			$GLOBALS['shurloc_test_styles']
		);

		self::assertSame(
			SHURLOC_SITE_TOOLS_URL .
				'assets/product/js/shurloc-product-migrations.js',
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['src']
		);

		self::assertSame(
			SHURLOC_SITE_TOOLS_URL .
				'assets/product/css/shurloc-product-migrations.css',
			$GLOBALS['shurloc_test_styles']
				['shurloc-product-migrations']['src']
		);
	}

	/**
	 * Verify migration assets are not enqueued on another Product Tools tab.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_skips_other_product_tools_tabs(): void {

		$_GET['page'] = 'shurloc-site-tools-products';
		$_GET['tab']  = 'catalog-report';

		$this->controller->enqueue_assets();

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_styles']
		);
	}

	/**
	 * Verify migration assets are not enqueued on another admin page.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_skips_other_admin_pages(): void {

		$_GET['page'] = 'other-page';
		$_GET['tab']  = 'migrations';

		$this->controller->enqueue_assets();

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_styles']
		);
	}
}
