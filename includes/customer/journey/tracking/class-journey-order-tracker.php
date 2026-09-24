<?php
/**
 * Customer Journey WooCommerce checkout order creation tracking.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Tracking;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Services\Journey_Event_Service;
use WC_Order;

/**
 * Record persisted checkout orders before payment or completion.
 *
 * Classic checkout and the Store API have separate creation hooks. The Store
 * API update hook also covers versions without its dedicated creation hook.
 * The event service deduplicates callbacks for the same order ID.
 */
final class Journey_Order_Tracker {
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
	 * Register classic and Store API checkout order hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_order_created', array( $this, 'order_created' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_created', array( $this, 'order_created' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'order_created' ), 10, 1 );
	}

	/**
	 * Record an order only when WooCommerce can load its persisted row.
	 *
	 * A Store API draft is still an order record, so status does not gate this
	 * event. Payment, completion, cancellation, and refunds are separate events.
	 *
	 * @param mixed $order WooCommerce order supplied by a checkout hook.
	 * @return void
	 */
	public function order_created( mixed $order ): void {
		if ( ! $order instanceof WC_Order || 0 >= $order->get_id() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order_id  = $order->get_id();
		$persisted = wc_get_order( $order_id );
		if ( ! $persisted instanceof WC_Order || $persisted->get_id() !== $order_id ) {
			return;
		}

		$this->events->record(
			event_type: Journey_Event_Type::ORDER_CREATED,
			order_id: $order_id,
			source: 'woocommerce'
		);
	}
}
