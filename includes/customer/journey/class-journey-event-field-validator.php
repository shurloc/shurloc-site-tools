<?php
/**
 * Customer Journey event field validation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Enforce the v1 field contract for an event assembled by trusted server code.
 *
 * The storage repository checks row shape and schema readiness. A later
 * collection layer must derive visitor, session, user, product, and order IDs
 * from server state rather than passing browser claims through this validator.
 */
final class Journey_Event_Field_Validator {
	/**
	 * Fields permitted in the fixed v1 event row.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_FIELDS = array(
		'session_id',
		'visitor_id',
		'user_id_at_event',
		'event_type',
		'occurred_at',
		'page_path',
		'post_id',
		'product_id',
		'variation_id',
		'quantity',
		'order_id',
		'active_ms',
		'source',
		'idempotency_key',
	);

	/**
	 * Shared sanitizer for relative page paths.
	 *
	 * @var Journey_Attribution_Sanitizer
	 */
	private Journey_Attribution_Sanitizer $attribution;

	/**
	 * Constructor.
	 *
	 * @param Journey_Attribution_Sanitizer|null $attribution Existing path sanitizer.
	 */
	public function __construct( ?Journey_Attribution_Sanitizer $attribution = null ) {
		$this->attribution = $attribution ?? new Journey_Attribution_Sanitizer();
	}

	/**
	 * Check fields and event-specific meaning before a repository insert.
	 *
	 * Fields unrelated to a type are rejected instead of being silently stored.
	 * This checks semantic event fields; the repository still validates the full
	 * storage row, including IDs and timestamps.
	 *
	 * @param array<string,mixed> $event Server-assembled event fields.
	 * @return bool Whether the fields form a supported v1 event.
	 */
	public function is_valid( array $event ): bool {
		$type = $event['event_type'] ?? null;
		if ( ! Journey_Event_Type::is_supported( value: $type ) ) {
			return false;
		}

		foreach ( array_keys( $event ) as $field ) {
			if ( ! in_array( $field, self::ALLOWED_FIELDS, true ) ) {
				return false;
			}
		}

		foreach ( array( 'post_id', 'product_id', 'variation_id', 'order_id' ) as $field ) {
			if ( isset( $event[ $field ] ) && ( ! is_int( $event[ $field ] ) || 0 >= $event[ $field ] ) ) {
				return false;
			}
		}

		$path = $event['page_path'] ?? null;
		if ( null !== $path && (
			! is_string( $path ) ||
			$this->attribution->sanitize( request_uri: $path, referrer_url: null )['landing_path'] !== $path
		) ) {
			return false;
		}

		$quantity = $event['quantity'] ?? null;
		if ( null !== $quantity && (
			! is_string( $quantity ) ||
			1 !== preg_match( '/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/', $quantity ) ||
			1 !== preg_match( '/[1-9]/', $quantity )
		) ) {
			return false;
		}

		$active_ms = $event['active_ms'] ?? 0;
		$key       = $event['idempotency_key'] ?? null;
		$source    = $event['source'] ?? null;
		if (
			! is_int( $active_ms ) || 0 > $active_ms ||
			( null !== $key && ( ! is_string( $key ) || 1 !== preg_match( '/\A[0-9a-f]{64}\z/', $key ) ) ) ||
			( null !== $source && ( ! is_string( $source ) || 1 !== preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $source ) ) ) ||
			( isset( $event['variation_id'] ) && ! isset( $event['product_id'] ) )
		) {
			return false;
		}

		$has_product  = isset( $event['product_id'] );
		$has_quantity = null !== $quantity;
		$has_order    = isset( $event['order_id'] );

		switch ( $type ) {
			case Journey_Event_Type::PAGE_VIEW:
				return null !== $path && ! $has_product && ! isset( $event['variation_id'] ) && ! $has_quantity && ! $has_order;

			case Journey_Event_Type::PRODUCT_VIEW:
				return null !== $path && $has_product && ! $has_quantity && ! $has_order;

			case Journey_Event_Type::ADD_TO_CART:
			case Journey_Event_Type::REMOVE_FROM_CART:
				return $has_product && $has_quantity && ! $has_order && 0 === $active_ms;

			case Journey_Event_Type::CHECKOUT_STARTED:
				return ! $has_product && ! isset( $event['variation_id'] ) && ! $has_quantity && ! $has_order && 0 === $active_ms;

			case Journey_Event_Type::ORDER_CREATED:
				return $has_order && null !== $key && ! $has_product && ! isset( $event['variation_id'] ) && ! $has_quantity && 0 === $active_ms;
		}

		return false;
	}
}
