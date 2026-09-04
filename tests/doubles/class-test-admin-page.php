<?php
/**
 * Admin page test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Test admin page.
 */
final class Test_Admin_Page implements Admin_Page_Interface {

	/**
	 * Render the test admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
	}
}
