<?php
/**
 * Scoped PHP function stubs for Journey tests.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

/**
 * Capture a cookie header without sending it to the PHPUnit process.
 *
 * @param string              $name Cookie name.
 * @param string              $value Cookie value.
 * @param array<string,mixed> $options Cookie options.
 * @return bool Configured test result.
 */
function setcookie( string $name, string $value, array $options ): bool {
	$GLOBALS['shurloc_journey_cookie_test_calls'][] = array(
		'name'    => $name,
		'value'   => $value,
		'options' => $options,
	);

	return $GLOBALS['shurloc_journey_cookie_test_result'] ?? true;
}

/**
 * Return the configured response header state.
 *
 * @return bool Whether headers have already been sent.
 */
function headers_sent(): bool {
	return $GLOBALS['shurloc_journey_cookie_test_headers_sent'] ?? false;
}
