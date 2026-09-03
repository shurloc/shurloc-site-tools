<?php
/**
 * Checkout admin page controller.
 *
 * Provides admin tools for checkout functions.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Checkout admin page controller.
 */
final class Admin_Page_Controller implements Admin_Page_Interface {

	/**
	 * Checkout Tools settings page.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $settings_page;

	/**
	 * Constructor.
	 *
	 * @param Settings_Page $settings_page Checkout Tools settings page.
	 */
	public function __construct(
		Settings_Page $settings_page
	) {
		$this->settings_page = $settings_page;
	}

	/**
	 * Render the Checkout Tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->settings_page->render_page();
	}
}
