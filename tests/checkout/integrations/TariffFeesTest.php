<?php
/**
 * Tests for Tariff_Fees.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Settings\Settings;
use Shurloc\SiteTools\Checkout\Test_WooCommerce;

/**
 * Tests tariff fee calculation.
 */
final class TariffFeesTest extends TestCase {

	/**
	 * Test WooCommerce instance.
	 *
	 * @var Test_WooCommerce
	 */
	private Test_WooCommerce $woocommerce;

	/**
	 * Sets up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->woocommerce = new Test_WooCommerce();

		$GLOBALS['shurloc_test_woocommerce']     = $this->woocommerce;
		$GLOBALS['shurloc_test_is_admin']        = false;
		$GLOBALS['shurloc_test_terms']           = array();
		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_options']         = array();
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_woocommerce'] = null;

		unset(
			$GLOBALS['shurloc_test_is_admin'],
			$GLOBALS['shurloc_test_terms'],
			$GLOBALS['shurloc_test_actions'],
			$GLOBALS['shurloc_test_action_metadata'],
			$GLOBALS['shurloc_test_options']
		);

		parent::tearDown();
	}

	/**
	 * Tests that the WooCommerce hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_cart_calculate_fees_action(): void {
		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->register();

		$this->assertSame(
			array(
				array( $tariff_fees, 'add_tariff_fees' ),
			),
			$GLOBALS['shurloc_test_actions']
				['woocommerce_cart_calculate_fees']
		);
	}

	/**
	 * Tests that an empty cart does not receive tariff fees.
	 *
	 * @return void
	 */
	public function test_empty_cart_adds_no_fees(): void {
		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that a non-mesh product does not receive a tariff fee.
	 *
	 * @return void
	 */
	public function test_non_mesh_product_adds_no_fee(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests the regular mesh tariff.
	 *
	 * @return void
	 */
	public function test_mesh_product_adds_three_percent_tariff(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(
				array(
					'name'    => 'Raw material import tariff',
					'amount'  => 3.00,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests the Sefar mesh tariff.
	 *
	 * @return void
	 */
	public function test_sefar_product_adds_nine_percent_tariff(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_tag',
			term: 'sefar'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(
				array(
					'name'    => 'Sefar Mesh Tariff',
					'amount'  => 9.00,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that the Sefar tariff takes precedence over the mesh tariff.
	 *
	 * @return void
	 */
	public function test_sefar_product_in_mesh_category_receives_only_sefar_tariff(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_tag',
			term: 'sefar'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(
				array(
					'name'    => 'Sefar Mesh Tariff',
					'amount'  => 9.00,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that multiple mesh products are combined before calculating the tariff.
	 *
	 * @return void
	 */
	public function test_multiple_mesh_products_are_combined(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
				'item-2' => array(
					'product_id' => 102,
					'line_total' => 50.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$this->add_product_term(
			product_id: 102,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(
				array(
					'name'    => 'Raw material import tariff',
					'amount'  => 4.50,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests a cart containing both regular mesh and Sefar products.
	 *
	 * @return void
	 */
	public function test_mixed_mesh_and_sefar_cart_adds_both_tariffs(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
				'item-2' => array(
					'product_id' => 102,
					'line_total' => 50.00,
				),
				'item-3' => array(
					'product_id' => 103,
					'line_total' => 200.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$this->add_product_term(
			product_id: 102,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$this->add_product_term(
			product_id: 103,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$this->add_product_term(
			product_id: 103,
			taxonomy: 'product_tag',
			term: 'sefar'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(
				array(
					'name'    => 'Raw material import tariff',
					'amount'  => 4.50,
					'taxable' => false,
				),
				array(
					'name'    => 'Sefar Mesh Tariff',
					'amount'  => 18.00,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that the cart line total is used for tariff calculation.
	 *
	 * @return void
	 */
	public function test_tariff_uses_discounted_line_total(): void {
		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 80.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(
				array(
					'name'    => 'Raw material import tariff',
					'amount'  => 2.40,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that a custom mesh tariff rate is used.
	 *
	 * @return void
	 */
	public function test_custom_mesh_tariff_rate_is_used(): void {
		$this->set_tariff_settings(
			tariffs: array(
				'mesh' => array(
					'enabled' => true,
					'rate'    => 5.0,
				),
			)
		);

		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			5.00,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests that a custom Sefar tariff rate is used.
	 *
	 * @return void
	 */
	public function test_custom_sefar_tariff_rate_is_used(): void {
		$this->set_tariff_settings(
			tariffs: array(
				'sefar' => array(
					'enabled' => true,
					'rate'    => 12.0,
				),
			)
		);

		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_tag',
			term: 'sefar'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			12.00,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests that the mesh tariff can be disabled.
	 *
	 * @return void
	 */
	public function test_disabled_mesh_tariff_is_not_added(): void {
		$this->set_tariff_settings(
			tariffs: array(
				'mesh' => array(
					'enabled' => false,
				),
			)
		);

		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that the Sefar tariff can be disabled.
	 *
	 * @return void
	 */
	public function test_disabled_sefar_tariff_is_not_added(): void {
		$this->set_tariff_settings(
			tariffs: array(
				'sefar' => array(
					'enabled' => false,
				),
			)
		);

		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_tag',
			term: 'sefar'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that tariff calculation does not run on normal admin requests.
	 *
	 * @return void
	 */
	public function test_admin_request_adds_no_fees(): void {
		$GLOBALS['shurloc_test_is_admin'] = true;

		$this->woocommerce->cart->set_cart(
			array(
				'item-1' => array(
					'product_id' => 101,
					'line_total' => 100.00,
				),
			)
		);

		$this->add_product_term(
			product_id: 101,
			taxonomy: 'product_cat',
			term: 'shurloc-mesh'
		);

		$tariff_fees = $this->create_tariff_fees();

		$tariff_fees->add_tariff_fees();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Creates the tariff fee handler.
	 *
	 * @return Tariff_Fees Tariff fee handler.
	 */
	private function create_tariff_fees(): Tariff_Fees {
		return new Tariff_Fees(
			settings: new Settings()
		);
	}

	/**
	 * Stores tariff settings for a test.
	 *
	 * @param array<string, mixed> $tariffs Tariff settings.
	 * @return void
	 */
	private function set_tariff_settings(
		array $tariffs
	): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => $tariffs,
		);
	}

	/**
	 * Adds a taxonomy term to the test term registry.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy name.
	 * @param string $term       Term slug.
	 * @return void
	 */
	private function add_product_term(
		int $product_id,
		string $taxonomy,
		string $term
	): void {
		$GLOBALS['shurloc_test_terms'][ $product_id ][ $taxonomy ][] = $term;
	}
}
