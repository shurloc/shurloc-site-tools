<?php
/**
 * Tests for the ShurLoc admin menu.
 *
 * @package ShurlocSiteTools
 */

declare(strict_types=1);

namespace Shurloc\SiteTools\Product\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests the ShurLoc admin menu registration.
 */
final class AdminMenuTest extends TestCase {

	/**
	 * Catalog report controller test double.
	 *
	 * @var Catalog_Report_Controller_Double
	 */
	private Catalog_Report_Controller_Double $catalog_report_controller;

	/**
	 * Admin menu under test.
	 *
	 * @var Admin_Menu
	 */
	private Admin_Menu $admin_menu;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test assignments.
		$GLOBALS['menu'] = array();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test assignments.
		$GLOBALS['submenu']                       = array();
		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_menu_pages']       = array();
		$GLOBALS['shurloc_test_submenu_pages']    = array();
		$GLOBALS['shurloc_test_removed_submenus'] = array();

		$this->catalog_report_controller =
		new Catalog_Report_Controller_Double();

		$this->admin_menu = new Admin_Menu(
			$this->catalog_report_controller
		);
	}

	/**
	 * Clear test state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test assignments.
		$GLOBALS['menu'] = array();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test assignments.
		$GLOBALS['submenu']                       = array();
		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_menu_pages']       = array();
		$GLOBALS['shurloc_test_submenu_pages']    = array();
		$GLOBALS['shurloc_test_removed_submenus'] = array();

		parent::tearDown();
	}

	/**
	 * Verify that the admin menu hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_menu_action(): void {

		$this->admin_menu->register();

		self::assertArrayHasKey(
			'admin_menu',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertContains(
			array(
				$this->admin_menu,
				'register_menu',
			),
			$GLOBALS['shurloc_test_actions']['admin_menu']
		);
	}

	/**
	 * Verify that the admin menu hook uses priority 20.
	 *
	 * @return void
	 */
	public function test_register_uses_expected_admin_menu_priority(): void {

		$this->admin_menu->register();

		self::assertSame(
			20,
			$GLOBALS['shurloc_test_action_metadata']
				['admin_menu'][0]['priority']
		);
	}

	/**
	 * Verify that the Products submenu is registered.
	 *
	 * @return void
	 */
	public function test_register_menu_adds_products_submenu(): void {

		$this->admin_menu->register_menu();

		self::assertCount(
			1,
			$GLOBALS['shurloc_test_submenu_pages']
		);

		$submenu = $GLOBALS['shurloc_test_submenu_pages'][0];

		self::assertSame(
			'shurloc-tools',
			$submenu['parent_slug']
		);

		self::assertSame(
			'ShurLoc Product Tools',
			$submenu['page_title']
		);

		self::assertSame(
			'Products',
			$submenu['menu_title']
		);

		self::assertSame(
			'manage_options',
			$submenu['capability']
		);

		self::assertSame(
			'shurloc-site-tools',
			$submenu['menu_slug']
		);

		self::assertSame(
			20,
			$submenu['position']
		);
	}

	/**
	 * Verify that the Products submenu delegates rendering to the catalog
	 * report controller.
	 *
	 * @return void
	 */
	public function test_products_submenu_uses_catalog_report_renderer(): void {

		$this->admin_menu->register_menu();

		$submenu = $GLOBALS['shurloc_test_submenu_pages'][0];

		self::assertSame(
			array(
				$this->catalog_report_controller,
				'render_page',
			),
			$submenu['callback']
		);
	}
}
