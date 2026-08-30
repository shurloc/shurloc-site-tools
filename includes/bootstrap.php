<?php
/**
 * Plugin bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools;

use Shurloc\SiteTools\Media\Bootstrap as Media_Bootstrap;
use Shurloc\SiteTools\SEO\Bootstrap as SEO_Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function shurloc_site_tools_bootstrap(): void {

	/**
	 * Autoloader.
	 */

	require_once SHURLOC_SITE_TOOLS_PATH . 'includes/class-autoloader.php';

	$autoloader = new Autoloader(
		base_directory: __DIR__,
	);

	$autoloader->register();

	/**
	 * Media domain.
	 */

	$media_bootstrap = new Media_Bootstrap();

	$media_bootstrap->register();

	/**
	 * SEO domain.
	 */

	$seo_bootstrap = new SEO_Bootstrap();

	$seo_bootstrap->register();
}

add_action(
	'plugins_loaded',
	__NAMESPACE__ . '\\shurloc_site_tools_bootstrap',
	20
);
