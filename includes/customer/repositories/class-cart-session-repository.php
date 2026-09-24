<?php
/**
 * WooCommerce cart session repository.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Repositories;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Cart_Session_Token;
use WC_Session_Handler;

/**
 * Reads non-empty carts from WooCommerce's default session table.
 *
 * WooCommerce does not provide a public API for enumerating every stored
 * session. Direct table access is therefore isolated here and is disabled
 * when a custom session handler is configured.
 *
 * @phpstan-type StoredCartItem array{
 *     cart_item_key:string,
 *     product_id:int,
 *     variation_id:int,
 *     quantity:int,
 *     line_subtotal:float,
 *     line_total:float,
 *     variation:array<string,string>
 * }
 * @phpstan-type StoredCartSession array{
 *     session_reference:string,
 *     cart_token_hash:string|null,
 *     user_id:int,
 *     cart_contents:array<int,StoredCartItem>,
 *     item_count:int,
 *     contents_total:float,
 *     expires_at:int,
 *     is_expired:bool
 * }
 */
final class Cart_Session_Repository {

	/**
	 * Find all non-empty carts in the default session store.
	 *
	 * Results are ordered by session expiry, which is the only activity-related
	 * timestamp persisted by WooCommerce's default session handler.
	 *
	 * @return array<int,StoredCartSession>
	 */
	public function find_non_empty(): array {

		if ( ! $this->supports_current_handler() ) {
			return array();
		}

		$carts = array();

		foreach ( $this->get_session_rows() as $row ) {

			$session_key = isset( $row->session_key ) && is_string( $row->session_key )
				? $row->session_key
				: '';

			$session_value = isset( $row->session_value ) && is_string( $row->session_value )
				? $row->session_value
				: '';

			$expires_at = isset( $row->session_expiry )
				? (int) $row->session_expiry
				: 0;

			if ( '' === $session_key || '' === $session_value ) {
				continue;
			}

			$session = $this->decode_array(
				value: $session_value,
			);

			if ( ! isset( $session['cart'] ) ) {
				continue;
			}

			$stored_cart = $this->decode_array(
				value: $session['cart'],
			);

			$cart_contents = $this->normalize_cart_contents(
				stored_cart: $stored_cart,
			);

			if ( array() === $cart_contents ) {
				continue;
			}

			$user_id = $this->get_user_id(
				session_key: $session_key,
			);

			$carts[] = array(
				'session_reference' => $this->get_session_reference(
					session_key: $session_key,
					user_id: $user_id,
				),
				'cart_token_hash'   => Journey_Cart_Session_Token::hash_token(
					token: $session[ Journey_Cart_Session_Token::SESSION_KEY ] ?? null,
				),
				'user_id'           => $user_id,
				'cart_contents'     => $cart_contents,
				'item_count'        => $this->get_item_count(
					cart_contents: $cart_contents,
				),
				'contents_total'    => $this->get_contents_total(
					session: $session,
					cart_contents: $cart_contents,
				),
				'expires_at'        => $expires_at,
				'is_expired'        => time() >= $expires_at,
			);
		}

		usort(
			$carts,
			static function ( array $first_cart, array $second_cart ): int {
				return $second_cart['expires_at'] <=> $first_cart['expires_at'];
			}
		);

		return $carts;
	}

	/**
	 * Determine whether the configured WooCommerce session storage is supported.
	 *
	 * @return bool
	 */
	public function supports_current_handler(): bool {

		$handler_class = apply_filters(
			'woocommerce_session_handler',
			WC_Session_Handler::class
		);

		return is_string( $handler_class ) &&
			WC_Session_Handler::class === ltrim( $handler_class, '\\' );
	}

	/**
	 * Retrieve candidate rows from WooCommerce's default session table.
	 *
	 * The serialized cart marker reduces unnecessary payloads before strict PHP
	 * validation. PHP validation remains authoritative because SQL LIKE is only
	 * a candidate filter.
	 *
	 * @return array<int,object>
	 */
	private function get_session_rows(): array {

		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_sessions';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT session_key, session_value, session_expiry FROM %i WHERE session_value LIKE %s ORDER BY session_expiry DESC',
				$table_name,
				'%s:4:"cart";%'
			)
		);

		return is_array( $rows )
			? $rows
			: array();
	}

	/**
	 * Decode a potentially serialized array.
	 *
	 * @param mixed $value Stored value.
	 * @return array<array-key,mixed>
	 */
	private function decode_array(
		mixed $value
	): array {

		$decoded = maybe_unserialize( $value );

		return is_array( $decoded )
			? $decoded
			: array();
	}

	/**
	 * Normalize the cart fields needed by the administrative listing.
	 *
	 * @param array<array-key,mixed> $stored_cart Stored WooCommerce cart data.
	 * @return array<int,StoredCartItem>
	 */
	private function normalize_cart_contents(
		array $stored_cart
	): array {

		$cart_contents = array();

		foreach ( $stored_cart as $item_key => $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$quantity = isset( $item['quantity'] )
				? (int) $item['quantity']
				: 0;

			if ( 0 >= $quantity ) {
				continue;
			}

			$cart_contents[] = array(
				'cart_item_key' => is_string( $item_key )
					? $item_key
					: (string) $item_key,
				'product_id'    => isset( $item['product_id'] )
					? (int) $item['product_id']
					: 0,
				'variation_id'  => isset( $item['variation_id'] )
					? (int) $item['variation_id']
					: 0,
				'quantity'      => $quantity,
				'line_subtotal' => isset( $item['line_subtotal'] )
					? (float) $item['line_subtotal']
					: 0.0,
				'line_total'    => isset( $item['line_total'] )
					? (float) $item['line_total']
					: 0.0,
				'variation'     => $this->normalize_variation(
					item: $item,
				),
			);
		}

		return $cart_contents;
	}

	/**
	 * Normalize stored variation attributes.
	 *
	 * @param array<array-key,mixed> $item Stored cart item.
	 * @return array<string,string>
	 */
	private function normalize_variation(
		array $item
	): array {

		if ( ! isset( $item['variation'] ) || ! is_array( $item['variation'] ) ) {
			return array();
		}

		$variation = array();

		foreach ( $item['variation'] as $attribute => $value ) {
			if ( is_string( $attribute ) && is_string( $value ) ) {
				$variation[ $attribute ] = $value;
			}
		}

		return $variation;
	}

	/**
	 * Get a registered user ID from a WooCommerce session key.
	 *
	 * @param string $session_key WooCommerce session key.
	 * @return int
	 */
	private function get_user_id(
		string $session_key
	): int {

		return ctype_digit( $session_key )
			? (int) $session_key
			: 0;
	}

	/**
	 * Get a non-sensitive reference suitable for internal view identifiers.
	 *
	 * @param string $session_key WooCommerce session key.
	 * @param int    $user_id     Registered user ID, or zero for a guest.
	 * @return string
	 */
	private function get_session_reference(
		string $session_key,
		int $user_id
	): string {

		if ( 0 < $user_id ) {
			return 'user-' . $user_id;
		}

		return 'guest-' . substr(
			hash( 'sha256', $session_key ),
			0,
			8
		);
	}

	/**
	 * Calculate total item quantity.
	 *
	 * @param array<int,StoredCartItem> $cart_contents Normalized cart contents.
	 * @return int
	 */
	private function get_item_count(
		array $cart_contents
	): int {

		$item_count = 0;

		foreach ( $cart_contents as $item ) {
			$item_count += $item['quantity'];
		}

		return $item_count;
	}

	/**
	 * Determine the stored cart contents total.
	 *
	 * @param array<array-key,mixed>    $session       Stored session data.
	 * @param array<int,StoredCartItem> $cart_contents Normalized cart contents.
	 * @return float
	 */
	private function get_contents_total(
		array $session,
		array $cart_contents
	): float {

		if ( isset( $session['cart_totals'] ) ) {
			$totals = $this->decode_array(
				value: $session['cart_totals'],
			);

			if ( isset( $totals['cart_contents_total'] ) ) {
				return (float) $totals['cart_contents_total'];
			}
		}

		$contents_total = 0.0;

		foreach ( $cart_contents as $item ) {
			$contents_total += $item['line_total'];
		}

		return $contents_total;
	}
}
