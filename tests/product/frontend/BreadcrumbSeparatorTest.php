<?php
/**
 * Tests for the breadcrumb separator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Tests the breadcrumb separator asset handling.
 */
final class BreadcrumbSeparatorTest extends TestCase {

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_is_product']       = true;
	}

	/**
	 * Verify that the frontend enqueue hook is registered.
	 *
	 * @return void
	 */
	public function test_registers_enqueue_hook(): void {

		$separator = new Breadcrumb_Separator();

		$separator->register();

		self::assertNotFalse(
			has_action(
				'wp_enqueue_scripts',
				array( $separator, 'enqueue_assets' )
			)
		);
	}

	/**
	 * Verify that no assets are enqueued outside product pages.
	 *
	 * @return void
	 */
	public function test_does_not_enqueue_assets_outside_product_pages(): void {

		$GLOBALS['shurloc_test_is_product'] = false;

		$separator = new Breadcrumb_Separator();

		$separator->enqueue_assets();

		self::assertSame( array(), $GLOBALS['shurloc_test_styles'] );
		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);
	}

	/**
	 * Verify that separator assets are enqueued on product pages.
	 *
	 * @return void
	 */
	public function test_enqueues_assets_on_product_pages(): void {

		$separator = new Breadcrumb_Separator();

		$separator->enqueue_assets();

		self::assertSame(
			array(
				'src'   => SHURLOC_SITE_TOOLS_URL .
					'assets/product/css/shurloc-breadcrumb-separator.css',
				'deps'  => array(),
				'ver'   => SHURLOC_SITE_TOOLS_VERSION,
				'media' => 'all',
			),
			$GLOBALS['shurloc_test_styles']
				['shurloc-breadcrumb-separator']
		);

		self::assertSame(
			array(
				array(
					'handle'    => 'shurloc-breadcrumb-separator',
					'src'       => SHURLOC_SITE_TOOLS_URL .
						'assets/product/js/shurloc-breadcrumb-separator.js',
					'deps'      => array(),
					'ver'       => SHURLOC_SITE_TOOLS_VERSION,
					'in_footer' => true,
				),
			),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);
	}
}
