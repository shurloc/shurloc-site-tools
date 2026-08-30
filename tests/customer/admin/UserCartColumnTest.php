<?php
/**
 * Tests for the user cart admin column.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;

/**
 * Tests the user cart admin column.
 */
final class UserCartColumnTest extends TestCase {

	/**
	 * Cart column under test.
	 *
	 * @var User_Cart_Column
	 */
	private User_Cart_Column $cart_column;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']          = array();
		$GLOBALS['shurloc_test_filter_metadata']  = array();
		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_user_meta']        = array();
		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_permalinks']       = array();

		$this->cart_column = new User_Cart_Column();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_filters']          = array();
		$GLOBALS['shurloc_test_filter_metadata']  = array();
		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_user_meta']        = array();
		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_permalinks']       = array();

		parent::tearDown();
	}

	/**
	 * Verify the Users columns filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_users_columns_filter(): void {

		$this->cart_column->register();

		self::assertContains(
			array(
				$this->cart_column,
				'add_column',
			),
			$GLOBALS['shurloc_test_filters']['manage_users_columns']
		);
	}

	/**
	 * Verify the custom column filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_custom_column_filter(): void {

		$this->cart_column->register();

		self::assertContains(
			array(
				$this->cart_column,
				'render_column',
			),
			$GLOBALS['shurloc_test_filters']['manage_users_custom_column']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_filter_metadata']
				['manage_users_custom_column'][0]['priority']
		);

		self::assertSame(
			3,
			$GLOBALS['shurloc_test_filter_metadata']
				['manage_users_custom_column'][0]['accepted_args']
		);
	}

	/**
	 * Verify the admin asset hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_enqueue_scripts_action(): void {

		$this->cart_column->register();

		self::assertContains(
			array(
				$this->cart_column,
				'enqueue_assets',
			),
			$GLOBALS['shurloc_test_actions']['admin_enqueue_scripts']
		);
	}

	/**
	 * Verify the Cart column is added.
	 *
	 * @return void
	 */
	public function test_cart_column_is_added(): void {

		$result = $this->cart_column->add_column(
			columns: array(
				'username' => 'Username',
				'email'    => 'Email',
			),
		);

		self::assertSame(
			'Cart',
			$result[ User_Cart_Column::CART_COLUMN ]
		);
	}

	/**
	 * Verify unrelated custom column output is preserved.
	 *
	 * @return void
	 */
	public function test_unrelated_column_preserves_existing_output(): void {

		$result = $this->cart_column->render_column(
			output: 'Existing output',
			column_name: 'email',
			user_id: 101,
		);

		self::assertSame(
			'Existing output',
			$result
		);
	}

	/**
	 * Verify users without a stored cart render an em dash.
	 *
	 * @return void
	 */
	public function test_missing_cart_renders_em_dash(): void {

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertSame(
			'&mdash;',
			$result
		);
	}

	/**
	 * Verify a zero item count renders an em dash.
	 *
	 * @return void
	 */
	public function test_zero_item_count_renders_em_dash(): void {

		$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Cart_Service::CART_COUNT_META_KEY ] = 0;

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertSame(
			'&mdash;',
			$result
		);
	}

	/**
	 * Verify cart item count and total are rendered.
	 *
	 * @return void
	 */
	public function test_cart_summary_is_rendered(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 3,
			total: 248.50,
			contents: array(),
		);

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertMatchesRegularExpression(
			'/3\s+items/',
			$result
		);

		self::assertStringContainsString(
			'$248.50',
			$result
		);
	}

	/**
	 * Verify singular item text is rendered correctly.
	 *
	 * @return void
	 */
	public function test_single_cart_item_uses_singular_label(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(),
		);

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertMatchesRegularExpression(
			'/1\s+item\b/',
			$result
		);

		self::assertDoesNotMatchRegularExpression(
			'/1\s+items\b/',
			$result
		);
	}

	/**
	 * Verify a unique cart panel ID is rendered for the user.
	 *
	 * @return void
	 */
	public function test_cart_panel_uses_user_specific_id(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(),
		);

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'id="shurloc-cart-panel-101"',
			$result
		);

		self::assertStringContainsString(
			'data-target="shurloc-cart-panel-101"',
			$result
		);
	}

	/**
	 * Verify a stored cart item is rendered.
	 *
	 * @return void
	 */
	public function test_cart_item_is_rendered(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 2,
			total: 50.00,
			contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 0,
					'name'          => 'Test Product',
					'sku'           => 'TEST-123',
					'quantity'      => 2,
					'line_subtotal' => 50.00,
					'line_total'    => 50.00,
				),
			),
		);

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/test-product/';

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertMatchesRegularExpression(
			'/2\s+×/',
			$result
		);

		self::assertStringContainsString(
			'Test Product',
			$result
		);

		self::assertStringContainsString(
			'(TEST-123)',
			$result
		);
	}

	/**
	 * Verify a product link is rendered.
	 *
	 * @return void
	 */
	public function test_cart_item_links_to_product(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 0,
					'name'          => 'Test Product',
					'sku'           => 'TEST-123',
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
				),
			),
		);

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/test-product/';

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'href="https://example.com/product/test-product/"',
			$result
		);
	}

	/**
	 * Verify a variation permalink is preferred.
	 *
	 * @return void
	 */
	public function test_variation_permalink_is_preferred(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 105,
					'name'          => 'Variation Product',
					'sku'           => 'VAR-123',
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
				),
			),
		);

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/parent/';

		$GLOBALS['shurloc_test_permalinks'][105] =
			'https://example.com/product/variation/';

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'href="https://example.com/product/variation/"',
			$result
		);
	}

	/**
	 * Verify the parent permalink is used when variation permalink is missing.
	 *
	 * @return void
	 */
	public function test_product_permalink_is_fallback_for_variation(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 105,
					'name'          => 'Variation Product',
					'sku'           => 'VAR-123',
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
				),
			),
		);

		$GLOBALS['shurloc_test_permalinks'][100] =
			'https://example.com/product/parent/';

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'href="https://example.com/product/parent/"',
			$result
		);
	}

	/**
	 * Verify seeded legacy cart items render without variation metadata.
	 *
	 * @return void
	 */
	public function test_legacy_cart_item_without_variation_is_supported(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 0,
					'name'          => 'Legacy Product',
					'sku'           => 'LEGACY',
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
				),
			),
		);

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'Legacy Product',
			$result
		);

		self::assertStringNotContainsString(
			'class="shurloc-cart-attrs"',
			$result
		);
	}

	/**
	 * Verify variation attributes are rendered.
	 *
	 * @return void
	 */
	public function test_variation_attributes_are_rendered(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(
				array(
					'cart_item_key' => 'abc123',
					'product_id'    => 100,
					'variation_id'  => 105,
					'name'          => 'Variation Product',
					'sku'           => 'VAR-123',
					'quantity'      => 1,
					'line_subtotal' => 25.00,
					'line_total'    => 25.00,
					'variation'     => array(
						'attribute_pa_color' => 'yellow',
						'attribute_size'     => 'large',
					),
				),
			),
		);

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'Color: yellow',
			$result
		);

		self::assertStringContainsString(
			'Size: large',
			$result
		);
	}

	/**
	 * Verify invalid stored cart entries are ignored.
	 *
	 * @return void
	 */
	public function test_non_array_cart_item_is_ignored(): void {

		$this->seed_cart_meta(
			user_id: 101,
			item_count: 1,
			total: 25.00,
			contents: array(
				'invalid',
			),
		);

		$result = $this->cart_column->render_column(
			output: '',
			column_name: User_Cart_Column::CART_COLUMN,
			user_id: 101,
		);

		self::assertMatchesRegularExpression(
			'/1\s+item\b/',
			$result
		);

		self::assertStringNotContainsString(
			'class="shurloc-cart-row"',
			$result
		);
	}

	/**
	 * Verify assets are enqueued on the Users screen.
	 *
	 * @return void
	 */
	public function test_assets_are_enqueued_on_users_screen(): void {

		$this->cart_column->enqueue_assets(
			hook_suffix: 'users.php',
		);

		self::assertCount(
			1,
			$GLOBALS['shurloc_test_styles']
		);

		self::assertCount(
			1,
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		self::assertArrayHasKey(
			'shurloc-user-cart-column',
			$GLOBALS['shurloc_test_styles']
		);

		self::assertSame(
			SHURLOC_SITE_TOOLS_URL .
			'assets/customer/css/shurloc-user-cart-column.css',
			$GLOBALS['shurloc_test_styles']
			['shurloc-user-cart-column']['src']
		);

		self::assertSame(
			'shurloc-user-cart-column',
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['handle']
		);
	}

	/**
	 * Verify assets are not enqueued on unrelated admin screens.
	 *
	 * @return void
	 */
	public function test_assets_are_not_enqueued_on_other_admin_screens(): void {

		$this->cart_column->enqueue_assets(
			hook_suffix: 'edit.php',
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_styles']
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);
	}

	/**
	 * Seed cart metadata for a test user.
	 *
	 * @param int              $user_id    User ID.
	 * @param int              $item_count Cart item count.
	 * @param float            $total      Cart contents total.
	 * @param array<int,mixed> $contents   Cart contents.
	 * @return void
	 */
	private function seed_cart_meta(
		int $user_id,
		int $item_count,
		float $total,
		array $contents
	): void {

		$GLOBALS['shurloc_test_user_meta'][ $user_id ] = array(
			User_Cart_Service::CART_COUNT_META_KEY => $item_count,
			User_Cart_Service::CART_TOTAL_META_KEY => $total,
			User_Cart_Service::CART_ITEMS_META_KEY => $contents,
		);
	}
}
