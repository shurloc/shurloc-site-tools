<?php
/**
 * Plugin Name:       Shur-loc Site Tools
 * Plugin URI:        https://github.com/shurloc/shurloc-site-tools
 * Description:       Site tools for the Shur-loc website.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.4
 * Author:            Shur-loc
 * Author URI:        https://shurloc.com/
 * Text Domain:       shurloc-site-tools
 *
 * @package ShurlocSiteTools
 */

namespace Shurloc\SiteTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/bootstrap.php';
