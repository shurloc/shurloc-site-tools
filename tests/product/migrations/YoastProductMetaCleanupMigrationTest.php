<?php
/**
 * Tests for the Yoast product meta cleanup migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Migrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests the Yoast product meta cleanup migration.
 */
final class YoastProductMetaCleanupMigrationTest extends TestCase {

	/**
	 * Migration under test.
	 *
	 * @var Yoast_Product_Meta_Cleanup_Migration
	 */
	private Yoast_Product_Meta_Cleanup_Migration $migration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_product_ids'] = array();
		$GLOBALS['shurloc_test_post_meta']   = array();
		$GLOBALS['shurloc_test_options']     = array();

		$this->migration =
			new Yoast_Product_Meta_Cleanup_Migration();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_product_ids'] = array();
		$GLOBALS['shurloc_test_post_meta']   = array();
		$GLOBALS['shurloc_test_options']     = array();

		parent::tearDown();
	}

	/**
	 * Verify the migration clears all targeted Yoast product metadata.
	 *
	 * @return void
	 */
	public function test_run_clears_targeted_yoast_product_meta(): void {

		$GLOBALS['shurloc_test_product_ids'] = array(
			100,
		);

		$GLOBALS['shurloc_test_post_meta'][100] = array(
			'_yoast_wpseo_primary_category'    => 10,
			'_yoast_wpseo_primary_product_cat' => 20,
			'_yoast_wpseo_content_score'       => 90,
		);

		$result = $this->migration->run();

		self::assertSame(
			1,
			$result['examined']
		);

		self::assertSame(
			1,
			$result['updated']
		);

		self::assertSame(
			0,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['errors']
		);

		self::assertArrayNotHasKey(
			'_yoast_wpseo_primary_category',
			$GLOBALS['shurloc_test_post_meta'][100] ?? array()
		);

		self::assertArrayNotHasKey(
			'_yoast_wpseo_primary_product_cat',
			$GLOBALS['shurloc_test_post_meta'][100] ?? array()
		);

		self::assertArrayNotHasKey(
			'_yoast_wpseo_content_score',
			$GLOBALS['shurloc_test_post_meta'][100] ?? array()
		);
	}

	/**
	 * Verify unrelated product metadata is preserved.
	 *
	 * @return void
	 */
	public function test_run_preserves_unrelated_product_meta(): void {

		$GLOBALS['shurloc_test_product_ids'] = array(
			100,
		);

		$GLOBALS['shurloc_test_post_meta'][100] = array(
			'_yoast_wpseo_primary_product_cat' => 20,
			'_sku'                             => 'TEST-100',
			'_price'                           => '25.00',
		);

		$this->migration->run();

		self::assertSame(
			'TEST-100',
			$GLOBALS['shurloc_test_post_meta'][100]['_sku']
		);

		self::assertSame(
			'25.00',
			$GLOBALS['shurloc_test_post_meta'][100]['_price']
		);

		self::assertArrayNotHasKey(
			'_yoast_wpseo_primary_product_cat',
			$GLOBALS['shurloc_test_post_meta'][100]
		);
	}

	/**
	 * Verify a product without targeted metadata is skipped.
	 *
	 * @return void
	 */
	public function test_run_skips_product_without_targeted_meta(): void {

		$GLOBALS['shurloc_test_product_ids'] = array(
			100,
		);

		$GLOBALS['shurloc_test_post_meta'][100] = array(
			'_sku' => 'TEST-100',
		);

		$result = $this->migration->run();

		self::assertSame(
			1,
			$result['examined']
		);

		self::assertSame(
			0,
			$result['updated']
		);

		self::assertSame(
			1,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['errors']
		);
	}

	/**
	 * Verify multiple products are processed independently.
	 *
	 * @return void
	 */
	public function test_run_processes_multiple_products(): void {

		$GLOBALS['shurloc_test_product_ids'] = array(
			100,
			200,
			300,
		);

		$GLOBALS['shurloc_test_post_meta'][100] = array(
			'_yoast_wpseo_primary_product_cat' => 10,
		);

		$GLOBALS['shurloc_test_post_meta'][200] = array(
			'_sku' => 'TEST-200',
		);

		$GLOBALS['shurloc_test_post_meta'][300] = array(
			'_yoast_wpseo_content_score' => 80,
		);

		$result = $this->migration->run();

		self::assertSame(
			3,
			$result['examined']
		);

		self::assertSame(
			2,
			$result['updated']
		);

		self::assertSame(
			1,
			$result['skipped']
		);

		self::assertSame(
			0,
			$result['errors']
		);
	}

	/**
	 * Verify a migration run records its timestamp and version.
	 *
	 * @return void
	 */
	public function test_run_records_last_run_metadata(): void {

		$before = time();

		$this->migration->run();

		$after = time();

		$last_run =
			$GLOBALS['shurloc_test_options']
				[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_OPTION ];

		self::assertGreaterThanOrEqual(
			$before,
			$last_run
		);

		self::assertLessThanOrEqual(
			$after,
			$last_run
		);

		self::assertSame(
			Yoast_Product_Meta_Cleanup_Migration::VERSION,
			$GLOBALS['shurloc_test_options']
				[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_VERSION_OPTION ]
		);
	}

	/**
	 * Verify a migration with no products still records a completed run.
	 *
	 * @return void
	 */
	public function test_run_with_no_products_records_completed_run(): void {

		$before = time();

		$result = $this->migration->run();

		$after = time();

		self::assertSame(
			array(
				'examined' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'errors'   => 0,
			),
			$result
		);

		$last_run =
			$GLOBALS['shurloc_test_options']
				[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_OPTION ];

		self::assertGreaterThanOrEqual(
			$before,
			$last_run
		);

		self::assertLessThanOrEqual(
			$after,
			$last_run
		);

		self::assertSame(
			Yoast_Product_Meta_Cleanup_Migration::VERSION,
			$GLOBALS['shurloc_test_options']
				[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_VERSION_OPTION ]
		);
	}

	/**
	 * Verify the stored last-run timestamp can be retrieved.
	 *
	 * @return void
	 */
	public function test_get_last_run_returns_stored_timestamp(): void {

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_OPTION ] = 1_000_000;

		self::assertSame(
			1_000_000,
			$this->migration->get_last_run()
		);
	}

	/**
	 * Verify the stored last-run migration version can be retrieved.
	 *
	 * @return void
	 */
	public function test_get_last_run_version_returns_stored_version(): void {

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LAST_RUN_VERSION_OPTION ] = 2;

		self::assertSame(
			2,
			$this->migration->get_last_run_version()
		);
	}

	/**
	 * Verify no migration lock is reported when none exists.
	 *
	 * @return void
	 */
	public function test_is_locked_returns_false_when_no_lock_exists(): void {

		self::assertFalse(
			$this->migration->is_locked()
		);
	}

	/**
	 * Verify an active migration lock is detected.
	 *
	 * @return void
	 */
	public function test_is_locked_returns_true_for_active_lock(): void {

		$locked_at = time();

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ] = $locked_at;

		self::assertTrue(
			$this->migration->is_locked()
		);
	}

	/**
	 * Verify a stale migration lock is removed.
	 *
	 * @return void
	 */
	public function test_is_locked_removes_stale_lock(): void {

		$locked_at = time() - 901;

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ] = $locked_at;

		self::assertFalse(
			$this->migration->is_locked()
		);

		self::assertArrayNotHasKey(
			Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}

	/**
	 * Verify the migration lock can be acquired.
	 *
	 * @return void
	 */
	public function test_acquire_lock_creates_lock(): void {

		$before = time();

		$result = $this->migration->acquire_lock();

		$after = time();

		self::assertTrue(
			$result
		);

		$locked_at =
			$GLOBALS['shurloc_test_options']
				[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ];

		self::assertGreaterThanOrEqual(
			$before,
			$locked_at
		);

		self::assertLessThanOrEqual(
			$after,
			$locked_at
		);
	}

	/**
	 * Verify an existing migration lock cannot be acquired again.
	 *
	 * @return void
	 */
	public function test_acquire_lock_returns_false_when_already_locked(): void {

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ] = time();

		self::assertFalse(
			$this->migration->acquire_lock()
		);
	}

	/**
	 * Verify a stale migration lock can be reacquired.
	 *
	 * @return void
	 */
	public function test_acquire_lock_replaces_stale_lock(): void {

		$stale_lock = time() - 901;

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ] = $stale_lock;

		$before = time();

		self::assertTrue(
			$this->migration->acquire_lock()
		);

		$after = time();

		$locked_at =
			$GLOBALS['shurloc_test_options']
				[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ];

		self::assertGreaterThanOrEqual(
			$before,
			$locked_at
		);

		self::assertLessThanOrEqual(
			$after,
			$locked_at
		);

		self::assertNotSame(
			$stale_lock,
			$locked_at
		);
	}

	/**
	 * Verify the migration lock can be released.
	 *
	 * @return void
	 */
	public function test_release_lock_removes_lock(): void {

		$GLOBALS['shurloc_test_options']
			[ Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION ] = time();

		$this->migration->release_lock();

		self::assertArrayNotHasKey(
			Yoast_Product_Meta_Cleanup_Migration::LOCK_OPTION,
			$GLOBALS['shurloc_test_options']
		);
	}
}
