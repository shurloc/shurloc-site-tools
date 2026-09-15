<?php
/**
 * Tests for the WooCommerce cart session repository.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc_Test_WPDB;

/**
 * Tests the WooCommerce cart session repository.
 */
final class CartSessionRepositoryTest extends TestCase {

	/**
	 * Repository under test.
	 *
	 * @var Cart_Session_Repository
	 */
	private Cart_Session_Repository $repository;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$GLOBALS['shurloc_test_filters'] = array();
		$GLOBALS['shurloc_test_time']    = 1_000_000;

		$this->repository = new Cart_Session_Repository();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$GLOBALS['shurloc_test_filters'] = array();
		$GLOBALS['shurloc_test_time']    = 0;

		parent::tearDown();
	}

	/**
	 * Verify a numeric session key identifies an authenticated cart.
	 *
	 * @return void
	 */
	public function test_finds_authenticated_cart(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array(
						'first' => $this->create_item(
							quantity: 2,
						),
					),
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertCount( 1, $carts );
		self::assertSame( 101, $carts[0]['user_id'] );
		self::assertSame( 'user-101', $carts[0]['session_reference'] );
		self::assertFalse( $carts[0]['is_expired'] );
	}

	/**
	 * Verify a non-numeric session key identifies a guest without exposing it.
	 *
	 * @return void
	 */
	public function test_finds_guest_cart_with_hashed_reference(): void {

		$session_key = 't_sensitive-session-key';

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: $session_key,
					cart: array(
						'first' => $this->create_item(),
					),
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertCount( 1, $carts );
		self::assertSame( 0, $carts[0]['user_id'] );
		self::assertSame(
			'guest-' . substr( hash( 'sha256', $session_key ), 0, 8 ),
			$carts[0]['session_reference']
		);
		self::assertStringNotContainsString(
			$session_key,
			$carts[0]['session_reference']
		);
	}

	/**
	 * Verify empty and zero-quantity carts are excluded.
	 *
	 * @return void
	 */
	public function test_excludes_empty_carts(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array(),
				),
				$this->create_row(
					session_key: '102',
					cart: array(
						'first' => $this->create_item(
							quantity: 0,
						),
					),
				),
			)
		);

		self::assertSame(
			array(),
			$this->repository->find_non_empty()
		);
	}

	/**
	 * Verify expired session rows remain visible and are marked expired.
	 *
	 * @return void
	 */
	public function test_includes_expired_sessions_and_marks_them_expired(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array(
						'first' => $this->create_item(),
					),
					expires_at: time() - 1,
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertCount( 1, $carts );
		self::assertTrue( $carts[0]['is_expired'] );
	}

	/**
	 * Verify malformed session payloads are ignored.
	 *
	 * @return void
	 */
	public function test_skips_malformed_session_data(): void {

		$GLOBALS['wpdb']->results = array(
			(object) array(
				'session_key'    => '101',
				'session_value'  => 'not serialized session data',
				'session_expiry' => 2_000_000,
			),
			(object) array(
				'session_key'    => '102',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
				'session_value'  => serialize(
					array(
						'cart' => 'not serialized cart data',
					)
				),
				'session_expiry' => 2_000_000,
			),
		);

		self::assertSame(
			array(),
			$this->repository->find_non_empty()
		);
	}

	/**
	 * Verify cart quantities are summed rather than row-counted.
	 *
	 * @return void
	 */
	public function test_calculates_total_item_quantity(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array(
						'first'  => $this->create_item( quantity: 2 ),
						'second' => $this->create_item( quantity: 3 ),
					),
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertSame( 5, $carts[0]['item_count'] );
	}

	/**
	 * Verify the stored WooCommerce cart total is preferred.
	 *
	 * @return void
	 */
	public function test_uses_stored_cart_contents_total(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array(
						'first' => $this->create_item(
							line_total: 45.0,
						),
					),
					contents_total: 40.0,
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertSame( 40.0, $carts[0]['contents_total'] );
	}

	/**
	 * Verify line totals are used when WooCommerce totals are unavailable.
	 *
	 * @return void
	 */
	public function test_calculates_total_from_line_totals(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array(
						'first'  => $this->create_item( line_total: 45.0 ),
						'second' => $this->create_item( line_total: 70.0 ),
					),
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertSame( 115.0, $carts[0]['contents_total'] );
	}

	/**
	 * Verify cart rows are sorted by the persisted activity proxy.
	 *
	 * @return void
	 */
	public function test_sorts_by_session_expiry_descending(): void {

		$this->set_rows(
			rows: array(
				$this->create_row(
					session_key: '101',
					cart: array( 'first' => $this->create_item() ),
					expires_at: time() + 1_500,
				),
				$this->create_row(
					session_key: '102',
					cart: array( 'second' => $this->create_item() ),
					expires_at: time() + 1_900,
				),
			)
		);

		$carts = $this->repository->find_non_empty();

		self::assertSame( 102, $carts[0]['user_id'] );
		self::assertSame( 101, $carts[1]['user_id'] );
	}

	/**
	 * Verify the active WordPress prefix and query constraints are used.
	 *
	 * @return void
	 */
	public function test_queries_current_non_empty_session_candidates(): void {

		$GLOBALS['wpdb']->prefix = 'custom_';

		$this->repository->find_non_empty();

		$prepared_query = $GLOBALS['wpdb']->prepared_queries[0];

		self::assertStringNotContainsString(
			'session_expiry >',
			$prepared_query['query']
		);
		self::assertStringContainsString(
			'session_value LIKE %s',
			$prepared_query['query']
		);
		self::assertStringContainsString(
			'ORDER BY session_expiry DESC',
			$prepared_query['query']
		);
		self::assertSame(
			'custom_woocommerce_sessions',
			$prepared_query['args'][0]
		);
		self::assertSame(
			'%s:4:"cart";%',
			$prepared_query['args'][1]
		);
	}

	/**
	 * Verify custom WooCommerce session storage is not queried as a table.
	 *
	 * @return void
	 */
	public function test_rejects_custom_session_handler(): void {

		$GLOBALS['shurloc_test_filters']['woocommerce_session_handler'][] =
			static function (): string {
				return 'Custom_Session_Handler';
			};

		self::assertFalse(
			$this->repository->supports_current_handler()
		);
		self::assertSame(
			array(),
			$this->repository->find_non_empty()
		);
		self::assertSame(
			array(),
			$GLOBALS['wpdb']->prepared_queries
		);
	}

	/**
	 * Set database rows returned to the repository.
	 *
	 * @param array<int,object> $rows Session rows.
	 * @return void
	 */
	private function set_rows(
		array $rows
	): void {

		$GLOBALS['wpdb']->results = $rows;
	}

	/**
	 * Create a stored WooCommerce session row.
	 *
	 * @param string              $session_key   Session key.
	 * @param array<string,mixed> $cart          Stored cart.
	 * @param int                 $expires_at    Session expiry.
	 * @param float|null          $contents_total Stored total.
	 * @return object
	 */
	private function create_row(
		string $session_key,
		array $cart,
		int $expires_at = 0,
		?float $contents_total = null
	): object {

		if ( 0 === $expires_at ) {
			$expires_at = time() + 3_600;
		}

		$session = array(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'cart' => serialize( $cart ),
		);

		if ( null !== $contents_total ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			$session['cart_totals'] = serialize(
				array(
					'cart_contents_total' => $contents_total,
				)
			);
		}

		return (object) array(
			'session_key'    => $session_key,
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'session_value'  => serialize( $session ),
			'session_expiry' => $expires_at,
		);
	}

	/**
	 * Create stored cart item data.
	 *
	 * @param int   $quantity   Quantity.
	 * @param float $line_total Line total.
	 * @return array<string,mixed>
	 */
	private function create_item(
		int $quantity = 1,
		float $line_total = 25.0
	): array {

		return array(
			'product_id'    => 200,
			'variation_id'  => 0,
			'quantity'      => $quantity,
			'line_subtotal' => $line_total,
			'line_total'    => $line_total,
			'variation'     => array(
				'attribute_pa_color' => 'blue',
			),
		);
	}
}
