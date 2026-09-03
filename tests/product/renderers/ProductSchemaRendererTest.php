<?php
/**
 * Tests for Product_Schema_Renderer.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

use PHPUnit\Framework\TestCase;

/**
 * Tests product schema rendering.
 */
final class ProductSchemaRendererTest extends TestCase {

	/**
	 * Schema renderer.
	 *
	 * @var Product_Schema_Renderer
	 */
	private Product_Schema_Renderer $renderer;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->renderer = new Product_Schema_Renderer();
	}

	/**
	 * Renderer should output JSON-LD script markup.
	 */
	public function test_renders_schema_as_json_ld_script(): void {

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => 'Test Product',
		);

		ob_start();

		$this->renderer->render(
			schema: $schema,
		);

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'<script type="application/ld+json">',
			$output
		);

		$this->assertStringContainsString(
			'</script>',
			$output
		);

		$this->assertStringContainsString(
			'"@context":"https://schema.org"',
			$output
		);

		$this->assertStringContainsString(
			'"@type":"Product"',
			$output
		);

		$this->assertStringContainsString(
			'"name":"Test Product"',
			$output
		);
	}

	/**
	 * Renderer should preserve escaped JSON characters.
	 */
	public function test_renders_special_characters_safely(): void {

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => 'Test "Special" Product',
		);

		ob_start();

		$this->renderer->render(
			schema: $schema,
		);

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'Test \\"Special\\" Product',
			$output
		);
	}

	/**
	 * Renderer should not escape slashes in URLs.
	 */
	public function test_renders_urls_without_escaping_slashes(): void {

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'url'      => 'https://example.com/product/test/',
		);

		ob_start();

		$this->renderer->render(
			schema: $schema,
		);

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'https://example.com/product/test/',
			$output
		);

		$this->assertStringNotContainsString(
			'https:\/\/example.com',
			$output
		);
	}
}
