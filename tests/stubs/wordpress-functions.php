<?php
/**
 * WordPress function test doubles.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );


/**
 * Test post meta values.
 */
$GLOBALS['shurloc_test_post_meta'] = array();


if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Retrieve post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_post_meta(
		int $post_id,
		string $key = '',
		bool $single = false
	): mixed {
		if ( '' === $key ) {
			return $GLOBALS['shurloc_test_post_meta'][ $post_id ] ?? array();
		}

		$value = $GLOBALS['shurloc_test_post_meta'][ $post_id ][ $key ] ?? '';

		if ( $single ) {
			return $value;
		}

		if ( '' === $value ) {
			return array();
		}

		return array( $value );
	}
}
