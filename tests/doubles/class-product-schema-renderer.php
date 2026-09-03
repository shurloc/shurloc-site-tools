<?php
/**
 * Product schema renderer test double.
 *
 * Provides a controllable implementation of the product schema renderer
 * interface for testing consumers that render product schema data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

/**
 * Product schema renderer test double.
 */
final class Product_Schema_Renderer_Double implements Product_Schema_Renderer_Interface {

	/**
	 * Calls to render().
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $calls = array();

	/**
	 * Render product schema.
	 *
	 * Records the supplied schema.
	 *
	 * @param array<string,mixed> $schema Product schema data.
	 * @return void
	 */
	public function render(
		array $schema
	): void {

		$this->calls[] = $schema;
	}

	/**
	 * Get calls to render().
	 *
	 * @return array<int,array<string,mixed>> Schemas passed to render().
	 */
	public function get_calls(): array {

		return $this->calls;
	}
}
