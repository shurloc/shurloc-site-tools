<?php
/**
 * Mesh product table shortcode interface.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Shortcodes;

/**
 * Mesh product table shortcode interface.
 */
interface Mesh_Product_Table_Shortcode_Interface {

	/**
	 * Render the mesh product table.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	public function render(
		array $attributes = array()
	): string;
}
