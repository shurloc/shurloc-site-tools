<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ShurlocSiteTools
 */

use Shurloc\SiteTools\Autoloader;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

/**
 * Load Composer's autoloader.
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * Load plugin autoloader.
 *
 * The autoloader cannot load itself, so this remains a manual include.
 */
require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

$shurloc_autoloader = new Autoloader(
	base_directory: dirname( __DIR__ ) . '/includes',
);

$shurloc_autoloader->register();

/**
 * Load stubs and test doubles.
 */
require_once __DIR__ . '/stubs/wordpress-functions.php';

require_once dirname( __DIR__ ) . '/includes/constants.php';
require_once dirname( __DIR__ ) . '/includes/bootstrap.php';

require_once __DIR__ . '/doubles/class-wp-query.php';
