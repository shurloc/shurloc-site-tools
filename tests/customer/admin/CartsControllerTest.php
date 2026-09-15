<?php
/**
 * Tests for the Customer carts admin controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Repositories\Cart_Session_Repository;
use Shurloc\SiteTools\Customer\Services\Cart_Listing_Service;
use Shurloc_Test_WPDB;

/**
 * Tests the Customer carts admin controller.
 */
final class CartsControllerTest extends TestCase {

	/**
	 * WooCommerce default session lifetime.
	 *
	 * @var int
	 */
	private const SESSION_LIFETIME = 48 * 3600;

	/**
	 * Controller under test.
	 *
	 * @var Carts_Controller
	 */
	private Carts_Controller $controller;

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

		$_GET = array();

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_filters']          = array();
		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_products']         = array();
		$GLOBALS['shurloc_test_users']            = array();
		$GLOBALS['shurloc_test_permalinks']       = array();

		$this->current_time           = \time();
		$GLOBALS['shurloc_test_time'] = $this->current_time;

		$repository = new Cart_Session_Repository();

		$this->controller = new Carts_Controller(
			listing_service: new Cart_Listing_Service(
				cart_session_repository: $repository,
			),
			session_repository: $repository,
			cart_details_renderer: new Cart_Details_Renderer(),
			time_formatter: new Relative_Time_Formatter(),
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$_GET = array();

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_filters']          = array();
		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$GLOBALS['shurloc_test_products']         = array();
		$GLOBALS['shurloc_test_users']            = array();
		$GLOBALS['shurloc_test_permalinks']       = array();
		$GLOBALS['shurloc_test_time']             = 0;

		parent::tearDown();
	}

	/**
	 * Verify the controller registers its asset hook.
	 *
	 * @return void
	 */
	public function test_register_adds_asset_hook(): void {

		$this->controller->register();

		self::assertContains(
			array( $this->controller, 'enqueue_assets' ),
			$GLOBALS['shurloc_test_actions']['admin_enqueue_scripts']
		);

		self::assertSame(
			0,
			$GLOBALS['shurloc_test_action_metadata']
				['admin_enqueue_scripts'][0]['accepted_args']
		);
	}

	/**
	 * Verify shared modal assets load only on the Carts tab.
	 *
	 * @return void
	 */
	public function test_assets_enqueue_only_on_carts_tab(): void {

		$_GET['page'] = 'shurloc-site-tools-customers';
		$_GET['tab']  = 'carts';

		$this->controller->enqueue_assets();

		self::assertArrayHasKey(
			'shurloc-user-cart-column',
			$GLOBALS['shurloc_test_styles']
		);
		self::assertSame(
			'shurloc-user-cart-column',
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['handle']
		);

		$GLOBALS['shurloc_test_styles']           = array();
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
		$_GET['tab']                              = 'overview';

		$this->controller->enqueue_assets();

		self::assertSame( array(), $GLOBALS['shurloc_test_styles'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );
	}

	/**
	 * Verify cart-modal assets do not load on another admin page.
	 *
	 * @return void
	 */
	public function test_assets_do_not_enqueue_on_another_admin_page(): void {

		$_GET['page'] = 'another-admin-page';
		$_GET['tab']  = 'carts';

		$this->controller->enqueue_assets();

		self::assertSame( array(), $GLOBALS['shurloc_test_styles'] );
		self::assertSame( array(), $GLOBALS['shurloc_test_enqueued_scripts'] );
	}

	/**
	 * Verify guest cart identity, details, activity, and status are rendered.
	 *
	 * @return void
	 */
	public function test_renders_guest_cart_row(): void {

		$session_key = 't_sensitive-guest-key';

		$this->add_session(
			session_key: $session_key,
			last_activity_at: $this->current_time - 600,
		);

		$output = $this->render();

		self::assertStringContainsString( '<strong>Guest</strong>', $output );
		self::assertStringContainsString( 'guest-', $output );
		self::assertStringNotContainsString( $session_key, $output );
		self::assertMatchesRegularExpression( '/1\s+item\b/', $output );
		self::assertStringContainsString( '$25.00', $output );
		self::assertStringContainsString( '10 minutes ago', $output );
		self::assertStringContainsString( '>Active</td>', $output );
	}

	/**
	 * Verify authenticated carts link to the WordPress user edit screen.
	 *
	 * @return void
	 */
	public function test_renders_authenticated_visitor_link(): void {

		$GLOBALS['shurloc_test_users'][101] = true;

		$this->add_session( session_key: '101' );

		$output = $this->render();

		self::assertStringContainsString( 'User #101', $output );
		self::assertStringContainsString( 'user-edit.php?user_id=101', $output );
		self::assertStringContainsString( '>Logged In</td>', $output );
	}

	/**
	 * Verify visitor filters are validated and preserve Customer Tools routing.
	 *
	 * @return void
	 */
	public function test_renders_authenticated_filter_and_counts(): void {

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: 't_guest' );

		$_GET['cart_filter'] = 'authenticated';

		$output = $this->render();

		self::assertStringContainsString( 'All', $output );
		self::assertStringContainsString( 'Authenticated', $output );
		self::assertStringContainsString( 'Unauthenticated', $output );
		self::assertStringContainsString( 'page=shurloc-site-tools-customers', $output );
		self::assertStringContainsString( 'tab=carts', $output );
		self::assertStringContainsString( 'User #101', $output );
		self::assertStringNotContainsString( '<strong>Guest</strong>', $output );
	}

	/**
	 * Verify invalid filter input falls back to All.
	 *
	 * @return void
	 */
	public function test_invalid_filter_falls_back_to_all(): void {

		$this->add_session( session_key: '101' );
		$this->add_session( session_key: 't_guest' );

		$_GET['cart_filter'] = '<script>invalid</script>';

		$output = $this->render();

		self::assertStringContainsString( 'User #101', $output );
		self::assertStringContainsString( '<strong>Guest</strong>', $output );
		self::assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Verify expired sessions are displayed with Expired status.
	 *
	 * @return void
	 */
	public function test_renders_expired_status(): void {

		$this->add_session(
			session_key: 't_expired',
			expires_at: $this->current_time - 1,
		);

		$output = $this->render();

		self::assertStringContainsString( '>Expired</td>', $output );
	}

	/**
	 * Verify the second results page and navigation arguments are rendered.
	 *
	 * @return void
	 */
	public function test_renders_pagination(): void {

		foreach ( range( 1, 21 ) as $number ) {
			$this->add_session( session_key: 't_guest-' . $number );
		}

		$_GET['paged'] = '2';

		$output = $this->render();

		self::assertStringContainsString( 'Page 2 of 2', $output );
		self::assertStringContainsString( 'cart_filter=all', $output );
		self::assertStringContainsString( '>Previous</a>', $output );
		self::assertStringNotContainsString( '>Next</a>', $output );
	}

	/**
	 * Verify custom session storage receives a clear limitation notice.
	 *
	 * @return void
	 */
	public function test_renders_custom_session_handler_notice(): void {

		$GLOBALS['shurloc_test_filters']['woocommerce_session_handler'][] =
			static fn (): string => 'Custom_Session_Handler';

		$output = $this->render();

		self::assertStringContainsString( 'notice-warning', $output );
		self::assertStringContainsString( 'custom WooCommerce session handler', $output );
		self::assertStringNotContainsString( 'shurloc-carts-table', $output );
	}

	/**
	 * Render the controller and return captured output.
	 *
	 * @return string
	 */
	private function render(): string {

		ob_start();
		$this->controller->render();
		$output = ob_get_clean();

		return is_string( $output ) ? $output : '';
	}

	/**
	 * Add a serialized WooCommerce session row.
	 *
	 * @param string   $session_key     Session key.
	 * @param int|null $last_activity_at Last activity timestamp.
	 * @param int|null $expires_at      Explicit expiry timestamp.
	 * @return void
	 */
	private function add_session(
		string $session_key,
		?int $last_activity_at = null,
		?int $expires_at = null
	): void {

		$last_activity_at ??= $this->current_time - 600;
		$expires_at       ??= $last_activity_at + self::SESSION_LIFETIME;

		$cart = array(
			'item-key' => array(
				'product_id'    => 200,
				'variation_id'  => 0,
				'quantity'      => 1,
				'line_subtotal' => 25.0,
				'line_total'    => 25.0,
			),
		);

		$session = array(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'cart'        => serialize( $cart ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'cart_totals' => serialize(
				array( 'cart_contents_total' => 25.0 )
			),
		);

		$GLOBALS['wpdb']->results[] = (object) array(
			'session_key'    => $session_key,
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture mirrors WooCommerce storage.
			'session_value'  => serialize( $session ),
			'session_expiry' => $expires_at,
		);
	}
}
