<?php
/**
 * Tests for the shared Shur-loc Tools admin menu.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Shared\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests the shared Shur-loc Tools admin menu.
 */
final class AdminMenuTest extends TestCase {

	/**
	 * Admin menu under test.
	 *
	 * @var Admin_Menu
	 */
	private Admin_Menu $admin_menu;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_menu_pages']      = array();
		$GLOBALS['shurloc_test_submenu_pages']   = array();
		$GLOBALS['shurloc_test_fired_actions']   = array();

		$this->admin_menu = new Admin_Menu();
	}

	/**
	 * Clear test state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_menu_pages']      = array();
		$GLOBALS['shurloc_test_submenu_pages']   = array();
		$GLOBALS['shurloc_test_fired_actions']   = array();

		parent::tearDown();
	}

	/**
	 * Verify that register attaches the admin menu callback.
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
	 * Verify that the admin menu callback uses priority 10.
	 *
	 * @return void
	 */
	public function test_register_uses_expected_priority(): void {

		$this->admin_menu->register();

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']
				['admin_menu'][0]['priority']
		);
	}

	/**
	 * Verify that the Shur-loc Tools parent menu is registered.
	 *
	 * @return void
	 */
	public function test_register_menu_adds_parent_menu(): void {

		$this->admin_menu->register_menu();

		self::assertCount(
			1,
			$GLOBALS['shurloc_test_menu_pages']
		);

		$menu = $GLOBALS['shurloc_test_menu_pages'][0];

		self::assertSame( 'Shur-loc Tools', $menu['page_title'] );
		self::assertSame( 'Shur-loc Tools', $menu['menu_title'] );
		self::assertSame( 'manage_options', $menu['capability'] );
		self::assertSame( 'shurloc-tools', $menu['menu_slug'] );
		self::assertSame(
			array(
				$this->admin_menu,
				'render_overview_page',
			),
			$menu['callback']
		);
		self::assertSame( 'dashicons-admin-tools', $menu['icon_url'] );
		self::assertSame( 56, $menu['position'] );
	}

	/**
	 * Verify that the Overview submenu is registered.
	 *
	 * @return void
	 */
	public function test_register_menu_adds_overview_submenu(): void {

		$this->admin_menu->register_menu();

		self::assertCount(
			1,
			$GLOBALS['shurloc_test_submenu_pages']
		);

		$submenu = $GLOBALS['shurloc_test_submenu_pages'][0];

		self::assertSame( 'shurloc-tools', $submenu['parent_slug'] );
		self::assertSame( 'Shur-loc Tools', $submenu['page_title'] );
		self::assertSame( 'Overview', $submenu['menu_title'] );
		self::assertSame( 'manage_options', $submenu['capability'] );
		self::assertSame( 'shurloc-tools', $submenu['menu_slug'] );
		self::assertSame(
			array(
				$this->admin_menu,
				'render_overview_page',
			),
			$submenu['callback']
		);
		self::assertSame( 0, $submenu['position'] );
	}

	/**
	 * Verify that the overview page renders its heading.
	 *
	 * @return void
	 */
	public function test_overview_page_renders_heading(): void {

		ob_start();

		$this->admin_menu->render_overview_page();

		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<h1>Shur-loc Tools</h1>', $output );
	}

	/**
	 * Verify that the overview page renders its description.
	 *
	 * @return void
	 */
	public function test_overview_page_renders_description(): void {

		ob_start();

		$this->admin_menu->render_overview_page();

		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString(
			'Administrative tools for Shur-loc WordPress and WooCommerce operations.',
			$output
		);
	}

	/**
	 * Verify that rendering the overview fires the extension hook.
	 *
	 * @return void
	 */
	public function test_overview_page_fires_overview_action(): void {

		$overview_rendered = false;

		add_action(
			'shurloc_tools_overview',
			static function () use ( &$overview_rendered ): void {
				$overview_rendered = true;
			}
		);

		ob_start();

		$this->admin_menu->render_overview_page();

		ob_end_clean();

		self::assertTrue( $overview_rendered );
	}

	/**
	 * Verify that plugins can contribute content through the overview hook.
	 *
	 * @return void
	 */
	public function test_plugins_can_render_overview_content(): void {

		add_action(
			'shurloc_tools_overview',
			static function (): void {
				echo '<h2>Products</h2>';
			},
			10
		);

		ob_start();

		$this->admin_menu->render_overview_page();

		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<h2>Products</h2>', $output );
	}
}
