<?php
/**
 * Tests for Customer Journey browser REST ingestion.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Rest;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_UUID;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;
use WC_Product;
use WP_Error;
use WP_REST_Request;

/**
 * Tests route registration, bounded input, and server-owned view writes.
 */
final class JourneyBrowserIngestionControllerTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Request cookies before setup.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_cookies;

	/**
	 * Server values before setup.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server;

	/**
	 * Prepare an eligible request and installed schema.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies     = $_COOKIE;
		$this->original_server      = $_SERVER;
		$_COOKIE                    = array();
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

		$GLOBALS['shurloc_test_options']                     = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_filters']                     = array();
		$GLOBALS['shurloc_test_actions']                     = array();
		$GLOBALS['shurloc_test_rest_routes']                 = array();
		$GLOBALS['shurloc_test_is_admin']                    = false;
		$GLOBALS['shurloc_test_doing_ajax']                  = false;
		$GLOBALS['shurloc_test_is_user_logged_in']           = false;
		$GLOBALS['shurloc_journey_test_doing_cron']          = false;
		$GLOBALS['shurloc_journey_test_current_user']        = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']        = array();
		$GLOBALS['shurloc_journey_cookie_test_result']       = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl']       = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;
		$GLOBALS['shurloc_test_url_post_ids']                = array();
		$GLOBALS['shurloc_test_url_to_postid_calls']         = array();
		$GLOBALS['shurloc_test_post_statuses']               = array();
		$GLOBALS['shurloc_test_post_types']                  = array();
		$GLOBALS['shurloc_test_permalinks']                  = array();
		$GLOBALS['shurloc_test_products']                    = array();
		$GLOBALS['shurloc_test_wc_page_ids']                 = array();
		unset( $GLOBALS['shurloc_test_home_url'] );

		$this->database = new Shurloc_Test_WPDB();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared request state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                                       = $this->original_cookies;
		$_SERVER                                       = $this->original_server;
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_rest_routes']           = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_test_is_user_logged_in']     = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_is_ssl'] = false;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;
		$GLOBALS['shurloc_test_url_post_ids']                = array();
		$GLOBALS['shurloc_test_url_to_postid_calls']         = array();
		$GLOBALS['shurloc_test_post_statuses']               = array();
		$GLOBALS['shurloc_test_post_types']                  = array();
		$GLOBALS['shurloc_test_permalinks']                  = array();
		$GLOBALS['shurloc_test_products']                    = array();
		$GLOBALS['shurloc_test_wc_page_ids']                 = array();
		unset( $GLOBALS['shurloc_test_home_url'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();
		parent::tearDown();
	}

	/**
	 * Registration stays on rest_api_init and supplies permission callbacks.
	 *
	 * @return void
	 */
	public function test_registers_two_post_routes_at_rest_api_init(): void {
		$controller = new Journey_Browser_Ingestion_Controller();
		$controller->register();

		self::assertCount( 1, $GLOBALS['shurloc_test_actions']['rest_api_init'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_rest_routes'] );
		$callback = $GLOBALS['shurloc_test_actions']['rest_api_init'][0];
		$callback();

		self::assertCount( 2, $GLOBALS['shurloc_test_rest_routes'] );
		foreach ( array( '/journey/view', '/journey/duration' ) as $path ) {
			$route = $GLOBALS['shurloc_test_rest_routes'][ Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE . $path ];
			self::assertSame( 'POST', $route['args']['methods'] );
			self::assertIsCallable( $route['args']['callback'] );
			self::assertIsCallable( $route['args']['permission_callback'] );
		}
	}

	/**
	 * A valid view uses WordPress page context and stores only one event on retry.
	 *
	 * @return void
	 */
	public function test_view_records_server_derived_page_and_reuses_idempotent_retry(): void {
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/about?utm_source=email'] = 7;
		$GLOBALS['shurloc_test_post_statuses'][7] = 'publish';
		$GLOBALS['shurloc_test_post_types'][7]    = 'page';
		$GLOBALS['shurloc_test_permalinks'][7]    = 'https://example.com/about/';

		$controller = new Journey_Browser_Ingestion_Controller();
		$request    = $this->json_request(
			array(
				'page_uri'     => '/about?utm_source=email',
				'referrer_url' => 'https://example.org/landing?secret=redacted',
				'view_token'   => str_repeat( 'a', 32 ),
			)
		);

		self::assertTrue( $controller->can_collect( $request ) );
		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => true,
			),
			$controller->record_view( $request )
		);
		self::assertSame( 7, $this->database->events[1]['post_id'] );
		self::assertSame( '/about', $this->database->events[1]['page_path'] );
		self::assertSame( 'browser', $this->database->events[1]['source'] );
		self::assertSame( Journey_Event_Type::PAGE_VIEW, $this->database->events[1]['event_type'] );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => false,
			),
			$controller->record_view( $request )
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( 1, $this->database->sessions[1]['page_view_count'] );
	}

	/**
	 * A tokened checkout view records one start for an entry across retries and reloads.
	 *
	 * @return void
	 */
	public function test_checkout_entry_is_distinct_from_page_view_and_idempotent_per_entry(): void {
		$this->configure_checkout_page();
		$controller = new Journey_Browser_Ingestion_Controller();
		$request    = $this->json_request(
			array(
				'page_uri'             => '/checkout?utm_source=email',
				'view_token'           => str_repeat( 'a', 32 ),
				'checkout_entry_token' => str_repeat( 'b', 32 ),
			)
		);

		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => true,
			),
			$controller->record_view( $request )
		);
		self::assertCount( 2, $this->database->events );
		self::assertSame( Journey_Event_Type::PAGE_VIEW, $this->database->events[1]['event_type'] );
		self::assertSame( Journey_Event_Type::CHECKOUT_STARTED, $this->database->events[2]['event_type'] );
		self::assertSame( 12, $this->database->events[2]['post_id'] );
		self::assertSame( '/checkout', $this->database->events[2]['page_path'] );
		self::assertSame( 'browser', $this->database->events[2]['source'] );
		self::assertSame( 1, $this->database->sessions[1]['checkout_started_count'] );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => false,
			),
			$controller->record_view( $request )
		);
		self::assertCount( 2, $this->database->events );

		$reload = $this->json_request(
			array(
				'page_uri'             => '/checkout?utm_source=email',
				'view_token'           => str_repeat( 'c', 32 ),
				'checkout_entry_token' => str_repeat( 'b', 32 ),
			)
		);
		self::assertSame(
			array(
				'event_id' => 3,
				'created'  => true,
			),
			$controller->record_view( $reload )
		);
		self::assertCount( 3, $this->database->events );
		self::assertSame( 2, $this->database->sessions[1]['page_view_count'] );
		self::assertSame( 1, $this->database->sessions[1]['checkout_started_count'] );

		$new_entry = $this->json_request(
			array(
				'page_uri'             => '/checkout?utm_source=email',
				'view_token'           => str_repeat( 'd', 32 ),
				'checkout_entry_token' => str_repeat( 'e', 32 ),
			)
		);
		self::assertSame(
			array(
				'event_id' => 4,
				'created'  => true,
			),
			$controller->record_view( $new_entry )
		);
		self::assertCount( 5, $this->database->events );
		self::assertSame( Journey_Event_Type::CHECKOUT_STARTED, $this->database->events[5]['event_type'] );
		self::assertSame( 2, $this->database->sessions[1]['checkout_started_count'] );
	}

	/**
	 * Older view requests without a checkout token remain valid on checkout.
	 *
	 * @return void
	 */
	public function test_checkout_view_without_entry_token_records_only_page_view(): void {
		$this->configure_checkout_page();
		$controller = new Journey_Browser_Ingestion_Controller();
		$request    = $this->json_request(
			array(
				'page_uri'   => '/checkout',
				'view_token' => str_repeat( 'a', 32 ),
			)
		);

		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => true,
			),
			$controller->record_view( $request )
		);
		self::assertCount( 1, $this->database->events );
		self::assertSame( Journey_Event_Type::PAGE_VIEW, $this->database->events[1]['event_type'] );
		self::assertSame( 0, $this->database->sessions[1]['checkout_started_count'] );
	}

	/**
	 * A failed checkout write reports failure so a retry can complete the entry.
	 *
	 * @return void
	 */
	public function test_checkout_write_failure_can_retry_after_page_view_was_saved(): void {
		$this->configure_checkout_page();
		$controller = new Journey_Browser_Ingestion_Controller();
		$view       = $this->json_request(
			array(
				'page_uri'   => '/checkout',
				'view_token' => str_repeat( 'a', 32 ),
			)
		);
		$controller->record_view( $view );
		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];

		$entry                             = $this->json_request(
			array(
				'page_uri'             => '/checkout',
				'view_token'           => str_repeat( 'a', 32 ),
				'checkout_entry_token' => str_repeat( 'b', 32 ),
			)
		);
		$this->database->fail_event_insert = true;
		$this->assert_error( 'shurloc_journey_unavailable', 503, $controller->record_view( $entry ) );
		self::assertCount( 1, $this->database->events );
		self::assertSame( 0, $this->database->sessions[1]['checkout_started_count'] );

		$this->database->fail_event_insert = false;
		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => false,
			),
			$controller->record_view( $entry )
		);
		self::assertCount( 2, $this->database->events );
		self::assertSame( 1, $this->database->sessions[1]['checkout_started_count'] );
	}

	/**
	 * A checkout token cannot turn another local route into a checkout start.
	 *
	 * @return void
	 */
	public function test_checkout_entry_token_is_rejected_off_the_configured_checkout_page(): void {
		$this->configure_checkout_page();
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/checkout/order-received/99'] = 12;
		$controller = new Journey_Browser_Ingestion_Controller();

		foreach (
			array(
				'/about'                      => 'shurloc_journey_invalid_checkout_page',
				'/checkout/order-received/99' => 'shurloc_journey_invalid_page',
			) as $page_uri => $expected_error
		) {
			$request = $this->json_request(
				array(
					'page_uri'             => $page_uri,
					'view_token'           => str_repeat( 'a', 32 ),
					'checkout_entry_token' => str_repeat( 'b', 32 ),
				)
			);
			$this->assert_error( $expected_error, 422, $controller->record_view( $request ) );
		}

		self::assertSame( array(), $this->database->insert_calls );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * A public product becomes a product view, while a draft page is rejected.
	 *
	 * @return void
	 */
	public function test_view_distinguishes_published_product_from_nonpublic_page(): void {
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/product/widget'] = 9;
		$GLOBALS['shurloc_test_post_statuses'][9]                                   = 'publish';
		$GLOBALS['shurloc_test_post_types'][9]                                      = 'product';
		$GLOBALS['shurloc_test_permalinks'][9]                                      = 'https://example.com/product/widget/';
		new WC_Product( 9 );

		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/draft'] = 10;
		$GLOBALS['shurloc_test_post_statuses'][10]                         = 'draft';
		$GLOBALS['shurloc_test_post_types'][10]                            = 'page';
		$GLOBALS['shurloc_test_permalinks'][10]                            = 'https://example.com/draft';

		$controller = new Journey_Browser_Ingestion_Controller();
		$draft      = $this->json_request(
			array(
				'page_uri'   => '/draft',
				'view_token' => str_repeat( 'e', 32 ),
			)
		);
		$this->assert_error( 'shurloc_journey_invalid_page', 422, $controller->record_view( $draft ) );
		self::assertSame( array(), $this->database->insert_calls );

		$product = $this->json_request(
			array(
				'page_uri'   => '/product/widget',
				'view_token' => str_repeat( 'f', 32 ),
			)
		);
		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => true,
			),
			$controller->record_view( $product )
		);
		self::assertSame( Journey_Event_Type::PRODUCT_VIEW, $this->database->events[1]['event_type'] );
		self::assertSame( 9, $this->database->events[1]['product_id'] );
		self::assertSame( 1, $this->database->sessions[1]['product_view_count'] );
	}

	/**
	 * Duration updates require the original cookie and original event ownership.
	 *
	 * @return void
	 */
	public function test_duration_updates_existing_view_only_for_its_visitor(): void {
		$controller = new Journey_Browser_Ingestion_Controller();
		$view       = $controller->record_view(
			$this->json_request(
				array(
					'page_uri'   => '/shop',
					'view_token' => str_repeat( 'b', 32 ),
				)
			)
		);
		self::assertSame(
			array(
				'event_id' => 1,
				'created'  => true,
			),
			$view
		);

		$duration = $this->json_request(
			array(
				'event_id'        => 1,
				'total_active_ms' => 1200,
			)
		);
		$this->assert_error( 'shurloc_journey_missing_visitor', 403, $controller->record_duration( $duration ) );
		self::assertCount( 1, $this->database->visitor_rows );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = $GLOBALS['shurloc_journey_cookie_test_calls'][0]['value'];
		self::assertSame( array( 'accepted' => true ), $controller->record_duration( $duration ) );
		self::assertSame( 1200, $this->database->events[1]['active_ms'] );
		self::assertSame( 1200, $this->database->sessions[1]['active_ms'] );

		$_COOKIE[ Journey_Visitor_Cookie::NAME ] = Journey_Visitor_UUID::generate();
		$this->assert_error( 'shurloc_journey_duration_rejected', 422, $controller->record_duration( $duration ) );
		self::assertSame( 1200, $this->database->events[1]['active_ms'] );
	}

	/**
	 * Permission policy prevents collection for foreign origins or disabled consent.
	 *
	 * @return void
	 */
	public function test_permission_rejects_foreign_origin_and_collection_exclusions(): void {
		$controller = new Journey_Browser_Ingestion_Controller();
		$request    = $this->json_request(
			array(
				'page_uri'   => '/shop',
				'view_token' => str_repeat( 'c', 32 ),
			)
		);
		$request->set_header( 'origin', 'https://foreign.example' );
		self::assertFalse( $controller->can_collect( $request ) );
		$request->set_header( 'origin', 'https://example.com' );
		$request->set_header( 'sec-fetch-site', 'cross-site' );
		self::assertFalse( $controller->can_collect( $request ) );
		$request->set_header( 'sec-fetch-site', 'same-origin' );
		self::assertTrue( $controller->can_collect( $request ) );

		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		self::assertFalse( $controller->can_collect( $request ) );
		$GLOBALS['shurloc_test_filters'] = array();
		$GLOBALS['shurloc_test_options'] = array();
		self::assertFalse( $controller->can_collect( $request ) );
		self::assertSame( array(), $this->database->insert_calls );
	}

	/**
	 * A logged-in cookie cannot bypass user exclusions when REST lacks a nonce.
	 *
	 * @return void
	 */
	public function test_permission_rejects_logged_in_cookie_treated_as_anonymous(): void {
		$controller                  = new Journey_Browser_Ingestion_Controller();
		$request                     = $this->json_request(
			array(
				'page_uri'   => '/shop',
				'view_token' => str_repeat( '1', 32 ),
			)
		);
		$_COOKIE[ LOGGED_IN_COOKIE ] = 'test-auth-cookie';

		self::assertFalse( $controller->can_collect( $request ) );
		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		self::assertTrue( $controller->can_collect( $request ) );
		self::assertSame( array(), $this->database->insert_calls );
	}

	/**
	 * Invalid media types, JSON, and view fields do not create an identity.
	 *
	 * @return void
	 */
	public function test_rejects_invalid_or_oversized_view_input_before_storage(): void {
		$controller = new Journey_Browser_Ingestion_Controller();
		$request    = new WP_REST_Request();
		$request->set_body( '{}' );
		$this->assert_error( 'shurloc_journey_json_required', 415, $controller->record_view( $request ) );

		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( '{' );
		$this->assert_error( 'shurloc_journey_invalid_json', 400, $controller->record_view( $request ) );

		$request->set_body( str_repeat( 'x', 20481 ) );
		$this->assert_error( 'shurloc_journey_invalid_body_size', 413, $controller->record_view( $request ) );

		$request = $this->json_request(
			array(
				'page_uri'   => '/shop',
				'view_token' => str_repeat( 'd', 32 ),
				'visitor_id' => 12,
			)
		);
		$this->assert_error( 'shurloc_journey_invalid_view', 400, $controller->record_view( $request ) );
		self::assertSame( array(), $this->database->insert_calls );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
	}

	/**
	 * Configure a public canonical WooCommerce checkout page.
	 *
	 * @return void
	 */
	private function configure_checkout_page(): void {
		$GLOBALS['shurloc_test_wc_page_ids']['checkout']                                       = 12;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/checkout']                  = 12;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/checkout?utm_source=email'] = 12;
		$GLOBALS['shurloc_test_post_statuses'][12] = 'publish';
		$GLOBALS['shurloc_test_post_types'][12]    = 'page';
		$GLOBALS['shurloc_test_permalinks'][12]    = 'https://example.com/checkout/';
	}

	/**
	 * Make a JSON REST request.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @return WP_REST_Request Request.
	 */
	private function json_request( array $payload ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
		return $request;
	}

	/**
	 * Assert a REST error code and status.
	 *
	 * @param string                       $code   Expected code.
	 * @param int                          $status Expected HTTP status.
	 * @param array<string,mixed>|WP_Error $result Response.
	 * @return void
	 */
	private function assert_error( string $code, int $status, array|WP_Error $result ): void {
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( $code, $result->get_error_code() );
		self::assertSame( array( 'status' => $status ), $result->get_error_data() );
	}
}
