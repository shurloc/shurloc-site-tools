<?php
/**
 * Tests for the SEO domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO;

use PHPUnit\Framework\TestCase;

/**
 * Tests the SEO domain bootstrap.
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
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Verify registering the SEO bootstrap wires SEO hooks.
	 *
	 * @return void
	 */
	public function test_register_registers_seo_hooks(): void {

		$bootstrap = new Bootstrap();

		$bootstrap->register();

		self::assertArrayHasKey(
			'wp_head',
			$GLOBALS['shurloc_test_actions']
		);
	}
}
