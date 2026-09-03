<?php
/**
 * Removes WooCommerce Product schema.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

/**
 * WooCommerce schema integration.
 */
final class WooCommerce_Schema_Integration {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'woocommerce_structured_data_product',
			array( $this, 'remove_product_schema' ),
			20
		);
	}

	/**
	 * Remove WooCommerce product schema.
	 *
	 * Returning an empty array prevents WooCommerce from outputting
	 * the Product JSON-LD block.
	 *
	 * @param array<string,mixed> $markup Product schema markup.
	 * @return array<string,mixed>
	 */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Squiz.Commenting.FunctionComment.Missing
	public function remove_product_schema(
		array $markup
	): array {

		return array();
	}
}
