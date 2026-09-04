<?php
/**
 * Tests for Admin_Menu.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Tests the Checkout Tools admin menu.
 */
final class AdminMenuTest extends TestCase {

	/**
	 * Test admin page.
	 *
	 * @var Admin_Page_Interface
	 */
	private Admin_Page_Interface $checkout_page;

	/**
	 * Admin menu.
	 *
	 * @var Admin_Menu
	 */
	private Admin_Menu $admin_menu;

	/**
	 * Sets up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_submenu_pages']   = array();

		$this->checkout_page = new Test_Admin_Page();

		$this->admin_menu = new Admin_Menu(
			checkout_page: $this->checkout_page
		);
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['shurloc_test_actions'],
			$GLOBALS['shurloc_test_action_metadata'],
			$GLOBALS['shurloc_test_submenu_pages']
		);

		parent::tearDown();
	}

	/**
	 * Tests that the admin menu hooks are registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_hooks(): void {
		$this->admin_menu->register();

		$this->assertCount(
			2,
			$GLOBALS['shurloc_test_actions']
		);

		$this->assertSame(
			array( array( $this->admin_menu, 'register_menu' ) ),
			$GLOBALS['shurloc_test_actions']['admin_menu']
		);

		$this->assertSame(
			40,
			$GLOBALS['shurloc_test_action_metadata']['admin_menu'][0]['priority']
		);

		$this->assertSame(
			array( array( $this->admin_menu, 'render_overview_section' ) ),
			$GLOBALS['shurloc_test_actions']['shurloc_tools_overview']
		);

		$this->assertSame(
			40,
			$GLOBALS['shurloc_test_action_metadata']['shurloc_tools_overview'][0]['priority']
		);
	}

	/**
	 * Tests that the Checkout Tools submenu is registered.
	 *
	 * @return void
	 */
	public function test_register_menu_adds_checkout_submenu(): void {
		$this->admin_menu->register_menu();

		$this->assertCount(
			1,
			$GLOBALS['shurloc_test_submenu_pages']
		);

		$submenu = $GLOBALS['shurloc_test_submenu_pages'][0];

		$this->assertSame(
			'shurloc-tools',
			$submenu['parent_slug']
		);

		$this->assertSame(
			'ShurLoc Checkout Tools',
			$submenu['page_title']
		);

		$this->assertSame(
			'Checkout',
			$submenu['menu_title']
		);

		$this->assertSame(
			'manage_options',
			$submenu['capability']
		);

		$this->assertSame(
			'shurloc-site-tools-checkout',
			$submenu['menu_slug']
		);

		$this->assertSame(
			array( $this->checkout_page, 'render_page' ),
			$submenu['callback']
		);

		$this->assertSame(
			40,
			$submenu['position']
		);
	}

	/**
	 * Tests that the overview section renders the Checkout Tools link.
	 *
	 * @return void
	 */
	public function test_render_overview_section_outputs_checkout_tools_link(): void {
		ob_start();

		$this->admin_menu->render_overview_section();

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'<h2>Checkout</h2>',
			$output
		);

		$this->assertStringContainsString(
			'Checkout and payment tools.',
			$output
		);

		$this->assertStringContainsString(
			'Open Checkout Tools',
			$output
		);

		$this->assertStringContainsString(
			'https://example.com/wp-admin/admin.php?page=shurloc-site-tools-checkout',
			$output
		);
	}
}
