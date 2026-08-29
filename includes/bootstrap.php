<?php
/**
 * Plugin bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap the plugin.
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
}

add_action(
	'plugins_loaded',
	__NAMESPACE__ . '\\shurloc_site_tools_bootstrap',
	20
);
