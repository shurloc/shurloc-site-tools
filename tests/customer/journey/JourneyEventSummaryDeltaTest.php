<?php
/**
 * Tests for Customer Journey session summary increments.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_V3;

/**
 * Tests one event-to-summary mapping without database rounding.
 */
final class JourneyEventSummaryDeltaTest extends TestCase {
	/**
	 * A product view adds one page view, one product view, and duration once.
	 *
	 * @return void
	 */
	public function test_page_and_product_views_have_distinct_counter_deltas(): void {
		self::assertSame(
			array(
				'event_count'     => 1,
				'page_view_count' => 1,
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::PAGE_VIEW )
		);
		self::assertSame(
			array(
				'event_count'     => 1,
				'page_view_count' => 1,
				'active_ms'       => 1200,
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::PAGE_VIEW, active_ms: 1200 )
		);
		self::assertSame(
			array(
				'event_count'        => 1,
				'page_view_count'    => 1,
				'product_view_count' => 1,
				'active_ms'          => 1500,
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::PRODUCT_VIEW, active_ms: 1500 )
		);
	}

	/**
	 * Later visible-time updates never count a second page occurrence.
	 *
	 * @return void
	 */
	public function test_duration_only_increment_does_not_add_a_view(): void {
		self::assertSame( array( 'active_ms' => 750 ), Journey_Event_Summary_Delta::for_view_duration( 750 ) );
		self::assertNull( Journey_Event_Summary_Delta::for_view_duration( 0 ) );
		self::assertNull( Journey_Event_Summary_Delta::for_view_duration( -1 ) );
	}

	/**
	 * Cart quantities remain exact decimal strings for database arithmetic.
	 *
	 * @return void
	 */
	public function test_cart_changes_map_count_and_exact_quantity(): void {
		self::assertSame(
			array(
				'event_count'    => 1,
				'cart_add_count' => 1,
				'added_quantity' => '2.5000',
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::ADD_TO_CART, quantity: '2.5000' )
		);
		self::assertSame(
			array(
				'event_count'       => 1,
				'cart_remove_count' => 1,
				'removed_quantity'  => '0.0001',
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::REMOVE_FROM_CART, quantity: '0.0001' )
		);
		self::assertSame(
			array(
				'event_count'    => 1,
				'cart_add_count' => 1,
				'added_quantity' => '999999999999.9999',
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::ADD_TO_CART, quantity: '999999999999.9999' )
		);
	}

	/**
	 * Checkout and order creation each count their own occurrence.
	 *
	 * @return void
	 */
	public function test_checkout_and_order_creation_have_single_counters(): void {
		self::assertSame(
			array(
				'event_count'            => 1,
				'checkout_started_count' => 1,
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::CHECKOUT_STARTED )
		);
		self::assertSame(
			array(
				'event_count'         => 1,
				'order_created_count' => 1,
			),
			Journey_Event_Summary_Delta::for_event( Journey_Event_Type::ORDER_CREATED )
		);
	}

	/**
	 * Unsupported combinations cannot silently change session summaries.
	 *
	 * @return void
	 */
	public function test_rejects_unsupported_types_quantities_and_duration(): void {
		foreach ( array( 'ORDER_PAID', 'ORDER_COMPLETED', 'ORDER_CANCELLED', 'ORDER_REFUNDED' ) as $future_type ) {
			self::assertNull( Journey_Event_Summary_Delta::for_event( $future_type ) );
		}

		foreach ( array( null, '', '0', '-1', '1.00000', '1000000000000', '1e3' ) as $invalid_quantity ) {
			self::assertNull( Journey_Event_Summary_Delta::for_event( Journey_Event_Type::ADD_TO_CART, quantity: $invalid_quantity ) );
		}

		self::assertNull( Journey_Event_Summary_Delta::for_event( Journey_Event_Type::PAGE_VIEW, quantity: '1' ) );
		self::assertNull( Journey_Event_Summary_Delta::for_event( Journey_Event_Type::CHECKOUT_STARTED, quantity: '1' ) );
		self::assertNull( Journey_Event_Summary_Delta::for_event( Journey_Event_Type::ADD_TO_CART, quantity: '1', active_ms: 1 ) );
		self::assertNull( Journey_Event_Summary_Delta::for_event( Journey_Event_Type::ORDER_CREATED, active_ms: 1 ) );
		self::assertNull( Journey_Event_Summary_Delta::for_event( Journey_Event_Type::PRODUCT_VIEW, active_ms: -1 ) );
	}

	/**
	 * Every returned field exists in the approved sessions table declaration.
	 *
	 * @return void
	 */
	public function test_delta_columns_match_the_current_session_schema(): void {
		$columns = Journey_Schema_V3::get_table_definitions()['shurloc_journey_sessions']['columns'];
		$events  = array(
			array( Journey_Event_Type::PAGE_VIEW, null, 1 ),
			array( Journey_Event_Type::PRODUCT_VIEW, null, 1 ),
			array( Journey_Event_Type::ADD_TO_CART, '1', 0 ),
			array( Journey_Event_Type::REMOVE_FROM_CART, '1', 0 ),
			array( Journey_Event_Type::CHECKOUT_STARTED, null, 0 ),
			array( Journey_Event_Type::ORDER_CREATED, null, 0 ),
		);

		foreach ( $events as $event ) {
			$delta = Journey_Event_Summary_Delta::for_event( $event[0], $event[1], $event[2] );
			self::assertNotNull( $delta );
			foreach ( array_keys( $delta ) as $column ) {
				self::assertArrayHasKey( $column, $columns );
			}
		}
	}
}
