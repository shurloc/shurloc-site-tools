<?php
/**
 * Tests for Customer Journey event field rules.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests the semantic fields accepted for each v1 event type.
 */
final class JourneyEventFieldValidatorTest extends TestCase {
	/**
	 * Each approved v1 event accepts its own minimal fields.
	 *
	 * @return void
	 */
	public function test_accepts_all_six_v1_event_shapes(): void {
		$validator = new Journey_Event_Field_Validator();
		$valid     = array(
			array(
				'event_type' => Journey_Event_Type::PAGE_VIEW,
				'page_path'  => '/pages/about',
				'post_id'    => 8,
				'active_ms'  => 1200,
			),
			array(
				'event_type'   => Journey_Event_Type::PRODUCT_VIEW,
				'page_path'    => '/product/widget',
				'product_id'   => 9,
				'variation_id' => 10,
				'active_ms'    => 1300,
			),
			array(
				'event_type'   => Journey_Event_Type::ADD_TO_CART,
				'product_id'   => 9,
				'variation_id' => 10,
				'quantity'     => '2.5000',
			),
			array(
				'event_type' => Journey_Event_Type::REMOVE_FROM_CART,
				'product_id' => 9,
				'quantity'   => '0.5',
			),
			array(
				'event_type' => Journey_Event_Type::CHECKOUT_STARTED,
				'page_path'  => '/checkout',
			),
			array(
				'event_type'      => Journey_Event_Type::ORDER_CREATED,
				'order_id'        => 81,
				'idempotency_key' => str_repeat( 'a', 64 ),
				'source'          => 'woocommerce',
			),
		);

		foreach ( $valid as $event ) {
			self::assertTrue( $validator->is_valid( $event ) );
		}
	}

	/**
	 * Product views are one specialized page view, not two event records.
	 *
	 * @return void
	 */
	public function test_page_and_product_views_require_distinct_fields(): void {
		$validator = new Journey_Event_Field_Validator();
		$page      = array(
			'event_type' => Journey_Event_Type::PAGE_VIEW,
			'page_path'  => '/product/widget',
		);

		self::assertTrue( $validator->is_valid( $page ) );
		self::assertFalse( $validator->is_valid( array_replace( $page, array( 'product_id' => 9 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $page, array( 'event_type' => Journey_Event_Type::PRODUCT_VIEW ) ) ) );
		self::assertTrue(
			$validator->is_valid(
				array_replace(
					$page,
					array(
						'event_type' => Journey_Event_Type::PRODUCT_VIEW,
						'product_id' => 9,
					)
				)
			)
		);
		self::assertFalse( $validator->is_valid( array( 'event_type' => Journey_Event_Type::PAGE_VIEW ) ) );
	}

	/**
	 * Cart changes require a product and a positive decimal quantity.
	 *
	 * @return void
	 */
	public function test_cart_events_require_product_and_quantity(): void {
		$validator = new Journey_Event_Field_Validator();
		$cart      = array(
			'event_type' => Journey_Event_Type::ADD_TO_CART,
			'product_id' => 9,
			'quantity'   => '2',
		);

		self::assertFalse(
			$validator->is_valid(
				array(
					'event_type' => Journey_Event_Type::ADD_TO_CART,
					'quantity'   => '2',
				)
			)
		);
		self::assertFalse(
			$validator->is_valid(
				array(
					'event_type' => Journey_Event_Type::ADD_TO_CART,
					'product_id' => 9,
				)
			)
		);
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'quantity' => '0' ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'quantity' => '1.00000' ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'quantity' => 2 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'product_id' => 0 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'variation_id' => -1 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'order_id' => 81 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $cart, array( 'active_ms' => 1 ) ) ) );
	}

	/**
	 * Checkout and order creation cannot be confused with paid order states.
	 *
	 * @return void
	 */
	public function test_checkout_and_order_created_have_separate_requirements(): void {
		$validator = new Journey_Event_Field_Validator();
		$order     = array(
			'event_type'      => Journey_Event_Type::ORDER_CREATED,
			'order_id'        => 81,
			'idempotency_key' => str_repeat( 'b', 64 ),
		);

		self::assertTrue( $validator->is_valid( $order ) );
		self::assertFalse(
			$validator->is_valid(
				array(
					'event_type' => Journey_Event_Type::ORDER_CREATED,
					'order_id'   => 81,
				)
			)
		);
		self::assertFalse(
			$validator->is_valid(
				array(
					'event_type'      => Journey_Event_Type::ORDER_CREATED,
					'idempotency_key' => str_repeat( 'b', 64 ),
				)
			)
		);
		self::assertFalse( $validator->is_valid( array_replace( $order, array( 'product_id' => 9 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $order, array( 'active_ms' => 1 ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $order, array( 'event_type' => 'ORDER_PAID' ) ) ) );
		self::assertFalse(
			$validator->is_valid(
				array(
					'event_type' => Journey_Event_Type::CHECKOUT_STARTED,
					'order_id'   => 81,
				)
			)
		);
	}

	/**
	 * Only clean relative paths and known columns reach persistence.
	 *
	 * @return void
	 */
	public function test_rejects_untrusted_paths_and_arbitrary_metadata(): void {
		$validator = new Journey_Event_Field_Validator();
		$page      = array(
			'event_type' => Journey_Event_Type::PAGE_VIEW,
			'page_path'  => '/pages/about',
		);

		foreach (
			array(
				'https://example.com/about',
				'//other.example/about',
				'/pages/about?email=private',
				'/pages/about#part',
				'/pages/a%00b',
				'/pages/a b',
				str_repeat( 'a', 1025 ),
			) as $path
		) {
			self::assertFalse( $validator->is_valid( array_replace( $page, array( 'page_path' => $path ) ) ) );
		}

		self::assertFalse( $validator->is_valid( array_replace( $page, array( 'metadata' => array( 'email' => 'private' ) ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $page, array( 'source' => 'browser/unsafe' ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $page, array( 'idempotency_key' => 'short' ) ) ) );
		self::assertFalse( $validator->is_valid( array_replace( $page, array( 'active_ms' => -1 ) ) ) );
		self::assertFalse( $validator->is_valid( array( 'event_type' => 'ORDER_COMPLETED' ) ) );
	}
}
