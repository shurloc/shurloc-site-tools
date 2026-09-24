<?php
/**
 * Tests for Customer Journey page context resolution.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;
use WC_Product;

/**
 * Tests server-derived page and product IDs for browser view requests.
 */
final class JourneyPageContextResolverTest extends TestCase {
	/**
	 * Shared global keys changed by these tests.
	 *
	 * @var list<string>
	 */
	private const GLOBAL_KEYS = array(
		'shurloc_test_home_url',
		'shurloc_test_url_post_ids',
		'shurloc_test_url_to_postid_calls',
		'shurloc_test_post_statuses',
		'shurloc_test_post_types',
		'shurloc_test_permalinks',
		'shurloc_test_products',
		'shurloc_test_wc_page_ids',
	);

	/**
	 * Values of globals that existed before this test.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_globals = array();

	/**
	 * Isolate WordPress and WooCommerce lookup fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( self::GLOBAL_KEYS as $key ) {
			if ( array_key_exists( $key, $GLOBALS ) ) {
				$this->original_globals[ $key ] = $GLOBALS[ $key ];
			}

			unset( $GLOBALS[ $key ] );
		}

		$GLOBALS['shurloc_test_url_post_ids']        = array();
		$GLOBALS['shurloc_test_url_to_postid_calls'] = array();
		$GLOBALS['shurloc_test_post_statuses']       = array();
		$GLOBALS['shurloc_test_post_types']          = array();
		$GLOBALS['shurloc_test_permalinks']          = array();
		$GLOBALS['shurloc_test_products']            = array();
		$GLOBALS['shurloc_test_wc_page_ids']         = array();
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( self::GLOBAL_KEYS as $key ) {
			unset( $GLOBALS[ $key ] );
		}

		foreach ( $this->original_globals as $key => $value ) {
			$GLOBALS[ $key ] = $value;
		}

		parent::tearDown();
	}

	/**
	 * A local archive route produces a page view without inventing a post ID.
	 *
	 * @return void
	 */
	public function test_unmapped_local_route_is_a_general_page_view(): void {
		$result = ( new Journey_Page_Context_Resolver() )->resolve( page_uri: '/shop?utm_source=email' );

		self::assertSame(
			array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'post_id'    => null,
				'product_id' => null,
			),
			$result
		);
		self::assertSame( array( 'https://example.com/shop?utm_source=email' ), $GLOBALS['shurloc_test_url_to_postid_calls'] );
	}

	/**
	 * Published pages and products use only IDs resolved by WordPress.
	 *
	 * @return void
	 */
	public function test_published_page_and_product_get_distinct_view_types(): void {
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/about?utm_source=email'] = 7;
		$GLOBALS['shurloc_test_post_statuses'][7] = 'publish';
		$GLOBALS['shurloc_test_post_types'][7]    = 'page';
		$GLOBALS['shurloc_test_permalinks'][7]    = 'https://example.com/about/';

		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/product/widget?color=red'] = 9;
		$GLOBALS['shurloc_test_post_statuses'][9] = 'publish';
		$GLOBALS['shurloc_test_post_types'][9]    = 'product';
		$GLOBALS['shurloc_test_permalinks'][9]    = 'https://example.com/product/widget/';
		new WC_Product( 9 );

		$resolver = new Journey_Page_Context_Resolver();
		self::assertSame(
			array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'post_id'    => 7,
				'product_id' => null,
			),
			$resolver->resolve( page_uri: '/about?utm_source=email' )
		);
		self::assertSame(
			array(
				'event_type' => Journey_Event_Type::PRODUCT_VIEW,
				'post_id'    => null,
				'product_id' => 9,
			),
			$resolver->resolve( page_uri: '/product/widget?color=red' )
		);
	}

	/**
	 * A URL claim cannot attach an unrelated or unpublished post to a view.
	 *
	 * @return void
	 */
	public function test_unpublished_mismatched_and_foreign_posts_are_rejected(): void {
		$url = 'https://example.com/product/widget';
		$GLOBALS['shurloc_test_url_post_ids'][ $url ] = 9;
		$GLOBALS['shurloc_test_post_types'][9]        = 'product';
		$GLOBALS['shurloc_test_post_statuses'][9]     = 'draft';
		$GLOBALS['shurloc_test_permalinks'][9]        = $url;
		$product                                      = new WC_Product( 9 );
		$resolver                                     = new Journey_Page_Context_Resolver();

		self::assertNull( $resolver->resolve( page_uri: '/product/widget' ) );

		$GLOBALS['shurloc_test_post_statuses'][9] = 'publish';
		$GLOBALS['shurloc_test_permalinks'][9]    = 'https://example.com/product/other';
		self::assertNull( $resolver->resolve( page_uri: '/product/widget' ) );

		$GLOBALS['shurloc_test_permalinks'][9] = 'https://other.example/product/widget';
		self::assertNull( $resolver->resolve( page_uri: '/product/widget' ) );

		$GLOBALS['shurloc_test_permalinks'][9] = $url;
		$product->set_status( 'private' );
		self::assertNull( $resolver->resolve( page_uri: '/product/widget' ) );

		$GLOBALS['shurloc_test_products'] = array();
		self::assertNull( $resolver->resolve( page_uri: '/product/widget' ) );
	}

	/**
	 * Mapped content of an unapproved post type is not recorded as a public page.
	 *
	 * @return void
	 */
	public function test_mapped_non_page_post_type_is_rejected(): void {
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/private-record'] = 17;
		$GLOBALS['shurloc_test_post_statuses'][17]                                  = 'publish';
		$GLOBALS['shurloc_test_post_types'][17]                                     = 'private_record';
		$GLOBALS['shurloc_test_permalinks'][17]                                     = 'https://example.com/private-record';

		self::assertNull( ( new Journey_Page_Context_Resolver() )->resolve( page_uri: '/private-record' ) );
	}

	/**
	 * Plain permalinks require the canonical ID query argument.
	 *
	 * @return void
	 */
	public function test_plain_permalink_query_must_match_canonical_post(): void {
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/?p=7&utm_source=email'] = 7;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/?p=8']                  = 7;
		$GLOBALS['shurloc_test_post_statuses'][7] = 'publish';
		$GLOBALS['shurloc_test_post_types'][7]    = 'post';
		$GLOBALS['shurloc_test_permalinks'][7]    = 'https://example.com/?p=7';

		$resolver = new Journey_Page_Context_Resolver();
		$valid    = $resolver->resolve( page_uri: '/?p=7&utm_source=email' );
		self::assertNotNull( $valid );
		self::assertSame( 7, $valid['post_id'] );
		self::assertNull( $resolver->resolve( page_uri: '/?p=8' ) );
	}

	/**
	 * A site in a subdirectory accepts only paths beneath its home path.
	 *
	 * @return void
	 */
	public function test_subdirectory_home_path_is_respected(): void {
		$GLOBALS['shurloc_test_home_url']                                        = 'https://example.com/store';
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/store/about'] = 7;
		$GLOBALS['shurloc_test_post_statuses'][7]                                = 'publish';
		$GLOBALS['shurloc_test_post_types'][7]                                   = 'page';
		$GLOBALS['shurloc_test_permalinks'][7]                                   = 'https://example.com/store/about';

		$resolver = new Journey_Page_Context_Resolver();
		$valid    = $resolver->resolve( page_uri: '/store/about' );
		self::assertNotNull( $valid );
		self::assertSame( 7, $valid['post_id'] );
		self::assertNull( $resolver->resolve( page_uri: '/outside' ) );
	}

	/**
	 * Unsafe or non-relative URLs never reach the WordPress post lookup.
	 *
	 * @return void
	 */
	public function test_invalid_relative_uri_is_rejected_before_lookup(): void {
		$resolver = new Journey_Page_Context_Resolver();
		foreach ( array( 'https://other.example/page', '//other.example/page', 'page', '/bad path', "/bad\npath" ) as $uri ) {
			self::assertNull( $resolver->resolve( page_uri: $uri ) );
		}

		self::assertSame( array(), $GLOBALS['shurloc_test_url_to_postid_calls'] );
	}

	/**
	 * Only the configured public checkout page qualifies for checkout entry.
	 *
	 * @return void
	 */
	public function test_checkout_page_requires_configured_canonical_public_page(): void {
		$checkout_url                                    = 'https://example.com/checkout?utm_source=email';
		$GLOBALS['shurloc_test_wc_page_ids']['checkout'] = 15;
		$GLOBALS['shurloc_test_url_post_ids'][ $checkout_url ] = 15;
		$GLOBALS['shurloc_test_post_statuses'][15]             = 'publish';
		$GLOBALS['shurloc_test_post_types'][15]                = 'page';
		$GLOBALS['shurloc_test_permalinks'][15]                = 'https://example.com/checkout/';
		foreach ( array( '/checkout/order-received/81', '/checkout/order-pay/81' ) as $endpoint ) {
			$GLOBALS['shurloc_test_url_post_ids'][ 'https://example.com' . $endpoint ] = 15;
		}
		$resolver = new Journey_Page_Context_Resolver();

		self::assertTrue( $resolver->is_checkout_page( page_uri: '/checkout?utm_source=email' ) );
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/checkout/order-received/81' ) );
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/checkout/order-pay/81' ) );
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/cart' ) );

		$GLOBALS['shurloc_test_wc_page_ids']['checkout'] = 16;
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/checkout?utm_source=email' ) );
		$GLOBALS['shurloc_test_wc_page_ids']['checkout'] = 15;
		$GLOBALS['shurloc_test_post_statuses'][15]       = 'draft';
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/checkout?utm_source=email' ) );
		$GLOBALS['shurloc_test_post_statuses'][15] = 'publish';
		$GLOBALS['shurloc_test_wc_page_ids']       = array();
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/checkout?utm_source=email' ) );
	}

	/**
	 * Checkout URLs with plain permalinks need the configured page query ID.
	 *
	 * @return void
	 */
	public function test_checkout_page_respects_plain_permalink_query(): void {
		$GLOBALS['shurloc_test_wc_page_ids']['checkout'] = 15;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/?page_id=15&utm_campaign=fall'] = 15;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/?page_id=16']                   = 15;
		$GLOBALS['shurloc_test_post_statuses'][15] = 'publish';
		$GLOBALS['shurloc_test_post_types'][15]    = 'page';
		$GLOBALS['shurloc_test_permalinks'][15]    = 'https://example.com/?page_id=15';

		$resolver = new Journey_Page_Context_Resolver();
		self::assertTrue( $resolver->is_checkout_page( page_uri: '/?page_id=15&utm_campaign=fall' ) );
		self::assertFalse( $resolver->is_checkout_page( page_uri: '/?page_id=16' ) );
	}
}
