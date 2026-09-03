<?php
/**
 * WooCommerce product breadcrumb schema integration.
 *
 * Synchronizes Yoast's BreadcrumbList schema with the visible WooCommerce
 * breadcrumb trail, disables WooCommerce's duplicate breadcrumb schema,
 * and links the WebPage schema node to the Product node.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Frontend;

use WC_Breadcrumb;

defined( 'ABSPATH' ) || exit;

/**
 * Manages product breadcrumb and WebPage schema.
 */
final class Breadcrumb_Schema {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'wpseo_schema_breadcrumb',
			array( $this, 'synchronize_breadcrumb_schema' ),
			99
		);

		add_filter(
			'woocommerce_structured_data_breadcrumblist',
			array( $this, 'disable_woocommerce_breadcrumb_schema' ),
			99,
			2
		);

		add_filter(
			'wpseo_schema_webpage',
			array( $this, 'add_webpage_main_entity' ),
			20
		);
	}

	/**
	 * Make Yoast's BreadcrumbList schema match the visible WooCommerce trail.
	 *
	 * The schema trail is normalized as:
	 *
	 * Home → Products → category hierarchy → product
	 *
	 * @param array<string, mixed> $piece Yoast BreadcrumbList schema data.
	 *
	 * @return array<string, mixed>
	 */
	public function synchronize_breadcrumb_schema( array $piece ): array {

		if ( ! $this->is_product_page() ) {
			return $piece;
		}

		if ( ! class_exists( 'WC_Breadcrumb' ) ) {
			return $piece;
		}

		$product_id = get_queried_object_id();

		if ( ! $product_id ) {
			return $piece;
		}

		$breadcrumb = new WC_Breadcrumb();
		$breadcrumb->generate();

		$woocommerce_crumbs = $breadcrumb->get_breadcrumb();

		if ( empty( $woocommerce_crumbs ) ) {
			return $piece;
		}

		$home_url     = home_url( '/' );
		$products_url = home_url( '/shop/' );
		$product_url  = get_permalink( $product_id );

		if ( ! is_string( $product_url ) || '' === $product_url ) {
			return $piece;
		}

		$normalized_crumbs = array(
			array(
				'Home',
				$home_url,
			),
			array(
				'Products',
				$products_url,
			),
		);

		foreach ( $woocommerce_crumbs as $crumb ) {

			if ( empty( $crumb[0] ) ) {
				continue;
			}

			$name = html_entity_decode(
				wp_strip_all_tags( (string) $crumb[0] ),
				ENT_QUOTES,
				get_bloginfo( 'charset' )
			);

			$url = isset( $crumb[1] )
				? (string) $crumb[1]
				: '';

			$normalized_url = '' !== $url
				? untrailingslashit( $url )
				: '';

			if (
				untrailingslashit( $home_url ) === $normalized_url ||
				untrailingslashit( $products_url ) === $normalized_url ||
				0 === strcasecmp( $name, 'Home' ) ||
				0 === strcasecmp( $name, 'Products' )
			) {
				continue;
			}

			$normalized_crumbs[] = array(
				$name,
				$url,
			);
		}

		$product_name = html_entity_decode(
			wp_strip_all_tags( get_the_title( $product_id ) ),
			ENT_QUOTES,
			get_bloginfo( 'charset' )
		);

		$last_crumb = end( $normalized_crumbs );
		reset( $normalized_crumbs );

		$last_name = (string) $last_crumb[0];

		if ( $last_name !== $product_name ) {
			$normalized_crumbs[] = array(
				$product_name,
				'',
			);
		}

		$item_list = array();
		$last_key  = array_key_last( $normalized_crumbs );

		foreach ( $normalized_crumbs as $key => $crumb ) {

			$name = trim( (string) $crumb[0] );

			$url = (string) $crumb[1];

			if ( '' === $name ) {
				continue;
			}

			$list_item = array(
				'@type'    => 'ListItem',
				'position' => count( $item_list ) + 1,
				'name'     => $name,
			);

			/*
			 * Yoast omits "item" from the final breadcrumb representing the
			 * current page.
			 */
			if ( $key !== $last_key && '' !== $url ) {
				$list_item['item'] = esc_url_raw( $url );
			}

			$item_list[] = $list_item;
		}

		if ( empty( $item_list ) ) {
			return $piece;
		}

		/*
		 * Preserve Yoast's existing @id so references from the WebPage node
		 * continue to point to this BreadcrumbList.
		 */
		$piece['@type']           = 'BreadcrumbList';
		$piece['itemListElement'] = $item_list;

		return $piece;
	}

	/**
	 * Disable WooCommerce's native BreadcrumbList schema on product pages.
	 *
	 * Yoast already outputs the synchronized BreadcrumbList in its JSON-LD
	 * graph. Keeping WooCommerce's version would produce duplicate breadcrumb
	 * structured data.
	 *
	 * @param array<string, mixed> $markup      WooCommerce schema markup.
	 * @param WC_Breadcrumb        $breadcrumbs WooCommerce breadcrumb object.
	 *
	 * @return array<string, mixed>
	 */
	public function disable_woocommerce_breadcrumb_schema(
		array $markup,
		WC_Breadcrumb $breadcrumbs
	): array {

		unset( $breadcrumbs );

		if ( ! $this->is_product_page() ) {
			return $markup;
		}

		return array();
	}

	/**
	 * Add the Product as the main entity of Yoast's WebPage node.
	 *
	 * @param array<string, mixed> $data Yoast WebPage schema data.
	 *
	 * @return array<string, mixed>
	 */
	public function add_webpage_main_entity( array $data ): array {

		if ( ! $this->is_product_page() ) {
			return $data;
		}

		$product_url = $this->get_product_url_from_schema( $data );

		if ( null === $product_url ) {
			return $data;
		}

		$data['mainEntity'] = array(
			'@id' => $product_url . '#product',
		);

		return $data;
	}

	/**
	 * Determine whether the current request is a product page.
	 *
	 * @return bool
	 */
	private function is_product_page(): bool {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Get the canonical product URL from a WebPage schema node.
	 *
	 * @param array<string, mixed> $data WebPage schema data.
	 *
	 * @return string|null
	 */
	private function get_product_url_from_schema( array $data ): ?string {

		if ( empty( $data['url'] ) || ! is_string( $data['url'] ) ) {
			return null;
		}

		$url = strtok( $data['url'], '#' );

		return trailingslashit( $url );
	}
}
