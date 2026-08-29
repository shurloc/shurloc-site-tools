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

		$GLOBALS['shurloc_test_actions'] = array();
		$GLOBALS['shurloc_test_filters'] = array();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions'] = array();
		$GLOBALS['shurloc_test_filters'] = array();

		parent::tearDown();
	}

	/**
	 * Verify the plugin bootstrap registers the Media domain.
	 *
	 * @return void
	 */
	public function test_bootstrap_registers_media_domain(): void {

		shurloc_site_tools_bootstrap();

		self::assertArrayHasKey(
			'manage_upload_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'admin_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);
	}
}
