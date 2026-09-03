<?php
/**
 * Product schema renderer interface.
 *
 * Defines the contract for rendering schema output.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

/**
 * Product schema renderer interface.
 */
interface Product_Schema_Renderer_Interface {

	/**
	 * Render product schema.
	 *
	 * @param array<string,mixed> $schema Product schema data.
	 * @return void
	 */
	public function render(
		array $schema
	): void;
}
