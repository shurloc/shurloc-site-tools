<?php
/**
 * Yoast primary product category integration.
 *
 * Prevents Yoast SEO from rendering its own primary product category UI while
 * preserving compatibility with Yoast's primary-category metadata.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates Shur-loc primary product categories with Yoast SEO.
 */
final class Yoast_Primary_Category_Integration {

	/**
	 * Product post type.
	 */
	private const POST_TYPE = 'product';

	/**
	 * Product category taxonomy.
	 */
	private const TAXONOMY = 'product_cat';

	/**
	 * Register Yoast integration hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'wpseo_primary_term_taxonomies',
			array(
				$this,
				'remove_product_category',
			),
			11,
			3
		);
	}

	/**
	 * Remove product categories from Yoast's primary-term selector.
	 *
	 * Shur-loc Product Tools provides its own primary-category selector for
	 * products while continuing to store the selected value in Yoast-compatible
	 * metadata.
	 *
	 * @param array<string,mixed> $taxonomies     Primary-term taxonomies.
	 * @param string              $post_type      Current post type.
	 * @param array<string,mixed> $all_taxonomies All registered taxonomies.
	 * @return array<string,mixed>
	 */
	public function remove_product_category(
		array $taxonomies,
		string $post_type,
		array $all_taxonomies
	): array {

		unset( $all_taxonomies );

		if ( self::POST_TYPE !== $post_type ) {
			return $taxonomies;
		}

		unset(
			$taxonomies[ self::TAXONOMY ]
		);

		return $taxonomies;
	}
}
