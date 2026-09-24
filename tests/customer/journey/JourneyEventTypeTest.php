<?php
/**
 * Tests for Customer Journey event type semantics.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests the exact v1 event set and specialized product-view counting.
 */
final class JourneyEventTypeTest extends TestCase {
	/**
	 * Only the six approved event types are collected in v1.
	 *
	 * @return void
	 */
	public function test_supported_types_are_the_exact_v1_set(): void {
		$types = array(
			'PAGE_VIEW',
			'PRODUCT_VIEW',
			'ADD_TO_CART',
			'REMOVE_FROM_CART',
			'CHECKOUT_STARTED',
			'ORDER_CREATED',
		);

		self::assertSame( $types, Journey_Event_Type::supported_types() );
		foreach ( $types as $type ) {
			self::assertTrue( Journey_Event_Type::is_supported( value: $type ) );
			self::assertLessThanOrEqual( 32, strlen( $type ) );
		}
	}

	/**
	 * Untrusted or future order-status values are not accepted by v1.
	 *
	 * @return void
	 */
	public function test_rejects_unknown_future_and_malformed_types(): void {
		foreach (
			array(
				'ORDER_PAID',
				'ORDER_COMPLETED',
				'ORDER_CANCELLED',
				'ORDER_REFUNDED',
				'page_view',
				' PAGE_VIEW',
				'',
				42,
				true,
				null,
				array( 'PAGE_VIEW' ),
			) as $type
		) {
			self::assertFalse( Journey_Event_Type::is_supported( value: $type ) );
		}
	}

	/**
	 * One product event is one page view and one product view.
	 *
	 * @return void
	 */
	public function test_product_view_counts_as_one_page_and_one_product_view(): void {
		self::assertTrue( Journey_Event_Type::counts_as_page_view( Journey_Event_Type::PAGE_VIEW ) );
		self::assertFalse( Journey_Event_Type::counts_as_product_view( Journey_Event_Type::PAGE_VIEW ) );
		self::assertTrue( Journey_Event_Type::counts_as_page_view( Journey_Event_Type::PRODUCT_VIEW ) );
		self::assertTrue( Journey_Event_Type::counts_as_product_view( Journey_Event_Type::PRODUCT_VIEW ) );
	}

	/**
	 * Cart, checkout, and order events do not inflate view totals.
	 *
	 * @return void
	 */
	public function test_transactional_events_do_not_count_as_views(): void {
		foreach (
			array(
				Journey_Event_Type::ADD_TO_CART,
				Journey_Event_Type::REMOVE_FROM_CART,
				Journey_Event_Type::CHECKOUT_STARTED,
				Journey_Event_Type::ORDER_CREATED,
				'ORDER_PAID',
			) as $type
		) {
			self::assertFalse( Journey_Event_Type::counts_as_page_view( $type ) );
			self::assertFalse( Journey_Event_Type::counts_as_product_view( $type ) );
		}
	}
}
