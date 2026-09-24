<?php
/**
 * Customer Journey event type contract.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Defines v1 events and their page-view counting semantics.
 *
 * A product page emits one PRODUCT_VIEW event. That event counts as both one
 * page view and one product view; producers must not also emit PAGE_VIEW for
 * the same view. ORDER_CREATED means an order record exists. It does not imply
 * payment or completion.
 */
final class Journey_Event_Type {
	/** A non-product page was viewed. */
	public const PAGE_VIEW = 'PAGE_VIEW';

	/** A product page was viewed. */
	public const PRODUCT_VIEW = 'PRODUCT_VIEW';

	/** A product quantity was added to the cart. */
	public const ADD_TO_CART = 'ADD_TO_CART';

	/** A product quantity was removed from the cart. */
	public const REMOVE_FROM_CART = 'REMOVE_FROM_CART';

	/** A visitor began checkout interaction. */
	public const CHECKOUT_STARTED = 'CHECKOUT_STARTED';

	/** A WooCommerce order record was created, regardless of payment status. */
	public const ORDER_CREATED = 'ORDER_CREATED';

	/**
	 * Event types collected in v1.
	 *
	 * @var list<string>
	 */
	private const SUPPORTED_TYPES = array(
		self::PAGE_VIEW,
		self::PRODUCT_VIEW,
		self::ADD_TO_CART,
		self::REMOVE_FROM_CART,
		self::CHECKOUT_STARTED,
		self::ORDER_CREATED,
	);

	/**
	 * Return the event types accepted by v1 collection.
	 *
	 * @return list<string> Supported event types.
	 */
	public static function supported_types(): array {
		return self::SUPPORTED_TYPES;
	}

	/**
	 * Check an untrusted event type without coercion or normalization.
	 *
	 * @param mixed $value Incoming event type.
	 * @return bool Whether v1 supports the exact type.
	 */
	public static function is_supported( mixed $value ): bool {
		return is_string( $value ) && in_array( $value, self::SUPPORTED_TYPES, true );
	}

	/**
	 * Whether one event contributes to the total page-view count.
	 *
	 * @param mixed $value Incoming event type.
	 * @return bool Whether it is a page or specialized product view.
	 */
	public static function counts_as_page_view( mixed $value ): bool {
		return self::PAGE_VIEW === $value || self::PRODUCT_VIEW === $value;
	}

	/**
	 * Whether one event contributes to the product-view count.
	 *
	 * @param mixed $value Incoming event type.
	 * @return bool Whether it is a product view.
	 */
	public static function counts_as_product_view( mixed $value ): bool {
		return self::PRODUCT_VIEW === $value;
	}
}
