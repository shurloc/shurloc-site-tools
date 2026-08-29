<?php
/**
 * Tests for the Media domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Media;

use PHPUnit\Framework\TestCase;

/**
 * Tests the Media domain bootstrap.
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
	 * Verify registering the Media bootstrap wires Media hooks.
	 *
	 * @return void
	 */
	public function test_register_registers_media_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

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
