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
	 * Tests that rendering is delegated to the settings page.
	 *
	 * @return void
	 */
	public function test_render_page_delegates_to_settings_page(): void {
		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = false;

		$settings_page = new Settings_Page(
			settings: new Settings()
		);

		$controller = new Admin_Page_Controller(
			settings_page: $settings_page
		);

		ob_start();

		$controller->render_page();

		$output = ob_get_clean();

		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = true;

		$this->assertSame(
			'',
			$output
		);
	}
}
