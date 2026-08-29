<?php
/**
 * Admin page interface.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Shared\Interfaces;

/**
 * Represents an admin page that can be rendered.
 */
interface Admin_Page_Interface {

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page(): void;
}
