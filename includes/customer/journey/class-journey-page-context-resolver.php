<?php
/**
 * Customer Journey browser page context resolution.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

use WC_Product;

/**
 * Derive a view type and object ID from WordPress, not browser object claims.
 *
 * A matching public permalink can identify a post or product. Other local
 * routes can still be page views without an object ID. This verifies page
 * context, but cannot prove that a browser actually displayed the page.
 */
final class Journey_Page_Context_Resolver {
	/**
	 * Shared relative-URI sanitizer.
	 *
	 * @var Journey_Attribution_Sanitizer
	 */
	private Journey_Attribution_Sanitizer $attribution;

	/**
	 * Constructor.
	 *
	 * @param Journey_Attribution_Sanitizer|null $attribution Existing sanitizer.
	 */
	public function __construct( ?Journey_Attribution_Sanitizer $attribution = null ) {
		$this->attribution = $attribution ?? new Journey_Attribution_Sanitizer();
	}

	/**
	 * Resolve a validated browser URI against the site's published content.
	 *
	 * A mapped but nonpublic or mismatched post is rejected. An unmapped local
	 * route is a general page view and has no asserted post or product ID.
	 *
	 * @param string $page_uri Browser-supplied relative page URI.
	 * @return array{event_type:string,post_id:int|null,product_id:int|null}|null Verified context.
	 */
	public function resolve( string $page_uri ): ?array {
		$path = $this->attribution->sanitize( request_uri: $page_uri, referrer_url: null )['landing_path'];
		$home = wp_parse_url( home_url() );
		if ( null === $path || ! is_array( $home ) ||
			! isset( $home['scheme'], $home['host'] ) ||
			! in_array( strtolower( $home['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}

		$home_path = rtrim( $home['path'] ?? '', '/' );
		if ( '' !== $home_path && $path !== $home_path && ! str_starts_with( $path, $home_path . '/' ) ) {
			return null;
		}

		$origin   = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		$page_url = $origin . $page_uri;
		$post_id  = url_to_postid( $page_url );
		if ( 0 >= $post_id ) {
			return array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'post_id'    => null,
				'product_id' => null,
			);
		}

		$permalink = get_permalink( $post_id );
		if ( 'publish' !== get_post_status( $post_id ) ||
			! is_string( $permalink ) ||
			! $this->matches_permalink( page_url: $page_url, permalink: $permalink, home_host: $home['host'] ) ) {
			return null;
		}

		$post_type = get_post_type( $post_id );
		if ( 'product' === $post_type ) {
			if ( ! function_exists( 'wc_get_product' ) ) {
				return null;
			}

			$product = wc_get_product( $post_id );
			if ( ! $product instanceof WC_Product || $product->get_id() !== $post_id || 'publish' !== $product->get_status() ) {
				return null;
			}

			return array(
				'event_type' => Journey_Event_Type::PRODUCT_VIEW,
				'post_id'    => null,
				'product_id' => $post_id,
			);
		}

		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return null;
		}

		return array(
			'event_type' => Journey_Event_Type::PAGE_VIEW,
			'post_id'    => $post_id,
			'product_id' => null,
		);
	}

	/**
	 * Verify a browser URI is the configured WooCommerce checkout page.
	 *
	 * Both classic checkout and Checkout Blocks use the configured page. The
	 * normal page resolver checks its published status and canonical permalink,
	 * excluding checkout subroutes such as order payment and order received.
	 *
	 * @param string $page_uri Browser-supplied relative page URI.
	 * @return bool Whether the URI identifies the public checkout page.
	 */
	public function is_checkout_page( string $page_uri ): bool {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		$checkout_id = wc_get_page_id( 'checkout' );
		if ( 0 >= $checkout_id ) {
			return false;
		}

		$context = $this->resolve( page_uri: $page_uri );
		return null !== $context &&
			Journey_Event_Type::PAGE_VIEW === $context['event_type'] &&
			$checkout_id === $context['post_id'];
	}

	/**
	 * Check that a resolved post corresponds to the claimed local page path.
	 *
	 * Canonical query arguments are required for sites using plain permalinks;
	 * extra campaign parameters on the viewed URL do not change the match.
	 *
	 * @param string $page_url  Claimed absolute page URL.
	 * @param string $permalink WordPress permalink for the mapped post.
	 * @param string $home_host Site host.
	 * @return bool Whether the mapped post belongs to this exact local page.
	 */
	private function matches_permalink( string $page_url, string $permalink, string $home_host ): bool {
		$page      = wp_parse_url( $page_url );
		$canonical = wp_parse_url( $permalink );
		if ( ! is_array( $page ) || ! is_array( $canonical ) ||
			! isset( $canonical['host'] ) ||
			strtolower( $canonical['host'] ) !== strtolower( $home_host ) ||
			'/' . trim( $page['path'] ?? '/', '/' ) !== '/' . trim( $canonical['path'] ?? '/', '/' ) ) {
			return false;
		}

		$canonical_query = array();
		$page_query      = array();
		parse_str( $canonical['query'] ?? '', $canonical_query );
		parse_str( $page['query'] ?? '', $page_query );
		foreach ( $canonical_query as $key => $value ) {
			if ( ! array_key_exists( $key, $page_query ) || $page_query[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}
}
