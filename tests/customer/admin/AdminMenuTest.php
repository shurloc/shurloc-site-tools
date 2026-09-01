<?php
/**
 * Tests for the Customer Tools admin menu.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Tests the Customer Tools admin menu.
 */
final class AdminMenuTest extends TestCase {

	/**
	 * Admin page test double.
	 *
	 * @var Admin_Page_Interface
	 */
	private Admin_Page_Interface $admin_page;

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
		$GLOBALS['shurloc_test_submenu_pages']   = array();

		$this->admin_page = new class() implements Admin_Page_Interface {

			/**
			 * Render the page.
			 *
			 * @return void
			 */
			public function render_page(): void {
				echo 'Customer page';
			}
		};

		$this->admin_menu = new Admin_Menu(
			customer_page: $this->admin_page,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_submenu_pages']   = array();

		parent::tearDown();
	}

	/**
	 * Verify the admin menu hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_menu_action(): void {

		$this->admin_menu->register();

		self::assertContains(
			array(
				$this->admin_menu,
				'register_menu',
			),
			$GLOBALS['shurloc_test_actions']['admin_menu']
		);

		self::assertSame(
			30,
			$GLOBALS['shurloc_test_action_metadata']
				['admin_menu'][0]['priority']
		);
	}

	/**
	 * Verify the Shur-loc Tools overview hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_tools_overview_action(): void {

		$this->admin_menu->register();

		self::assertContains(
			array(
				$this->admin_menu,
				'render_overview_section',
			),
			$GLOBALS['shurloc_test_actions']['shurloc_tools_overview']
		);

		self::assertSame(
			30,
			$GLOBALS['shurloc_test_action_metadata']
				['shurloc_tools_overview'][0]['priority']
		);
	}

	/**
	 * Verify the Customer Tools submenu is registered.
	 *
	 * @return void
	 */
	public function test_register_menu_adds_customer_submenu(): void {

		$this->admin_menu->register_menu();

		self::assertCount(
			1,
			$GLOBALS['shurloc_test_submenu_pages']
		);

		$submenu =
			$GLOBALS['shurloc_test_submenu_pages'][0];

		self::assertSame(
			'shurloc-tools',
			$submenu['parent_slug']
		);

		self::assertSame(
			'Shur-loc Customer Tools',
			$submenu['page_title']
		);

		self::assertSame(
			'Customers',
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
			array(
				$this->admin_page,
				'render_page',
			),
			$submenu['callback']
		);

		self::assertSame(
			30,
			$submenu['position']
		);
	}

	/**
	 * Verify the overview section renders its heading.
	 *
	 * @return void
	 */
	public function test_overview_section_renders_heading(): void {

		ob_start();

		$this->admin_menu->render_overview_section();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Customers',
			$output
		);
	}

	/**
	 * Verify the overview section renders its description.
	 *
	 * @return void
	 */
	public function test_overview_section_renders_description(): void {

		ob_start();

		$this->admin_menu->render_overview_section();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Customer tools.',
			$output
		);
	}

	/**
	 * Verify the overview section links to Customer Tools.
	 *
	 * @return void
	 */
	public function test_overview_section_links_to_customer_tools(): void {

		ob_start();

		$this->admin_menu->render_overview_section();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'page=shurloc-site-tools',
			$output
		);

		self::assertStringContainsString(
			'Open Customer Tools',
			$output
		);
	}
}
