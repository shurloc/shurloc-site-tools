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

/**
 * Registered test actions.
 */
$GLOBALS['shurloc_test_actions'] = array();

/**
 * Registered test action metadata.
 */
$GLOBALS['shurloc_test_action_metadata'] = array();

/**
 * Registered test filters.
 */
$GLOBALS['shurloc_test_filters'] = array();

/**
 * Registered test filter metadata.
 */
$GLOBALS['shurloc_test_filter_metadata'] = array();

/**
 * Whether the current test request is an admin request.
 */
$GLOBALS['shurloc_test_is_admin'] = true;

/**
 * Registered test styles.
 */
$GLOBALS['shurloc_test_styles'] = array();

/**
 * Whether the current test query is the main query.
 */
$GLOBALS['shurloc_test_is_main_query'] = true;

/**
 * Current test page ID.
 */
$GLOBALS['shurloc_test_page_id'] = 0;

/**
 * Current test post.
 */
$GLOBALS['shurloc_test_post'] = null;

/**
 * Filtered test content.
 */
$GLOBALS['shurloc_test_filtered_content'] = null;

/**
 * Current test timestamp.
 */
$GLOBALS['shurloc_test_time'] = 0;

/**
 * Current test user ID.
 */
$GLOBALS['shurloc_test_current_user_id'] = 0;

/**
 * Whether the current test user is logged in.
 */
$GLOBALS['shurloc_test_is_user_logged_in'] = false;

/**
 * Test user meta values.
 */
$GLOBALS['shurloc_test_user_meta'] = array();


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

if ( ! function_exists( 'add_action' ) ) {

	/**
	 * Register a test action.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return true
	 */
	function add_action(
		string $hook,
		$callback,
		int $priority = 10,
		int $accepted_args = 1
	): bool {

		$GLOBALS['shurloc_test_actions'][ $hook ][] =
			$callback;

		$GLOBALS['shurloc_test_action_metadata'][ $hook ][] =
			array(
				'priority'      => $priority,
				'accepted_args' => $accepted_args,
			);

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {

	/**
	 * Register a test filter.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return true
	 */
	function add_filter(
		string $hook,
		$callback,
		int $priority = 10,
		int $accepted_args = 1
	): bool {

		$GLOBALS['shurloc_test_filters'][ $hook ][] =
			$callback;

		$GLOBALS['shurloc_test_filter_metadata'][ $hook ][] =
			array(
				'priority'      => $priority,
				'accepted_args' => $accepted_args,
			);

		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {

	/**
	 * Determine whether the current test request is an admin request.
	 *
	 * @return bool
	 */
	function is_admin(): bool {

		return $GLOBALS['shurloc_test_is_admin'];
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {

	/**
	 * Sanitize a test key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key(
		string $key
	): string {

		$key = strtolower( $key );

		return preg_replace(
			'/[^a-z0-9_\-]/',
			'',
			$key
		) ?? '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {

	/**
	 * Remove slashes from test data.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function wp_unslash(
		string $value
	): string {

		return stripslashes( $value );
	}
}

if ( ! function_exists( '__' ) ) {

	/**
	 * Return a translated test string.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __(
		string $text,
		string $domain = 'default'
	): string {

		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {

	/**
	 * Escape test HTML text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html(
		string $text
	): string {

		return htmlspecialchars(
			$text,
			ENT_QUOTES,
			'UTF-8'
		);
	}
}

if ( ! function_exists( 'esc_attr' ) ) {

	/**
	 * Escape a test HTML attribute.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr(
		string $text
	): string {

		return htmlspecialchars(
			$text,
			ENT_QUOTES,
			'UTF-8'
		);
	}
}

if ( ! function_exists( 'esc_html__' ) ) {

	/**
	 * Escape a translated test HTML string.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__(
		string $text,
		string $domain = 'default'
	): string {

		unset( $domain );

		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {

	/**
	 * Escape a translated test HTML attribute.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__(
		string $text,
		string $domain = 'default'
	): string {

		unset( $domain );

		return esc_attr( $text );
	}
}

if ( ! function_exists( 'selected' ) ) {

	/**
	 * Render a selected HTML attribute.
	 *
	 * @param mixed $selected Selected value.
	 * @param mixed $current  Current value.
	 * @param bool  $display  Whether to display the attribute.
	 * @return string
	 */
	function selected(
		$selected,
		$current = true,
		bool $display = true
	): string {

		$result = $selected === $current
			? ' selected="selected"'
			: '';

		if ( $display ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed test-only HTML attribute.
			echo $result;
		}

		return $result;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {

	/**
	 * Register a test stylesheet enqueue.
	 *
	 * @param string            $handle Stylesheet handle.
	 * @param string|false      $src    Stylesheet source.
	 * @param array<int,string> $deps   Dependencies.
	 * @param string|false      $ver    Version.
	 * @param string            $media  Media type.
	 * @return void
	 */
	function wp_enqueue_style(
		string $handle,
		$src = false,
		array $deps = array(),
		$ver = false,
		string $media = 'all'
	): void {

		$GLOBALS['shurloc_test_styles'][ $handle ] =
			array(
				'src'   => $src,
				'deps'  => $deps,
				'ver'   => $ver,
				'media' => $media,
			);
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Append a trailing slash to a string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function trailingslashit(
		string $value
	): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Get the filesystem directory path for a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_path(
		string $file
	): string {
		return trailingslashit(
			dirname( $file )
		);
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Get the URL directory for a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_url(
		string $file
	): string {
		unset( $file );

		return 'https://example.com/wp-content/plugins/shurloc-site-tools/';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strip all HTML tags from a string.
	 *
	 * @param string $text Text containing HTML.
	 * @return string
	 */
	function wp_strip_all_tags(
		string $text
	): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test stub implements wp_strip_all_tags().
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'is_page' ) ) {
	/**
	 * Determine whether the current test page matches the requested page.
	 *
	 * @param int|string|array<int|string> $page Page identifier.
	 * @return bool
	 */
	function is_page(
		$page = ''
	): bool {
		if ( '' === $page ) {
			return 0 < $GLOBALS['shurloc_test_page_id'];
		}

		if ( is_array( $page ) ) {
			return in_array(
				$GLOBALS['shurloc_test_page_id'],
				$page,
				true
			);
		}

		return $GLOBALS['shurloc_test_page_id'] === (int) $page;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Get the current test post.
	 *
	 * @return WP_Post|null
	 */
	function get_post(): ?WP_Post {
		$post = $GLOBALS['shurloc_test_post'];

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $post;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply a test filter.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Filtered value.
	 * @param mixed  ...$args   Additional arguments.
	 * @return mixed
	 */
	function apply_filters(
		string $hook_name,
		$value,
		...$args
	) {
		unset( $args );

		if (
			'the_content' === $hook_name &&
			null !== $GLOBALS['shurloc_test_filtered_content']
		) {
			return $GLOBALS['shurloc_test_filtered_content'];
		}

		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encode test data as JSON.
	 *
	 * @param mixed        $value Value to encode.
	 * @param int          $flags JSON encoding flags.
	 * @param positive-int $depth Maximum depth.
	 * @return string|false
	 */
	function wp_json_encode(
		$value,
		int $flags = 0,
		int $depth = 512
	) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test stub implements wp_json_encode().
		return json_encode(
			$value,
			$flags,
			$depth
		);
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * Return test HTML after KSES filtering.
	 *
	 * @param string                            $content         Content.
	 * @param array<string,array<string,mixed>> $allowed_html Allowed HTML.
	 * @return string
	 */
	function wp_kses(
		string $content,
		array $allowed_html
	): string {
		unset( $allowed_html );

		return $content;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Format a test timestamp.
	 *
	 * @param string   $format    Date format.
	 * @param int|null $timestamp Timestamp.
	 * @return string
	 */
	function wp_date(
		string $format,
		?int $timestamp = null
	): string {
		if ( null === $timestamp ) {
			$timestamp = $GLOBALS['shurloc_test_time'];
		}

		return gmdate(
			$format,
			$timestamp
		);
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Return the singular or plural test translation.
	 *
	 * @param string $single Singular text.
	 * @param string $plural Plural text.
	 * @param int    $number Number.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function _n(
		string $single,
		string $plural,
		int $number,
		string $domain = 'default'
	): string {
		unset( $domain );

		return 1 === $number
			? $single
			: $plural;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Retrieve a test option value.
	 *
	 * @param string $option         Option name.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	function get_option(
		string $option,
		$default_value = false
	) {
		if ( 'date_format' === $option ) {
			return 'F j, Y';
		}

		return $default_value;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Get the current test user ID.
	 *
	 * @return int
	 */
	function get_current_user_id(): int {
		return $GLOBALS['shurloc_test_current_user_id'];
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * Determine whether the current test user is logged in.
	 *
	 * @return bool
	 */
	function is_user_logged_in(): bool {
		return $GLOBALS['shurloc_test_is_user_logged_in'];
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * Retrieve test user meta.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_user_meta(
		int $user_id,
		string $key = '',
		bool $single = false
	) {
		if ( '' === $key ) {
			return $GLOBALS['shurloc_test_user_meta'][ $user_id ] ?? array();
		}

		$value = $GLOBALS['shurloc_test_user_meta'][ $user_id ][ $key ] ?? '';

		if ( $single ) {
			return $value;
		}

		if ( '' === $value ) {
			return array();
		}

		return array( $value );
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * Update test user meta.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	function update_user_meta(
		int $user_id,
		string $key,
		$value
	): bool {
		$GLOBALS['shurloc_test_user_meta'][ $user_id ][ $key ] = $value;

		return true;
	}
}
