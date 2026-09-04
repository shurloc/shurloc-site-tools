<?php
/**
 * Tests for Admin_Page_Controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Settings\Settings;

/**
 * Tests the Checkout admin page controller.
 */
final class AdminPageControllerTest extends TestCase {

	/**
	 * Admin page controller under test.
	 *
	 * @var Admin_Page_Controller
	 */
	private Admin_Page_Controller $controller;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options']             = array();
		$GLOBALS['shurloc_test_registered_settings'] = array();
		$GLOBALS['shurloc_test_settings_sections']   = array();
		$GLOBALS['shurloc_test_settings_fields']     = array();
		$GLOBALS['shurloc_test_user_capabilities']   = array(
			'manage_options' => true,
		);

		$_GET = array();

		$settings_page = new Settings_Page(
			settings: new Settings()
		);
		$settings_page->register_settings();

		$this->controller = new Admin_Page_Controller(
			settings_page: $settings_page
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['shurloc_test_options'],
			$GLOBALS['shurloc_test_registered_settings'],
			$GLOBALS['shurloc_test_settings_sections'],
			$GLOBALS['shurloc_test_settings_fields'],
			$GLOBALS['shurloc_test_user_capabilities']
		);

		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Tests that the overview tab is displayed by default.
	 *
	 * @return void
	 */
	public function test_render_page_displays_overview_by_default(): void {
		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'Checkout Tools',
			$output
		);

		$this->assertStringContainsString(
			'Checkout and payment tools.',
			$output
		);

		$this->assertStringNotContainsString(
			'<form action="options.php" method="post">',
			$output
		);
	}

	/**
	 * Tests that the tariff fees tab displays the settings form.
	 *
	 * @return void
	 */
	public function test_render_page_displays_tariff_fees_tab(): void {
		$_GET['page'] = Settings_Page::PAGE_SLUG;
		$_GET['tab']  = 'tariff-fees';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'<form action="options.php" method="post">',
			$output
		);

		$this->assertStringContainsString(
			'[tariffs][mesh][enabled]',
			$output
		);

		$this->assertStringNotContainsString(
			'Checkout and payment tools.',
			$output
		);
	}

	/**
	 * Tests that an invalid tab falls back to the overview.
	 *
	 * @return void
	 */
	public function test_render_page_invalid_tab_falls_back_to_overview(): void {
		$_GET['page'] = Settings_Page::PAGE_SLUG;
		$_GET['tab']  = 'invalid-tab';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'Checkout and payment tools.',
			$output
		);

		$this->assertStringNotContainsString(
			'<form action="options.php" method="post">',
			$output
		);
	}

	/**
	 * Tests that the overview tab is active by default.
	 *
	 * @return void
	 */
	public function test_overview_tab_is_active_by_default(): void {
		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/tab=overview[^\"]*"[^>]*class="nav-tab nav-tab-active"/',
			$output
		);
	}

	/**
	 * Tests that the tariff fees tab is active when selected.
	 *
	 * @return void
	 */
	public function test_tariff_fees_tab_is_active_when_selected(): void {
		$_GET['page'] = Settings_Page::PAGE_SLUG;
		$_GET['tab']  = 'tariff-fees';

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/tab=tariff-fees[^\"]*"[^>]*class="nav-tab nav-tab-active"/',
			$output
		);
	}

	/**
	 * Tests that unauthorized users receive no page output.
	 *
	 * @return void
	 */
	public function test_render_page_returns_without_output_for_unauthorized_user(): void {
		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = false;

		ob_start();

		$this->controller->render_page();

		$output = (string) ob_get_clean();

		$this->assertSame(
			'',
			$output
		);
	}
}
