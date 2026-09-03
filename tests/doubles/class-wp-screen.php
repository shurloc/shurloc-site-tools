<?php
/**
 * WordPress screen test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * Test replacement for WP_Screen.
 */
final class WP_Screen {

	/**
	 * Current post type.
	 *
	 * @var string
	 */
	public string $post_type = '';
}
