<?php
/**
 * WordPress function test doubles.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

use Shurloc\SiteTools\Checkout\Test_WooCommerce;

/*
 * Define the WordPress cache-duration constant when WordPress is not loaded.
 */
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

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
 * Current queried object ID.
 */
$GLOBALS['shurloc_test_queried_object_id'] = 0;

/**
 * Test post titles indexed by post ID.
 */
$GLOBALS['shurloc_test_titles'] = array();

/**
 * Test post permalinks indexed by post ID.
 */
$GLOBALS['shurloc_test_permalinks'] = array();

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

/**
 * WooCommerce instance used by tests.
 */
$GLOBALS['shurloc_test_woocommerce'] = null;

/**
 * WooCommerce orders indexed by order ID.
 */
$GLOBALS['shurloc_test_orders'] = array();

/**
 * WordPress user IDs returned by get_users() during tests.
 */
$GLOBALS['shurloc_test_users'] = array();

/**
 * WordPress options stored during tests.
 */
$GLOBALS['shurloc_test_options'] = array();

/**
 * Registered settings.
 */
$GLOBALS['shurloc_test_registered_settings'] = array();

/**
 * Registered settings sections.
 */
$GLOBALS['shurloc_test_settings_sections'] = array();

/**
 * Registered settings fields.
 */
$GLOBALS['shurloc_test_settings_fields'] = array();

/**
 * Nonce fields generated during tests.
 */
$GLOBALS['shurloc_test_nonce_fields'] = array();

/**
 * Whether nonce verification should succeed.
 */
$GLOBALS['shurloc_test_nonce_valid'] = true;

/**
 * Test user capabilities.
 */
$GLOBALS['shurloc_test_user_capabilities'] = array();

/**
 * Admin referer checks performed during tests.
 */
$GLOBALS['shurloc_test_admin_referer_checks'] = array();

/**
 * Redirect URLs recorded during tests.
 */
$GLOBALS['shurloc_test_redirects'] = array();

/**
 * Messages passed to wp_die() during tests.
 */
$GLOBALS['shurloc_test_wp_die_messages'] = array();

/**
 * Registered submenu pages.
 */
$GLOBALS['shurloc_test_submenu_pages'] = array();

/**
 * WordPress product IDs returned during tests.
 */
$GLOBALS['shurloc_test_product_ids'] = array();

/**
 * Stored taxonomy terms.
 */
$GLOBALS['shurloc_test_terms'] = array();

/**
 * Stored product comments.
 */
$GLOBALS['shurloc_test_comments'] = array();

/**
 * Stored shortcode registrations.
 */
$GLOBALS['wp_shortcodes'] = array();

/**
 * Enqueued styles.
 */
$GLOBALS['shurloc_test_enqueued_styles'] = array();

/**
 * Enqueued scripts.
 */
$GLOBALS['shurloc_test_enqueued_scripts'] = array();

/**
 * Localized scripts.
 */
$GLOBALS['shurloc_test_localized_scripts'] = array();

/**
 * Registered styles.
 */
$GLOBALS['shurloc_test_registered_styles'] = array();

/**
 * Registered scripts.
 */
$GLOBALS['shurloc_test_registered_scripts'] = array();

/**
 * Stored transient values.
 */
$GLOBALS['shurloc_test_transients'] = array();

/**
 * Product post types keyed by object ID.
 */
$GLOBALS['shurloc_test_post_types'] = array();

/**
 * Test autosave IDs.
 */
$GLOBALS['shurloc_test_autosaves'] = array();

/**
 * Test revision IDs.
 */
$GLOBALS['shurloc_test_revisions'] = array();

/**
 * Registered top-level administration menu pages.
 */
$GLOBALS['shurloc_test_menu_pages'] = array();

/**
 * Removed administration submenu pages.
 */
$GLOBALS['shurloc_test_removed_submenus'] = array();

/**
 * Taxonomy term assignments indexed by object ID.
 */
$GLOBALS['shurloc_test_post_terms'] = array();

/**
 * Current WordPress screen used during tests.
 */
$GLOBALS['shurloc_test_current_screen'] = null;

/**
 * Nonce verification checks recorded during tests.
 */
$GLOBALS['shurloc_test_nonce_checks'] = array();


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

		return $GLOBALS['shurloc_test_is_admin'] ?? false;
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

if ( ! function_exists( 'esc_textarea' ) ) {

	/**
	 * Escape text for use in a textarea.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_textarea(
		string $text
	): string {

		return htmlspecialchars(
			$text,
			ENT_QUOTES | ENT_SUBSTITUTE,
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

if ( ! function_exists( 'checked' ) ) {

	/**
	 * Output or return the checked HTML attribute.
	 *
	 * @param mixed $checked Current value.
	 * @param mixed $current Value to compare against.
	 * @param bool  $display Whether to display the attribute.
	 * @return string
	 */
	function checked(
		mixed $checked,
		mixed $current = true,
		bool $display = true
	): string {

		$result = $checked === $current
			? ' checked="checked"'
			: '';

		if ( $display ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed test-only HTML attribute.
			echo $result;
		}

		return $result;
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

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Remove trailing slashes from a string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( string $value ): string {

		return rtrim( $value, '/\\' );
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

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Get a test home URL.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	function home_url( string $path = '' ): string {

		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Get test blog information.
	 *
	 * @param string $show Information to retrieve.
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {

		unset( $show );

		return 'UTF-8';
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Sanitize a test URL.
	 *
	 * @param string $url URL to sanitize.
	 * @return string
	 */
	function esc_url_raw( string $url ): string {

		return $url;
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

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * Get the current test queried object ID.
	 *
	 * @return int
	 */
	function get_queried_object_id(): int {

		return $GLOBALS['shurloc_test_queried_object_id'];
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Get a test post title.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function get_the_title( int $post_id ): string {

		return $GLOBALS['shurloc_test_titles'][ $post_id ] ?? '';
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

		if ( empty( $GLOBALS['shurloc_test_filters'][ $hook_name ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['shurloc_test_filters'][ $hook_name ] as $callback ) {
			$value = $callback( $value );
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
	 * Get a test WordPress option.
	 *
	 * @param string $option_name    Option name.
	 * @param mixed  $default_return Value returned when the option is unavailable.
	 * @return mixed
	 */
	function get_option(
		string $option_name,
		mixed $default_return = false
	): mixed {

		if (
			array_key_exists(
				$option_name,
				$GLOBALS['shurloc_test_options']
			)
		) {
			return $GLOBALS['shurloc_test_options'][ $option_name ];
		}

		if ( 'date_format' === $option_name ) {
			return 'F j, Y';
		}

		return $default_return;
	}
}

if ( ! function_exists( 'register_setting' ) ) {

	/**
	 * Register a WordPress setting.
	 *
	 * @param string               $option_group Settings group.
	 * @param string               $option_name  Option name.
	 * @param array<string, mixed> $args         Registration arguments.
	 * @return void
	 */
	function register_setting(
		string $option_group,
		string $option_name,
		array $args = array()
	): void {

		$GLOBALS['shurloc_test_registered_settings'][] = array(
			'option_group' => $option_group,
			'option_name'  => $option_name,
			'args'         => $args,
		);
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {

	/**
	 * Register a settings section.
	 *
	 * @param string   $id       Section ID.
	 * @param string   $title    Section title.
	 * @param callable $callback Section callback.
	 * @param string   $page     Settings page.
	 * @return void
	 */
	function add_settings_section(
		string $id,
		string $title,
		callable $callback,
		string $page
	): void {

		$GLOBALS['shurloc_test_settings_sections'][] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
			'page'     => $page,
		);
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {

	/**
	 * Register a settings field.
	 *
	 * @param string   $id       Field ID.
	 * @param string   $title    Field title.
	 * @param callable $callback Field callback.
	 * @param string   $page     Settings page.
	 * @param string   $section  Settings section.
	 * @return void
	 */
	function add_settings_field(
		string $id,
		string $title,
		callable $callback,
		string $page,
		string $section = 'default'
	): void {

		$GLOBALS['shurloc_test_settings_fields'][] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
			'page'     => $page,
			'section'  => $section,
		);
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

if ( ! function_exists( 'WC' ) ) {
	/**
	 * Get the WooCommerce test instance.
	 *
	 * @return WooCommerce|Test_WooCommerce
	 */
	function WC(): WooCommerce|Test_WooCommerce { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Matches WooCommerce WC() API.

		if (
			! $GLOBALS['shurloc_test_woocommerce'] instanceof WooCommerce &&
			! $GLOBALS['shurloc_test_woocommerce'] instanceof Test_WooCommerce
		) {
			$GLOBALS['shurloc_test_woocommerce'] = new WooCommerce();
		}

		return $GLOBALS['shurloc_test_woocommerce'];
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Get a WooCommerce test order.
	 *
	 * @param int $order_id Order ID.
	 * @return WC_Order|false
	 */
	function wc_get_order(
		int $order_id
	): WC_Order|false {

		if (
			! isset( $GLOBALS['shurloc_test_orders'][ $order_id ] )
		) {
			return false;
		}

		$order = $GLOBALS['shurloc_test_orders'][ $order_id ];

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		return $order;
	}
}

if ( ! function_exists( 'delete_user_meta' ) ) {
	/**
	 * Delete test user metadata.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Metadata key.
	 * @return bool
	 */
	function delete_user_meta(
		int $user_id,
		string $meta_key
	): bool {

		if (
			! isset( $GLOBALS['shurloc_test_user_meta'][ $user_id ] ) ||
			! is_array( $GLOBALS['shurloc_test_user_meta'][ $user_id ] )
		) {
			return false;
		}

		if (
			! array_key_exists(
				$meta_key,
				$GLOBALS['shurloc_test_user_meta'][ $user_id ]
			)
		) {
			return false;
		}

		unset(
			$GLOBALS['shurloc_test_user_meta'][ $user_id ][ $meta_key ]
		);

		if (
			empty(
				$GLOBALS['shurloc_test_user_meta'][ $user_id ]
			)
		) {
			unset(
				$GLOBALS['shurloc_test_user_meta'][ $user_id ]
			);
		}

		return true;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape a URL.
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url(
		string $url
	): string {

		return htmlspecialchars(
			$url,
			ENT_QUOTES,
			'UTF-8'
		);
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * Render a test submit button.
	 *
	 * @param string               $text             Button text.
	 * @param string               $type             Button type.
	 * @param string               $name             Button name.
	 * @param bool                 $wrap             Whether to wrap the button in a paragraph.
	 * @param array<string,scalar> $other_attributes Additional button attributes.
	 * @return void
	 */
	function submit_button(
		string $text = 'Save Changes',
		string $type = 'primary large',
		string $name = 'submit',
		bool $wrap = true,
		array $other_attributes = array()
	): void {

		unset( $type, $other_attributes );

		$button = sprintf(
			'<input type="submit" name="%1$s" value="%2$s" />',
			esc_attr( $name ),
			esc_attr( $text )
		);

		if ( $wrap ) {
			echo '<p class="submit">' . $button . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stub output is escaped above.
			return;
		}

			echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stub output is escaped above.
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Execute callbacks registered for an action.
	 *
	 * @param string $hook_name Action hook name.
	 * @param mixed  ...$args   Arguments passed to registered callbacks.
	 * @return void
	 */
	function do_action(
		string $hook_name,
		mixed ...$args
	): void {

		if ( ! isset( $GLOBALS['shurloc_test_actions'][ $hook_name ] ) ) {
			return;
		}

		foreach ( $GLOBALS['shurloc_test_actions'][ $hook_name ] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Add query arguments to a URL.
	 *
	 * @param array<string,int|string> $args Query arguments.
	 * @param string                   $url  Base URL.
	 * @return string
	 */
	function add_query_arg(
		array $args,
		string $url
	): string {

		$query = http_build_query( $args );

		if ( '' === $query ) {
			return $url;
		}

		$separator = str_contains( $url, '?' )
			? '&'
			: '?';

		return $url . $separator . $query;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Get a test WordPress admin URL.
	 *
	 * @param string $path Path relative to the admin directory.
	 * @return string
	 */
	function admin_url(
		string $path = ''
	): string {

		$admin_url = 'https://example.com/wp-admin/';

		if ( '' === $path ) {
			return $admin_url;
		}

		return $admin_url . ltrim(
			$path,
			'/'
		);
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Sanitize content using the allowed post HTML rules.
	 *
	 * Test stub returns the supplied content unchanged.
	 *
	 * @param string $data Content to sanitize.
	 * @return string
	 */
	function wp_kses_post(
		string $data
	): string {

		return $data;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Get a test permalink.
	 *
	 * @param int $post_id Post ID.
	 * @return string|false
	 */
	function get_permalink(
		int $post_id
	): string|false {

		return $GLOBALS['shurloc_test_permalinks'][ $post_id ] ?? false;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Enqueue a test script.
	 *
	 * @param string            $handle    Script handle.
	 * @param string            $src       Script source URL.
	 * @param array<int,string> $deps      Script dependencies.
	 * @param string|bool|null  $ver       Script version.
	 * @param bool              $in_footer Whether to enqueue in the footer.
	 * @return void
	 */
	function wp_enqueue_script(
		string $handle,
		string $src = '',
		array $deps = array(),
		string|bool|null $ver = false,
		bool $in_footer = false
	): void {

		$GLOBALS['shurloc_test_enqueued_scripts'][] = array(
			'handle'    => $handle,
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {

	/**
	 * Localize data for a script.
	 *
	 * @param string               $handle      Script handle.
	 * @param string               $object_name JavaScript object name.
	 * @param array<string, mixed> $data        Data.
	 * @return bool
	 */
	function wp_localize_script(
		string $handle,
		string $object_name,
		array $data
	): bool {

		$GLOBALS['shurloc_test_localized_scripts'][] = array(
			'handle'      => $handle,
			'object_name' => $object_name,
			'data'        => $data,
		);

		return true;
	}
}

if ( ! function_exists( 'get_users' ) ) {
	/**
	 * Get test WordPress users.
	 *
	 * @param array<string,mixed> $args User query arguments.
	 * @return int[]
	 */
	function get_users(
		array $args = array()
	): array {

		unset( $args );

		return $GLOBALS['shurloc_test_users'];
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Update a test WordPress option.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @return bool
	 */
	function update_option(
		string $option_name,
		mixed $value
	): bool {

		$GLOBALS['shurloc_test_options'][ $option_name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Add a test WordPress option.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @param string $deprecated  Deprecated argument.
	 * @param bool   $autoload    Whether the option should autoload.
	 * @return bool
	 */
	function add_option(
		string $option_name,
		mixed $value = '',
		string $deprecated = '',
		bool $autoload = true
	): bool {

		unset( $deprecated, $autoload );

		if (
			array_key_exists(
				$option_name,
				$GLOBALS['shurloc_test_options']
			)
		) {
			return false;
		}

		$GLOBALS['shurloc_test_options'][ $option_name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Delete a test WordPress option.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	function delete_option(
		string $option_name
	): bool {

		if (
			! array_key_exists(
				$option_name,
				$GLOBALS['shurloc_test_options']
			)
		) {
			return false;
		}

		unset(
			$GLOBALS['shurloc_test_options'][ $option_name ]
		);

		return true;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	/**
	 * Get test user data.
	 *
	 * @param int $user_id User ID.
	 * @return WP_User|false
	 */
	function get_userdata(
		int $user_id
	): WP_User|false {

		if (
			isset( $GLOBALS['shurloc_test_users'][ $user_id ] ) &&
			true === $GLOBALS['shurloc_test_users'][ $user_id ]
		) {
			return new WP_User( $user_id );
		}

		if (
			in_array(
				$user_id,
				$GLOBALS['shurloc_test_users'],
				true
			)
		) {
			return new WP_User( $user_id );
		}

		return false;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	/**
	 * Unserialize data when appropriate.
	 *
	 * @param mixed $data Data to potentially unserialize.
	 * @return mixed
	 */
	function maybe_unserialize(
		mixed $data
	): mixed {

		if ( ! is_string( $data ) ) {
			return $data;
		}

		$trimmed_data = trim( $data );

		if ( '' === $trimmed_data ) {
			return $data;
		}

		if (
		! preg_match(
			'/^(?:a|O|s|i|d|b):|^N;/',
			$trimmed_data
		)
		) {
			return $data;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Test stub mirrors WordPress behavior.
		$result = unserialize( $trimmed_data );

		if (
			false === $result &&
			'b:0;' !== $trimmed_data
		) {
			return $data;
		}

		return $result;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Output a test nonce field.
	 *
	 * @param string $action  Nonce action.
	 * @param string $name    Nonce field name.
	 * @param bool   $referer Whether to output a referer field.
	 * @param bool   $display Whether to display the field.
	 * @return string
	 */
	function wp_nonce_field(
		string $action = '-1',
		string $name = '_wpnonce',
		bool $referer = true,
		bool $display = true
	): string {

		unset( $referer );

		$GLOBALS['shurloc_test_nonce_fields'][] = array(
			'action' => $action,
			'name'   => $name,
		);

		$field = sprintf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( $name ),
			esc_attr( 'test-nonce-' . $action )
		);

		if ( $display ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test-only generated HTML.
		}

		return $field;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Create a test nonce.
	 *
	 * @param string|int $action Nonce action.
	 * @return string
	 */
	function wp_create_nonce(
		string|int $action = -1
	): string {

		return 'test-nonce-' . (string) $action;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Verify a test nonce.
	 *
	 * @param string     $nonce  Nonce value.
	 * @param string|int $action Nonce action.
	 * @return int|false
	 */
	function wp_verify_nonce(
		string $nonce,
		string|int $action = -1
	): int|false {

		if ( ! $GLOBALS['shurloc_test_nonce_valid'] ) {
			return false;
		}

		return 'test-nonce-' . (string) $action === $nonce
			? 1
			: false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Determine whether the current test user has a capability.
	 *
	 * @param string $capability Capability name.
	 * @param mixed  ...$args    Additional capability arguments.
	 * @return bool
	 */
	function current_user_can(
		string $capability,
		mixed ...$args
	): bool {

		unset( $args );

		return $GLOBALS['shurloc_test_user_capabilities']
			[ $capability ] ?? true;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Record an admin referer check.
	 *
	 * @param string $action     Nonce action.
	 * @param string $query_arg  Nonce request argument.
	 * @return int|false
	 */
	function check_admin_referer(
		string $action = '-1',
		string $query_arg = '_wpnonce'
	): int|false {

		unset( $query_arg );

		$GLOBALS['shurloc_test_admin_referer_checks'][] =
			$action;

		return $GLOBALS['shurloc_test_nonce_valid']
			? 1
			: false;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanitize a test text field.
	 *
	 * @param string $value Value to sanitize.
	 * @return string
	 */
	function sanitize_text_field(
		string $value
	): string {

		return trim(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test stub intentionally mirrors basic sanitization behavior.
			strip_tags( $value )
		);
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {

	/**
	 * Sanitize a multiline text field.
	 *
	 * @param string $text Text to sanitize.
	 * @return string
	 */
	function sanitize_textarea_field(
		string $text
	): string {

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Reduce dependency on WordPress functions.
		$text = strip_tags( $text );

		$text = str_replace(
			array( "\r\n", "\r" ),
			"\n",
			$text
		);

		return trim( $text );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Stop test execution with a WordPress error.
	 *
	 * @param string $message Error message.
	 * @return never
	 *
	 * @throws RuntimeException When called during a test.
	 */
	function wp_die(
		string $message
	): never {

		$GLOBALS['shurloc_test_wp_die_messages'][] =
			$message;

		throw new RuntimeException(
			esc_html( $message )
		);
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Convert a value to a non-negative integer.
	 *
	 * @param mixed $value Value to convert.
	 * @return int
	 */
	function absint(
		mixed $value
	): int {

		return abs(
			(int) $value
		);
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Register a test submenu page.
	 *
	 * @param string         $parent_slug Parent menu slug.
	 * @param string         $page_title  Page title.
	 * @param string         $menu_title  Menu title.
	 * @param string         $capability  Required capability.
	 * @param string         $menu_slug   Menu slug.
	 * @param callable|null  $callback    Page callback.
	 * @param int|float|null $position   Menu position.
	 * @return string
	 */
	function add_submenu_page(
		string $parent_slug,
		string $page_title,
		string $menu_title,
		string $capability,
		string $menu_slug,
		?callable $callback = null,
		int|float|null $position = null
	): string {

		$GLOBALS['shurloc_test_submenu_pages'][] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'menu_slug'   => $menu_slug,
			'callback'    => $callback,
			'position'    => $position,
		);

		return 'shurloc-checkout-tools' === $menu_slug
			? 'shurloc-checkout-tools'
			: 'shurloc-test-submenu-hook';
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {

	/**
	 * Get current post ID.
	 *
	 * @return int
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid, Squiz.Commenting.FunctionComment.Missing
	function get_the_ID(): int {

		return 123;
	}
}


if ( ! function_exists( 'has_filter' ) ) {

	/**
	 * Check whether a filter is registered.
	 *
	 * @param string        $hook     Hook name.
	 * @param callable|null $callback Optional callback.
	 * @return int|bool Priority if found, true if callbacks exist and no callback
	 *                   was specified, otherwise false.
	 */
	function has_filter(
		string $hook,
		$callback = null
	) {

		if ( empty( $GLOBALS['shurloc_test_filters'][ $hook ] ) ) {
			return false;
		}

		if ( null === $callback ) {
			return true;
		}

		foreach (
			$GLOBALS['shurloc_test_filters'][ $hook ]
			as $registered
		) {

			if ( $registered === $callback ) {
				return 10;
			}
		}

		return false;
	}
}


if ( ! function_exists( 'has_action' ) ) {

	/**
	 * Check whether an action is registered.
	 *
	 * @param string   $hook Hook name.
	 * @param callable $callback Optional callback.
	 * @return int|bool Priority or bool.
	 */
	function has_action(
		string $hook,
		$callback = null
	) {

		if (
			empty(
				$GLOBALS['shurloc_test_actions'][ $hook ]
			)
		) {
			return false;
		}

		if ( null === $callback ) {
			return true;
		}

		foreach (
			$GLOBALS['shurloc_test_actions'][ $hook ]
			as $registered
		) {

			if ( $registered === $callback ) {

				return 10;
			}
		}

		return false;
	}
}


if ( ! function_exists( 'get_edit_post_link' ) ) {

	/**
	 * Get edit post link.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $context Context.
	 * @return string
	 */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Squiz.Commenting.FunctionComment.Missing
	function get_edit_post_link(
		int $post_id,
		string $context = ''
	): string {

		return 'https://example.com/wp-admin/post.php?post=' . $post_id;
	}
}


if ( ! function_exists( 'wp_set_object_terms' ) ) {

	/**
	 * Set object terms.
	 *
	 * @param int                      $object_id Object ID.
	 * @param string|array<string|int> $terms Terms.
	 * @param string                   $taxonomy Taxonomy.
	 * @return bool
	 */
	function wp_set_object_terms(
		int $object_id,
		$terms,
		string $taxonomy
	): bool {

		if ( ! isset( $GLOBALS['shurloc_test_terms'][ $object_id ] ) ) {
			$GLOBALS['shurloc_test_terms'][ $object_id ] = array();
		}

		$GLOBALS['shurloc_test_terms'][ $object_id ][ $taxonomy ] = (array) $terms;

		return true;
	}
}


if ( ! function_exists( 'get_the_terms' ) ) {

	/**
	 * Get object terms.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<int,string>
	 */
	function get_the_terms(
		int $object_id,
		string $taxonomy
	): array {

		return $GLOBALS['shurloc_test_terms'][ $object_id ][ $taxonomy ] ?? array();
	}
}


if ( ! function_exists( 'wp_get_post_terms' ) ) {

	/**
	 * Get post terms.
	 *
	 * @param int                  $post_id Post ID.
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string,mixed>  $args Arguments.
	 * @return array<int,string>
	 */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Squiz.Commenting.FunctionComment.Missing
	function wp_get_post_terms(
		int $post_id,
		string $taxonomy,
		array $args = array()
	): array {

		return $GLOBALS['shurloc_test_terms'][ $post_id ][ $taxonomy ] ?? array();
	}
}


if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {

	/**
	 * Get attachment image URL.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size Image size.
	 * @return false
	 */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Squiz.Commenting.FunctionComment.Missing
	function wp_get_attachment_image_url(
		int $attachment_id,
		string $size = 'full'
	) {

		return false;
	}
}


if ( ! function_exists( 'is_wp_error' ) ) {

	/**
	 * Determine whether a value is a WP_Error object.
	 *
	 * @param mixed $thing Value to check.
	 * @return bool
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Squiz.Commenting.FunctionComment.Missing
	function is_wp_error(
		$thing
	): bool {

		return false;
	}
}


if ( ! function_exists( 'get_comments' ) ) {

	/**
	 * Get test comments.
	 *
	 * @param array<string,mixed> $args Comment query arguments.
	 * @return array<int,object>
	 */
	function get_comments(
		array $args = array()
	): array {

		$post_id = $args['post_id'] ?? 0;

		return $GLOBALS['shurloc_test_comments'][ $post_id ] ?? array();
	}
}


if ( ! function_exists( 'stripslashes_deep' ) ) {

	/**
	 * Remove slashes recursively.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function stripslashes_deep( $value ) {

		if ( is_array( $value ) ) {

			return array_map(
				'stripslashes_deep',
				$value
			);
		}

		return stripslashes( $value );
	}
}


if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Register a shortcode callback.
	 *
	 * @param string   $tag      Shortcode tag.
	 * @param callable $callback Shortcode callback.
	 * @return bool True when the shortcode is registered.
	 */
	function add_shortcode(
		string $tag,
		callable $callback
	): bool {

		if ( ! isset( $GLOBALS['wp_shortcodes'] ) ) {
			$GLOBALS['wp_shortcodes'] = array();
		}

		$GLOBALS['wp_shortcodes'][ $tag ] = $callback;

		return true;
	}
}


if ( ! function_exists( 'is_singular' ) ) {

	/**
	 * Determine whether current request is singular.
	 *
	 * @return bool
	 */
	function is_singular(): bool {

		return true;
	}
}


if ( ! function_exists( 'has_shortcode' ) ) {

	/**
	 * Determine whether content contains a shortcode.
	 *
	 * @param string $content Content to search.
	 * @param string $tag     Shortcode tag.
	 * @return bool
	 */
	function has_shortcode(
		string $content,
		string $tag
	): bool {

		return str_contains(
			$content,
			'[' . $tag
		);
	}
}


if ( ! function_exists( 'wp_style_is' ) ) {

	/**
	 * Determine whether a test stylesheet has been enqueued.
	 *
	 * @param string $handle Stylesheet handle.
	 * @param string $status Status query.
	 * @return bool
	 */
	function wp_style_is(
		string $handle,
		string $status = 'enqueued'
	): bool {

		switch ( $status ) {

			case 'registered':
				return isset(
					$GLOBALS['shurloc_test_registered_styles'][ $handle ]
				);

			case 'enqueued':
				return isset(
					$GLOBALS['shurloc_test_enqueued_styles'][ $handle ]
				);

			default:
				return false;
		}
	}
}


if ( ! function_exists( 'wp_register_style' ) ) {

	/**
	 * Register a stylesheet.
	 *
	 * @param string            $handle Stylesheet handle.
	 * @param string|false      $src    Stylesheet source.
	 * @param array<int,string> $deps   Dependencies.
	 * @param string|false      $ver    Version.
	 * @param string            $media  Media type.
	 * @return void
	 */
	function wp_register_style(
		string $handle,
		$src = false,
		array $deps = array(),
		$ver = false,
		string $media = 'all'
	): void {

		$GLOBALS['shurloc_test_registered_styles'][ $handle ] = array(
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		);
	}
}


if ( ! function_exists( '_e' ) ) {

	/**
	 * Echo translated text.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return void
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Squiz.Commenting.FunctionComment.Missing
	function _e(
		string $text,
		string $domain = 'default'
	): void {

		echo esc_html( $text );
	}
}


if ( ! function_exists( 'esc_html_e' ) ) {

	/**
	 * Translate, escape, and echo text.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return void
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Squiz.Commenting.FunctionComment.Missing
	function esc_html_e(
		string $text,
		string $domain = 'default'
	): void {

		echo esc_html( $text );
	}
}


if ( ! function_exists( 'wp_register_script' ) ) {

	/**
	 * Register a script for tests.
	 *
	 * @param string           $handle    Handle.
	 * @param string           $src       Source URL.
	 * @param string[]         $deps      Dependencies.
	 * @param string|bool|null $ver       Version.
	 * @param bool             $in_footer Whether to load in the footer.
	 * @return void
	 */
	function wp_register_script(
		string $handle,
		string $src,
		array $deps = array(),
		$ver = false,
		bool $in_footer = false
	): void {

		$GLOBALS['shurloc_test_registered_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}


if ( ! function_exists( 'wp_script_is' ) ) {

	/**
	 * Check whether a script is registered.
	 *
	 * @param string $handle Script handle.
	 * @param string $status Status to check.
	 * @return bool
	 */
	function wp_script_is(
		string $handle,
		string $status
	): bool {

		if ( 'registered' !== $status ) {
			return false;
		}

		return isset(
			$GLOBALS['shurloc_test_registered_scripts'][ $handle ]
		);
	}
}


if ( ! function_exists( 'get_posts' ) ) {

	/**
	 * Test replacement for get_posts().
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return int[]
	 */
	function get_posts(
		array $args = array()
	): array {

		$product_ids = $GLOBALS['shurloc_test_product_ids'];

		if (
			isset( $args['post__not_in'] ) &&
			is_array( $args['post__not_in'] )
		) {
			$product_ids = array_values(
				array_diff(
					$product_ids,
					array_map(
						'intval',
						$args['post__not_in']
					)
				)
			);
		}

		if (
			isset( $args['posts_per_page'] ) &&
			is_int( $args['posts_per_page'] ) &&
			0 < $args['posts_per_page']
		) {
			$product_ids = array_slice(
				$product_ids,
				0,
				$args['posts_per_page']
			);
		}

		return array_map(
			'intval',
			$product_ids
		);
	}
}


if ( ! function_exists( 'get_transient' ) ) {

	/**
	 * Retrieve a test transient.
	 *
	 * @param string $key Transient key.
	 *
	 * @return mixed
	 */
	function get_transient(
		string $key
	) {

		return $GLOBALS['shurloc_test_transients'][ $key ]
			?? false;
	}
}


if ( ! function_exists( 'set_transient' ) ) {

	/**
	 * Store a test transient.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Expiration in seconds.
	 *
	 * @return bool
	 */
	function set_transient(
		string $key,
		$value,
		int $expiration = 0
	): bool {

		unset( $expiration );

		$GLOBALS['shurloc_test_transients'][ $key ] = $value;

		return true;
	}
}


if ( ! function_exists( 'wp_is_post_autosave' ) ) {

	/**
	 * Determine whether a post is an autosave.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int|false
	 */
	function wp_is_post_autosave(
		int $post_id
	) {

		return in_array(
			$post_id,
			$GLOBALS['shurloc_test_autosaves'],
			true
		)
			? $post_id
			: false;
	}
}


if ( ! function_exists( 'wp_is_post_revision' ) ) {

	/**
	 * Determine whether a post is a revision.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int|false
	 */
	function wp_is_post_revision(
		int $post_id
	) {

		return in_array(
			$post_id,
			$GLOBALS['shurloc_test_revisions'],
			true
		)
			? $post_id
			: false;
	}
}


if ( ! function_exists( 'get_post_type' ) ) {

	/**
	 * Retrieve a test post type.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string|false
	 */
	function get_post_type(
		int $post_id
	) {

		return $GLOBALS['shurloc_test_post_types'][ $post_id ]
			?? false;
	}
}


if ( ! function_exists( 'add_menu_page' ) ) {

	/**
	 * Test replacement for add_menu_page().
	 *
	 * @param string         $page_title Page title.
	 * @param string         $menu_title Menu title.
	 * @param string         $capability Required capability.
	 * @param string         $menu_slug  Menu slug.
	 * @param callable|null  $callback   Page callback.
	 * @param string         $icon_url   Menu icon.
	 * @param int|float|null $position  Menu position.
	 *
	 * @return string
	 */
	function add_menu_page(
		string $page_title,
		string $menu_title,
		string $capability,
		string $menu_slug,
		?callable $callback = null,
		string $icon_url = '',
		$position = null
	): string {

		$GLOBALS['shurloc_test_menu_pages'][] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
			'icon_url'   => $icon_url,
			'position'   => $position,
		);

		return 'toplevel_page_' . $menu_slug;
	}
}


if ( ! function_exists( 'remove_submenu_page' ) ) {

	/**
	 * Test replacement for remove_submenu_page().
	 *
	 * @param string $menu_slug    Parent menu slug.
	 * @param string $submenu_slug Submenu slug.
	 *
	 * @return false
	 */
	function remove_submenu_page(
		string $menu_slug,
		string $submenu_slug
	) {

		$GLOBALS['shurloc_test_removed_submenus'][] = array(
			'parent_slug' => $menu_slug,
			'menu_slug'   => $submenu_slug,
		);

		return false;
	}
}


if ( ! function_exists( 'get_term' ) ) {

	/**
	 * Retrieve a test taxonomy term.
	 *
	 * Test replacement for get_term().
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return object|null
	 */
	function get_term(
		int $term_id,
		string $taxonomy = ''
	): object|null {

		if (
			'' === $taxonomy ||
			! isset(
				$GLOBALS['shurloc_test_terms'][ $taxonomy ][ $term_id ]
			)
		) {
			return null;
		}

		$term =
			$GLOBALS['shurloc_test_terms'][ $taxonomy ][ $term_id ];

		return is_object( $term )
			? $term
			: null;
	}
}


if ( ! function_exists( 'has_term' ) ) {

	/**
	 * Determine whether a post has a test taxonomy term.
	 *
	 * Test replacement for has_term().
	 *
	 * @param int|string|array<int|string> $term     Term ID, slug, or terms.
	 * @param string                       $taxonomy Taxonomy name.
	 * @param int                          $post_id  Post ID.
	 * @return bool
	 */
	function has_term(
		int|string|array $term,
		string $taxonomy,
		int $post_id
	): bool {

		$requested_terms = is_array( $term )
			? $term
			: array( $term );

		$post_terms = $GLOBALS['shurloc_test_post_terms'][ $post_id ]
			?? array();

		foreach ( $requested_terms as $requested_term ) {
			if ( in_array( (int) $requested_term, $post_terms, true ) ) {
				return true;
			}
		}

		$taxonomy_terms = $GLOBALS['shurloc_test_terms'][ $post_id ][ $taxonomy ]
			?? array();

		if ( is_array( $term ) ) {
			return array_intersect( $term, $taxonomy_terms ) !== array();
		}

		return in_array( $term, $taxonomy_terms, true );
	}
}


if ( ! function_exists( 'update_post_meta' ) ) {

	/**
	 * Update test post metadata.
	 *
	 * Test replacement for update_post_meta().
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Metadata key.
	 * @param mixed  $value   Metadata value.
	 * @return bool
	 */
	function update_post_meta(
		int $post_id,
		string $key,
		$value
	): bool {

		$GLOBALS['shurloc_test_post_meta'][ $post_id ][ $key ] =
		$value;

		return true;
	}
}


if ( ! function_exists( 'delete_post_meta' ) ) {

	/**
	 * Delete test post metadata.
	 *
	 * Test replacement for delete_post_meta().
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Metadata key.
	 * @return bool
	 */
	function delete_post_meta(
		int $post_id,
		string $key
	): bool {

		if (
			! array_key_exists(
				$key,
				$GLOBALS['shurloc_test_post_meta'][ $post_id ]
					?? array()
			)
		) {
			return false;
		}

		unset(
			$GLOBALS['shurloc_test_post_meta'][ $post_id ][ $key ]
		);

		if (
			empty(
				$GLOBALS['shurloc_test_post_meta'][ $post_id ]
			)
		) {
			unset(
				$GLOBALS['shurloc_test_post_meta'][ $post_id ]
			);
		}

		return true;
	}
}


if ( ! function_exists( 'get_current_screen' ) ) {

	/**
	 * Get the current test admin screen.
	 *
	 * Test replacement for get_current_screen().
	 *
	 * @return WP_Screen|null
	 */
	function get_current_screen(): ?WP_Screen {

		$screen = $GLOBALS['shurloc_test_current_screen']
			?? null;

		return $screen instanceof WP_Screen
			? $screen
			: null;
	}
}


if ( ! function_exists( 'get_terms' ) ) {

	/**
	 * Retrieve test taxonomy terms.
	 *
	 * Test replacement for get_terms().
	 *
	 * @param array<string,mixed> $args Term query arguments.
	 * @return array<int,object>
	 */
	function get_terms(
		array $args = array()
	): array {

		$taxonomy = isset( $args['taxonomy'] )
			? (string) $args['taxonomy']
			: '';

		if (
			'' === $taxonomy ||
			! isset(
				$GLOBALS['shurloc_test_terms'][ $taxonomy ]
			)
		) {
			return array();
		}

		return array_values(
			$GLOBALS['shurloc_test_terms'][ $taxonomy ]
		);
	}
}


if ( ! function_exists( 'metadata_exists' ) ) {

	/**
	 * Determine whether test metadata exists.
	 *
	 * Test replacement for metadata_exists().
	 *
	 * @param string $meta_type Metadata type.
	 * @param int    $object_id Object ID.
	 * @param string $meta_key  Metadata key.
	 * @return bool
	 */
	function metadata_exists(
		string $meta_type,
		int $object_id,
		string $meta_key
	): bool {

		if ( 'post' !== $meta_type ) {
			return false;
		}

		return array_key_exists(
			$meta_key,
			$GLOBALS['shurloc_test_post_meta'][ $object_id ]
				?? array()
		);
	}
}
