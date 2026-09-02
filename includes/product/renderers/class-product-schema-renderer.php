<?php
/**
 * Product schema renderer.
 *
 * Renders generated product schema as Schema.org JSON-LD.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

/**
 * Product schema renderer.
 */
final class Product_Schema_Renderer implements Product_Schema_Renderer_Interface {

	/**
	 * Render product schema as JSON-LD.
	 *
	 * @param array<string,mixed> $schema Product schema.
	 * @return void
	 */
	public function render(
		array $schema
	): void {

		echo '<script type="application/ld+json">';
		echo wp_json_encode(
			$schema,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		echo '</script>';
		echo "\n";
	}
}
