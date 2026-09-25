<?php
/**
 * Plugin Name:       Shur-loc Site Tools
 * Plugin URI:        https://github.com/shurloc/shurloc-site-tools
 * Description:       Site tools for the Shur-loc website.
 * Version:           0.10.0
 * Requires at least: 7.0
 * Requires PHP:      8.4
 * Requires Plugins:  woocommerce, wordpress-seo
 * Author:            Shur-loc
 * Author URI:        https://shurloc.com/
 * Text Domain:       shurloc-site-tools
 *
 * @package ShurlocSiteTools
 */

namespace Shurloc\SiteTools;

use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Install the Customer Journey schema when the plugin is activated.
 *
 * Activation can run after plugins_loaded, before the normal bootstrap has
 * registered the plugin autoloader.
 *
 * @return void
 */
function shurloc_site_tools_activate_journey_schema(): void {
	require_once SHURLOC_SITE_TOOLS_PATH . 'includes/class-autoloader.php';

	$autoloader = new Autoloader( base_directory: SHURLOC_SITE_TOOLS_PATH . 'includes' );
	$autoloader->register();

	$migrator = new Journey_Schema_Migrator();
	$migrator->migrate();
}

register_activation_hook(
	__FILE__,
	__NAMESPACE__ . '\\shurloc_site_tools_activate_journey_schema'
);
