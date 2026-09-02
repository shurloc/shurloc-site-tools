<?php
/**
 * Tests for mesh product table shortcode.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Shortcodes;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\DTO\Mesh_Table_Data;
use Shurloc\SiteTools\Product\Factories\Mesh_Table_Data_Factory;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Models\Mesh_Product_Result;
use Shurloc\SiteTools\Product\Models\Mesh_Specification;
use Shurloc\SiteTools\Product\Renderers\Mesh_Product_Table_Renderer_Double;
use Shurloc\SiteTools\Product\Services\Mesh_Product_Data_Service_Double;
use WC_Product;

/**
 * Tests mesh product table shortcode.
 */
final class MeshProductTableShortcodeTest extends TestCase {

	/**
	 * Mesh product table shortcode.
	 *
	 * @var Mesh_Product_Table_Shortcode
	 */
	private Mesh_Product_Table_Shortcode $shortcode;

	/**
	 * Renderer double.
	 *
	 * @var Mesh_Product_Table_Renderer_Double
	 */
	private Mesh_Product_Table_Renderer_Double $renderer;

	/**
	 * Mesh analysis result.
	 *
	 * @var Mesh_Product_Result
	 */
	private Mesh_Product_Result $result;

	/**
	 * Mesh product data service double.
	 *
	 * @var Mesh_Product_Data_Service_Double
	 */
	private Mesh_Product_Data_Service_Double $data_service;

	/**
	 * Mesh table data factory.
	 *
	 * @var Mesh_Table_Data_Factory
	 */
	private Mesh_Table_Data_Factory $table_data_factory;


	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->result = new Mesh_Product_Result();

		$this->data_service = new Mesh_Product_Data_Service_Double(
			result: $this->result,
		);

		$this->table_data_factory = new Mesh_Table_Data_Factory();

		$this->renderer = new Mesh_Product_Table_Renderer_Double(
			output: '<table>Mesh Table</table>',
		);

		$this->shortcode = new Mesh_Product_Table_Shortcode(
			$this->data_service,
			$this->table_data_factory,
			$this->renderer,
		);
	}


	/**
	 * Clean up test globals.
	 */
	protected function tearDown(): void {

		unset( $GLOBALS['wp_shortcodes'] );
		unset( $GLOBALS['product'] );

		parent::tearDown();
	}


	/**
	 * Registers the shortcode.
	 */
	public function test_register_adds_shortcode(): void {

		$this->shortcode->register();

		$this->assertArrayHasKey(
			'shurloc_mesh_table',
			$GLOBALS['wp_shortcodes']
		);
	}


	/**
	 * Returns an empty string when no product exists.
	 */
	public function test_render_returns_empty_when_no_product_exists(): void {

		unset( $GLOBALS['product'] );

		$this->assertSame(
			'',
			$this->shortcode->render()
		);
	}


	/**
	 * Returns an empty string for non-mesh products.
	 */
	public function test_render_returns_empty_for_non_mesh_product(): void {

		$GLOBALS['product'] = new WC_Product( 1 );

		$this->assertSame(
			'',
			$this->shortcode->render()
		);
	}


	/**
	 * Calls the renderer for mesh products.
	 */
	public function test_render_returns_renderer_output(): void {

		$GLOBALS['product'] = new WC_Product( 1 );

		$this->result->add_mesh_variation(
			entry: new Catalog_Variation_Entry(
				variation: '110/80 White $12.99',
				price: 12.99,
				product_id: 1,
				product_name: 'Test Product',
				edit_url: '',
			),
			spec: new Mesh_Specification(
				raw: '110/80 White $12.99',
				mesh_count: 110,
				thread_diameter: 80,
				modifier: null,
				color: 'White',
				pack_size: null,
				price_text: '$12.99',
				recognized: true,
				unknown_tokens: array(),
			),
		);

		$html = $this->shortcode->render();

		$this->assertSame(
			'<table>Mesh Table</table>',
			$html
		);

		$this->assertCount(
			1,
			$this->renderer->get_calls()
		);
	}


	/**
	 * Passes table data to the renderer.
	 */
	public function test_render_passes_table_data_to_renderer(): void {

		$GLOBALS['product'] = new WC_Product( 1 );

		$this->result->add_mesh_variation(
			entry: new Catalog_Variation_Entry(
				variation: '110/80 White $12.99',
				price: 12.99,
				product_id: 1,
				product_name: 'Test Product',
				edit_url: '',
			),
			spec: new Mesh_Specification(
				raw: '110/80 White $12.99',
				mesh_count: 110,
				thread_diameter: 80,
				modifier: null,
				color: 'White',
				pack_size: null,
				price_text: '$12.99',
				recognized: true,
				unknown_tokens: array(),
			),
		);

		$this->shortcode->render();

		$rendered_data = $this->renderer->get_calls()[0];

		// @phpstan-ignore method.alreadyNarrowedType (Runtime assertion intentionally verifies the PHPDoc contract.)
		$this->assertInstanceOf(
			Mesh_Table_Data::class,
			$rendered_data
		);

		$this->assertTrue(
			$rendered_data->has_rows()
		);

		$this->assertSame(
			1,
			$rendered_data->count()
		);
	}
}
