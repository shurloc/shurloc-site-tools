<?php
/**
 * PHPStan bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'SHURLOC_SITE_TOOLS_VERSION' ) ) {
	define(
		'SHURLOC_SITE_TOOLS_VERSION',
		'0.1.0'
	);
}

if ( ! defined( 'SHURLOC_SITE_TOOLS_PATH' ) ) {
	define(
		'SHURLOC_SITE_TOOLS_PATH',
		__DIR__ . '/'
	);
}

if ( ! defined( 'SHURLOC_SITE_TOOLS_URL' ) ) {
	define(
		'SHURLOC_SITE_TOOLS_URL',
		'https://example.com/wp-content/plugins/shurloc-site-tools/'
	);
}
