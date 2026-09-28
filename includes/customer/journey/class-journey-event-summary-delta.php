<?php
/**
 * Customer Journey event summary increments.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Map one new event to the corresponding session summary increments.
 *
 * The result contains only columns changed by the event. Quantity remains a
 * decimal string so a later database update does not pass through PHP floats.
 *
 * @phpstan-type SummaryDelta array{
 *     event_count?:int,
 *     page_view_count?:int,
 *     product_view_count?:int,
 *     cart_add_count?:int,
 *     cart_remove_count?:int,
 *     added_quantity?:string,
 *     removed_quantity?:string,
 *     checkout_started_count?:int,
 *     order_created_count?:int,
 *     active_ms?:int
 * }
 */
final class Journey_Event_Summary_Delta {
	/**
	 * Return a bounded counter delta for one accepted v1 event.
	 *
	 * Page and product view duration belongs to the same view event. A product
	 * view increases the page-view count once and the product-view count once.
	 * Future order lifecycle events need their own explicit mapping.
	 *
	 * @param string      $event_type Event type.
	 * @param string|null $quantity   Positive decimal for cart changes.
	 * @param int         $active_ms  Estimated visible duration for a view.
	 * @return SummaryDelta|null Counter increments, or null for invalid input.
	 */
	public static function for_event( string $event_type, ?string $quantity = null, int $active_ms = 0 ): ?array {
		if ( 0 > $active_ms || ! Journey_Event_Type::is_supported( value: $event_type ) ) {
			return null;
		}

		if ( Journey_Event_Type::counts_as_page_view( value: $event_type ) ) {
			if ( null !== $quantity ) {
				return null;
			}

			$delta = array(
				'event_count'     => 1,
				'page_view_count' => 1,
			);
			if ( Journey_Event_Type::counts_as_product_view( value: $event_type ) ) {
				$delta['product_view_count'] = 1;
			}

			if ( 0 < $active_ms ) {
				$delta['active_ms'] = $active_ms;
			}

			return $delta;
		}

		if ( 0 !== $active_ms ) {
			return null;
		}

		switch ( $event_type ) {
			case Journey_Event_Type::ADD_TO_CART:
				if ( null === $quantity || ! self::is_valid_quantity( quantity: $quantity ) ) {
					return null;
				}

				return array(
					'event_count'    => 1,
					'cart_add_count' => 1,
					'added_quantity' => $quantity,
				);

			case Journey_Event_Type::REMOVE_FROM_CART:
				if ( null === $quantity || ! self::is_valid_quantity( quantity: $quantity ) ) {
					return null;
				}

				return array(
					'event_count'       => 1,
					'cart_remove_count' => 1,
					'removed_quantity'  => $quantity,
				);

			case Journey_Event_Type::CHECKOUT_STARTED:
				return null === $quantity
					? array(
						'event_count'            => 1,
						'checkout_started_count' => 1,
					)
					: null;

			case Journey_Event_Type::ORDER_CREATED:
				return null === $quantity
					? array(
						'event_count'         => 1,
						'order_created_count' => 1,
					)
					: null;
		}

		return null;
	}

	/**
	 * Return a duration-only increment for an existing verified view event.
	 *
	 * The caller must confirm the target is a page or product view and update
	 * that event row together with the session summary in one transaction.
	 * This delta never increments a view count a second time.
	 *
	 * @param int $active_ms Additional visible time in milliseconds.
	 * @return array{active_ms:int}|null Duration increment, or null when empty.
	 */
	public static function for_view_duration( int $active_ms ): ?array {
		return 0 < $active_ms ? array( 'active_ms' => $active_ms ) : null;
	}

	/**
	 * Accept only positive quantities that fit DECIMAL(16,4).
	 *
	 * @param string $quantity Decimal quantity.
	 * @return bool Whether the database can store the quantity exactly.
	 */
	private static function is_valid_quantity( string $quantity ): bool {
		return 1 === preg_match( '/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/', $quantity ) &&
			1 === preg_match( '/[1-9]/', $quantity );
	}
}
