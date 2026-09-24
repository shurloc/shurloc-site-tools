<?php
/**
 * Tests for Customer Journey WooCommerce cart event tracking.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Tracking;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Test_WooCommerce;
use Shurloc\SiteTools\Customer\Journey\Journey_Cart_Session_Token;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Test_User;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;
use WC_Cart_Double;

/**
 * Verify WooCommerce cart hooks produce validated quantity-delta events.
 */
final class JourneyCartTrackerTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * WooCommerce test instance.
	 *
	 * @var Test_WooCommerce
	 */
	private Test_WooCommerce $woocommerce;

	/**
	 * Original request cookies.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_cookies;

	/**
	 * Original server environment.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server;

	/**
	 * Prepare an eligible anonymous request with an installed Journey schema.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_cookies                        = $_COOKIE;
		$this->original_server                         = $_SERVER;
		$_COOKIE                                       = array();
		$_SERVER['HTTP_USER_AGENT']                    = 'Mozilla/5.0';
		$_SERVER['REQUEST_URI']                        = '/?wc-ajax=add_to_cart';
		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_action_metadata']       = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_options']               = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_is_admin']              = false;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;

		$this->database                      = new Shurloc_Test_WPDB();
		$this->woocommerce                   = new Test_WooCommerce();
		$GLOBALS['shurloc_test_woocommerce'] = $this->woocommerce;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_COOKIE                                       = $this->original_cookies;
		$_SERVER                                       = $this->original_server;
		$GLOBALS['shurloc_test_actions']               = array();
		$GLOBALS['shurloc_test_action_metadata']       = array();
		$GLOBALS['shurloc_test_filters']               = array();
		$GLOBALS['shurloc_test_options']               = array();
		$GLOBALS['shurloc_test_is_admin']              = true;
		$GLOBALS['shurloc_test_doing_ajax']            = false;
		$GLOBALS['shurloc_journey_test_doing_cron']    = false;
		$GLOBALS['shurloc_journey_test_current_user']  = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_cookie_test_calls']  = array();
		$GLOBALS['shurloc_journey_cookie_test_result'] = true;
		$GLOBALS['shurloc_journey_cookie_test_headers_sent'] = false;
		$GLOBALS['shurloc_test_woocommerce']                 = null;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the shared test database double.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Core and Store API cart changes share the same WooCommerce hooks.
	 *
	 * @return void
	 */
	public function test_registers_successful_cart_mutation_hooks(): void {
		$tracker = new Journey_Cart_Tracker();
		$tracker->register();

		self::assertSame(
			array(
				'woocommerce_add_to_cart'        => array( array( $tracker, 'added_to_cart' ) ),
				'woocommerce_cart_item_removed'  => array( array( $tracker, 'removed_from_cart' ) ),
				'woocommerce_after_cart_item_quantity_update' => array( array( $tracker, 'quantity_updated' ) ),
				'woocommerce_cart_item_restored' => array( array( $tracker, 'restored_to_cart' ) ),
			),
			$GLOBALS['shurloc_test_actions']
		);
		self::assertSame( 4, $GLOBALS['shurloc_test_action_metadata']['woocommerce_add_to_cart'][0]['accepted_args'] );
		self::assertSame( 2, $GLOBALS['shurloc_test_action_metadata']['woocommerce_cart_item_removed'][0]['accepted_args'] );
		self::assertSame( 4, $GLOBALS['shurloc_test_action_metadata']['woocommerce_after_cart_item_quantity_update'][0]['accepted_args'] );
		self::assertSame( 2, $GLOBALS['shurloc_test_action_metadata']['woocommerce_cart_item_restored'][0]['accepted_args'] );
		self::assertSame( array(), $this->database->events );
	}

	/**
	 * Explicit adds use the added delta and retain the variation ID.
	 *
	 * @return void
	 */
	public function test_add_to_cart_records_simple_and_variation_quantities(): void {
		$tracker = new Journey_Cart_Tracker();
		$tracker->added_to_cart( 'simple-key', 19, 2, 0 );
		$tracker->added_to_cart( 'variation-key', 19, 0.75, 20 );

		self::assertCount( 2, $this->database->events );
		self::assertSame( Journey_Event_Type::ADD_TO_CART, $this->database->events[1]['event_type'] );
		self::assertSame( 19, $this->database->events[1]['product_id'] );
		self::assertNull( $this->database->events[1]['variation_id'] );
		self::assertSame( '2', $this->database->events[1]['quantity'] );
		self::assertNull( $this->database->events[1]['page_path'] );
		self::assertSame( 'woocommerce', $this->database->events[1]['source'] );
		self::assertSame( 20, $this->database->events[2]['variation_id'] );
		self::assertSame( '0.75', $this->database->events[2]['quantity'] );
		self::assertSame( 2, $this->database->sessions[1]['cart_add_count'] );
		self::assertSame( '2.7500', $this->database->sessions[1]['added_quantity'] );
	}

	/**
	 * A successful cart event links the WooCommerce cart to its Journey session.
	 *
	 * @return void
	 */
	public function test_successful_cart_event_links_current_cart_and_journey_session(): void {
		$token  = str_repeat( 'ab', 32 );
		$before = gmdate( 'Y-m-d H:i:s' );
		$this->woocommerce->session->set( Journey_Cart_Session_Token::SESSION_KEY, $token );

		( new Journey_Cart_Tracker() )->added_to_cart( 'item-key', 19, 1, 0 );
		$after = gmdate( 'Y-m-d H:i:s' );

		self::assertCount( 1, $this->database->cart_links );
		self::assertSame( hash( 'sha256', $token ), $this->database->cart_links[1]['cart_token_hash'] );
		self::assertSame( 1, $this->database->cart_links[1]['visitor_id'] );
		self::assertSame( 1, $this->database->cart_links[1]['session_id'] );
		self::assertSame( $this->database->cart_links[1]['linked_at'], $this->database->cart_links[1]['last_seen_at'] );
		self::assertGreaterThanOrEqual( $before, $this->database->cart_links[1]['linked_at'] );
		self::assertLessThanOrEqual( $after, $this->database->cart_links[1]['linked_at'] );
	}

	/**
	 * A completed removal and undo each record the cart item's actual quantity.
	 *
	 * @return void
	 */
	public function test_remove_and_restore_use_woocommerce_item_state(): void {
		$cart = new WC_Cart_Double();
		$item = array(
			'product_id'   => 31,
			'variation_id' => 32,
			'quantity'     => '1.2500',
		);
		$cart->set_test_removed_cart( array( 'item-key' => $item ) );

		$tracker = new Journey_Cart_Tracker();
		$tracker->removed_from_cart( 'missing-key', $cart );
		$tracker->removed_from_cart( 'item-key', $cart );
		$cart->set_test_removed_cart( array() );
		$cart->set_test_cart( array( 'item-key' => $item ) );
		$tracker->restored_to_cart( 'item-key', $cart );

		self::assertCount( 2, $this->database->events );
		self::assertSame( Journey_Event_Type::REMOVE_FROM_CART, $this->database->events[1]['event_type'] );
		self::assertSame( '1.25', $this->database->events[1]['quantity'] );
		self::assertSame( 31, $this->database->events[1]['product_id'] );
		self::assertSame( 32, $this->database->events[1]['variation_id'] );
		self::assertSame( Journey_Event_Type::ADD_TO_CART, $this->database->events[2]['event_type'] );
		self::assertSame( '1.25', $this->database->events[2]['quantity'] );
		self::assertSame( 1, $this->database->sessions[1]['cart_remove_count'] );
		self::assertSame( 1, $this->database->sessions[1]['cart_add_count'] );
	}

	/**
	 * Quantity edits record exact deltas, including fractional changes.
	 *
	 * @return void
	 */
	public function test_quantity_updates_record_only_positive_differences(): void {
		$cart = new WC_Cart_Double();
		$cart->set_test_cart(
			array(
				'item-key' => array(
					'product_id'   => 41,
					'variation_id' => 42,
				),
			)
		);
		$tracker = new Journey_Cart_Tracker();
		$tracker->quantity_updated( 'item-key', '3.5000', '2.0000', $cart );
		$tracker->quantity_updated( 'item-key', '2.2500', '3.5000', $cart );
		$tracker->quantity_updated( 'item-key', 2.25, 2.25, $cart );

		self::assertCount( 2, $this->database->events );
		self::assertSame( Journey_Event_Type::ADD_TO_CART, $this->database->events[1]['event_type'] );
		self::assertSame( '1.5', $this->database->events[1]['quantity'] );
		self::assertSame( 'cart_quantity', $this->database->events[1]['source'] );
		self::assertSame( Journey_Event_Type::REMOVE_FROM_CART, $this->database->events[2]['event_type'] );
		self::assertSame( '1.25', $this->database->events[2]['quantity'] );
		self::assertSame( '1.5000', $this->database->sessions[1]['added_quantity'] );
		self::assertSame( '1.2500', $this->database->sessions[1]['removed_quantity'] );
	}

	/**
	 * Malformed hook data and ineligible requests never create Journey rows.
	 *
	 * @return void
	 */
	public function test_rejects_invalid_values_and_respects_collection_gate(): void {
		$tracker = new Journey_Cart_Tracker();
		$tracker->added_to_cart( '', 5, 1, 0 );
		$tracker->added_to_cart( 'item-key', '5', 1, 0 );
		$tracker->added_to_cart( 'item-key', 5, 0, 0 );
		$tracker->added_to_cart( 'item-key', 5, 1.23456, 0 );
		$tracker->added_to_cart( 'item-key', 5, 1, -1 );
		$tracker->removed_from_cart( 'item-key', new WC_Cart_Double() );
		$tracker->quantity_updated( 'item-key', 2, 1, new WC_Cart_Double() );
		self::assertSame( array(), $this->database->events );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );

		$GLOBALS['shurloc_test_options'] = array();
		$tracker->added_to_cart( 'item-key', 5, 1, 0 );
		self::assertSame( array(), $this->database->events );

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = Journey_Schema_Migrator::CURRENT_VERSION;
		add_filter( Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER, static fn (): bool => false );
		$tracker->added_to_cart( 'item-key', 5, 1, 0 );
		self::assertSame( array(), $this->database->events );
		self::assertSame( array(), $GLOBALS['shurloc_journey_cookie_test_calls'] );
		self::assertSame( array(), $this->database->cart_links );
		self::assertNull( $this->woocommerce->session->get( Journey_Cart_Session_Token::SESSION_KEY ) );
	}
}
