<?php
/**
 * Media domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Media;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Media\Admin\Library_SEO_Controller;
use Shurloc\SiteTools\Media\Services\SEO_Service;

/**
 * Bootstrap the Media domain.
 */
final class Bootstrap {

	/**
	 * Register Media functionality.
	 *
	 * @return void
	 */
	public function register(): void {

		$seo_service = new SEO_Service();

		$library_seo_controller = new Library_SEO_Controller(
			seo_service: $seo_service,
		);

		$library_seo_controller->register();
	}
}
