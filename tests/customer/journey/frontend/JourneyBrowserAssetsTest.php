<?php
/**
 * Tests for Customer Journey browser assets.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Frontend;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Rest\Journey_Browser_Ingestion_Controller;

/**
 * Tests cache-safe frontend output and the browser transport configuration.
 */
final class JourneyBrowserAssetsTest extends TestCase {
	/**
	 * Original server environment.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server;

	/**
	 * Prepare an eligible frontend request.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->original_server      = $_SERVER;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_enqueued_scripts']     = array();
		$GLOBALS['shurloc_test_inline_scripts']       = array();
		$GLOBALS['shurloc_test_filters']              = array();
		$GLOBALS['shurloc_test_options']              = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_is_admin']             = false;
		$GLOBALS['shurloc_test_is_user_logged_in']    = false;
		$GLOBALS['shurloc_test_is_checkout']          = false;
		$GLOBALS['shurloc_journey_test_doing_cron']   = false;
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_test_wc_page_ids']          = array();
		$GLOBALS['shurloc_test_url_post_ids']         = array();
		$GLOBALS['shurloc_test_post_statuses']        = array();
		$GLOBALS['shurloc_test_post_types']           = array();
		$GLOBALS['shurloc_test_permalinks']           = array();
		unset( $GLOBALS['shurloc_test_rest_url'] );
	}

	/**
	 * Restore shared state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER                                      = $this->original_server;
		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_enqueued_scripts']     = array();
		$GLOBALS['shurloc_test_inline_scripts']       = array();
		$GLOBALS['shurloc_test_filters']              = array();
		$GLOBALS['shurloc_test_options']              = array();
		$GLOBALS['shurloc_test_is_admin']             = true;
		$GLOBALS['shurloc_test_is_user_logged_in']    = false;
		$GLOBALS['shurloc_test_is_checkout']          = false;
		$GLOBALS['shurloc_journey_test_doing_cron']   = false;
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_test_wc_page_ids']          = array();
		$GLOBALS['shurloc_test_url_post_ids']         = array();
		$GLOBALS['shurloc_test_post_statuses']        = array();
		$GLOBALS['shurloc_test_post_types']           = array();
		$GLOBALS['shurloc_test_permalinks']           = array();
		unset( $GLOBALS['shurloc_test_rest_url'] );
		parent::tearDown();
	}

	/**
	 * The registrar attaches only to the frontend asset hook.
	 *
	 * @return void
	 */
	public function test_register_adds_frontend_enqueue_hook(): void {
		$assets = new Journey_Browser_Assets();
		$assets->register();

		self::assertSame(
			array( array( $assets, 'enqueue_assets' ) ),
			$GLOBALS['shurloc_test_actions']['wp_enqueue_scripts']
		);
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );
	}

	/**
	 * Anonymous pages receive both REST routes without an authenticated nonce.
	 *
	 * @return void
	 */
	public function test_anonymous_frontend_enqueues_script_with_cacheable_configuration(): void {
		$assets = new Journey_Browser_Assets();
		$assets->enqueue_assets();

		self::assertCount( 1, $GLOBALS['shurloc_test_enqueued_scripts'] );
		self::assertSame(
			array(
				'handle'    => Journey_Browser_Assets::SCRIPT_HANDLE,
				'src'       => SHURLOC_SITE_TOOLS_URL . 'assets/customer/js/shurloc-journey-tracker.js',
				'deps'      => array(),
				'ver'       => SHURLOC_SITE_TOOLS_VERSION,
				'in_footer' => true,
			),
			$GLOBALS['shurloc_test_enqueued_scripts'][0]
		);
		self::assertSame(
			array(
				'viewUrl'        => 'https://example.com/wp-json/' . Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE . '/journey/view',
				'durationUrl'    => 'https://example.com/wp-json/' . Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE . '/journey/duration',
				'nonce'          => '',
				'isCheckoutPage' => false,
			),
			$this->inline_config()
		);
	}

	/**
	 * Logged-in pages get a REST nonce and respect a custom REST base URL.
	 *
	 * @return void
	 */
	public function test_authenticated_frontend_uses_rest_nonce_and_site_route_base(): void {
		$GLOBALS['shurloc_test_is_user_logged_in']    = true;
		$GLOBALS['shurloc_journey_test_current_user'] = new Journey_Collection_Test_User( 37 );
		$GLOBALS['shurloc_test_rest_url']             = 'https://example.com/store/wp-json/';

		( new Journey_Browser_Assets() )->enqueue_assets();

		self::assertSame(
			array(
				'viewUrl'        => 'https://example.com/store/wp-json/' . Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE . '/journey/view',
				'durationUrl'    => 'https://example.com/store/wp-json/' . Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE . '/journey/duration',
				'nonce'          => 'test-nonce-wp_rest',
				'isCheckoutPage' => false,
			),
			$this->inline_config()
		);
	}

	/**
	 * Only the configured public checkout page is marked for entry tracking.
	 *
	 * @return void
	 */
	public function test_checkout_flag_excludes_subroutes_and_unpublished_pages(): void {
		$GLOBALS['shurloc_test_is_checkout']             = true;
		$GLOBALS['shurloc_test_wc_page_ids']['checkout'] = 12;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/checkout?utm_source=email']  = 12;
		$GLOBALS['shurloc_test_url_post_ids']['https://example.com/checkout/order-received/99'] = 12;
		$GLOBALS['shurloc_test_post_statuses'][12] = 'publish';
		$GLOBALS['shurloc_test_post_types'][12]    = 'page';
		$GLOBALS['shurloc_test_permalinks'][12]    = 'https://example.com/checkout/';
		$_SERVER['REQUEST_URI']                    = '/checkout?utm_source=email';

		$assets = new Journey_Browser_Assets();
		$assets->enqueue_assets();
		self::assertTrue( $this->inline_config()['isCheckoutPage'] );

		$GLOBALS['shurloc_test_inline_scripts'] = array();
		$_SERVER['REQUEST_URI']                 = '/checkout/order-received/99';
		$assets->enqueue_assets();
		self::assertFalse( $this->inline_config()['isCheckoutPage'] );

		$GLOBALS['shurloc_test_inline_scripts']    = array();
		$_SERVER['REQUEST_URI']                    = '/checkout?utm_source=email';
		$GLOBALS['shurloc_test_post_statuses'][12] = 'draft';
		$assets->enqueue_assets();
		self::assertFalse( $this->inline_config()['isCheckoutPage'] );

		$GLOBALS['shurloc_test_inline_scripts']    = array();
		$GLOBALS['shurloc_test_post_statuses'][12] = 'publish';
		$GLOBALS['shurloc_test_is_checkout']       = false;
		$assets->enqueue_assets();
		self::assertFalse( $this->inline_config()['isCheckoutPage'] );
	}

	/**
	 * Request-specific policy does not vary cacheable storefront asset output.
	 *
	 * @return void
	 */
	public function test_request_policy_cannot_omit_tracker_from_shared_cache(): void {
		$assets = new Journey_Browser_Assets();
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		$_SERVER['HTTP_USER_AGENT']                 = 'Googlebot/2.1';
		$GLOBALS['shurloc_journey_test_doing_cron'] = true;
		$assets->enqueue_assets();

		self::assertCount( 1, $GLOBALS['shurloc_test_enqueued_scripts'] );
		self::assertCount( 1, $GLOBALS['shurloc_test_inline_scripts'] );
	}

	/**
	 * Missing schema prevents unusable transport output.
	 *
	 * @return void
	 */
	public function test_schema_must_be_ready(): void {
		$assets                          = new Journey_Browser_Assets();
		$GLOBALS['shurloc_test_options'] = array();

		$assets->enqueue_assets();
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_inline_scripts'] );
	}

	/**
	 * WordPress administration pages never get the storefront tracker.
	 *
	 * @return void
	 */
	public function test_admin_requests_do_not_enqueue(): void {
		$assets                           = new Journey_Browser_Assets();
		$GLOBALS['shurloc_test_is_admin'] = true;
		$assets->enqueue_assets();

		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_inline_scripts'] );
	}

	/**
	 * Decode the script configuration without evaluating JavaScript.
	 *
	 * @return array<string,mixed> Configuration.
	 */
	private function inline_config(): array {
		self::assertCount( 1, $GLOBALS['shurloc_test_inline_scripts'] );
		$inline = $GLOBALS['shurloc_test_inline_scripts'][0];
		self::assertSame( Journey_Browser_Assets::SCRIPT_HANDLE, $inline['handle'] );
		self::assertSame( 'before', $inline['position'] );
		self::assertStringStartsWith( 'window.shurlocJourneyBrowser = ', $inline['data'] );
		self::assertStringEndsWith( ';', $inline['data'] );

		$json   = substr( $inline['data'], strlen( 'window.shurlocJourneyBrowser = ' ), -1 );
		$config = json_decode( $json, true );
		self::assertIsArray( $config );
		return $config;
	}
}
