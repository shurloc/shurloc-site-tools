<?php
/**
 * Namespaced function test overrides.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Migrations;

/**
 * Return the current test timestamp.
 *
 * @return int
 */
function time(): int {
	return $GLOBALS['shurloc_test_time'];
}
