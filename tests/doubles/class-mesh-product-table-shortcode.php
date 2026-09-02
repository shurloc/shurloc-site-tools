<?php
/**
 * Mesh product table shortcode double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Shortcodes;

/**
 * Mesh product table shortcode double.
 */
final class Mesh_Product_Table_Shortcode_Double implements Mesh_Product_Table_Shortcode_Interface {

	/**
	 * HTML to return.
	 *
	 * @var string
	 */
	public string $html = '';

	/**
	 * Number of render calls.
	 *
	 * @var int
	 */
	public int $render_calls = 0;

	/**
	 * Render shortcode.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	public function render(
		array $attributes = array()
	): string {

		++$this->render_calls;

		return $this->html;
	}
}
