<?php
/**
 * Tests mesh product table shortcode integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer;
use Shurloc\SiteTools\Product\Factories\Mesh_Table_Data_Factory;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Renderers\Mesh_Product_Table_Renderer;
use Shurloc\SiteTools\Product\Services\Mesh_Product_Data_Service;
use Shurloc\SiteTools\Product\Services\Product_Catalog_Service;
use Shurloc\SiteTools\Product\Shortcodes\Mesh_Product_Table_Shortcode;
use WC_Product;

/**
 * Tests the complete mesh product table rendering flow.
 */
final class MeshProductTableIntegrationTest extends TestCase {

	/**
	 * Clean up global state.
	 */
	protected function tearDown(): void {

		unset( $GLOBALS['product'] );
		$GLOBALS['shurloc_test_products'] = array();
		unset( $GLOBALS['shurloc_test_styles'] );
		unset( $GLOBALS['shurloc_test_enqueued_scripts'] );

		parent::tearDown();
	}

	/**
	 * Mesh products render a specification table.
	 */
	public function test_mesh_product_renders_table_output(): void {

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '110/80 White',
					'price' => '12.99',
				),
			),
		);

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertStringContainsString(
			'<table',
			$html
		);

		$this->assertStringContainsString(
			'110',
			$html
		);

		$this->assertStringContainsString(
			'80',
			$html
		);

		$this->assertStringContainsString(
			'White',
			$html
		);

		$this->assertStringContainsString(
			'$12.99',
			$html
		);
	}

	/**
	 * Rendering the shortcode enqueues its frontend assets.
	 */
	public function test_mesh_product_enqueues_assets(): void {

		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '110/80 White',
					'price' => '12.99',
				),
			),
		);

		$this->render_mesh_table(
			product: $product,
		);

		$this->assertArrayHasKey(
			Mesh_Product_Table_Assets::STYLE_HANDLE,
			$GLOBALS['shurloc_test_styles']
		);

		$this->assertContains(
			Mesh_Product_Table_Assets::SCRIPT_HANDLE,
			array_column(
				$GLOBALS['shurloc_test_enqueued_scripts'],
				'handle'
			)
		);
	}

	/**
	 * Mesh products without variations return empty output.
	 */
	public function test_product_without_mesh_variations_returns_empty_output(): void {

		$product = new \Test_WC_Product( 1 );

		$product->set_name(
			name: 'Empty Mesh Product'
		);

		$product->set_type(
			type: 'variable'
		);

		$GLOBALS['product'] = $product;

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertSame(
			'',
			$html
		);
	}

	/**
	 * Mesh products render multiple variation rows.
	 */
	public function test_mesh_product_renders_multiple_variation_rows(): void {

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '110/80 White',
					'price' => '12.99',
				),
				array(
					'id'    => 102,
					'value' => '160/64 Yellow',
					'price' => '14.99',
				),
			),
		);

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertStringContainsString(
			'110',
			$html
		);

		$this->assertStringContainsString(
			'160',
			$html
		);

		$this->assertStringContainsString(
			'White',
			$html
		);

		$this->assertStringContainsString(
			'Yellow',
			$html
		);

		$this->assertStringContainsString(
			'$12.99',
			$html
		);

		$this->assertStringContainsString(
			'$14.99',
			$html
		);
	}

	/**
	 * Mesh table hides modifier column when no modifiers exist.
	 */
	public function test_mesh_table_hides_modifier_column_when_no_modifiers_exist(): void {

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '110/80 White',
					'price' => '12.99',
				),
			),
		);

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertStringNotContainsString(
			'<th>Modifier</th>',
			$html
		);
	}

	/**
	 * Mesh table hides pack size column when no pack sizes exist.
	 */
	public function test_mesh_table_hides_pack_size_column_when_no_pack_sizes_exist(): void {

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '110/80 White',
					'price' => '12.99',
				),
			),
		);

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertStringNotContainsString(
			'<th>Pack Size</th>',
			$html
		);
	}

	/**
	 * Mesh table shows optional columns when data exists.
	 */
	public function test_mesh_table_shows_optional_columns_when_data_exists(): void {

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '10 Pack - 110/80 HD White',
					'price' => '12.99',
				),
			),
		);

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertStringContainsString(
			'class="shurloc-mesh-table-modifier"',
			$html
		);

		$this->assertStringContainsString(
			'class="shurloc-mesh-table-pack-size"',
			$html
		);
	}

	/**
	 * Mesh products can render variations without a color.
	 */
	public function test_mesh_product_renders_variation_without_color(): void {

		$product = $this->create_mesh_product(
			variations: array(
				array(
					'id'    => 101,
					'value' => '120/48 (S)',
					'price' => '24.10',
				),
			),
		);

		$html = $this->render_mesh_table(
			product: $product,
		);

		$this->assertStringContainsString(
			'<table',
			$html
		);

		$this->assertStringContainsString(
			'120',
			$html
		);

		$this->assertStringContainsString(
			'48',
			$html
		);

		$this->assertStringContainsString(
			'S',
			$html
		);

		$this->assertStringContainsString(
			'$24.10',
			$html
		);
	}

	/**
	 * Create a mesh product test double.
	 *
	 * @param array<int,array{id:int,value:string,price:string}> $variations Variation data.
	 * @return WC_Product Product test double.
	 */
	private function create_mesh_product(
		array $variations,
	): WC_Product {

		$children = array();

		foreach ( $variations as $variation_data ) {

			$variation = new \Test_WC_Product_Variation(
				$variation_data['id'],
			);

			$variation->set_variation_attributes(
				array(
					'attribute_select-mesh-count' => $variation_data['value'],
				)
			);

			$variation->set_price(
				price: $variation_data['price'],
			);

			$children[] = $variation_data['id'];
		}

		$product = new \Test_WC_Product( 1 );

		$product->set_name(
			name: 'Test Mesh Product'
		);

		$product->set_type(
			type: 'variable'
		);

		$product->set_children(
			children: $children
		);

		$GLOBALS['product'] = $product;

		return $product;
	}

	/**
	 * Render mesh table shortcode output.
	 *
	 * @param WC_Product $product Product to render.
	 * @return string Rendered HTML.
	 */
	private function render_mesh_table(
		WC_Product $product,
	): string {

		$catalog_service = new Product_Catalog_Service();

		$analyzer = new Mesh_Product_Analyzer(
			parser: new Mesh_Parser(),
		);

		$data_service = new Mesh_Product_Data_Service(
			catalog_service: $catalog_service,
			mesh_analyzer: $analyzer,
		);

		$table_data_factory = new Mesh_Table_Data_Factory();

		$renderer = new Mesh_Product_Table_Renderer();

		$shortcode = new Mesh_Product_Table_Shortcode(
			data_service: $data_service,
			table_data_factory: $table_data_factory,
			renderer: $renderer,
		);

		return $shortcode->render();
	}
}
