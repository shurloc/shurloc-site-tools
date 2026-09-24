<?php
/**
 * Customer Journey WooCommerce cart event tracking.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Tracking;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Services\Journey_Event_Service;
use WC_Cart;

/**
 * Record successful WooCommerce cart quantity changes as Journey events.
 *
 * Core cart hooks also cover Store API cart mutations used by WooCommerce
 * Blocks. The event service owns collection eligibility and schema checks.
 */
final class Journey_Cart_Tracker {
	/** Units of precision supported by the Journey quantity column. */
	private const QUANTITY_SCALE = 10000;

	/**
	 * Server-owned event service.
	 *
	 * @var Journey_Event_Service
	 */
	private Journey_Event_Service $events;

	/**
	 * Constructor.
	 *
	 * @param Journey_Event_Service|null $events Event recorder.
	 */
	public function __construct( ?Journey_Event_Service $events = null ) {
		$this->events = $events ?? new Journey_Event_Service();
	}

	/**
	 * Register only hooks that represent a successful cart mutation.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_add_to_cart', array( $this, 'added_to_cart' ), 10, 4 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'removed_from_cart' ), 10, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'quantity_updated' ), 10, 4 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'restored_to_cart' ), 10, 2 );
	}

	/**
	 * Record the quantity from an explicit successful add-to-cart operation.
	 *
	 * @param mixed $cart_item_key WooCommerce cart item key.
	 * @param mixed $product_id    Parent or simple product ID.
	 * @param mixed $quantity      Quantity added, not the resulting cart total.
	 * @param mixed $variation_id  Variation ID or zero.
	 * @return void
	 */
	public function added_to_cart( mixed $cart_item_key, mixed $product_id, mixed $quantity, mixed $variation_id ): void {
		if ( ! is_string( $cart_item_key ) || '' === $cart_item_key ) {
			return;
		}

		$this->record_event(
			event_type: Journey_Event_Type::ADD_TO_CART,
			product_id: $product_id,
			variation_id: $variation_id,
			quantity: $this->scaled_quantity( quantity: $quantity ),
			source: 'woocommerce'
		);
	}

	/**
	 * Read a successfully removed item's original quantity from the cart.
	 *
	 * @param mixed $cart_item_key WooCommerce cart item key.
	 * @param mixed $cart          WooCommerce cart.
	 * @return void
	 */
	public function removed_from_cart( mixed $cart_item_key, mixed $cart ): void {
		if ( ! is_string( $cart_item_key ) || '' === $cart_item_key || ! $cart instanceof WC_Cart ) {
			return;
		}

		$this->record_item(
			event_type: Journey_Event_Type::REMOVE_FROM_CART,
			item: $cart->get_removed_cart_contents()[ $cart_item_key ] ?? null,
			source: 'woocommerce'
		);
	}

	/**
	 * Record the positive or negative difference after a cart quantity update.
	 *
	 * WooCommerce removes a zero-quantity item through remove_cart_item(), which
	 * fires the removal hook instead of this quantity-update hook.
	 *
	 * @param mixed $cart_item_key WooCommerce cart item key.
	 * @param mixed $new_quantity  Resulting cart item quantity.
	 * @param mixed $old_quantity  Previous cart item quantity.
	 * @param mixed $cart          WooCommerce cart.
	 * @return void
	 */
	public function quantity_updated( mixed $cart_item_key, mixed $new_quantity, mixed $old_quantity, mixed $cart ): void {
		if ( ! is_string( $cart_item_key ) || '' === $cart_item_key || ! $cart instanceof WC_Cart ) {
			return;
		}

		$new = $this->scaled_quantity( quantity: $new_quantity );
		$old = $this->scaled_quantity( quantity: $old_quantity );
		if ( null === $new || null === $old || 0 >= $new || 0 >= $old || $new === $old ) {
			return;
		}

		$item = $cart->get_cart_contents()[ $cart_item_key ] ?? null;
		if ( ! is_array( $item ) ) {
			return;
		}

		$this->record_event(
			event_type: $new > $old ? Journey_Event_Type::ADD_TO_CART : Journey_Event_Type::REMOVE_FROM_CART,
			product_id: $item['product_id'] ?? null,
			variation_id: $item['variation_id'] ?? 0,
			quantity: abs( $new - $old ),
			source: 'cart_quantity'
		);
	}

	/**
	 * A successful undo restores the removed quantity to the cart.
	 *
	 * @param mixed $cart_item_key WooCommerce cart item key.
	 * @param mixed $cart          WooCommerce cart.
	 * @return void
	 */
	public function restored_to_cart( mixed $cart_item_key, mixed $cart ): void {
		if ( ! is_string( $cart_item_key ) || '' === $cart_item_key || ! $cart instanceof WC_Cart ) {
			return;
		}

		$this->record_item(
			event_type: Journey_Event_Type::ADD_TO_CART,
			item: $cart->get_cart_contents()[ $cart_item_key ] ?? null,
			source: 'woocommerce'
		);
	}

	/**
	 * Record the quantity and IDs from a WooCommerce cart item.
	 *
	 * @param string $event_type Cart event type.
	 * @param mixed  $item       Cart item value.
	 * @param string $source     Server-selected event source.
	 * @return void
	 */
	private function record_item( string $event_type, mixed $item, string $source ): void {
		if ( ! is_array( $item ) ) {
			return;
		}

		$this->record_event(
			event_type: $event_type,
			product_id: $item['product_id'] ?? null,
			variation_id: $item['variation_id'] ?? 0,
			quantity: $this->scaled_quantity( quantity: $item['quantity'] ?? null ),
			source: $source
		);
	}

	/**
	 * Discard malformed hook values before calling the central event service.
	 *
	 * @param string   $event_type   ADD_TO_CART or REMOVE_FROM_CART.
	 * @param mixed    $product_id   Server-provided product ID.
	 * @param mixed    $variation_id Server-provided variation ID or zero.
	 * @param int|null $quantity     Positive quantity in ten-thousandths.
	 * @param string   $source       Server-selected event source.
	 * @return void
	 */
	private function record_event( string $event_type, mixed $product_id, mixed $variation_id, ?int $quantity, string $source ): void {
		if ( ! is_int( $product_id ) || 0 >= $product_id ||
			! is_int( $variation_id ) || 0 > $variation_id || null === $quantity || 0 >= $quantity ) {
			return;
		}

		$this->events->record(
			event_type: $event_type,
			product_id: $product_id,
			variation_id: 0 < $variation_id ? $variation_id : null,
			quantity: $this->decimal_quantity( quantity: $quantity ),
			source: $source
		);
	}

	/**
	 * Parse a WooCommerce quantity without floating-point delta arithmetic.
	 *
	 * @param mixed $quantity WooCommerce quantity.
	 * @return int|null Quantity in ten-thousandths, or null when out of range.
	 */
	private function scaled_quantity( mixed $quantity ): ?int {
		if ( ! is_int( $quantity ) && ! is_float( $quantity ) && ! is_string( $quantity ) ) {
			return null;
		}

		$value = (string) $quantity;
		if ( 1 !== preg_match( '/\A(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,4}))?\z/', $value, $matches ) ) {
			return null;
		}

		return ( (int) $matches[1] * self::QUANTITY_SCALE ) +
			(int) str_pad( $matches[2] ?? '', 4, '0' );
	}

	/**
	 * Convert a validated scaled quantity to the event's decimal format.
	 *
	 * @param int $quantity Positive quantity in ten-thousandths.
	 * @return string Canonical decimal quantity.
	 */
	private function decimal_quantity( int $quantity ): string {
		$whole     = intdiv( $quantity, self::QUANTITY_SCALE );
		$fraction  = $quantity % self::QUANTITY_SCALE;
		$formatted = (string) $whole;

		if ( 0 !== $fraction ) {
			$formatted .= '.' . rtrim( str_pad( (string) $fraction, 4, '0', STR_PAD_LEFT ), '0' );
		}

		return $formatted;
	}
}
