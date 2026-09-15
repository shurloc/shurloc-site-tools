<?php
/**
 * Tests for the cart details renderer.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests the shared cart details renderer.
 */
final class CartDetailsRendererTest extends TestCase {

	/**
	 * Renderer under test.
	 *
	 * @var Cart_Details_Renderer
	 */
	private Cart_Details_Renderer $renderer;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_permalinks'] = array();

		$this->renderer = new Cart_Details_Renderer();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_permalinks'] = array();

		parent::tearDown();
	}

	/**
	 * Verify a zero item count renders an em dash.
	 *
	 * @return void
	 */
	public function test_zero_item_count_renders_em_dash(): void {

		self::assertSame(
			'&mdash;',
			$this->renderer->render(
				reference: '101',
				item_count: 0,
				total: 0.0,
				contents: array(),
			)
		);
	}

	/**
	 * Verify the cart summary and reusable panel controls are rendered.
	 *
	 * @return void
	 */
	public function test_renders_summary_and_panel_controls(): void {

		$output = $this->renderer->render(
			reference: 'guest-abcd1234',
			item_count: 3,
			total: 127.50,
			contents: array(),
		);

		self::assertMatchesRegularExpression( '/3\s+items/', $output );
		self::assertStringContainsString( '$127.50', $output );
		self::assertStringContainsString(
			'class="shurloc-cart-toggle"',
			$output
		);
		self::assertStringContainsString(
			'data-target="shurloc-cart-panel-guest-abcd1234"',
			$output
		);
		self::assertStringContainsString(
			'id="shurloc-cart-panel-guest-abcd1234"',
			$output
		);
		self::assertStringContainsString(
			'class="shurloc-cart-close"',
			$output
		);
	}

	/**
	 * Verify singular item text is rendered correctly.
	 *
	 * @return void
	 */
	public function test_uses_singular_item_label(): void {

		$output = $this->renderer->render(
			reference: '101',
			item_count: 1,
			total: 25.0,
			contents: array(),
		);

		self::assertMatchesRegularExpression( '/1\s+item\b/', $output );
		self::assertDoesNotMatchRegularExpression( '/1\s+items\b/', $output );
	}

	/**
	 * Verify unsafe reference characters cannot alter panel attributes.
	 *
	 * @return void
	 */
	public function test_normalizes_panel_reference(): void {

		$output = $this->renderer->render(
			reference: 'guest value"><script>',
			item_count: 1,
			total: 25.0,
			contents: array(),
		);

		self::assertStringContainsString(
			'id="shurloc-cart-panel-guest-value---script-"',
			$output
		);
		self::assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Verify complete item details are rendered and escaped.
	 *
	 * @return void
	 */
	public function test_renders_cart_item_details(): void {

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/test-product/';

		$output = $this->renderer->render(
			reference: '101',
			item_count: 2,
			total: 50.0,
			contents: array(
				$this->create_item(
					name: 'Test <Product>',
					sku: 'TEST<123>',
					quantity: 2,
					line_total: 50.0,
				),
			),
		);

		self::assertStringContainsString( '2 ×', $output );
		self::assertStringContainsString( 'Test &lt;Product&gt;', $output );
		self::assertStringContainsString( '(TEST&lt;123&gt;)', $output );
		self::assertStringContainsString(
			'href="https://example.com/product/test-product/"',
			$output
		);
		self::assertStringContainsString(
			'class="shurloc-cart-line-total"',
			$output
		);
		self::assertStringContainsString( '$50.00', $output );
	}

	/**
	 * Verify variation attributes and the variation permalink are used.
	 *
	 * @return void
	 */
	public function test_renders_variation_details(): void {

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/parent/';
		$GLOBALS['shurloc_test_permalinks'][105] =
			'https://example.com/product/variation/';

		$item                 = $this->create_item();
		$item['variation_id'] = 105;
		$item['variation']    = array(
			'attribute_pa_color' => 'yellow',
			'attribute_size'     => 'large',
		);

		$output = $this->renderer->render(
			reference: '101',
			item_count: 1,
			total: 25.0,
			contents: array( $item ),
		);

		self::assertStringContainsString(
			'href="https://example.com/product/variation/"',
			$output
		);
		self::assertStringContainsString( 'Color: yellow', $output );
		self::assertStringContainsString( 'Size: large', $output );
	}

	/**
	 * Verify the parent permalink is used if a variation link is unavailable.
	 *
	 * @return void
	 */
	public function test_uses_parent_product_link_as_variation_fallback(): void {

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/parent/';

		$item                 = $this->create_item();
		$item['variation_id'] = 105;

		$output = $this->renderer->render(
			reference: '101',
			item_count: 1,
			total: 25.0,
			contents: array( $item ),
		);

		self::assertStringContainsString(
			'href="https://example.com/product/parent/"',
			$output
		);
	}

	/**
	 * Verify legacy items without optional fields remain supported.
	 *
	 * @return void
	 */
	public function test_supports_legacy_cart_item(): void {

		$output = $this->renderer->render(
			reference: '101',
			item_count: 1,
			total: 25.0,
			contents: array(
				array(
					'product_id'   => 100,
					'variation_id' => 0,
					'name'         => 'Legacy Product',
					'sku'          => 'LEGACY',
					'quantity'     => 1,
				),
			),
		);

		self::assertStringContainsString( 'Legacy Product', $output );
		self::assertStringNotContainsString(
			'class="shurloc-cart-line-total"',
			$output
		);
		self::assertStringNotContainsString(
			'class="shurloc-cart-attrs"',
			$output
		);
	}

	/**
	 * Verify numeric stored values are normalized before rendering.
	 *
	 * @return void
	 */
	public function test_normalizes_numeric_cart_item_values(): void {

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/test-product/';

		$output = $this->renderer->render(
			reference: '101',
			item_count: 2,
			total: 50.25,
			contents: array(
				array(
					'product_id'   => '100',
					'variation_id' => '0',
					'name'         => 'Test Product',
					'sku'          => 'TEST-123',
					'quantity'     => '2',
					'line_total'   => '50.25',
					'variation'    => array(),
				),
			),
		);

		self::assertStringContainsString( '2 ×', $output );
		self::assertStringContainsString(
			'href="https://example.com/product/test-product/"',
			$output
		);
		self::assertStringContainsString( '$50.25', $output );
	}

	/**
	 * Verify invalid stored values normalize to safe rendering defaults.
	 *
	 * @return void
	 */
	public function test_normalizes_invalid_cart_item_values(): void {

		$output = $this->renderer->render(
			reference: '101',
			item_count: 1,
			total: 0.0,
			contents: array(
				array(
					'product_id'   => array( 100 ),
					'variation_id' => array( 105 ),
					'name'         => array( 'Invalid' ),
					'sku'          => array( 'Invalid' ),
					'quantity'     => array( 2 ),
					'line_total'   => array( 50.0 ),
					'variation'    => 'invalid',
				),
			),
		);

		self::assertStringContainsString( '0 ×', $output );
		self::assertStringNotContainsString( 'target="_blank"', $output );
		self::assertStringNotContainsString(
			'class="shurloc-cart-sku"',
			$output
		);
		self::assertStringNotContainsString(
			'class="shurloc-cart-line-total"',
			$output
		);
		self::assertStringNotContainsString(
			'class="shurloc-cart-attrs"',
			$output
		);
	}

	/**
	 * Verify invalid cart entries are ignored.
	 *
	 * @return void
	 */
	public function test_ignores_non_array_cart_item(): void {

		$output = $this->renderer->render(
			reference: '101',
			item_count: 1,
			total: 25.0,
			contents: array( 'invalid' ),
		);

		self::assertMatchesRegularExpression( '/1\s+item\b/', $output );
		self::assertStringNotContainsString(
			'class="shurloc-cart-row"',
			$output
		);
	}

	/**
	 * Create normalized cart item data.
	 *
	 * @param string $name       Product name.
	 * @param string $sku        Product SKU.
	 * @param int    $quantity   Quantity.
	 * @param float  $line_total Line total.
	 * @return array<string,mixed>
	 */
	private function create_item(
		string $name = 'Test Product',
		string $sku = 'TEST-123',
		int $quantity = 1,
		float $line_total = 25.0
	): array {

		return array(
			'cart_item_key' => 'abc123',
			'product_id'    => 100,
			'variation_id'  => 0,
			'name'          => $name,
			'sku'           => $sku,
			'quantity'      => $quantity,
			'line_subtotal' => $line_total,
			'line_total'    => $line_total,
			'variation'     => array(),
		);
	}
}
