<?php
/**
 * Tests for the cart listing service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Repositories\Cart_Session_Repository;
use Shurloc_Test_WPDB;
use WC_Product;

/**
 * Tests the cart listing service.
 */
final class CartListingServiceTest extends TestCase {

	/**
	 * WooCommerce default session lifetime.
	 *
	 * @var int
	 */
	private const SESSION_LIFETIME = 48 * 3600;

	/**
	 * Service under test.
	 *
	 * @var Cart_Listing_Service
	 */
	private Cart_Listing_Service $service;

	/**
	 * Current test timestamp.
	 *
	 * @var int
	 */
	private int $current_time;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$GLOBALS['shurloc_test_filters']                 = array();
		$GLOBALS['shurloc_test_products']                = array();
		$GLOBALS['shurloc_test_user_capabilities_by_id'] = array();

		$this->current_time           = \time();
		$GLOBALS['shurloc_test_time'] = $this->current_time;

		$this->service = new Cart_Listing_Service(
			cart_session_repository: new Cart_Session_Repository(),
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		$GLOBALS['shurloc_test_filters']                 = array();
		$GLOBALS['shurloc_test_products']                = array();
		$GLOBALS['shurloc_test_user_capabilities_by_id'] = array();
		$GLOBALS['shurloc_test_time']                    = 0;

		parent::tearDown();
	}

	/**
	 * Verify authenticated carts receive Logged In status.
	 *
	 * @return void
	 */
	public function test_authenticated_cart_is_logged_in(): void {

		$this->add_session(
			session_key: '101',
			last_activity_at: $this->current_time - 7_200,
		);

		$listing = $this->service->get_listing();

		self::assertSame( 101, $listing['items'][0]['user_id'] );
		self::assertSame(
			Cart_Listing_Service::STATUS_LOGGED_IN,
			$listing['items'][0]['status']
		);
	}

	/**
	 * Verify a recent guest cart receives Active status.
	 *
	 * @return void
	 */
	public function test_recent_guest_cart_is_active(): void {

		$this->add_session(
			session_key: 't_guest-active',
			last_activity_at: $this->current_time - 1_800,
		);

		$listing = $this->service->get_listing();

		self::assertSame( 0, $listing['items'][0]['user_id'] );
		self::assertSame(
			Cart_Listing_Service::STATUS_ACTIVE,
			$listing['items'][0]['status']
		);
	}

	/**
	 * Verify an older guest cart receives Possibly Abandoned status.
	 *
	 * @return void
	 */
	public function test_older_guest_cart_is_possibly_abandoned(): void {

		$this->add_session(
			session_key: 't_guest-abandoned',
			last_activity_at: $this->current_time - 7_200,
		);

		$listing = $this->service->get_listing();

		self::assertSame(
			Cart_Listing_Service::STATUS_POSSIBLY_ABANDONED,
			$listing['items'][0]['status']
		);
	}

	/**
	 * Verify Expired status takes precedence for authenticated carts.
	 *
	 * @return void
	 */
	public function test_expired_status_takes_precedence(): void {

		$this->add_session(
			session_key: '101',
			last_activity_at: $this->current_time - self::SESSION_LIFETIME,
			expires_at: $this->current_time - 1,
		);

		$listing = $this->service->get_listing();

		self::assertTrue( $listing['items'][0]['is_expired'] );
		self::assertSame(
			Cart_Listing_Service::STATUS_EXPIRED,
			$listing['items'][0]['status']
		);
	}

	/**
	 * Verify last activity is derived from persisted session expiry.
	 *
	 * @return void
	 */
	public function test_derives_last_activity_from_session_expiry(): void {

		$last_activity_at = $this->current_time - 900;

		$this->add_session(
			session_key: 't_guest',
			last_activity_at: $last_activity_at,
		);

		$listing = $this->service->get_listing();

		self::assertSame(
			$last_activity_at,
			$listing['items'][0]['last_activity_at']
		);
	}

	/**
	 * Verify WooCommerce's configured session lifetime is respected.
	 *
	 * @return void
	 */
	public function test_uses_filtered_session_lifetime(): void {

		$GLOBALS['shurloc_test_filters']['wc_session_expiration'][] =
			static fn (): int => 3_600;

		$this->add_session(
			session_key: 't_guest',
			last_activity_at: $this->current_time,
			expires_at: $this->current_time + 1_800,
		);

		$listing = $this->service->get_listing();

		self::assertSame(
			$this->current_time - 1_800,
			$listing['items'][0]['last_activity_at']
		);
	}

	/**
	 * Verify the abandonment threshold is filterable.
	 *
	 * @return void
	 */
	public function test_abandonment_threshold_is_filterable(): void {

		$GLOBALS['shurloc_test_filters']
			['shurloc_site_tools_cart_abandonment_threshold'][] =
				static fn (): int => 1_800;

		$this->add_session(
			session_key: 't_guest',
			last_activity_at: $this->current_time - 2_400,
		);

		$listing = $this->service->get_listing();

		self::assertSame(
			Cart_Listing_Service::STATUS_POSSIBLY_ABANDONED,
			$listing['items'][0]['status']
		);
	}

	/**
	 * Verify the authenticated filter excludes guest carts.
	 *
	 * @return void
	 */
	public function test_filters_authenticated_carts(): void {

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: 't_guest' );

		$listing = $this->service->get_listing(
			filter: Cart_Listing_Service::FILTER_AUTHENTICATED,
		);

		self::assertCount( 1, $listing['items'] );
		self::assertSame( 101, $listing['items'][0]['user_id'] );
		self::assertSame( 1, $listing['total_items'] );
	}

	/**
	 * Verify the unauthenticated filter excludes registered-user carts.
	 *
	 * @return void
	 */
	public function test_filters_unauthenticated_carts(): void {

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: 't_guest' );

		$listing = $this->service->get_listing(
			filter: Cart_Listing_Service::FILTER_UNAUTHENTICATED,
		);

		self::assertCount( 1, $listing['items'] );
		self::assertSame( 0, $listing['items'][0]['user_id'] );
		self::assertSame( 1, $listing['total_items'] );
	}

	/**
	 * Verify administrator carts are excluded from the report.
	 *
	 * @return void
	 */
	public function test_excludes_administrator_carts(): void {

		$GLOBALS['shurloc_test_user_capabilities_by_id'][101]['manage_options'] = true;

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: 't_guest' );

		$listing = $this->service->get_listing();

		self::assertCount( 1, $listing['items'] );
		self::assertSame( 1, $listing['total_items'] );
		self::assertSame( 1, $listing['counts']['all'] );
		self::assertSame( 0, $listing['counts']['authenticated'] );
		self::assertSame( 1, $listing['counts']['unauthenticated'] );
	}

	/**
	 * Verify explicitly configured user IDs are excluded from the report.
	 *
	 * @return void
	 */
	public function test_excludes_configured_user_ids(): void {

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: '102' );

		$service = new Cart_Listing_Service(
			cart_session_repository: new Cart_Session_Repository(),
			excluded_user_ids: array( 101 ),
		);

		$listing = $service->get_listing();

		self::assertCount( 1, $listing['items'] );
		self::assertSame( 102, $listing['items'][0]['user_id'] );
		self::assertSame( 1, $listing['counts']['all'] );
	}

	/**
	 * Verify invalid filters fall back to All and counts remain unfiltered.
	 *
	 * @return void
	 */
	public function test_invalid_filter_falls_back_and_counts_all_carts(): void {

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: '102' );
		$this->add_session( session_key: 't_guest' );

		$listing = $this->service->get_listing(
			filter: 'invalid',
		);

		self::assertSame( Cart_Listing_Service::FILTER_ALL, $listing['filter'] );
		self::assertSame(
			array(
				'all'             => 3,
				'authenticated'   => 2,
				'unauthenticated' => 1,
			),
			$listing['counts']
		);
		self::assertCount( 3, $listing['items'] );
	}

	/**
	 * Verify results are paginated after visitor filtering.
	 *
	 * @return void
	 */
	public function test_paginates_filtered_results(): void {

		foreach ( range( 101, 125 ) as $user_id ) {
			$this->add_session( session_key: (string) $user_id );
		}

		$this->add_session( session_key: 't_guest' );

		$listing = $this->service->get_listing(
			filter: Cart_Listing_Service::FILTER_AUTHENTICATED,
			page: 2,
			per_page: 20,
		);

		self::assertCount( 5, $listing['items'] );
		self::assertSame( 25, $listing['total_items'] );
		self::assertSame( 2, $listing['total_pages'] );
		self::assertSame( 2, $listing['page'] );
		self::assertSame( 20, $listing['per_page'] );
	}

	/**
	 * Verify product details are generated for the shared modal renderer.
	 *
	 * @return void
	 */
	public function test_generates_modal_cart_detail_data(): void {

		$product = new WC_Product( 201 );
		$product->set_name( 'Blue Shirt - Large' );
		$product->set_sku( 'SHIRT-BLUE-L' );

		$GLOBALS['shurloc_test_products'][201] = $product;

		$this->add_session(
			session_key: 't_guest',
			cart: array(
				'variation-key' => $this->create_item(
					product_id: 200,
					variation_id: 201,
					quantity: 2,
					line_total: 75.0,
					variation: array(
						'attribute_pa_color' => 'blue',
						'attribute_size'     => 'large',
					),
				),
			),
		);

		$listing = $this->service->get_listing();
		$item    = $listing['items'][0]['cart_contents'][0];

		self::assertSame( 'Blue Shirt - Large', $item['name'] );
		self::assertSame( 'SHIRT-BLUE-L', $item['sku'] );
		self::assertSame( 2, $item['quantity'] );
		self::assertSame( 75.0, $item['line_total'] );
		self::assertSame(
			array(
				'attribute_pa_color' => 'blue',
				'attribute_size'     => 'large',
			),
			$item['variation']
		);
	}

	/**
	 * Verify missing products retain an identifiable fallback name.
	 *
	 * @return void
	 */
	public function test_missing_product_uses_identifiable_name(): void {

		$this->add_session(
			session_key: 't_guest',
			cart: array(
				'missing' => $this->create_item(
					product_id: 999,
				),
			),
		);

		$listing = $this->service->get_listing();

		self::assertSame(
			'Product #999',
			$listing['items'][0]['cart_contents'][0]['name']
		);
	}

	/**
	 * Add a serialized WooCommerce session row.
	 *
	 * @param string                   $session_key     Session key.
	 * @param int|null                 $last_activity_at Last activity timestamp.
	 * @param int|null                 $expires_at      Explicit expiry timestamp.
	 * @param array<string,mixed>|null $cart            Stored cart contents.
	 * @return void
	 */
	private function add_session(
		string $session_key,
		?int $last_activity_at = null,
		?int $expires_at = null,
		?array $cart = null
	): void {

		$last_activity_at ??= $this->current_time - 600;
		$expires_at       ??= $last_activity_at + self::SESSION_LIFETIME;
		$cart             ??= array(
			'item-key' => $this->create_item(),
		);

		$session = array(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'cart'        => serialize( $cart ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'cart_totals' => serialize(
				array(
					'cart_contents_total' => 25.0,
				)
			),
		);

		$GLOBALS['wpdb']->results[] = (object) array(
			'session_key'    => $session_key,
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'session_value'  => serialize( $session ),
			'session_expiry' => $expires_at,
		);
	}

	/**
	 * Create stored cart item data.
	 *
	 * @param int                  $product_id   Product ID.
	 * @param int                  $variation_id Variation ID.
	 * @param int                  $quantity     Quantity.
	 * @param float                $line_total   Line total.
	 * @param array<string,string> $variation    Variation attributes.
	 * @return array<string,mixed>
	 */
	private function create_item(
		int $product_id = 200,
		int $variation_id = 0,
		int $quantity = 1,
		float $line_total = 25.0,
		array $variation = array()
	): array {

		return array(
			'product_id'    => $product_id,
			'variation_id'  => $variation_id,
			'quantity'      => $quantity,
			'line_subtotal' => $line_total,
			'line_total'    => $line_total,
			'variation'     => $variation,
		);
	}
}
