<?php
/**
 * Tests for the mesh product table WooCommerce tab.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Shortcodes\Mesh_Product_Table_Shortcode_Double;
use WC_Product;

/**
 * Tests for Mesh_Product_Table_Tab.
 */
final class MeshProductTableTabIntegrationTest extends TestCase {

	/**
	 * Shortcode test double.
	 *
	 * @var Mesh_Product_Table_Shortcode_Double
	 */
	private Mesh_Product_Table_Shortcode_Double $shortcode;

	/**
	 * Tab under test.
	 *
	 * @var Mesh_Product_Table_Tab
	 */
	private Mesh_Product_Table_Tab $tab;

	/**
	 * Set up test.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->shortcode = new Mesh_Product_Table_Shortcode_Double();

		$this->tab = new Mesh_Product_Table_Tab(
			shortcode: $this->shortcode,
		);

		unset( $GLOBALS['product'] );
	}

	/**
	 * Register adds the WooCommerce filter.
	 */
	public function test_register_adds_product_tab_filter(): void {

		$this->tab->register();

		$this->assertNotFalse(
			has_filter(
				'woocommerce_product_tabs',
				array(
					$this->tab,
					'register_tab',
				)
			)
		);
	}

	/**
	 * Register tab returns original tabs when no product exists.
	 */
	public function test_register_tab_without_product_returns_tabs(): void {

		$tabs = array(
			'description' => array(),
		);

		$this->assertSame(
			$tabs,
			$this->tab->register_tab( $tabs )
		);

		$this->assertSame(
			0,
			$this->shortcode->render_calls
		);
	}

	/**
	 * Register tab skips when shortcode returns empty output.
	 */
	public function test_register_tab_skips_when_shortcode_returns_empty(): void {

		$GLOBALS['product'] = new WC_Product( 123 );

		$this->shortcode->html = '';

		$tabs = $this->tab->register_tab( array() );

		$this->assertArrayNotHasKey(
			'shurloc_mesh_specifications',
			$tabs
		);

		$this->assertSame(
			1,
			$this->shortcode->render_calls
		);
	}

	/**
	 * Register tab adds the mesh specification tab.
	 */
	public function test_register_tab_adds_mesh_tab(): void {

		$GLOBALS['product'] = new WC_Product( 123 );

		$this->shortcode->html = '<table>Mesh</table>';

		$tabs = $this->tab->register_tab( array() );

		$this->assertArrayHasKey(
			'shurloc_mesh_specifications',
			$tabs
		);

		$this->assertSame(
			'Mesh Specifications',
			$tabs['shurloc_mesh_specifications']['title']
		);

		$this->assertSame(
			35,
			$tabs['shurloc_mesh_specifications']['priority']
		);

		$this->assertSame(
			array(
				$this->tab,
				'render_tab',
			),
			$tabs['shurloc_mesh_specifications']['callback']
		);

		$this->assertSame(
			1,
			$this->shortcode->render_calls
		);
	}

	/**
	 * Render tab outputs shortcode HTML.
	 */
	public function test_render_tab_outputs_shortcode_html(): void {

		$this->shortcode->html = '<table>Mesh</table>';

		ob_start();

		$this->tab->render_tab();

		$output = ob_get_clean();

		$this->assertSame(
			'<table>Mesh</table>',
			$output
		);

		$this->assertSame(
			1,
			$this->shortcode->render_calls
		);
	}
}
